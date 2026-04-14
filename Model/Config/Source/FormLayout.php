<?php

declare(strict_types=1);

namespace Panth\DynamicForms\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class FormLayout implements OptionSourceInterface
{
    /**
     * Return array of layout options
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => '1column', 'label' => __('1 Column')],
            ['value' => '2columns-left', 'label' => __('2 Columns with Left Bar')],
            ['value' => '2columns-right', 'label' => __('2 Columns with Right Bar')]
        ];
    }
}
