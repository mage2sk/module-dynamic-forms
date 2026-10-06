<?php
declare(strict_types=1);

namespace Panth\DynamicForms\Model;

use Magento\Framework\Model\AbstractModel;
use Panth\DynamicForms\Model\ResourceModel\Submission as SubmissionResource;

class Submission extends AbstractModel
{
    protected $_eventPrefix = 'panth_dynamic_form_submission';

    protected function _construct(): void
    {
        $this->_init(SubmissionResource::class);
    }
}
