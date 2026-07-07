<?php
declare(strict_types=1);

namespace Panth\DynamicForms\Model\ResourceModel\FormField;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Panth\DynamicForms\Model\FormField as FormFieldModel;
use Panth\DynamicForms\Model\ResourceModel\FormField as FormFieldResource;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'field_id';

    protected $_eventPrefix = 'panth_dynamic_form_field_collection';

    protected function _construct(): void
    {
        $this->_init(FormFieldModel::class, FormFieldResource::class);
    }
}
