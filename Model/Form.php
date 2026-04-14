<?php

declare(strict_types=1);

namespace Panth\DynamicForms\Model;

use Magento\Framework\Model\AbstractModel;
use Panth\DynamicForms\Model\ResourceModel\Form as FormResource;

class Form extends AbstractModel
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'panth_dynamic_form';

    /**
     * @inheritdoc
     */
    protected function _construct(): void
    {
        $this->_init(FormResource::class);
    }
}
