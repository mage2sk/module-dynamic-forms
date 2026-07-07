<?php
declare(strict_types=1);

namespace Panth\DynamicForms\Model\ResourceModel\Form;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Panth\DynamicForms\Model\Form as FormModel;
use Panth\DynamicForms\Model\ResourceModel\Form as FormResource;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'form_id';

    protected $_eventPrefix = 'panth_dynamic_form_collection';

    protected function _construct(): void
    {
        $this->_init(FormModel::class, FormResource::class);
    }
}
