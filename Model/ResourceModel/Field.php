<?php

declare(strict_types=1);

namespace Panth\DynamicForms\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Field extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('panth_dynamic_form_field', 'field_id');
    }
}
