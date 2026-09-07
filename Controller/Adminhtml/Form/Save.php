<?php
declare(strict_types=1);

namespace Panth\DynamicForms\Controller\Adminhtml\Form;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Panth\DynamicForms\Model\Form\SaveDataPreparer;
use Panth\DynamicForms\Model\FormFactory;
use Panth\DynamicForms\Model\FieldFactory;
use Panth\DynamicForms\Model\ResourceModel\Form as FormResource;
use Panth\DynamicForms\Model\ResourceModel\Field as FieldResource;
use Panth\DynamicForms\Model\ResourceModel\Field\CollectionFactory as FieldCollectionFactory;
use Psr\Log\LoggerInterface;

class Save extends Action
{
    public const ADMIN_RESOURCE = 'Panth_DynamicForms::form';

    private FormFactory $formFactory;
    private FormResource $formResource;
    private FieldFactory $fieldFactory;
    private FieldResource $fieldResource;
    private FieldCollectionFactory $fieldCollectionFactory;
    private DataPersistorInterface $dataPersistor;
    private Json $json;
    private LoggerInterface $logger;
    private SaveDataPreparer $dataPreparer;

    public function __construct(
        Context $context,
        FormFactory $formFactory,
        FormResource $formResource,
        FieldFactory $fieldFactory,
        FieldResource $fieldResource,
        FieldCollectionFactory $fieldCollectionFactory,
        DataPersistorInterface $dataPersistor,
        Json $json,
        LoggerInterface $logger,
        SaveDataPreparer $dataPreparer
    ) {
        parent::__construct($context);
        $this->formFactory = $formFactory;
        $this->formResource = $formResource;
        $this->fieldFactory = $fieldFactory;
        $this->fieldResource = $fieldResource;
        $this->fieldCollectionFactory = $fieldCollectionFactory;
        $this->dataPersistor = $dataPersistor;
        $this->json = $json;
        $this->logger = $logger;
        $this->dataPreparer = $dataPreparer;
    }

    public function execute(): ResultInterface
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $data = $this->getRequest()->getPostValue();

        if (!$data) {
            $this->logger->debug('DynamicForms Save: No POST data received');
            return $resultRedirect->setPath('*/*/');
        }

        $formId = $this->dataPreparer->getFormId($data);
        $this->logger->debug('DynamicForms Save: Incoming request', [
            'form_id' => $formId,
            'has_fields_json' => isset($data['fields_json']),
        ]);

        $model = $this->formFactory->create();

        if ($formId) {
            $this->formResource->load($model, $formId);
            if (!$model->getId()) {
                $this->logger->error('DynamicForms Save: Form ID ' . $formId . ' not found in database');
                $this->messageManager->addErrorMessage(__('This form no longer exists.'));
                return $resultRedirect->setPath('*/*/');
            }
        }

        $fieldsJson = (string) ($data['fields_json'] ?? '[]');
        $formType = $this->dataPreparer->getFormType($data);
        $urlKey = $this->dataPreparer->getUrlKey($data);
        $data['url_key'] = $urlKey;

        if ($this->dataPreparer->requiresUrlKey($formType) && $urlKey === '') {
            $this->messageManager->addErrorMessage(__('URL Key is required for forms with a standalone page.'));
            $this->dataPersistor->set('panth_dynamicforms_form', $data);
            if ($formId) {
                return $resultRedirect->setPath('*/*/edit', ['form_id' => $formId]);
            }
            return $resultRedirect->setPath('*/*/new');
        }

        if ($urlKey !== '') {
            try {
                $this->validateUrlKeyUniqueness($urlKey, $formId);
            } catch (LocalizedException $e) {
                $this->logger->error('DynamicForms Save: URL key validation failed', [
                    'url_key' => $urlKey,
                    'error' => $e->getMessage(),
                ]);
                $this->messageManager->addErrorMessage($e->getMessage());
                $this->dataPersistor->set('panth_dynamicforms_form', $data);
                if ($formId) {
                    return $resultRedirect->setPath('*/*/edit', ['form_id' => $formId]);
                }
                return $resultRedirect->setPath('*/*/new');
            }
        }

        $model->setData($this->dataPreparer->prepare($data));

        if ($formId) {
            $model->setId($formId);
        }

        $connection = $this->formResource->getConnection();
        $connection->beginTransaction();

