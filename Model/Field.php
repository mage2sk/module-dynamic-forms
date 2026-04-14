<?php

declare(strict_types=1);

namespace Panth\DynamicForms\Model;

use Magento\Framework\Model\AbstractModel;
use Panth\DynamicForms\Model\ResourceModel\Field as FieldResource;

class Field extends AbstractModel
{
    protected $_eventPrefix = 'panth_dynamic_form_field';

    protected function _construct(): void
    {
        $this->_init(FieldResource::class);
    }
}
