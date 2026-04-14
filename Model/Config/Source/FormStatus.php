<?php

declare(strict_types=1);

namespace Panth\DynamicForms\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class FormStatus implements OptionSourceInterface
{
    public const STATUS_NEW = 'new';
    public const STATUS_READ = 'read';
    public const STATUS_REPLIED = 'replied';
    public const STATUS_CLOSED = 'closed';

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::STATUS_NEW, 'label' => __('New')],
            ['value' => self::STATUS_READ, 'label' => __('Read')],
            ['value' => self::STATUS_REPLIED, 'label' => __('Replied')],
            ['value' => self::STATUS_CLOSED, 'label' => __('Closed')],
        ];
    }
}
