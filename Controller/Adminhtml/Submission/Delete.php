<?php

declare(strict_types=1);

namespace Panth\DynamicForms\Controller\Adminhtml\Submission;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultInterface;
use Panth\DynamicForms\Model\SubmissionFactory;
use Panth\DynamicForms\Model\ResourceModel\Submission as SubmissionResource;

class Delete extends Action
{
    public const ADMIN_RESOURCE = 'Panth_DynamicForms::submission';

    private SubmissionFactory $submissionFactory;
    private SubmissionResource $submissionResource;

    public function __construct(
        Context $context,
        SubmissionFactory $submissionFactory,
        SubmissionResource $submissionResource
    ) {
        parent::__construct($context);
        $this->submissionFactory = $submissionFactory;
        $this->submissionResource = $submissionResource;
    }

    public function execute(): ResultInterface
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $submissionId = (int) $this->getRequest()->getParam('submission_id');
        $formId = (int) $this->getRequest()->getParam('form_id');

        if (!$submissionId) {
            $this->messageManager->addErrorMessage(__('We cannot find a submission to delete.'));
            return $resultRedirect->setPath('*/*/index', ['form_id' => $formId]);
        }

        try {
            $model = $this->submissionFactory->create();
            $this->submissionResource->load($model, $submissionId);

            if (!$model->getId()) {
                $this->messageManager->addErrorMessage(__('This submission no longer exists.'));
                return $resultRedirect->setPath('*/*/index', ['form_id' => $formId]);
            }

            $formId = (int) $model->getData('form_id');
            $this->submissionResource->delete($model);
            $this->messageManager->addSuccessMessage(__('The submission has been deleted.'));
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $resultRedirect->setPath('*/*/index', ['form_id' => $formId]);
    }

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed(self::ADMIN_RESOURCE);
    }
}
