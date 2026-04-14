<?php

declare(strict_types=1);

namespace Panth\DynamicForms\Controller\Adminhtml\Form;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
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

    public function __construct(
        Context $context,
        FormFactory $formFactory,
        FormResource $formResource,
        FieldFactory $fieldFactory,
        FieldResource $fieldResource,
        FieldCollectionFactory $fieldCollectionFactory,
        DataPersistorInterface $dataPersistor,
        Json $json,
        LoggerInterface $logger
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
    }

    public function execute(): ResultInterface
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $data = $this->getRequest()->getPostValue();

        $this->logger->info('DynamicForms Save: Incoming POST data', [
            'keys' => array_keys($data ?: []),
            'form_id' => $data['form_id'] ?? 'not set',
            'name' => $data['name'] ?? 'not set',
            'url_key' => $data['url_key'] ?? 'not set',
            'has_fields_json' => isset($data['fields_json']) ? 'yes' : 'no',
        ]);

        if (!$data) {
            $this->logger->warning('DynamicForms Save: No POST data received');
            return $resultRedirect->setPath('*/*/');
        }

        $formId = (int) ($data['form_id'] ?? 0);
        $model = $this->formFactory->create();

        if ($formId) {
            $this->formResource->load($model, $formId);
            if (!$model->getId()) {
                $this->logger->error('DynamicForms Save: Form ID ' . $formId . ' not found in database');
                $this->messageManager->addErrorMessage(__('This form no longer exists.'));
                return $resultRedirect->setPath('*/*/');
            }
            $this->logger->info('DynamicForms Save: Loaded existing form', ['form_id' => $formId]);
        } else {
            $this->logger->info('DynamicForms Save: Creating new form');
        }

        // Extract fields JSON before setting form data
        $fieldsJson = $data['fields_json'] ?? '[]';
        $this->logger->info('DynamicForms Save: Fields JSON', [
            'fields_json_length' => strlen($fieldsJson),
            'fields_json_preview' => mb_substr($fieldsJson, 0, 500),
        ]);

        // Handle form_type
        $formType = $data['form_type'] ?? 'page';

        // Handle url_key based on form_type
        $urlKey = isset($data['url_key']) ? trim((string) $data['url_key']) : '';

        // If widget-only, clear URL key (not needed)
        if ($formType === 'widget') {
            $data['url_key'] = null;
            $urlKey = '';
            $this->logger->info('DynamicForms Save: Widget-only form, url_key cleared');
        }

        // If page or both type, URL key is required
        if (in_array($formType, ['page', 'both']) && $urlKey === '') {
            $this->messageManager->addErrorMessage(__('URL Key is required for forms with a standalone page.'));
            $this->dataPersistor->set('panth_dynamicforms_form', $data);
            if ($formId) {
                return $resultRedirect->setPath('*/*/edit', ['form_id' => $formId]);
            }
            return $resultRedirect->setPath('*/*/new');
        }

        if ($urlKey === '') {
            $data['url_key'] = null;
            $this->logger->info('DynamicForms Save: url_key is empty, setting to NULL');
        } else {
            // Sanitize url_key
            $urlKey = strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '-', $urlKey));
            $urlKey = preg_replace('/-+/', '-', trim($urlKey, '-'));
            $data['url_key'] = $urlKey;
            $this->logger->info('DynamicForms Save: Sanitized url_key', ['url_key' => $urlKey]);

            // Validate url_key uniqueness
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

        // Remove keys that should not be saved to the form model
        unset(
            $data['fields_json'],
            $data['form_key'],
            $data['fields_note']
        );

        // Set form data
        $model->setData($data);

        // Ensure form_id is preserved for existing forms
        if ($formId) {
            $model->setId($formId);
        }

        $this->logger->info('DynamicForms Save: Model data before save', [
            'model_data_keys' => array_keys($model->getData()),
            'name' => $model->getData('name'),
            'url_key' => $model->getData('url_key'),
            'is_active' => $model->getData('is_active'),
            'store_id' => $model->getData('store_id'),
        ]);

        try {
            $this->formResource->save($model);
            $savedFormId = (int) $model->getId();
            $this->logger->info('DynamicForms Save: Form saved successfully', ['form_id' => $savedFormId]);

            // Process fields
            $this->processFields($savedFormId, $fieldsJson);

            $this->messageManager->addSuccessMessage(__('The form has been saved.'));
            $this->dataPersistor->clear('panth_dynamicforms_form');

            if ($this->getRequest()->getParam('back') === 'edit') {
                return $resultRedirect->setPath('*/*/edit', ['form_id' => $savedFormId]);
            }

            return $resultRedirect->setPath('*/*/');
        } catch (LocalizedException $e) {
            $this->logger->error('DynamicForms Save: LocalizedException', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
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

    /**
     * Validate that the url_key is unique across forms and URL rewrites
     */
    private function validateUrlKeyUniqueness(string $urlKey, int $currentFormId): void
    {
        // Check uniqueness in panth_dynamic_form table
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

        // Check uniqueness in url_rewrite table
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

    /**
     * Process form fields: add new, update existing, delete removed
     */
    private function processFields(int $formId, string $fieldsJson): void
    {
        $this->logger->info('DynamicForms Save: Processing fields', ['form_id' => $formId]);

        try {
            $fields = $this->json->unserialize($fieldsJson);
        } catch (\Exception $e) {
            $this->logger->error('DynamicForms Save: Failed to parse fields JSON', [
                'error' => $e->getMessage(),
                'json_preview' => mb_substr($fieldsJson, 0, 500),
            ]);
            $fields = [];
        }

        if (!is_array($fields)) {
            $this->logger->warning('DynamicForms Save: Parsed fields is not an array, resetting to empty');
            $fields = [];
        }

        $this->logger->info('DynamicForms Save: Field count', ['count' => count($fields)]);

        // Get existing field IDs for this form
        $existingCollection = $this->fieldCollectionFactory->create();
        $existingCollection->addFieldToFilter('form_id', $formId);
        $existingFieldIds = [];
        foreach ($existingCollection as $existingField) {
            $existingFieldIds[] = (int) $existingField->getId();
        }

        $this->logger->info('DynamicForms Save: Existing fields', [
            'existing_field_ids' => $existingFieldIds,
        ]);

        // Track which field IDs are in the submitted data
        $submittedFieldIds = [];

        foreach ($fields as $sortOrder => $fieldData) {
            $fieldId = isset($fieldData['field_id']) ? (int) $fieldData['field_id'] : 0;

            $fieldModel = $this->fieldFactory->create();

            if ($fieldId && in_array($fieldId, $existingFieldIds, true)) {
                // Update existing field
                $this->fieldResource->load($fieldModel, $fieldId);
                $submittedFieldIds[] = $fieldId;
                $this->logger->info('DynamicForms Save: Updating existing field', ['field_id' => $fieldId]);
            } else {
                $this->logger->info('DynamicForms Save: Creating new field', [
                    'label' => $fieldData['label'] ?? '',
                    'field_type' => $fieldData['field_type'] ?? 'text',
                ]);
            }

            // Set field data
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

            // Handle JSON fields
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
                $this->logger->info('DynamicForms Save: Field saved', [
                    'field_id' => $fieldModel->getId(),
                    'label' => $fieldModel->getData('label'),
                ]);
            } catch (\Exception $e) {
                $this->logger->error('DynamicForms Save: Failed to save field', [
                    'label' => $fieldData['label'] ?? '',
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        }

        // Delete fields that were removed
        $fieldsToDelete = array_diff($existingFieldIds, $submittedFieldIds);
        if (!empty($fieldsToDelete)) {
            $this->logger->info('DynamicForms Save: Deleting removed fields', [
                'field_ids' => array_values($fieldsToDelete),
            ]);
        }

        foreach ($fieldsToDelete as $deleteFieldId) {
            $fieldModel = $this->fieldFactory->create();
            $this->fieldResource->load($fieldModel, $deleteFieldId);
            if ($fieldModel->getId()) {
                $this->fieldResource->delete($fieldModel);
                $this->logger->info('DynamicForms Save: Deleted field', ['field_id' => $deleteFieldId]);
            }
        }

        $this->logger->info('DynamicForms Save: Field processing complete');
    }

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed(self::ADMIN_RESOURCE);
    }
}
