<?php

declare(strict_types=1);

namespace Panth\DynamicForms\Model\ResourceModel\Field;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Panth\DynamicForms\Model\Field;
use Panth\DynamicForms\Model\ResourceModel\Field as FieldResource;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'field_id';

    protected function _construct(): void
    {
        $this->_init(Field::class, FieldResource::class);
    }
}
