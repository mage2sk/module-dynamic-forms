<?php
declare(strict_types=1);

namespace Panth\DynamicForms\Model\ResourceModel\Submission;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Panth\DynamicForms\Model\Submission as SubmissionModel;
use Panth\DynamicForms\Model\ResourceModel\Submission as SubmissionResource;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'submission_id';

    protected $_eventPrefix = 'panth_dynamic_form_submission_collection';

    protected function _construct(): void
    {
        $this->_init(SubmissionModel::class, SubmissionResource::class);
    }
}
