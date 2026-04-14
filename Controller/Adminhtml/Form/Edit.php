<?php

declare(strict_types=1);

namespace Panth\DynamicForms\Controller\Adminhtml\Form;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Controller\ResultInterface;
use Panth\DynamicForms\Model\FormFactory;
use Panth\DynamicForms\Model\ResourceModel\Form as FormResource;

class Edit extends Action
{
    public const ADMIN_RESOURCE = 'Panth_DynamicForms::form';

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
        $formId = (int) $this->getRequest()->getParam('form_id');
        $model = $this->formFactory->create();

        if ($formId) {
            $this->formResource->load($model, $formId);
            if (!$model->getId()) {
                $this->messageManager->addErrorMessage(__('This form no longer exists.'));
                $resultRedirect = $this->resultRedirectFactory->create();
                return $resultRedirect->setPath('*/*/');
            }
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Panth_DynamicForms::form');
        $resultPage->getConfig()->getTitle()->prepend(
            $model->getId() ? __('Edit Form: %1', $model->getData('name')) : __('New Form')
        );

        return $resultPage;
    }

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed(self::ADMIN_RESOURCE);
    }
}
