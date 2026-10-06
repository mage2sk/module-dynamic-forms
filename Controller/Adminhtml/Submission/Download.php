<?php
declare(strict_types=1);

namespace Panth\DynamicForms\Controller\Adminhtml\Submission;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\App\ResponseInterface;
use Panth\DynamicForms\Helper\Data as Helper;
use Panth\DynamicForms\Model\SubmissionValueFactory;
use Panth\DynamicForms\Model\ResourceModel\SubmissionValue as SubmissionValueResource;

class Download extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Panth_DynamicForms::submission';

    private FileFactory $fileFactory;
    private SubmissionValueFactory $submissionValueFactory;
    private SubmissionValueResource $submissionValueResource;
    private Helper $helper;

    public function __construct(
        Context $context,
        FileFactory $fileFactory,
        SubmissionValueFactory $submissionValueFactory,
        SubmissionValueResource $submissionValueResource,
        Helper $helper
    ) {
        parent::__construct($context);
        $this->fileFactory = $fileFactory;
        $this->submissionValueFactory = $submissionValueFactory;
        $this->submissionValueResource = $submissionValueResource;
        $this->helper = $helper;
    }

    /**
     * @return ResponseInterface|ResultInterface
     */
    public function execute()
    {
        $valueId = (int) $this->getRequest()->getParam('value_id');
        $value = $this->submissionValueFactory->create();
        if ($valueId > 0) {
            $this->submissionValueResource->load($value, $valueId);
        }

        $path = null;
        if ($value->getId() && $value->getData('field_type') === 'file') {
            $path = $this->helper->getUploadedFilePath((string) $value->getData('value'));
        }

        if ($path === null) {
            $this->messageManager->addErrorMessage(__('The requested file could not be found.'));
            $resultRedirect = $this->resultRedirectFactory->create();
            return $resultRedirect->setPath('*/*/index');
        }

        return $this->fileFactory->create(
            basename($path),
            ['type' => 'filename', 'value' => $path],
            $this->helper->getUploadRoot(),
            'application/octet-stream'
        );
    }

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed(self::ADMIN_RESOURCE);
    }
}
