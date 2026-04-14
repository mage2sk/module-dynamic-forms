<?php

declare(strict_types=1);

namespace Panth\DynamicForms\Controller\Adminhtml\Form;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultInterface;
use Panth\DynamicForms\Model\FormFactory;
use Panth\DynamicForms\Model\ResourceModel\Form as FormResource;

class Delete extends Action
{
    public const ADMIN_RESOURCE = 'Panth_DynamicForms::form';

    private FormFactory $formFactory;
    private FormResource $formResource;

    public function __construct(
        Context $context,
        FormFactory $formFactory,
        FormResource $formResource
    ) {
        parent::__construct($context);
        $this->formFactory = $formFactory;
        $this->formResource = $formResource;
    }

    public function execute(): ResultInterface
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $formId = (int) $this->getRequest()->getParam('form_id');

        if (!$formId) {
            $this->messageManager->addErrorMessage(__('We cannot find a form to delete.'));
            return $resultRedirect->setPath('*/*/');
        }

        try {
            $model = $this->formFactory->create();
            $this->formResource->load($model, $formId);

            if (!$model->getId()) {
                $this->messageManager->addErrorMessage(__('This form no longer exists.'));
                return $resultRedirect->setPath('*/*/');
            }

            $this->formResource->delete($model);
            $this->messageManager->addSuccessMessage(__('The form has been deleted.'));
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $resultRedirect->setPath('*/*/');
    }

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed(self::ADMIN_RESOURCE);
    }
}
