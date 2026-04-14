<?php

declare(strict_types=1);

namespace Panth\DynamicForms\Controller\Adminhtml\Submission;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Controller\ResultInterface;
use Panth\DynamicForms\Model\FormFactory;
use Panth\DynamicForms\Model\ResourceModel\Form as FormResource;

class Index extends Action
{
    public const ADMIN_RESOURCE = 'Panth_DynamicForms::submission';

    private PageFactory $resultPageFactory;
    private FormFactory $formFactory;
    private FormResource $formResource;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        FormFactory $formFactory,
        FormResource $formResource
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->formFactory = $formFactory;
        $this->formResource = $formResource;
    }

    public function execute(): ResultInterface
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Panth_DynamicForms::submission');

        $formId = (int) $this->getRequest()->getParam('form_id');
        if ($formId) {
            $form = $this->formFactory->create();
            $this->formResource->load($form, $formId);
            if ($form->getId()) {
                $resultPage->getConfig()->getTitle()->prepend(
                    __('Submissions for: %1', $form->getData('name'))
                );
                return $resultPage;
            }
        }

        $resultPage->getConfig()->getTitle()->prepend(__('Form Submissions'));

        return $resultPage;
    }

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed(self::ADMIN_RESOURCE);
    }
}
