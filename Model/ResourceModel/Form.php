<?php

declare(strict_types=1);

namespace Panth\DynamicForms\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Form extends AbstractDb
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'panth_dynamic_form_resource';

    /**
     * @inheritdoc
     */
    protected function _construct(): void
    {
        $this->_init('panth_dynamic_form', 'form_id');
    }
}
