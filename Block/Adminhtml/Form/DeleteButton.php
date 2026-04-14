<?php

declare(strict_types=1);

namespace Panth\DynamicForms\Block\Adminhtml\Form;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;
use Magento\Backend\Block\Widget\Context;

class DeleteButton implements ButtonProviderInterface
{
    private Context $context;

    public function __construct(Context $context)
    {
        $this->context = $context;
    }

    public function getButtonData(): array
    {
        $formId = (int) $this->context->getRequest()->getParam('form_id');
        if (!$formId) {
            return [];
        }

        return [
            'label' => __('Delete'),
            'class' => 'delete',
            'on_click' => sprintf(
                "deleteConfirm('%s', '%s')",
                __('Are you sure you want to delete this form?'),
                $this->context->getUrlBuilder()->getUrl('*/*/delete', ['form_id' => $formId])
            ),
            'sort_order' => 20,
        ];
    }
}
