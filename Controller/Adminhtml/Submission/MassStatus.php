<?php
declare(strict_types=1);

namespace Panth\DynamicForms\Controller\Adminhtml\Submission;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Ui\Component\MassAction\Filter;
use Panth\DynamicForms\Model\Config\Source\FormStatus;
use Panth\DynamicForms\Model\ResourceModel\Submission\CollectionFactory;
use Panth\DynamicForms\Model\ResourceModel\Submission as SubmissionResource;

class MassStatus extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Panth_DynamicForms::submission';

    private Filter $filter;
    private CollectionFactory $collectionFactory;
    private SubmissionResource $submissionResource;

    public function __construct(
        Context $context,
        Filter $filter,
        CollectionFactory $collectionFactory,
        SubmissionResource $submissionResource
    ) {
        parent::__construct($context);
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
        $this->submissionResource = $submissionResource;
    }

    public function execute(): ResultInterface
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $status = (string) $this->getRequest()->getParam('status');
        $validStatuses = [
            FormStatus::STATUS_NEW,
            FormStatus::STATUS_READ,
            FormStatus::STATUS_REPLIED,
            FormStatus::STATUS_CLOSED,
        ];

        if (!in_array($status, $validStatuses, true)) {
            $this->messageManager->addErrorMessage(__('Invalid status.'));
            return $resultRedirect->setPath('*/*/');
        }

        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            $count = 0;

            foreach ($collection as $submission) {
                $submission->setData('status', $status);
                $this->submissionResource->save($submission);
                $count++;
            }

            $this->messageManager->addSuccessMessage(
                __('A total of %1 submission(s) have been updated.', $count)
            );
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $resultRedirect->setPath('*/*/');
    }
}
