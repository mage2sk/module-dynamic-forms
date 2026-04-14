<?php

declare(strict_types=1);

namespace Panth\DynamicForms\Controller\Adminhtml\Submission;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultInterface;
use Magento\Ui\Component\MassAction\Filter;
use Panth\DynamicForms\Model\ResourceModel\Submission\CollectionFactory;
use Panth\DynamicForms\Model\ResourceModel\Submission as SubmissionResource;

class MassDelete extends Action
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
        $formId = (int) $this->getRequest()->getParam('form_id');

        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            $count = 0;

            foreach ($collection as $submission) {
                if (!$formId) {
                    $formId = (int) $submission->getData('form_id');
                }
                $this->submissionResource->delete($submission);
                $count++;
            }

            $this->messageManager->addSuccessMessage(
                __('A total of %1 submission(s) have been deleted.', $count)
            );
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
