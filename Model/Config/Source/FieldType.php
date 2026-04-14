<?php

declare(strict_types=1);

namespace Panth\DynamicForms\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class FieldType implements OptionSourceInterface
{
    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'text', 'label' => __('Text')],
            ['value' => 'textarea', 'label' => __('Textarea')],
            ['value' => 'email', 'label' => __('Email')],
            ['value' => 'phone', 'label' => __('Phone')],
            ['value' => 'number', 'label' => __('Number')],
            ['value' => 'select', 'label' => __('Dropdown (Select)')],
            ['value' => 'multiselect', 'label' => __('Multi-Select')],
            ['value' => 'checkbox', 'label' => __('Checkbox')],
            ['value' => 'radio', 'label' => __('Radio Buttons')],
            ['value' => 'file', 'label' => __('File Upload')],
            ['value' => 'date', 'label' => __('Date')],
            ['value' => 'hidden', 'label' => __('Hidden')],
            ['value' => 'wysiwyg', 'label' => __('WYSIWYG Editor')],
        ];
    }
}
