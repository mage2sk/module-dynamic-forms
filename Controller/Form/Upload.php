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
use Panth\DynamicForms\Model\FormFactory;
use Panth\DynamicForms\Model\ResourceModel\Form as FormResource;
use Panth\DynamicForms\Model\ResourceModel\Field\CollectionFactory as FieldCollectionFactory;
use Panth\Core\Security\UploadExtensionPolicy;
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
    private UploadExtensionPolicy $uploadExtensionPolicy;
    private FormFactory $formFactory;
    private FormResource $formResource;
    private FieldCollectionFactory $fieldCollectionFactory;

    public function __construct(
        RequestInterface $request,
        JsonFactory $jsonFactory,
        Filesystem $filesystem,
        UploaderFactory $uploaderFactory,
        StoreManagerInterface $storeManager,
        Helper $helper,
        LoggerInterface $logger,
        UploadExtensionPolicy $uploadExtensionPolicy,
        FormFactory $formFactory,
        FormResource $formResource,
        FieldCollectionFactory $fieldCollectionFactory
    ) {
        $this->request = $request;
        $this->jsonFactory = $jsonFactory;
        $this->filesystem = $filesystem;
        $this->uploaderFactory = $uploaderFactory;
        $this->storeManager = $storeManager;
        $this->helper = $helper;
        $this->logger = $logger;
        $this->uploadExtensionPolicy = $uploadExtensionPolicy;
        $this->formFactory = $formFactory;
        $this->formResource = $formResource;
        $this->fieldCollectionFactory = $fieldCollectionFactory;
    }

    private const BLOCKED_EXTENSIONS = [
        'html', 'htm', 'xhtml', 'shtml', 'svg', 'svgz', 'js', 'mjs', 'xml', 'xsl', 'xslt',
        'swf', 'exe', 'dll', 'bat', 'cmd', 'com', 'msi', 'jar', 'vbs', 'ps1',
    ];

    private const BLOCKED_MIME_TYPES = [
        'text/html', 'application/xhtml+xml', 'image/svg+xml', 'text/xml', 'application/xml',
        'application/javascript', 'text/javascript', 'application/x-httpd-php', 'text/x-php',
        'application/x-php', 'text/x-shellscript', 'application/x-sh', 'application/x-executable',
        'application/x-dosexec', 'application/x-msdownload', 'application/java-archive',
        'application/x-shockwave-flash',
    ];

    private const EXPECTED_MIME_TYPES = [
        'jpg' => ['image/jpeg', 'image/pjpeg'],
        'jpeg' => ['image/jpeg', 'image/pjpeg'],
        'png' => ['image/png'],
        'gif' => ['image/gif'],
        'webp' => ['image/webp'],
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword', 'application/vnd.ms-office', 'application/cdfv2', 'application/x-ole-storage'],
        'xls' => ['application/vnd.ms-excel', 'application/vnd.ms-office', 'application/cdfv2', 'application/x-ole-storage'],
        'docx' => [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
            'application/octet-stream',
        ],
        'xlsx' => [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/zip',
            'application/octet-stream',
        ],
        'csv' => ['text/plain', 'text/csv', 'application/csv'],
        'txt' => ['text/plain'],
    ];

    public function execute(): \Magento\Framework\Controller\Result\Json
    {
        $result = $this->jsonFactory->create();

        if (!$this->helper->isEnabled()) {
            return $result->setData([
                'success' => false,
                'message' => __('Form uploads are currently disabled.'),
            ]);
        }

        $formId = (int) $this->request->getParam('form_id');
        $fieldName = (string) $this->request->getParam('field_name', '');
        if (!$this->isFileFieldOfActiveForm($formId, $fieldName)) {
            return $result->setData([
                'success' => false,
                'message' => __('File uploads are not accepted for this form.'),
            ]);
        }

        try {
            $uploader = $this->uploaderFactory->create(['fileId' => 'file']);

            $allowedExtensions = $this->helper->getAllowedExtensions();
            $uploader->setAllowedExtensions($allowedExtensions);
            $uploader->setAllowRenameFiles(true);
            $uploader->setFilesDispersion(false);

            $fileInfo = $uploader->validateFile();
            $originalName = is_string($fileInfo['name'] ?? null) ? $fileInfo['name'] : '';
            $this->uploadExtensionPolicy->assertSafeExtension($originalName);

            $extension = strtolower((string) $uploader->getFileExtension());
            if (in_array($extension, self::BLOCKED_EXTENSIONS, true)
                || !in_array($extension, $allowedExtensions, true)
            ) {
                return $result->setData([
                    'success' => false,
                    'message' => __('File type is not allowed. Allowed types: %1', implode(', ', $allowedExtensions)),
                ]);
            }

            $maxSize = $this->helper->getMaxFileSize();
            $fileSize = (int) $uploader->getFileSize();
            if ($fileSize > $maxSize) {
                $maxSizeMb = round($maxSize / (1024 * 1024), 2);
                return $result->setData([
                    'success' => false,
                    'message' => __('File size exceeds the maximum allowed size of %1 MB.', $maxSizeMb),
                ]);
            }

            if (!$this->isContentTypeAllowed((string) ($fileInfo['tmp_name'] ?? ''), $extension)) {
                return $result->setData([
                    'success' => false,
                    'message' => __('The file content does not match its extension.'),
                ]);
            }

            $mediaDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
            $uploadPath = $this->helper->getUploadRelativePath();

            if (!$mediaDirectory->isDirectory($uploadPath)) {
                $mediaDirectory->create($uploadPath);
            }

            $targetDir = $mediaDirectory->getAbsolutePath($uploadPath);
            $storedName = bin2hex(random_bytes(16)) . '.' . $extension;
            $uploadResult = $uploader->save($targetDir, $storedName);

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
                'name' => $originalName !== '' ? $originalName : $filename,
                'size' => $uploadResult['size'] ?? 0,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('DynamicForms file upload error: ' . $e->getMessage());

            $message = __('An error occurred during file upload.');

            if (strpos($e->getMessage(), 'extension') !== false
                || strpos($e->getMessage(), 'file type') !== false
            ) {
                $message = __(
                    'File type is not allowed. Allowed types: %1',
                    implode(', ', $this->helper->getAllowedExtensions())
                );
            } elseif (strpos($e->getMessage(), 'was not uploaded') !== false) {
                $message = __('No file was uploaded. Please select a file and try again.');
            }

            return $result->setData([
                'success' => false,
                'message' => $message,
            ]);
        }
    }

    private function isFileFieldOfActiveForm(int $formId, string $fieldName): bool
    {
        if ($formId <= 0 || $fieldName === '') {
            return false;
        }

        $form = $this->formFactory->create();
        $this->formResource->load($form, $formId);
        if (!$form->getId() || !$form->getData('is_active') || !$this->helper->isFormAvailableInStore($form)) {
            return false;
        }

        $collection = $this->fieldCollectionFactory->create();
        $collection->addFieldToFilter('form_id', $formId)
            ->addFieldToFilter('name', $fieldName)
            ->addFieldToFilter('field_type', 'file')
            ->addFieldToFilter('is_active', 1)
            ->setPageSize(1);

        return $collection->getSize() > 0;
    }

    private function isContentTypeAllowed(string $tmpName, string $extension): bool
    {
        if ($tmpName === '') {
            return false;
        }

        if (!class_exists(\finfo::class)) {
            return true;
        }

        $mimeType = strtolower((string) (new \finfo(FILEINFO_MIME_TYPE))->file($tmpName));
        if ($mimeType === '' || in_array($mimeType, self::BLOCKED_MIME_TYPES, true)) {
            return false;
        }

        if (!isset(self::EXPECTED_MIME_TYPES[$extension])) {
            return true;
        }

        return in_array($mimeType, self::EXPECTED_MIME_TYPES[$extension], true);
    }
}
