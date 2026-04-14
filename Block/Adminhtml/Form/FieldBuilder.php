<?php

declare(strict_types=1);

namespace Panth\DynamicForms\Block\Adminhtml\Form;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Serialize\Serializer\Json;
use Panth\DynamicForms\Model\FormFactory;
use Panth\DynamicForms\Model\ResourceModel\Form as FormResource;
use Panth\DynamicForms\Model\ResourceModel\Field\CollectionFactory as FieldCollectionFactory;
use Panth\DynamicForms\Model\Config\Source\FieldType;
use Panth\DynamicForms\Model\Config\Source\FieldWidth;

class FieldBuilder extends Template
{
    private FormFactory $formFactory;
    private FormResource $formResource;
    private FieldCollectionFactory $fieldCollectionFactory;
    private Json $json;
    private FieldType $fieldTypeSource;
    private FieldWidth $fieldWidthSource;

    public function __construct(
        Context $context,
        FormFactory $formFactory,
        FormResource $formResource,
        FieldCollectionFactory $fieldCollectionFactory,
        Json $json,
        FieldType $fieldTypeSource,
        FieldWidth $fieldWidthSource,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->formFactory = $formFactory;
        $this->formResource = $formResource;
        $this->fieldCollectionFactory = $fieldCollectionFactory;
        $this->json = $json;
        $this->fieldTypeSource = $fieldTypeSource;
        $this->fieldWidthSource = $fieldWidthSource;
    }

    public function getFieldsJson(): string
    {
        $formId = (int) $this->getRequest()->getParam('form_id');
        if (!$formId) {
            return '[]';
        }

        $collection = $this->fieldCollectionFactory->create();
        $collection->addFieldToFilter('form_id', $formId);
        $collection->setOrder('sort_order', 'ASC');

        $fields = [];
        foreach ($collection as $field) {
            $fieldData = $field->getData();

            // Parse JSON fields
            if (!empty($fieldData['options']) && is_string($fieldData['options'])) {
                try {
                    $fieldData['options'] = $this->json->unserialize($fieldData['options']);
                } catch (\Exception $e) {
                    $fieldData['options'] = [];
                }
            } else {
                $fieldData['options'] = $fieldData['options'] ?? [];
            }

            if (!empty($fieldData['validation_rules']) && is_string($fieldData['validation_rules'])) {
                try {
                    $fieldData['validation_rules'] = $this->json->unserialize($fieldData['validation_rules']);
                } catch (\Exception $e) {
                    $fieldData['validation_rules'] = [];
                }
            } else {
                $fieldData['validation_rules'] = $fieldData['validation_rules'] ?? [];
            }

            $fields[] = $fieldData;
        }

        return $this->json->serialize($fields);
    }

    public function getFieldTypesJson(): string
    {
        return $this->json->serialize($this->fieldTypeSource->toOptionArray());
    }

    public function getFieldWidthsJson(): string
    {
        return $this->json->serialize($this->fieldWidthSource->toOptionArray());
    }

    /**
     * Types that require options configuration
     */
    public function getOptionFieldTypes(): string
    {
        return $this->json->serialize(['select', 'multiselect', 'checkbox', 'radio']);
    }
}
