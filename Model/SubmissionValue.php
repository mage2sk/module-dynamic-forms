<?php

declare(strict_types=1);

namespace Panth\DynamicForms\Model;

use Magento\Framework\Model\AbstractModel;
use Panth\DynamicForms\Model\ResourceModel\SubmissionValue as SubmissionValueResource;

class SubmissionValue extends AbstractModel
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'panth_dynamic_form_submission_value';

    /**
     * @inheritdoc
     */
    protected function _construct(): void
    {
        $this->_init(SubmissionValueResource::class);
    }
}
