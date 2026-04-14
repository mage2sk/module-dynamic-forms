<?php

declare(strict_types=1);

namespace Panth\DynamicForms\Controller\Form;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Magento\Store\Model\StoreManagerInterface;
use Panth\DynamicForms\Helper\Data as Helper;
use Psr\Log\LoggerInterface;

class Upload implements HttpPostActionInterface
{
    private RequestInterface $request;
    private JsonFactory $jsonFactory;
    private Filesystem $filesystem;
    private UploaderFactory $uploaderFactory;
    private StoreManagerInterface $storeManager;
    private Helper $helper;
    private LoggerInterface $logger;

    public function __construct(
        RequestInterface $request,
        JsonFactory $jsonFactory,
        Filesystem $filesystem,
        UploaderFactory $uploaderFactory,
        StoreManagerInterface $storeManager,
        Helper $helper,
        LoggerInterface $logger
    ) {
        $this->request = $request;
        $this->jsonFactory = $jsonFactory;
        $this->filesystem = $filesystem;
        $this->uploaderFactory = $uploaderFactory;
        $this->storeManager = $storeManager;
        $this->helper = $helper;
        $this->logger = $logger;
    }

    public function execute(): \Magento\Framework\Controller\Result\Json
    {
        $result = $this->jsonFactory->create();

        if (!$this->helper->isEnabled()) {
            return $result->setData([
                'success' => false,
                'message' => __('Form uploads are currently disabled.'),
            ]);
        }

        $fieldName = $this->request->getParam('field_name', 'file');

        try {
            // The JS sends file as 'file' in FormData
            $uploader = $this->uploaderFactory->create(['fileId' => 'file']);

            // Set allowed extensions
            $allowedExtensions = $this->helper->getAllowedExtensions();
            $uploader->setAllowedExtensions($allowedExtensions);
            $uploader->setAllowRenameFiles(true);
            $uploader->setFilesDispersion(false);

            // Validate file size
            $maxSize = $this->helper->getMaxFileSize();
            $fileSize = $uploader->getFileSize();
            if ($fileSize > $maxSize) {
                $maxSizeMb = $maxSize / (1024 * 1024);
                return $result->setData([
                    'success' => false,
                    'message' => __('File size exceeds the maximum allowed size of %1 MB.', $maxSizeMb),
                ]);
            }

            // Create upload directory if it doesn't exist
            $mediaDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
            $uploadPath = $this->helper->getUploadRelativePath();

            if (!$mediaDirectory->isDirectory($uploadPath)) {
                $mediaDirectory->create($uploadPath);
            }

            $targetDir = $mediaDirectory->getAbsolutePath($uploadPath);
            $uploadResult = $uploader->save($targetDir);

            if (!$uploadResult || !isset($uploadResult['file'])) {
                return $result->setData([
                    'success' => false,
                    'message' => __('File upload failed. Please try again.'),
                ]);
            }

            $filename = $uploadResult['file'];
            $fileUrl = $this->helper->getFileUrl($filename);

            return $result->setData([
                'success' => true,
                'file' => $filename,
                'url' => $fileUrl,
                'name' => $uploadResult['name'] ?? $filename,
                'size' => $uploadResult['size'] ?? 0,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('DynamicForms file upload error: ' . $e->getMessage());

            $message = __('An error occurred during file upload.');

            // Provide more specific error messages
            if (strpos($e->getMessage(), 'extension') !== false) {
                $message = __('File type is not allowed. Allowed types: %1', implode(', ', $this->helper->getAllowedExtensions()));
            } elseif (strpos($e->getMessage(), 'was not uploaded') !== false) {
                $message = __('No file was uploaded. Please select a file and try again.');
            }

            return $result->setData([
                'success' => false,
                'message' => $message,
            ]);
        }
    }
}
