<?php

declare(strict_types=1);

namespace Panth\DynamicForms\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class FieldWidth implements OptionSourceInterface
{
    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'full', 'label' => __('Full Width')],
            ['value' => 'half', 'label' => __('Half Width')],
            ['value' => 'third', 'label' => __('One Third Width')],
        ];
    }
}
