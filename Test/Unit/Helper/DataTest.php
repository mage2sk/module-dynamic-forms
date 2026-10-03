<?php
declare(strict_types=1);

namespace Panth\DynamicForms\Test\Unit\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\DataObject;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\ReadInterface;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Mail\TransportInterface;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Panth\DynamicForms\Helper\Data;
use Panth\DynamicForms\Test\Unit\Support\ModelBuilderTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class DataTest extends TestCase
{
    use ModelBuilderTrait;

    private array $config = [];
    private array $flags = [];
    private array $mailCalls = [];
    private array $logErrors = [];
    private array $translationCalls = [];
    private ?\Exception $sendFailure = null;
    private array $mediaFiles = [];

    private function helper(): Data
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            fn($path) => $this->config[$path] ?? null
        );
        $scopeConfig->method('isSetFlag')->willReturnCallback(
            fn($path) => (bool) ($this->flags[$path] ?? false)
        );

        $logger = $this->createStub(LoggerInterface::class);
        $logger->method('error')->willReturnCallback(function ($message) {
            $this->logErrors[] = (string) $message;
        });

        $context = $this->createStub(Context::class);
        $context->method('getScopeConfig')->willReturn($scopeConfig);
        $context->method('getLogger')->willReturn($logger);

        $transport = $this->createStub(TransportInterface::class);
        $transport->method('sendMessage')->willReturnCallback(function () {
            if ($this->sendFailure) {
                throw $this->sendFailure;
            }
            $this->mailCalls[] = ['sendMessage', []];
        });

        $builder = $this->createStub(TransportBuilder::class);
        $chain = ['setTemplateIdentifier', 'setTemplateOptions', 'setTemplateVars', 'setFromByScope', 'addTo', 'addCc', 'addBcc'];
        foreach ($chain as $method) {
            $builder->method($method)->willReturnCallback(function (...$args) use ($builder, $method) {
                $this->mailCalls[] = [$method, $args];
                return $builder;
            });
        }
        $builder->method('getTransport')->willReturn($transport);

        $inline = $this->createStub(StateInterface::class);
        $inline->method('suspend')->willReturnCallback(function () use ($inline) {
            $this->translationCalls[] = 'suspend';
            return $inline;
        });
        $inline->method('resume')->willReturnCallback(function () use ($inline) {
            $this->translationCalls[] = 'resume';
            return $inline;
        });

        $store = $this->createStub(Store::class);
        $store->method('getId')->willReturn(3);
        $store->method('getName')->willReturn('Main Store');
        $store->method('getBaseUrl')->willReturnCallback(
            static fn($type = 'link') => $type === 'media' ? 'https://shop.test/media/' : 'https://shop.test/'
        );
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $media = $this->createStub(ReadInterface::class);
        $media->method('getAbsolutePath')->willReturn('/var/www/pub/media/');
        $media->method('isFile')->willReturnCallback(fn($path) => in_array($path, $this->mediaFiles, true));
        $filesystem = $this->createStub(Filesystem::class);
        $filesystem->method('getDirectoryRead')->willReturn($media);

        return new Data($context, $builder, $inline, $storeManager, $filesystem);
    }

    private function callsOf(string $method): array
    {
        return array_values(array_map(
            static fn($call) => $call[1],
            array_filter($this->mailCalls, static fn($call) => $call[0] === $method)
        ));
    }

    public function testFlagsReadTheirOwnConfigPaths(): void
    {
        $this->flags = [
            'panth_dynamicforms/general/enabled' => true,
            'panth_dynamicforms/spam/honeypot_enabled' => true,
        ];
        $helper = $this->helper();

        $this->assertTrue($helper->isEnabled());
        $this->assertTrue($helper->isHoneypotEnabled());
        $this->assertFalse($helper->isAjaxEnabled());
        $this->assertFalse($helper->isContentGuardEnabled());
    }

    public function testGetConfigReturnsRawValue(): void
    {
        $this->config['any/path'] = 'value';

        $this->assertSame('value', $this->helper()->getConfig('any/path'));
        $this->assertNull($this->helper()->getConfig('other/path'));
    }

    public function testBlockedTermsDefaultToEmptyString(): void
    {
        $this->assertSame('', $this->helper()->getBlockedTerms());

        $this->config['panth_dynamicforms/spam/extra_blocked_terms'] = "casino\nloan";
        $this->assertSame("casino\nloan", $this->helper()->getBlockedTerms());
    }

    public function testAllowedExtensionsAreNormalisedAndDeduplicated(): void
    {
        $this->config['panth_dynamicforms/general/allowed_file_extensions'] = ' .PDF, png ,pdf,, .Jpg ';

        $this->assertSame(['pdf', 'png', 'jpg'], $this->helper()->getAllowedExtensions());
    }

    public function testAllowedExtensionsFallBackToDefaultsWhenEmpty(): void
    {
        $this->config['panth_dynamicforms/general/allowed_file_extensions'] = ' , . ,';
        $list = $this->helper()->getAllowedExtensions();

        $this->assertContains('pdf', $list);
        $this->assertContains('docx', $list);
        $this->assertCount(9, $list);
    }

    public function testMaxFileSizeConvertsMegabytesToBytes(): void
    {
        $this->config['panth_dynamicforms/general/max_file_size'] = '2.5';

        $this->assertSame((int) (2.5 * 1024 * 1024), $this->helper()->getMaxFileSize());
    }

    public function testMaxFileSizeDefaultsToTenMegabytesForZeroOrNegative(): void
    {
        $this->config['panth_dynamicforms/general/max_file_size'] = '-4';
        $this->assertSame(10 * 1024 * 1024, $this->helper()->getMaxFileSize());

        $this->config['panth_dynamicforms/general/max_file_size'] = null;
        $this->assertSame(10 * 1024 * 1024, $this->helper()->getMaxFileSize());
    }

    #[DataProvider('uploadPathProvider')]
    public function testUploadRelativePathIsValidated(?string $configured, string $expected): void
    {
        $this->config['panth_dynamicforms/general/upload_dir'] = $configured;

        $this->assertSame($expected, $this->helper()->getUploadRelativePath());
    }

    public static function uploadPathProvider(): array
    {
        return [
            'unset' => [null, 'dynamicforms/uploads'],
            'custom nested' => ['forms/files', 'forms/files'],
            'slashes trimmed' => ['/forms/files/', 'forms/files'],
            'traversal rejected' => ['../etc', 'dynamicforms/uploads'],
            'dots rejected' => ['forms/../x', 'dynamicforms/uploads'],
            'spaces rejected' => ['my files', 'dynamicforms/uploads'],
            'double slash rejected' => ['a//b', 'dynamicforms/uploads'],
        ];
    }

    public function testUploadDirIsMediaPathPlusRelativePath(): void
    {
        $this->assertSame('/var/www/pub/media/dynamicforms/uploads', $this->helper()->getUploadDir());
    }

    public function testIsUploadedFileRequiresSafeNameAndExistingFile(): void
    {
        $this->mediaFiles = ['dynamicforms/uploads/abc123.pdf'];
        $helper = $this->helper();

        $this->assertTrue($helper->isUploadedFile('abc123.pdf'));
        $this->assertFalse($helper->isUploadedFile('missing.pdf'));
        $this->assertFalse($helper->isUploadedFile('../abc123.pdf'));
        $this->assertFalse($helper->isUploadedFile('.hidden.pdf'));
        $this->assertFalse($helper->isUploadedFile('noextension'));
        $this->assertFalse($helper->isUploadedFile('sub/abc123.pdf'));
    }

    public function testPageLayoutOnlyAcceptsKnownLayouts(): void
    {
        $this->config['panth_dynamicforms/display/form_layout'] = '2columns-left';
        $this->assertSame('2columns-left', $this->helper()->getPageLayout());

        $this->config['panth_dynamicforms/display/form_layout'] = '3columns';
        $this->assertSame('1column', $this->helper()->getPageLayout());
    }

    public function testShowTitleAndDescriptionDefaultToTrueWhenUnset(): void
    {
        $helper = $this->helper();
        $this->assertTrue($helper->isShowFormTitle());
        $this->assertTrue($helper->isShowFormDescription());

        $this->config['panth_dynamicforms/display/show_form_title'] = '0';
        $this->config['panth_dynamicforms/display/show_form_description'] = '1';
        $this->flags['panth_dynamicforms/display/show_form_description'] = true;
        $helper = $this->helper();
        $this->assertFalse($helper->isShowFormTitle());
        $this->assertTrue($helper->isShowFormDescription());
    }

    public function testLoadingTextFallsBackWhenBlank(): void
    {
        $this->config['panth_dynamicforms/display/loading_text'] = '   ';
        $this->assertSame('Submitting...', $this->helper()->getLoadingText());

        $this->config['panth_dynamicforms/display/loading_text'] = ' Sending ';
        $this->assertSame('Sending', $this->helper()->getLoadingText());
    }

    public function testFormAvailabilityByStore(): void
    {
        $helper = $this->helper();

        $this->assertTrue($helper->isFormAvailableInStore(new DataObject(['store_id' => 0])));
        $this->assertTrue($helper->isFormAvailableInStore(new DataObject(['store_id' => 3])));
        $this->assertFalse($helper->isFormAvailableInStore(new DataObject(['store_id' => 2])));
        $this->assertTrue($helper->isFormAvailableInStore(new DataObject(['store_id' => 2]), 2));
        $this->assertSame(3, $helper->getCurrentStoreId());
    }

    public function testUrlBuilders(): void
    {
        $helper = $this->helper();

        $this->assertSame('https://shop.test/pages/contact', $helper->getFormUrl($this->form(['url_key' => 'contact'])));
        $this->assertSame(
            'https://shop.test/media/dynamicforms/uploads/file.pdf',
            $helper->getFileUrl('/file.pdf')
        );
    }

    public function testFieldTypeLabels(): void
    {
        $helper = $this->helper();

        $this->assertSame('Dropdown', $helper->getFieldTypeLabel('select'));
        $this->assertSame('Multi-Select', $helper->getFieldTypeLabel('multiselect'));
        $this->assertSame('Rating', $helper->getFieldTypeLabel('rating'));
    }

    public function testAdminNotificationIsSkippedWithoutRecipient(): void
    {
        $this->helper()->sendAdminNotification($this->form(), $this->submission(), []);

        $this->assertSame([], $this->mailCalls);
        $this->assertSame([], $this->translationCalls);
    }

    public function testAdminNotificationBuildsEscapedTableAndRecipients(): void
    {
        $form = $this->form([
            'admin_email' => 'owner@shop.test',
            'admin_email_cc' => 'a@shop.test, ,b@shop.test',
            'admin_email_bcc' => 'audit@shop.test',
            'name' => 'Contact',
        ]);
        $submission = $this->submission(['submission_id' => 9, 'customer_email' => 'c@x.test']);

        $this->helper()->sendAdminNotification($form, $submission, [
            ['label' => 'Message', 'value' => '<b>hi</b>', 'type' => 'textarea'],
            ['label' => 'File', 'value' => 'https://shop.test/media/f.pdf', 'type' => 'file'],
            ['label' => 'Local', 'value' => 'f.pdf', 'type' => 'file'],
        ]);

        $vars = $this->callsOf('setTemplateVars')[0][0];
        $this->assertSame('Contact', $vars['form_name']);
        $this->assertSame(9, $vars['submission_id']);
        $this->assertSame('Guest', $vars['customer_name']);
        $this->assertSame('c@x.test', $vars['customer_email']);
        $this->assertSame('N/A', $vars['customer_ip']);
        $this->assertSame('Main Store', $vars['store_name']);
        $this->assertStringContainsString('&lt;b&gt;hi&lt;/b&gt;', $vars['submission_fields']);
        $this->assertStringContainsString('<a href="https://shop.test/media/f.pdf"', $vars['submission_fields']);
        $this->assertStringNotContainsString('<a href="f.pdf"', $vars['submission_fields']);

        $this->assertSame([['panth_dynamicforms_email_admin_email_template']], $this->callsOf('setTemplateIdentifier'));
        $this->assertSame([['general', 3]], $this->callsOf('setFromByScope'));
        $this->assertSame(['owner@shop.test'], array_column($this->callsOf('addTo'), 0));
        $this->assertSame(['a@shop.test', 'b@shop.test'], array_column($this->callsOf('addCc'), 0));
        $this->assertSame(['audit@shop.test'], array_column($this->callsOf('addBcc'), 0));
        $this->assertCount(1, $this->callsOf('sendMessage'));
        $this->assertSame(['suspend', 'resume'], $this->translationCalls);
    }

    public function testAdminNotificationUsesConfiguredTemplateAndSender(): void
    {
        $this->config['panth_dynamicforms/email/admin_email_template'] = 'custom_tpl';
        $this->config['panth_dynamicforms/email/admin_email_sender'] = 'sales';

        $this->helper()->sendAdminNotification(
            $this->form(['admin_email' => 'o@x.test', 'title' => 'T']),
            $this->submission(),
            []
        );

        $this->assertSame([['custom_tpl']], $this->callsOf('setTemplateIdentifier'));
        $this->assertSame([['sales', 3]], $this->callsOf('setFromByScope'));
        $this->assertSame('T', $this->callsOf('setTemplateVars')[0][0]['form_name']);
    }

    public function testAdminNotificationFailureIsLoggedAndTranslationResumed(): void
    {
        $this->sendFailure = new \RuntimeException('smtp down');

        $this->helper()->sendAdminNotification($this->form(['admin_email' => 'o@x.test']), $this->submission(), []);

        $this->assertSame(['DynamicForms admin notification error: smtp down'], $this->logErrors);
        $this->assertSame(['suspend', 'resume'], $this->translationCalls);
    }

    public function testAutoReplyIsSkippedWhenDisabledMissingOrInvalidEmail(): void
    {
        $helper = $this->helper();
        $helper->sendAutoReply(
            $this->form(['auto_reply_enabled' => 0]),
            $this->submission(['customer_email' => 'a@b.test'])
        );
        $helper->sendAutoReply($this->form(['auto_reply_enabled' => 1]), $this->submission());
        $helper->sendAutoReply(
            $this->form(['auto_reply_enabled' => 1]),
            $this->submission(['customer_email' => 'not-an-email'])
        );

        $this->assertSame([], $this->mailCalls);
    }

    public function testAutoReplyReplacesPlaceholdersAndEscapesBodyOnly(): void
    {
        $form = $this->form([
            'auto_reply_enabled' => 1,
            'name' => 'Quote',
            'auto_reply_subject' => 'Re: {{ form_name }} for {{name}} {{unknown}}',
            'auto_reply_body' => 'Hi {{customer_name}}, we got {{email}}',
        ]);
        $submission = $this->submission([
            'customer_email' => 'a@b.test',
            'customer_id' => 5,
            'customer_name' => "<Ann>\r\nBcc: x",
        ]);

        $this->helper()->sendAutoReply($form, $submission);

        $vars = $this->callsOf('setTemplateVars')[0][0];
        $this->assertSame('<Ann> Bcc: x', $vars['customer_name']);
        $this->assertSame('Re: Quote for <Ann> Bcc: x {{unknown}}', $vars['auto_reply_subject']);
        $this->assertSame('Hi &lt;Ann&gt; Bcc: x, we got a@b.test', $vars['auto_reply_body']);
        $this->assertSame([['a@b.test', '<Ann> Bcc: x']], $this->callsOf('addTo'));
        $this->assertSame(
            [['panth_dynamicforms_email_autoreply_email_template']],
            $this->callsOf('setTemplateIdentifier')
        );
    }

    public function testAutoReplyIgnoresGuestSuppliedNameAndTruncatesLongNames(): void
    {
        $form = $this->form(['auto_reply_enabled' => 1, 'auto_reply_body' => 'Hello']);

        $this->helper()->sendAutoReply($form, $this->submission([
            'customer_email' => 'a@b.test',
            'customer_name' => 'Guest Typed Name',
        ]));
        $this->assertSame('Customer', $this->callsOf('setTemplateVars')[0][0]['customer_name']);
        $this->assertSame('Hello', $this->callsOf('setTemplateVars')[0][0]['auto_reply_body']);

        $this->mailCalls = [];
        $this->helper()->sendAutoReply($form, $this->submission([
            'customer_email' => 'a@b.test',
            'customer_id' => 1,
            'customer_name' => str_repeat('n', 150),
        ]));
        $this->assertSame(100, strlen($this->callsOf('setTemplateVars')[0][0]['customer_name']));
    }

    public function testAutoReplySenderFallsBackToAdminSender(): void
    {
        $this->config['panth_dynamicforms/email/admin_email_sender'] = 'support';
        $form = $this->form(['auto_reply_enabled' => 1]);

        $this->helper()->sendAutoReply($form, $this->submission(['customer_email' => 'a@b.test']));
        $this->assertSame([['support', 3]], $this->callsOf('setFromByScope'));

        $this->mailCalls = [];
        $this->config['panth_dynamicforms/email/autoreply_email_sender'] = 'custom2';
        $this->helper()->sendAutoReply($form, $this->submission(['customer_email' => 'a@b.test']));
        $this->assertSame([['custom2', 3]], $this->callsOf('setFromByScope'));
    }

    public function testAutoReplyFailureIsLogged(): void
    {
        $this->sendFailure = new \RuntimeException('boom');

        $this->helper()->sendAutoReply(
            $this->form(['auto_reply_enabled' => 1]),
            $this->submission(['customer_email' => 'a@b.test'])
        );

        $this->assertSame(['DynamicForms auto-reply error: boom'], $this->logErrors);
        $this->assertSame(['suspend', 'resume'], $this->translationCalls);
    }
}
