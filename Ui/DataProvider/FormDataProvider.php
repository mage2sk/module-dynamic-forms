<?php

declare(strict_types=1);

namespace Panth\DynamicForms\Ui\DataProvider;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Panth\DynamicForms\Model\ResourceModel\Form\CollectionFactory;
use Panth\DynamicForms\Model\ResourceModel\Field\CollectionFactory as FieldCollectionFactory;

class FormDataProvider extends AbstractDataProvider
{
    private DataPersistorInterface $dataPersistor;
    private FieldCollectionFactory $fieldCollectionFactory;
    private Json $json;
    private ?array $loadedData = null;

    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        CollectionFactory $collectionFactory,
        FieldCollectionFactory $fieldCollectionFactory,
        DataPersistorInterface $dataPersistor,
        Json $json,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        $this->fieldCollectionFactory = $fieldCollectionFactory;
        $this->dataPersistor = $dataPersistor;
        $this->json = $json;
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    public function getData(): array
    {
        if ($this->loadedData !== null) {
            return $this->loadedData;
        }

        $this->loadedData = [];
        $items = $this->collection->getItems();

        foreach ($items as $form) {
            $formData = $form->getData();
            $formId = (int) $form->getId();

            // Load fields for this form
            $fieldCollection = $this->fieldCollectionFactory->create();
            $fieldCollection->addFieldToFilter('form_id', $formId);
            $fieldCollection->setOrder('sort_order', 'ASC');

            $fields = [];
            foreach ($fieldCollection as $field) {
                $fieldData = $field->getData();

                // Parse JSON fields for the builder
                if (!empty($fieldData['options']) && is_string($fieldData['options'])) {
                    try {
                        $fieldData['options'] = $this->json->unserialize($fieldData['options']);
                    } catch (\Exception $e) {
                        $fieldData['options'] = [];
                    }
                }

                if (!empty($fieldData['validation_rules']) && is_string($fieldData['validation_rules'])) {
                    try {
                        $fieldData['validation_rules'] = $this->json->unserialize($fieldData['validation_rules']);
                    } catch (\Exception $e) {
                        $fieldData['validation_rules'] = [];
                    }
                }

                $fields[] = $fieldData;
            }

            $formData['fields_json'] = $this->json->serialize($fields);

            // Set widget usage info with actual form ID
            $formData['widget_usage_info'] = 'Form ID: ' . $formId;

            $this->loadedData[$formId] = $formData;
        }

        // Check for persisted data (after validation error)
        $data = $this->dataPersistor->get('panth_dynamicforms_form');
        if (!empty($data)) {
            $form = $this->collection->getNewEmptyItem();
            $form->setData($data);
            $this->loadedData[$form->getId()] = $form->getData();
            $this->dataPersistor->clear('panth_dynamicforms_form');
        }

        return $this->loadedData;
    }
}
