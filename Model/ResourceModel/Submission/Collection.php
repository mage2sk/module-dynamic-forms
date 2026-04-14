<?php

declare(strict_types=1);

namespace Panth\DynamicForms\Model\ResourceModel\Submission;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Panth\DynamicForms\Model\Submission as SubmissionModel;
use Panth\DynamicForms\Model\ResourceModel\Submission as SubmissionResource;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'submission_id';

    /**
     * @var string
     */
    protected $_eventPrefix = 'panth_dynamic_form_submission_collection';

    /**
     * @inheritdoc
     */
    protected function _construct(): void
    {
        $this->_init(SubmissionModel::class, SubmissionResource::class);
    }
}