        try {
            $this->formResource->save($model);
            $savedFormId = (int) $model->getId();
            $this->logger->debug('DynamicForms Save: Form saved', ['form_id' => $savedFormId]);

            $this->processFields($savedFormId, $fieldsJson);
            $connection->commit();

            $this->messageManager->addSuccessMessage(__('The form has been saved.'));
            $this->dataPersistor->clear('panth_dynamicforms_form');

            if ($this->getRequest()->getParam('back') === 'edit') {
                return $resultRedirect->setPath('*/*/edit', ['form_id' => $savedFormId]);
            }

            return $resultRedirect->setPath('*/*/');
        } catch (LocalizedException $e) {
            $connection->rollBack();
            $this->logger->error('DynamicForms Save: LocalizedException', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $connection->rollBack();
            $this->logger->critical('DynamicForms Save: Unexpected exception', [
                'exception_class' => get_class($e),
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->messageManager->addErrorMessage(
                __('Something went wrong while saving the form. Check var/log/system.log for details.')
            );
        }

        $this->dataPersistor->set('panth_dynamicforms_form', $data);

        if ($formId) {
            return $resultRedirect->setPath('*/*/edit', ['form_id' => $formId]);
        }

        return $resultRedirect->setPath('*/*/new');
    }

    private function validateUrlKeyUniqueness(string $urlKey, int $currentFormId): void
    {
        $connection = $this->formResource->getConnection();
        $select = $connection->select()
            ->from($this->formResource->getMainTable(), ['form_id'])
            ->where('url_key = ?', $urlKey);

        if ($currentFormId) {
            $select->where('form_id != ?', $currentFormId);
        }

        $existingId = $connection->fetchOne($select);
        if ($existingId) {
            throw new LocalizedException(
                __('The URL key "%1" is already used by another form (ID: %2). Please choose a different URL key.', $urlKey, $existingId)
            );
        }

        $urlRewriteTable = $connection->getTableName('url_rewrite');
        $select = $connection->select()
            ->from($urlRewriteTable, ['url_rewrite_id'])
            ->where('request_path = ?', $urlKey)
            ->limit(1);

        $rewriteId = $connection->fetchOne($select);
        if ($rewriteId) {
            throw new LocalizedException(
                __('The URL key "%1" conflicts with an existing URL rewrite. Please choose a different URL key.', $urlKey)
            );
        }
    }

    private function processFields(int $formId, string $fieldsJson): void
    {
        try {
            $fields = $this->json->unserialize($fieldsJson);
        } catch (\Exception $e) {
            $this->logger->error('DynamicForms Save: Failed to parse fields JSON', [
                'form_id' => $formId,
                'error' => $e->getMessage(),
            ]);
            $fields = [];
        }

        if (!is_array($fields)) {
            $this->logger->debug('DynamicForms Save: Parsed fields is not an array, resetting to empty');
            $fields = [];
        }

        $existingCollection = $this->fieldCollectionFactory->create();
        $existingCollection->addFieldToFilter('form_id', $formId);
        $existingFieldIds = [];
        foreach ($existingCollection as $existingField) {
            $existingFieldIds[] = (int) $existingField->getId();
        }

        $this->logger->debug('DynamicForms Save: Processing fields', [
            'form_id' => $formId,
            'submitted_count' => count($fields),
            'existing_field_ids' => $existingFieldIds,
        ]);

        $submittedFieldIds = [];

        foreach ($fields as $sortOrder => $fieldData) {
            if (!is_array($fieldData)) {
                continue;
            }

            $fieldId = isset($fieldData['field_id']) ? (int) $fieldData['field_id'] : 0;

            $fieldModel = $this->fieldFactory->create();

            if ($fieldId && in_array($fieldId, $existingFieldIds, true)) {
                $this->fieldResource->load($fieldModel, $fieldId);
                $submittedFieldIds[] = $fieldId;
            }

            $fieldModel->setData('form_id', $formId);
            $fieldModel->setData('field_type', $fieldData['field_type'] ?? 'text');
            $fieldModel->setData('label', $fieldData['label'] ?? '');
            $fieldModel->setData('name', $fieldData['name'] ?? '');
            $fieldModel->setData('placeholder', $fieldData['placeholder'] ?? '');
            $fieldModel->setData('default_value', $fieldData['default_value'] ?? '');
            $fieldModel->setData('is_required', (int) ($fieldData['is_required'] ?? 0));
            $fieldModel->setData('css_class', $fieldData['css_class'] ?? '');
            $fieldModel->setData('width', $fieldData['width'] ?? 'full');
            $fieldModel->setData('sort_order', (int) ($fieldData['sort_order'] ?? $sortOrder));
            $fieldModel->setData('is_active', (int) ($fieldData['is_active'] ?? 1));

            if (isset($fieldData['options'])) {
                $fieldModel->setData(
                    'options',
                    is_array($fieldData['options'])
                        ? $this->json->serialize($fieldData['options'])
                        : $fieldData['options']
                );
            }

            if (isset($fieldData['validation_rules'])) {
                $fieldModel->setData(
                    'validation_rules',
                    is_array($fieldData['validation_rules'])
                        ? $this->json->serialize($fieldData['validation_rules'])
                        : $fieldData['validation_rules']
                );
            }

            try {
                $this->fieldResource->save($fieldModel);
                $this->logger->debug('DynamicForms Save: Field saved', [
                    'form_id' => $formId,
                    'field_id' => (int) $fieldModel->getId(),
                ]);
            } catch (\Exception $e) {
                $this->logger->error('DynamicForms Save: Failed to save field', [
                    'form_id' => $formId,
                    'field_id' => $fieldId,
                    'position' => $sortOrder,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        }

        $fieldsToDelete = array_diff($existingFieldIds, $submittedFieldIds);

        foreach ($fieldsToDelete as $deleteFieldId) {
            $fieldModel = $this->fieldFactory->create();
            $this->fieldResource->load($fieldModel, $deleteFieldId);
            if ($fieldModel->getId()) {
                $this->fieldResource->delete($fieldModel);
            }
        }

        $this->logger->debug('DynamicForms Save: Field processing complete', [
            'form_id' => $formId,
            'saved_count' => count($fields),
            'deleted_field_ids' => array_values($fieldsToDelete),
        ]);
    }

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed(self::ADMIN_RESOURCE);
    }
}
