<?php

declare(strict_types=1);

namespace Panth\DynamicForms\Controller\Adminhtml\Submission;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Panth\DynamicForms\Model\Config\Source\FormStatus;
use Panth\DynamicForms\Model\SubmissionFactory;
use Panth\DynamicForms\Model\ResourceModel\Submission as SubmissionResource;

class UpdateStatus extends Action
{
    public const ADMIN_RESOURCE = 'Panth_DynamicForms::submission';

    private JsonFactory $jsonFactory;
    private SubmissionFactory $submissionFactory;
    private SubmissionResource $submissionResource;

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        SubmissionFactory $submissionFactory,
        SubmissionResource $submissionResource
    ) {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->submissionFactory = $submissionFactory;
        $this->submissionResource = $submissionResource;
    }

    public function execute(): ResultInterface
    {
        $resultJson = $this->jsonFactory->create();
        $submissionId = (int) $this->getRequest()->getParam('submission_id');
        $status = (string) $this->getRequest()->getParam('status');
        $adminNotes = $this->getRequest()->getParam('admin_notes');

        $validStatuses = [
            FormStatus::STATUS_NEW,
            FormStatus::STATUS_READ,
            FormStatus::STATUS_REPLIED,
            FormStatus::STATUS_CLOSED,
        ];

        if (!$submissionId || !in_array($status, $validStatuses, true)) {
            return $resultJson->setData([
                'success' => false,
                'message' => __('Invalid parameters.'),
            ]);
        }

        try {
            $submission = $this->submissionFactory->create();
            $this->submissionResource->load($submission, $submissionId);

            if (!$submission->getId()) {
                return $resultJson->setData([
                    'success' => false,
                    'message' => __('Submission not found.'),
                ]);
            }

            $submission->setData('status', $status);

            if ($adminNotes !== null) {
                $submission->setData('admin_notes', $adminNotes);
            }

            $this->submissionResource->save($submission);

            return $resultJson->setData([
                'success' => true,
                'message' => __('Status updated successfully.'),
            ]);
        } catch (\Exception $e) {
            return $resultJson->setData([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed(self::ADMIN_RESOURCE);
    }
}
