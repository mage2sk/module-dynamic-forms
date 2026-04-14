<?php

declare(strict_types=1);

namespace Panth\DynamicForms\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class SubmissionValue extends AbstractDb
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'panth_dynamic_form_submission_value_resource';

    /**
     * @inheritdoc
     */
    protected function _construct(): void
    {
        $this->_init('panth_dynamic_form_submission_value', 'value_id');
    }
}
