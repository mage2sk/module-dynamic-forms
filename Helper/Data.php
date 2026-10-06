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
    private const XML_PATH_AUTO_REPLY_SENDER = 'panth_dynamicforms/email/autoreply_email_sender';
    private const XML_PATH_UPLOAD_DIR = 'panth_dynamicforms/general/upload_dir';
    private const XML_PATH_FORM_LAYOUT = 'panth_dynamicforms/display/form_layout';
    private const XML_PATH_SHOW_TITLE = 'panth_dynamicforms/display/show_form_title';
    private const XML_PATH_SHOW_DESCRIPTION = 'panth_dynamicforms/display/show_form_description';
    private const XML_PATH_LOADING_TEXT = 'panth_dynamicforms/display/loading_text';
    private const DEFAULT_ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx'];
    private const DEFAULT_MAX_FILE_SIZE_MB = 10;
    private const PAGE_LAYOUTS = ['1column', '2columns-left', '2columns-right'];
    private const HEADER_VALUE_MAX_LENGTH = 100;
    private const XML_PATH_HONEYPOT_ENABLED = 'panth_dynamicforms/spam/honeypot_enabled';
    private const XML_PATH_CONTENT_GUARD_ENABLED = 'panth_dynamicforms/spam/content_guard_enabled';
    private const XML_PATH_BLOCKED_TERMS = 'panth_dynamicforms/spam/extra_blocked_terms';

    private const UPLOAD_DIR = 'dynamicforms/uploads';

    private const UPLOAD_ROOT = DirectoryList::VAR_DIR;

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

    public function getConfig(string $path, ?int $storeId = null): ?string
    {
        return $this->scopeConfig->getValue(
            $path,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isHoneypotEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_HONEYPOT_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isContentGuardEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_CONTENT_GUARD_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getBlockedTerms(?int $storeId = null): string
    {
        return (string) ($this->scopeConfig->getValue(
            self::XML_PATH_BLOCKED_TERMS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?? '');
    }

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isAjaxEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_AJAX_SUBMIT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getAllowedExtensions(?int $storeId = null): array
    {
        $extensions = $this->getConfig(self::XML_PATH_ALLOWED_EXTENSIONS, $storeId);
        $list = $extensions
            ? array_map(static fn ($ext) => strtolower(trim((string) $ext, " \t\n\r\0\x0B.")), explode(',', $extensions))
            : [];
        $list = array_values(array_unique(array_filter($list, static fn ($ext) => $ext !== '')));

        return $list ?: self::DEFAULT_ALLOWED_EXTENSIONS;
    }

    public function getMaxFileSize(?int $storeId = null): int
    {
        $sizeMb = (float) $this->getConfig(self::XML_PATH_MAX_FILE_SIZE, $storeId);
        if ($sizeMb <= 0) {
            $sizeMb = self::DEFAULT_MAX_FILE_SIZE_MB;
        }

        return (int) ($sizeMb * 1024 * 1024);
    }

    public function getUploadDir(): string
    {
        $varDir = $this->filesystem->getDirectoryRead(self::UPLOAD_ROOT)->getAbsolutePath();
        return $varDir . $this->getUploadRelativePath();
    }

    public function getUploadRoot(): string
    {
        return self::UPLOAD_ROOT;
    }

    public function isStoredFileName(string $filename): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]*(\.[A-Za-z0-9]+)+$/', $filename);
    }

    public function getUploadedFilePath(string $filename): ?string
    {
        if (!$this->isStoredFileName($filename)) {
            return null;
        }

        $relative = $this->getUploadRelativePath() . '/' . $filename;
        $directory = $this->filesystem->getDirectoryRead(self::UPLOAD_ROOT);

        return $directory->isFile($relative) ? $relative : null;
    }

    public function getUploadRelativePath(): string
    {
        $path = trim((string) $this->scopeConfig->getValue(self::XML_PATH_UPLOAD_DIR), " \t\n\r\0\x0B/");
        if ($path === '' || !preg_match('#^[A-Za-z0-9_-]+(/[A-Za-z0-9_-]+)*$#', $path)) {
            return self::UPLOAD_DIR;
        }

        return $path;
    }

    public function isUploadedFile(string $filename): bool
    {
        return $this->getUploadedFilePath($filename) !== null;
    }

    public function getPageLayout(?int $storeId = null): string
    {
        $layout = (string) $this->getConfig(self::XML_PATH_FORM_LAYOUT, $storeId);

        return in_array($layout, self::PAGE_LAYOUTS, true) ? $layout : '1column';
    }

    public function isShowFormTitle(?int $storeId = null): bool
    {
        return $this->getConfig(self::XML_PATH_SHOW_TITLE, $storeId) === null
            || $this->scopeConfig->isSetFlag(self::XML_PATH_SHOW_TITLE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isShowFormDescription(?int $storeId = null): bool
    {
        return $this->getConfig(self::XML_PATH_SHOW_DESCRIPTION, $storeId) === null
            || $this->scopeConfig->isSetFlag(self::XML_PATH_SHOW_DESCRIPTION, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getLoadingText(?int $storeId = null): string
    {
        $text = trim((string) $this->getConfig(self::XML_PATH_LOADING_TEXT, $storeId));

        return $text !== '' ? $text : (string) __('Submitting...');
    }

    public function getCurrentStoreId(): int
    {
        return (int) $this->storeManager->getStore()->getId();
    }

    public function isFormAvailableInStore(\Magento\Framework\DataObject $form, ?int $storeId = null): bool
    {
        $formStoreId = (int) $form->getData('store_id');
        if ($formStoreId === 0) {
            return true;
        }

        return $formStoreId === ($storeId ?? $this->getCurrentStoreId());
    }

    public function getFormUrl(Form $form): string
    {
        $baseUrl = $this->storeManager->getStore()->getBaseUrl();
        return rtrim($baseUrl, '/') . '/pages/' . $form->getData('url_key');
    }

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

        $fieldsHtml = '<table cellpadding="0" cellspacing="0" border="0" width="100%">';
        foreach ($values as $value) {
            $label = htmlspecialchars($value['label'] ?? '', ENT_QUOTES);
            $val = htmlspecialchars($value['value'] ?? '', ENT_QUOTES);
            if ($value['type'] === 'file' && $val && str_starts_with($val, 'http')) {
                $val = '<a href="' . $val . '" target="_blank" style="color:#0D9488;">Download File</a>';
            } elseif ($value['type'] === 'file' && $val !== '') {
                $val = htmlspecialchars(
                    (string) __('File uploaded. Open submission #%1 in the admin to download it.', $submission->getId()),
                    ENT_QUOTES
                );
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

    public function sendAutoReply(Form $form, Submission $submission): void
    {
        if (!$form->getData('auto_reply_enabled') || !$submission->getData('customer_email')) {
            return;
        }

        $customerEmail = (string) $submission->getData('customer_email');
        if (!filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $storeId = (int) $this->storeManager->getStore()->getId();
        $senderIdentity = $this->getConfig(self::XML_PATH_AUTO_REPLY_SENDER, $storeId)
            ?: ($this->getConfig(self::XML_PATH_SENDER_IDENTITY, $storeId) ?: 'general');
        $templateId = $this->getConfig(self::XML_PATH_AUTO_REPLY_TEMPLATE, $storeId)
            ?: 'panth_dynamicforms_email_autoreply_email_template';

        $formName = (string) ($form->getData('title') ?: $form->getData('name'));
        $storeName = (string) $this->storeManager->getStore()->getName();
        $customerName = $submission->getData('customer_id')
            ? $this->cleanHeaderValue((string) $submission->getData('customer_name'))
            : '';
        if ($customerName === '') {
            $customerName = 'Customer';
        }

        $placeholders = [
            'name' => $customerName,
            'customer_name' => $customerName,
            'email' => $customerEmail,
            'customer_email' => $customerEmail,
            'form_name' => $formName,
            'store_name' => $storeName,
        ];

        $templateVars = [
            'form_name' => $formName,
            'form_title' => $formName,
            'customer_name' => $customerName,
            'store_name' => $storeName,
            'auto_reply_subject' => $this->applyPlaceholders(
                (string) $form->getData('auto_reply_subject'),
                $placeholders,
                false
            ),
            'auto_reply_body' => $this->applyPlaceholders(
                (string) $form->getData('auto_reply_body'),
                $placeholders,
                true
            ),
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
                ->addTo($customerEmail, $customerName)
                ->getTransport();

            $transport->sendMessage();
        } catch (\Exception $e) {
            $this->_logger->error('DynamicForms auto-reply error: ' . $e->getMessage());
        } finally {
            $this->inlineTranslation->resume();
        }
    }

    private function cleanHeaderValue(string $value): string
    {
        $value = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value));

        return mb_substr($value, 0, self::HEADER_VALUE_MAX_LENGTH);
    }

    private function applyPlaceholders(string $text, array $placeholders, bool $escapeHtml): string
    {
        if ($text === '' || !str_contains($text, '{{')) {
            return $text;
        }

        return (string) preg_replace_callback(
            '/\{\{\s*([a-z_]+)\s*\}\}/i',
            static function (array $match) use ($placeholders, $escapeHtml): string {
                $key = strtolower($match[1]);
                if (!array_key_exists($key, $placeholders)) {
                    return $match[0];
                }
                $value = (string) $placeholders[$key];
                return $escapeHtml ? htmlspecialchars($value, ENT_QUOTES) : $value;
            },
            $text
        );
    }

    public function getFieldTypeLabel(string $type): string
    {
        return self::FIELD_TYPE_LABELS[$type] ?? ucfirst($type);
    }
}
