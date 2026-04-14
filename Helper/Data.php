<?php

declare(strict_types=1);

namespace Panth\DynamicForms\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;
use Panth\DynamicForms\Model\Form;
use Panth\DynamicForms\Model\Submission;

class Data extends AbstractHelper
{
    private const XML_PATH_ENABLED = 'panth_dynamicforms/general/enabled';
    private const XML_PATH_AJAX_SUBMIT = 'panth_dynamicforms/display/ajax_submit';
    private const XML_PATH_ALLOWED_EXTENSIONS = 'panth_dynamicforms/general/allowed_file_extensions';
    private const XML_PATH_MAX_FILE_SIZE = 'panth_dynamicforms/general/max_file_size';
    private const XML_PATH_ADMIN_EMAIL_TEMPLATE = 'panth_dynamicforms/email/admin_email_template';
    private const XML_PATH_AUTO_REPLY_TEMPLATE = 'panth_dynamicforms/email/autoreply_email_template';
    private const XML_PATH_SENDER_IDENTITY = 'panth_dynamicforms/email/admin_email_sender';

    private const UPLOAD_DIR = 'dynamicforms/uploads';

    private const FIELD_TYPE_LABELS = [
        'text' => 'Text Field',
        'textarea' => 'Text Area',
        'email' => 'Email',
        'phone' => 'Phone',
        'select' => 'Dropdown',
        'checkbox' => 'Checkbox',
        'radio' => 'Radio Button',
        'file' => 'File Upload',
        'date' => 'Date',
        'number' => 'Number',
        'hidden' => 'Hidden',
        'wysiwyg' => 'WYSIWYG Editor',
        'multiselect' => 'Multi-Select',
    ];

    private TransportBuilder $transportBuilder;
    private StateInterface $inlineTranslation;
    private StoreManagerInterface $storeManager;
    private Filesystem $filesystem;

    public function __construct(
        Context $context,
        TransportBuilder $transportBuilder,
        StateInterface $inlineTranslation,
        StoreManagerInterface $storeManager,
        Filesystem $filesystem
    ) {
        parent::__construct($context);
        $this->transportBuilder = $transportBuilder;
        $this->inlineTranslation = $inlineTranslation;
        $this->storeManager = $storeManager;
        $this->filesystem = $filesystem;
    }

    /**
     * Get system config value
     */
    public function getConfig(string $path, ?int $storeId = null): ?string
    {
        return $this->scopeConfig->getValue(
            $path,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Check if module is enabled
     */
    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Check if AJAX submission is enabled
     */
    public function isAjaxEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_AJAX_SUBMIT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get allowed file extensions as array
     */
    public function getAllowedExtensions(?int $storeId = null): array
    {
        $extensions = $this->getConfig(self::XML_PATH_ALLOWED_EXTENSIONS, $storeId);
        if (!$extensions) {
            return ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'zip'];
        }

        return array_map('trim', explode(',', $extensions));
    }

    /**
     * Get max file size in bytes
     */
    public function getMaxFileSize(?int $storeId = null): int
    {
        $sizeMb = (int) $this->getConfig(self::XML_PATH_MAX_FILE_SIZE, $storeId);
        if ($sizeMb <= 0) {
            $sizeMb = 5; // Default 5MB
        }

        return $sizeMb * 1024 * 1024;
    }

    /**
     * Get upload directory path
     */
    public function getUploadDir(): string
    {
        $mediaDir = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA)->getAbsolutePath();
        return $mediaDir . self::UPLOAD_DIR;
    }

    /**
     * Get relative upload path for media URLs
     */
    public function getUploadRelativePath(): string
    {
        return self::UPLOAD_DIR;
    }

    /**
     * Get frontend URL for a form
     */
    public function getFormUrl(Form $form): string
    {
        $baseUrl = $this->storeManager->getStore()->getBaseUrl();
        return rtrim($baseUrl, '/') . '/pages/' . $form->getData('url_key');
    }

