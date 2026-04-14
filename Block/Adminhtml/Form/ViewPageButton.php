<?php

declare(strict_types=1);

namespace Panth\DynamicForms\Block\Adminhtml\Form;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panth\DynamicForms\Model\FormFactory;
use Panth\DynamicForms\Model\ResourceModel\Form as FormResource;

class ViewPageButton implements ButtonProviderInterface
{
    private RequestInterface $request;
    private StoreManagerInterface $storeManager;
    private FormFactory $formFactory;
    private FormResource $formResource;

    public function __construct(
        RequestInterface $request,
        StoreManagerInterface $storeManager,
        FormFactory $formFactory,
        FormResource $formResource
    ) {
        $this->request = $request;
        $this->storeManager = $storeManager;
        $this->formFactory = $formFactory;
        $this->formResource = $formResource;
    }

    public function getButtonData(): array
    {
        $formId = (int) $this->request->getParam('form_id');
        if (!$formId) {
            return [];
        }

        $form = $this->formFactory->create();
        $this->formResource->load($form, $formId);
        $urlKey = $form->getData('url_key');

        if (!$urlKey) {
            return [];
        }

        $baseUrl = rtrim($this->storeManager->getStore()->getBaseUrl(), '/');
        $frontendUrl = $baseUrl . '/pages/' . $urlKey;

        return [
            'label' => __('View Page'),
            'on_click' => "window.open('" . $frontendUrl . "', '_blank')",
            'class' => 'action-secondary',
            'sort_order' => 5,
        ];
    }
}
