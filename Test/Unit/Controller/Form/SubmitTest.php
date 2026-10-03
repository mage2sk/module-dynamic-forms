<?php
declare(strict_types=1);

namespace Panth\DynamicForms\Test\Unit\Controller\Form;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Request\Http;
use Magento\Framework\DataObject;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Panth\DynamicForms\Controller\Form\Submit;
use Panth\DynamicForms\Helper\Data as Helper;
use Panth\DynamicForms\Model\AutoReplyGuard;
use Panth\DynamicForms\Model\Form;
use Panth\DynamicForms\Model\FormFactory;
use Panth\DynamicForms\Model\RedirectUrlValidator;
use Panth\DynamicForms\Model\ResourceModel\Field\Collection as FieldCollection;
use Panth\DynamicForms\Model\ResourceModel\Field\CollectionFactory as FieldCollectionFactory;
use Panth\DynamicForms\Model\ResourceModel\Form as FormResource;
use Panth\DynamicForms\Model\ResourceModel\Submission as SubmissionResource;
use Panth\DynamicForms\Model\ResourceModel\SubmissionValue as SubmissionValueResource;
use Panth\DynamicForms\Model\Spam\ContentGuard;
use Panth\DynamicForms\Model\Submission;
use Panth\DynamicForms\Model\SubmissionFactory;
use Panth\DynamicForms\Model\SubmissionValue;
use Panth\DynamicForms\Model\SubmissionValueFactory;
use Panth\DynamicForms\Test\Unit\Support\ModelBuilderTrait;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SubmitTest extends TestCase
{
    use ModelBuilderTrait;

    private array $params = [];
    private bool $formKeyValid = true;
    private bool $enabled = true;
    private bool $honeypot = false;
    private bool $availableInStore = true;
    private ?string $spamReason = null;
    private array $formData = ['form_id' => 5, 'is_active' => 1];
    private array $fields = [];
    private array $uploadedFiles = [];
    private ?array $loggedInCustomer = null;
    private bool $autoReplyAllowed = true;
    private ?\Exception $saveFailure = null;

    private array $savedSubmissions = [];
    private array $savedValues = [];
    private array $adminNotifications = [];
    private array $autoReplies = [];
    private array $logs = [];
    private array $guardInput = [];
    private array $autoReplyChecks = [];

    private function execute(): array
    {
        $request = $this->createStub(Http::class);
        $request->method('getParams')->willReturnCallback(fn() => $this->params);
        $request->method('getParam')->willReturnCallback(
            fn($key, $default = null) => array_key_exists($key, $this->params) ? $this->params[$key] : $default
        );

        $captured = [];
        $json = $this->createStub(Json::class);
        $json->method('setData')->willReturnCallback(function ($data) use ($json, &$captured) {
            $captured = $data;
            return $json;
        });
        $jsonFactory = $this->createStub(JsonFactory::class);
        $jsonFactory->method('create')->willReturn($json);

        $formKeyValidator = $this->createStub(FormKeyValidator::class);
        $formKeyValidator->method('validate')->willReturnCallback(fn() => $this->formKeyValid);

        $customerSession = $this->createStub(CustomerSession::class);
        $customerSession->method('isLoggedIn')->willReturnCallback(fn() => $this->loggedInCustomer !== null);
        $customerSession->method('getCustomer')->willReturnCallback(function () {
            return new DataObject($this->loggedInCustomer);
        });

        $store = $this->createStub(Store::class);
        $store->method('getId')->willReturn(2);
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $formFactory = $this->createStub(FormFactory::class);
        $formFactory->method('create')->willReturnCallback(fn() => $this->form());
        $formResource = $this->createStub(FormResource::class);
        $formResource->method('load')->willReturnCallback(function (Form $form, $id) use ($formResource) {
            if ((int) $id === (int) ($this->formData['form_id'] ?? 0)) {
                $form->setData($this->formData);
            }
            return $formResource;
        });

        $submissionFactory = $this->createStub(SubmissionFactory::class);
        $submissionFactory->method('create')->willReturnCallback(fn() => $this->submission());
        $submissionResource = $this->createStub(SubmissionResource::class);
        $submissionResource->method('save')->willReturnCallback(function (Submission $s) use ($submissionResource) {
            if ($this->saveFailure) {
                throw $this->saveFailure;
            }
            $s->setData('submission_id', 77);
            $this->savedSubmissions[] = $s->getData();
            return $submissionResource;
        });

        $valueFactory = $this->createStub(SubmissionValueFactory::class);
        $valueFactory->method('create')->willReturnCallback(fn() => $this->submissionValue());
        $valueResource = $this->createStub(SubmissionValueResource::class);
        $valueResource->method('save')->willReturnCallback(function (SubmissionValue $v) use ($valueResource) {
            $this->savedValues[] = $v->getData();
            return $valueResource;
        });

        $fieldModels = array_map(fn($f) => $this->field($f), $this->fields);
        $fieldCollection = $this->collectionOf(FieldCollection::class, $fieldModels);
        $fieldCollectionFactory = $this->createStub(FieldCollectionFactory::class);
        $fieldCollectionFactory->method('create')->willReturn($fieldCollection);

        $helper = $this->createStub(Helper::class);
        $helper->method('isEnabled')->willReturnCallback(fn() => $this->enabled);
        $helper->method('isHoneypotEnabled')->willReturnCallback(fn() => $this->honeypot);
        $helper->method('isFormAvailableInStore')->willReturnCallback(fn() => $this->availableInStore);
        $helper->method('isUploadedFile')->willReturnCallback(fn($f) => in_array($f, $this->uploadedFiles, true));
        $helper->method('getFileUrl')->willReturnCallback(static fn($f) => 'https://shop.test/media/up/' . $f);
        $helper->method('sendAdminNotification')->willReturnCallback(function ($form, $sub, $values) {
            $this->adminNotifications[] = $values;
        });
        $helper->method('sendAutoReply')->willReturnCallback(function ($form, $sub) {
            $this->autoReplies[] = $sub->getData('customer_email');
        });

        $remote = $this->createStub(RemoteAddress::class);
        $remote->method('getRemoteAddress')->willReturn('10.0.0.1');

        $logger = $this->createStub(LoggerInterface::class);
        foreach (['info', 'error'] as $level) {
            $logger->method($level)->willReturnCallback(function ($message, $context = []) use ($level) {
                $this->logs[] = [$level, $message, $context];
            });
        }

        $contentGuard = $this->createStub(ContentGuard::class);
        $contentGuard->method('detect')->willReturnCallback(function ($values, $keys) {
            $this->guardInput = [$values, $keys];
            return $this->spamReason;
        });
        $contentGuard->method('sample')->willReturn('sample-text');

        $autoReplyGuard = $this->createStub(AutoReplyGuard::class);
        $autoReplyGuard->method('isAllowed')->willReturnCallback(function ($email, $ip, $store) {
            $this->autoReplyChecks[] = [$email, $ip, $store];
            return $this->autoReplyAllowed;
        });

        $redirect = $this->createStub(RedirectUrlValidator::class);
        $redirect->method('sanitize')->willReturnCallback(
            static fn($url) => str_starts_with((string) $url, '/') ? (string) $url : ''
        );

        $controller = new Submit(
            $request,
            $jsonFactory,
            $formKeyValidator,
            $customerSession,
            $storeManager,
            $formFactory,
            $formResource,
            $submissionFactory,
            $submissionResource,
            $valueFactory,
            $valueResource,
            $fieldCollectionFactory,
            $helper,
            $remote,
            $logger,
            $contentGuard,
            $autoReplyGuard,
            $redirect
        );

        $this->assertSame($json, $controller->execute());

        return array_map(static fn($v) => $v instanceof \Magento\Framework\Phrase ? (string) $v : $v, $captured);
    }

    private function errorsOf(array $result): array
    {
        return array_map('strval', $result['errors'] ?? []);
    }

    public function testInvalidFormKeyIsRejected(): void
    {
        $this->formKeyValid = false;
        $this->params = ['form_id' => 5];

        $result = $this->execute();

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid form key', $result['message']);
        $this->assertSame([], $this->savedSubmissions);
    }

    public function testDisabledModuleIsRejected(): void
    {
        $this->enabled = false;
        $this->params = ['form_id' => 5];

        $result = $this->execute();

        $this->assertFalse($result['success']);
        $this->assertSame('This form is no longer available.', $result['message']);
    }

    public function testMissingFormIdIsRejected(): void
    {
        $this->params = [];

        $this->assertSame('Invalid form.', $this->execute()['message']);
    }

    public function testUnknownInactiveOrForeignStoreFormIsRejected(): void
    {
        $this->params = ['form_id' => 99];
        $this->assertSame('This form is no longer available.', $this->execute()['message']);

        $this->params = ['form_id' => 5];
        $this->formData['is_active'] = 0;
        $this->assertSame('This form is no longer available.', $this->execute()['message']);

        $this->formData['is_active'] = 1;
        $this->availableInStore = false;
        $this->assertFalse($this->execute()['success']);
        $this->assertSame([], $this->savedSubmissions);
    }

    public function testFilledHoneypotPretendsSuccessButStoresNothing(): void
    {
        $this->honeypot = true;
        $this->formData['success_message'] = 'Thanks!';
        $this->formData['redirect_url'] = '/thanks';
        $this->params = ['form_id' => 5, Submit::HONEYPOT_FIELD => 'http://spam.test', 'message' => 'hi'];

        $result = $this->execute();

        $this->assertTrue($result['success']);
        $this->assertSame('Thanks!', $result['message']);
        $this->assertSame('/thanks', $result['redirect_url']);
        $this->assertSame([], $this->savedSubmissions);
        $this->assertSame([], $this->adminNotifications);
        $blocked = array_values(array_filter($this->logs, static fn($l) => str_contains($l[1], 'blocked')));
        $this->assertSame('honeypot filled', $blocked[0][2]['reason']);
        $this->assertSame('10.0.0.1', $blocked[0][2]['ip']);
        $this->assertSame('sample-text', $blocked[0][2]['sample']);
    }

    public function testContentGuardSeesOnlyUserValuesAndBlocksSilently(): void
    {
        $this->spamReason = 'too many links';
        $this->params = [
            'form_id' => 5,
            'form_key' => 'k',
            'ajax' => 1,
            '_internal' => 'x',
            Submit::HONEYPOT_FIELD => '',
            'message' => 'hello',
            'name' => 'Ann',
        ];

        $result = $this->execute();

        $this->assertTrue($result['success']);
        $this->assertSame('Thank you! Your form has been submitted successfully.', $result['message']);
        $this->assertSame('', $result['redirect_url']);
        $this->assertSame([['message' => 'hello', 'name' => 'Ann'], ['message', 'name']], $this->guardInput);
        $this->assertSame([], $this->savedSubmissions);
    }

    public function testMissingHoneypotFieldIsLoggedButNotBlocked(): void
    {
        $this->honeypot = true;
        $this->params = ['form_id' => 5];

        $result = $this->execute();

        $this->assertTrue($result['success']);
        $this->assertNotEmpty(array_filter($this->logs, static fn($l) => str_contains($l[1], 'honeypot field was not present')));
        $this->assertCount(1, $this->savedSubmissions);
    }

    public function testRequiredAndTypeValidationErrors(): void
    {
        $this->fields = [
            ['field_id' => 1, 'name' => 'name', 'label' => 'Name', 'field_type' => 'text', 'is_required' => 1],
            ['field_id' => 2, 'name' => 'email', 'label' => 'Email', 'field_type' => 'email'],
            ['field_id' => 3, 'name' => 'phone', 'label' => 'Phone', 'field_type' => 'phone'],
            ['field_id' => 4, 'name' => 'qty', 'label' => 'Qty', 'field_type' => 'number'],
            ['field_id' => 5, 'name' => 'cv', 'label' => 'CV', 'field_type' => 'file', 'is_required' => 1],
            ['field_id' => 6, 'name' => 'att', 'label' => 'Attachment', 'field_type' => 'file'],
        ];
        $this->params = [
            'form_id' => 5,
            'name' => '   ',
            'email' => 'nope',
            'phone' => 'abc',
            'qty' => 'ten',
            'att' => 'unknown.pdf',
        ];

        $result = $this->execute();

        $this->assertFalse($result['success']);
        $this->assertSame('Please correct the errors below.', $result['message']);
        $this->assertSame([
            'name' => 'Name is required.',
            'email' => 'Please enter a valid email address.',
            'phone' => 'Please enter a valid phone number.',
            'qty' => 'Please enter a valid number.',
            'cv' => 'CV is required.',
            'att' => 'Please upload the file for Attachment again.',
        ], $this->errorsOf($result));
        $this->assertSame([], $this->savedSubmissions);
    }

    public function testChoiceFieldsOnlyAcceptConfiguredOptions(): void
    {
        $this->fields = [
            ['field_id' => 1, 'name' => 'color', 'label' => 'Color', 'field_type' => 'select',
                'options' => json_encode([['label' => 'Red', 'value' => 'red'], ['label' => 'Blue']])],
            ['field_id' => 2, 'name' => 'tags', 'label' => 'Tags', 'field_type' => 'multiselect',
                'options' => json_encode(['a', 'b'])],
            ['field_id' => 3, 'name' => 'free', 'label' => 'Free', 'field_type' => 'radio', 'options' => 'not json'],
        ];

        $this->params = ['form_id' => 5, 'color' => 'green', 'tags' => ['a', 'z'], 'free' => 'anything'];
        $errors = $this->errorsOf($this->execute());
        $this->assertSame('Please select a valid option for Color.', $errors['color']);
        $this->assertSame('Please select a valid option for Tags.', $errors['tags']);
        $this->assertArrayNotHasKey('free', $errors);

        $this->params = ['form_id' => 5, 'color' => 'Blue', 'tags' => ['a', ' ', 'b', ['nested']], 'free' => 'x'];
        $result = $this->execute();
        $this->assertTrue($result['success']);
        $this->assertSame('Blue', $this->savedValues[0]['value']);
        $this->assertSame('a, b', $this->savedValues[1]['value']);
    }

    public function testValidationRulesAreApplied(): void
    {
        $this->fields = [
            ['field_id' => 1, 'name' => 'a', 'label' => 'A', 'field_type' => 'text',
                'validation_rules' => json_encode(['min_length' => 3])],
            ['field_id' => 2, 'name' => 'b', 'label' => 'B', 'field_type' => 'text',
                'validation_rules' => json_encode(['max_length' => 2])],
            ['field_id' => 3, 'name' => 'c', 'label' => 'C', 'field_type' => 'number',
                'validation_rules' => json_encode(['min' => 5])],
            ['field_id' => 4, 'name' => 'd', 'label' => 'D', 'field_type' => 'number',
                'validation_rules' => json_encode(['max' => 5])],
            ['field_id' => 5, 'name' => 'e', 'label' => 'E', 'field_type' => 'text',
                'validation_rules' => json_encode(['pattern' => '^[0-9]+$', 'pattern_message' => 'Digits only'])],
            ['field_id' => 6, 'name' => 'f', 'label' => 'F', 'field_type' => 'text',
                'validation_rules' => json_encode(['pattern' => '^x$'])],
            ['field_id' => 7, 'name' => 'g', 'label' => 'G', 'field_type' => 'text',
                'validation_rules' => json_encode(['min_length' => 10])],
        ];
        $this->params = ['form_id' => 5, 'a' => 'ab', 'b' => 'abc', 'c' => '4', 'd' => '6', 'e' => '12a', 'f' => 'y', 'g' => ''];

        $this->assertSame([
            'a' => 'A must be at least 3 characters.',
            'b' => 'B must be no more than 2 characters.',
            'c' => 'C must be at least 5.',
            'd' => 'D must be no more than 5.',
            'e' => 'Digits only',
            'f' => 'Please enter a valid value for F.',
        ], $this->errorsOf($this->execute()));
    }

    public function testPatternsContainingSlashesAreMatched(): void
    {
        $this->fields = [
            ['field_id' => 1, 'name' => 'a', 'label' => 'A', 'field_type' => 'text',
                'validation_rules' => json_encode(['pattern' => '^\d+/\d+$'])],
            ['field_id' => 2, 'name' => 'b', 'label' => 'B', 'field_type' => 'text',
                'validation_rules' => json_encode(['pattern' => '^\d+\/\d+$'])],
            ['field_id' => 3, 'name' => 'c', 'label' => 'C', 'field_type' => 'text',
                'validation_rules' => json_encode(['pattern' => '^x/y$'])],
        ];
        $this->params = ['form_id' => 5, 'a' => '12/34', 'b' => '5/6', 'c' => 'nope'];

        $this->assertSame(
            ['c' => 'Please enter a valid value for C.'],
            $this->errorsOf($this->execute())
        );
    }

    public function testOverlongValueIsRejected(): void
    {
        $this->fields = [['field_id' => 1, 'name' => 'msg', 'label' => 'Message', 'field_type' => 'textarea']];
        $this->params = ['form_id' => 5, 'msg' => str_repeat('x', 65536)];

        $this->assertSame(
            ['msg' => 'Message must be no more than 65535 characters.'],
            $this->errorsOf($this->execute())
        );
    }

    public function testGuestSubmissionIsStoredWithDerivedIdentityAndNotifications(): void
    {
        $this->uploadedFiles = ['abc.pdf'];
        $this->formData += ['auto_reply_enabled' => 1, 'redirect_url' => 'https://evil.test', 'success_message' => 'Done'];
        $this->fields = [
            ['field_id' => 1, 'name' => 'full', 'label' => 'Full Name', 'field_type' => 'text'],
            ['field_id' => 2, 'name' => 'mail', 'label' => 'Mail', 'field_type' => 'email'],
            ['field_id' => 3, 'name' => 'cv', 'label' => 'CV', 'field_type' => 'file'],
            ['field_id' => 4, 'name' => 'tel', 'label' => 'Tel', 'field_type' => 'phone'],
        ];
        $this->params = ['form_id' => 5, 'full' => ' Ann Lee ', 'mail' => 'ann@x.test', 'cv' => 'abc.pdf', 'tel' => '+44 (0) 1234-567'];

        $result = $this->execute();

        $this->assertSame(['success' => true, 'message' => 'Done', 'redirect_url' => ''], $result);
        $this->assertSame([
            'form_id' => 5,
            'customer_id' => null,
            'customer_email' => 'ann@x.test',
            'customer_name' => 'Ann Lee',
            'customer_ip' => '10.0.0.1',
            'store_id' => 2,
            'status' => 'new',
            'submission_id' => 77,
        ], $this->savedSubmissions[0]);
        $this->assertCount(4, $this->savedValues);
        $this->assertSame(77, $this->savedValues[2]['submission_id']);
        $this->assertSame('https://shop.test/media/up/abc.pdf', $this->savedValues[2]['value']);
        $this->assertSame(
            ['label' => 'Mail', 'value' => 'ann@x.test', 'type' => 'email'],
            $this->adminNotifications[0][1]
        );
        $this->assertSame([['ann@x.test', '10.0.0.1', 2]], $this->autoReplyChecks);
        $this->assertSame(['ann@x.test'], $this->autoReplies);
    }

    public function testLoggedInCustomerIdentityWinsAndAutoReplyRespectsGuard(): void
    {
        $this->loggedInCustomer = ['email' => 'member@x.test', 'name' => 'Member', 'id' => '12'];
        $this->autoReplyAllowed = false;
        $this->formData['auto_reply_enabled'] = 1;
        $this->fields = [['field_id' => 1, 'name' => 'mail', 'label' => 'Name', 'field_type' => 'email']];
        $this->params = ['form_id' => 5, 'mail' => 'other@x.test'];

        $this->assertTrue($this->execute()['success']);
        $this->assertSame('member@x.test', $this->savedSubmissions[0]['customer_email']);
        $this->assertSame('Member', $this->savedSubmissions[0]['customer_name']);
        $this->assertSame(12, $this->savedSubmissions[0]['customer_id']);
        $this->assertCount(1, $this->autoReplyChecks);
        $this->assertSame([], $this->autoReplies);
    }

    public function testAutoReplyDisabledOnFormSkipsGuard(): void
    {
        $this->params = ['form_id' => 5];

        $this->assertTrue($this->execute()['success']);
        $this->assertSame([], $this->autoReplyChecks);
        $this->assertCount(1, $this->adminNotifications);
    }

    public function testSaveFailureReturnsGenericErrorAndLogs(): void
    {
        $this->saveFailure = new \RuntimeException('db gone');
        $this->params = ['form_id' => 5];

        $result = $this->execute();

        $this->assertFalse($result['success']);
        $this->assertSame('An error occurred while submitting the form. Please try again.', $result['message']);
        $errors = array_values(array_filter($this->logs, static fn($l) => $l[0] === 'error'));
        $this->assertSame('DynamicForms submission error: db gone', $errors[0][1]);
        $this->assertSame(5, $errors[0][2]['form_id']);
        $this->assertSame([], $this->adminNotifications);
    }
}