    /**
     * Send admin notification email
     */
    public function sendAdminNotification(Form $form, Submission $submission, array $values): void
    {
        $adminEmail = $form->getData('admin_email');
        if (!$adminEmail) {
            return;
        }

        $storeId = (int) $this->storeManager->getStore()->getId();
        $senderIdentity = $this->getConfig(self::XML_PATH_SENDER_IDENTITY, $storeId) ?: 'general';
        $templateId = $this->getConfig(self::XML_PATH_ADMIN_EMAIL_TEMPLATE, $storeId)
            ?: 'panth_dynamicforms_email_admin_email_template';

        // Build HTML table of submitted fields
        $fieldsHtml = '<table cellpadding="0" cellspacing="0" border="0" width="100%">';
        foreach ($values as $value) {
            $label = htmlspecialchars($value['label'] ?? '', ENT_QUOTES);
            $val = htmlspecialchars($value['value'] ?? '', ENT_QUOTES);
            if ($value['type'] === 'file' && $val && str_starts_with($val, 'http')) {
                $val = '<a href="' . $val . '" target="_blank" style="color:#0D9488;">Download File</a>';
            }
            $fieldsHtml .= '<tr>'
                . '<td style="padding:8px 0;font-size:14px;color:#6B7280;width:160px;vertical-align:top;border-bottom:1px solid #F3F4F6;">' . $label . '</td>'
                . '<td style="padding:8px 0;font-size:14px;color:#1F2937;border-bottom:1px solid #F3F4F6;">' . $val . '</td>'
                . '</tr>';
        }
        $fieldsHtml .= '</table>';

        $storeName = $this->storeManager->getStore()->getName();

        $templateVars = [
            'form_name' => $form->getData('title') ?: $form->getData('name'),
            'submission_id' => $submission->getId(),
            'submission_date' => $submission->getData('created_at') ?: date('Y-m-d H:i:s'),
            'customer_name' => $submission->getData('customer_name') ?: 'Guest',
            'customer_email' => $submission->getData('customer_email') ?: 'N/A',
            'customer_ip' => $submission->getData('customer_ip') ?: 'N/A',
            'store_name' => $storeName,
            'submission_fields' => $fieldsHtml,
        ];

        $this->inlineTranslation->suspend();

        try {
            $transport = $this->transportBuilder
                ->setTemplateIdentifier($templateId)
                ->setTemplateOptions([
                    'area' => \Magento\Framework\App\Area::AREA_FRONTEND,
                    'store' => $storeId,
                ])
                ->setTemplateVars($templateVars)
                ->setFromByScope($senderIdentity, $storeId)
                ->addTo($adminEmail);

            $cc = $form->getData('admin_email_cc');
            if ($cc) {
                foreach (array_map('trim', explode(',', $cc)) as $ccEmail) {
                    if ($ccEmail) {
                        $transport->addCc($ccEmail);
                    }
                }
            }

            $bcc = $form->getData('admin_email_bcc');
            if ($bcc) {
                foreach (array_map('trim', explode(',', $bcc)) as $bccEmail) {
                    if ($bccEmail) {
                        $transport->addBcc($bccEmail);
                    }
                }
            }

            $transport->getTransport()->sendMessage();
        } catch (\Exception $e) {
            $this->_logger->error('DynamicForms admin notification error: ' . $e->getMessage());
        } finally {
            $this->inlineTranslation->resume();
        }
    }

    /**
     * Send auto-reply email to customer
     */
    public function sendAutoReply(Form $form, Submission $submission): void
    {
        if (!$form->getData('auto_reply_enabled') || !$submission->getData('customer_email')) {
            return;
        }

        $storeId = (int) $this->storeManager->getStore()->getId();
        $senderIdentity = $this->getConfig(self::XML_PATH_SENDER_IDENTITY, $storeId) ?: 'general';
        $templateId = $this->getConfig(self::XML_PATH_AUTO_REPLY_TEMPLATE, $storeId)
            ?: 'panth_dynamicforms_email_autoreply_email_template';

        $templateVars = [
            'form_title' => $form->getData('title') ?: $form->getData('name'),
            'customer_name' => $submission->getData('customer_name') ?: 'Customer',
            'auto_reply_subject' => $form->getData('auto_reply_subject'),
            'auto_reply_body' => $form->getData('auto_reply_body'),
        ];

        $this->inlineTranslation->suspend();

        try {
            $transport = $this->transportBuilder
                ->setTemplateIdentifier($templateId)
                ->setTemplateOptions([
                    'area' => \Magento\Framework\App\Area::AREA_FRONTEND,
                    'store' => $storeId,
                ])
                ->setTemplateVars($templateVars)
                ->setFromByScope($senderIdentity, $storeId)
                ->addTo($submission->getData('customer_email'), $submission->getData('customer_name'))
                ->getTransport();

            $transport->sendMessage();
        } catch (\Exception $e) {
            $this->_logger->error('DynamicForms auto-reply error: ' . $e->getMessage());
        } finally {
            $this->inlineTranslation->resume();
        }
    }

    /**
     * Get human-readable field type label
     */
    public function getFieldTypeLabel(string $type): string
    {
        return self::FIELD_TYPE_LABELS[$type] ?? ucfirst($type);
    }

    /**
     * Get media URL for uploaded file
     */
    public function getFileUrl(string $filename): string
    {
        $baseMediaUrl = $this->storeManager->getStore()->getBaseUrl(
            \Magento\Framework\UrlInterface::URL_TYPE_MEDIA
        );

        return rtrim($baseMediaUrl, '/') . '/' . self::UPLOAD_DIR . '/' . ltrim($filename, '/');
    }
}
