<?php

declare(strict_types=1);

namespace Panth\DynamicForms\Model;

use Magento\Framework\Model\AbstractModel;
use Panth\DynamicForms\Model\ResourceModel\FormField as FormFieldResource;

class FormField extends AbstractModel
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'panth_dynamic_form_field';

    /**
     * @inheritdoc
     */
    protected function _construct(): void
    {
        $this->_init(FormFieldResource::class);
    }
}
