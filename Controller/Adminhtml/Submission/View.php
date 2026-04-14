<?php

declare(strict_types=1);

namespace Panth\DynamicForms\Controller\Adminhtml\Submission;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Controller\ResultInterface;
use Panth\DynamicForms\Model\SubmissionFactory;
use Panth\DynamicForms\Model\ResourceModel\Submission as SubmissionResource;

class View extends Action
{
    public const ADMIN_RESOURCE = 'Panth_DynamicForms::submission';

    private PageFactory $resultPageFactory;
    private SubmissionFactory $submissionFactory;
    private SubmissionResource $submissionResource;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        SubmissionFactory $submissionFactory,
        SubmissionResource $submissionResource
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->submissionFactory = $submissionFactory;
        $this->submissionResource = $submissionResource;
    }

    public function execute(): ResultInterface
    {
        $submissionId = (int) $this->getRequest()->getParam('submission_id');
        $submission = $this->submissionFactory->create();
        $this->submissionResource->load($submission, $submissionId);

        if (!$submission->getId()) {
            $this->messageManager->addErrorMessage(__('This submission no longer exists.'));
            $resultRedirect = $this->resultRedirectFactory->create();
            return $resultRedirect->setPath('*/*/index');
        }

        // Mark as read if status is new
        if ($submission->getData('status') === 'new') {
            $submission->setData('status', 'read');
            $this->submissionResource->save($submission);
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Panth_DynamicForms::submission');
        $resultPage->getConfig()->getTitle()->prepend(
            __('Submission #%1', $submission->getId())
        );

        return $resultPage;
    }

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed(self::ADMIN_RESOURCE);
    }
}
