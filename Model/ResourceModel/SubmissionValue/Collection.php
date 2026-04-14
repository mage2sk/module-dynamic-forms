<?php

declare(strict_types=1);

namespace Panth\DynamicForms\Model\ResourceModel\SubmissionValue;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Panth\DynamicForms\Model\SubmissionValue as SubmissionValueModel;
use Panth\DynamicForms\Model\ResourceModel\SubmissionValue as SubmissionValueResource;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'value_id';

    /**
     * @var string
     */
    protected $_eventPrefix = 'panth_dynamic_form_submission_value_collection';

    /**
     * @inheritdoc
     */
    protected function _construct(): void
    {
        $this->_init(SubmissionValueModel::class, SubmissionValueResource::class);
    }
}
