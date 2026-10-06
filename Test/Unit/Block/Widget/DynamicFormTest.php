<?php
declare(strict_types=1);

namespace Panth\DynamicForms\Test\Unit\Block\Widget;

use Magento\Cms\Model\Template\Filter;
use Magento\Cms\Model\Template\FilterProvider;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Panth\Core\Helper\Theme as ThemeHelper;
use Panth\DynamicForms\Block\Widget\DynamicForm;
use Panth\DynamicForms\Controller\Form\Submit;
use Panth\DynamicForms\Helper\Data as Helper;
use Panth\DynamicForms\Model\Form;
use Panth\DynamicForms\Model\FormFactory;
use Panth\DynamicForms\Model\RedirectUrlValidator;
use Panth\DynamicForms\Model\ResourceModel\Field\Collection;
use Panth\DynamicForms\Model\ResourceModel\Field\CollectionFactory;
use Panth\DynamicForms\Model\ResourceModel\Form as FormResource;
use Panth\DynamicForms\Test\Unit\Support\BlockBuilderTrait;
use Panth\DynamicForms\Test\Unit\Support\ModelBuilderTrait;
use PHPUnit\Framework\TestCase;

class DynamicFormTest extends TestCase
{
    use BlockBuilderTrait;
    use ModelBuilderTrait;

    private ?Form $registered = null;
    private array $storedForms = [];
    private bool $availableInStore = true;
    private bool $honeypot = true;
    private bool $enabled = true;
    private int $formLoads = 0;
    private array $fieldFilters = [];
    private ?\Exception $filterFailure = null;

    private function block(array $data = [], array $fields = []): DynamicForm
    {
        $helper = $this->createStub(Helper::class);
        $helper->method('isEnabled')->willReturnCallback(fn() => $this->enabled);
        $helper->method('isHoneypotEnabled')->willReturnCallback(fn() => $this->honeypot);
        $helper->method('isFormAvailableInStore')->willReturnCallback(fn() => $this->availableInStore);
        $helper->method('isAjaxEnabled')->willReturn(true);
        $helper->method('getLoadingText')->willReturn('Sending...');
        $helper->method('getAllowedExtensions')->willReturn(['pdf']);
        $helper->method('getMaxFileSize')->willReturn(2 * 1024 * 1024);
        $helper->method('isShowFormTitle')->willReturn(false);
        $helper->method('isShowFormDescription')->willReturn(true);

        $registry = $this->createStub(Registry::class);
        $registry->method('registry')->willReturnCallback(
            fn($key) => $key === 'current_dynamic_form' ? $this->registered : null
        );

        $formFactory = $this->createStub(FormFactory::class);
        $formFactory->method('create')->willReturnCallback(fn() => $this->form());
        $formResource = $this->createStub(FormResource::class);
        $formResource->method('load')->willReturnCallback(function (Form $form, $id) use ($formResource) {
            $this->formLoads++;
            if (isset($this->storedForms[(int) $id])) {
                $form->setData($this->storedForms[(int) $id]);
            }
            return $formResource;
        });

        $this->fieldFilters = [];
        $collection = $this->collectionOf(Collection::class, array_map(fn($f) => $this->field($f), $fields), $this->fieldFilters);
        $collectionFactory = $this->createStub(CollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);

        $pageFilter = $this->createStub(Filter::class);
        $pageFilter->method('filter')->willReturnCallback(function ($content) {
            if ($this->filterFailure) {
                throw $this->filterFailure;
            }
            return '[filtered]' . $content;
        });
        $filterProvider = $this->createStub(FilterProvider::class);
        $filterProvider->method('getPageFilter')->willReturn($pageFilter);

        $theme = $this->createStub(ThemeHelper::class);
        $theme->method('isHyva')->willReturn(true);

        $formKey = $this->createStub(FormKey::class);
        $formKey->method('getFormKey')->willReturn('FK123');

        $redirect = $this->createStub(RedirectUrlValidator::class);
        $redirect->method('sanitize')->willReturnCallback(
            static fn($url) => str_starts_with((string) $url, '/') ? (string) $url : ''
        );

        $url = $this->createStub(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(static fn($route) => 'https://shop.test/' . $route);

        return $this->buildBlock(DynamicForm::class, [
            '_urlBuilder' => $url,
            'formFactory' => $formFactory,
            'formResource' => $formResource,
            'fieldCollectionFactory' => $collectionFactory,
            'helper' => $helper,
            'filterProvider' => $filterProvider,
            'registry' => $registry,
            'themeHelper' => $theme,
            'formKey' => $formKey,
            'redirectUrlValidator' => $redirect,
        ], $data);
    }

    private function callPrivate(object $object, string $method, ...$args)
    {
        $reflection = new \ReflectionMethod($object, $method);
        return $reflection->invoke($object, ...$args);
    }

    public function testRegisteredFormFromStandalonePageWins(): void
    {
        $this->registered = $this->form(['form_id' => 8, 'is_active' => 1]);

        $block = $this->block();

        $this->assertSame($this->registered, $block->getForm());
        $this->assertTrue($block->isStandalonePage());
        $this->assertSame(0, $this->formLoads);
    }

    public function testWidgetFormIsLoadedOnceByConfiguredId(): void
    {
        $this->storedForms[4] = ['form_id' => 4, 'is_active' => 1];

        $block = $this->block(['form_id' => '4']);

        $this->assertSame(4, $block->getForm()->getId());
        $this->assertSame($block->getForm(), $block->getForm());
        $this->assertSame(1, $this->formLoads);
        $this->assertFalse($block->isStandalonePage());
    }

    public function testInactiveMissingOrForeignFormsAreHidden(): void
    {
        $this->assertNull($this->block()->getForm());

        $this->assertNull($this->block(['form_id' => 99])->getForm());

        $this->storedForms[4] = ['form_id' => 4, 'is_active' => 0];
        $this->assertNull($this->block(['form_id' => 4])->getForm());

        $this->storedForms[4]['is_active'] = 1;
        $this->availableInStore = false;
        $this->assertNull($this->block(['form_id' => 4])->getForm());

        $this->registered = $this->form(['form_id' => 8]);
        $this->assertNull($this->block()->getForm());
    }

    public function testWithoutFormEverythingDegradesToEmptyValues(): void
    {
        $block = $this->block();

        $this->assertSame([], $block->getFields());
        $this->assertSame('{}', $block->getFormConfig());
        $this->assertSame('', $block->getSafeRedirectUrl());
        $this->assertSame([], $block->getFormStyle());
        $this->assertSame('dynamicForm_0', $block->getFormIdentifier());
        $this->assertSame('[]', $block->getFieldsJson());
        $this->assertFalse($block->isStandalonePage());
    }

    public function testFieldsAreActiveOnesOfTheFormAndCached(): void
    {
        $this->storedForms[4] = ['form_id' => 4, 'is_active' => 1];
        $block = $this->block(['form_id' => 4], [['field_id' => 1, 'name' => 'a']]);

        $fields = $block->getFields();

        $this->assertCount(1, $fields);
        $this->assertSame($fields, $block->getFields());
        $this->assertSame(['form_id' => 4, 'is_active' => 1], $this->fieldFilters);
    }

    public function testFormConfigIsSafeJson(): void
    {
        $this->storedForms[4] = [
            'form_id' => 4,
            'is_active' => 1,
            'redirect_url' => 'https://evil.test',
            'submit_button_text' => "Send <now> 'quick'",
        ];

        $json = $this->block(['form_id' => 4])->getFormConfig();
        $config = json_decode($json, true);

        $this->assertStringNotContainsString('<', $json);
        $this->assertStringNotContainsString("'", $json);
        $this->assertSame(4, $config['form_id']);
        $this->assertSame('https://shop.test/dynamicforms/form/submit', $config['submit_url']);
        $this->assertSame('https://shop.test/dynamicforms/form/upload', $config['upload_url']);
        $this->assertTrue($config['ajax_enabled']);
        $this->assertSame('Sending...', $config['loading_text']);
        $this->assertSame('Thank you! Your form has been submitted successfully.', $config['success_message']);
        $this->assertSame('', $config['redirect_url']);
        $this->assertSame("Send <now> 'quick'", $config['submit_button_text']);
        $this->assertSame(['pdf'], $config['allowed_extensions']);
        $this->assertEquals(2, $config['max_file_size_mb']);
    }

    public function testSafeRedirectKeepsRelativePaths(): void
    {
        $this->storedForms[4] = ['form_id' => 4, 'is_active' => 1, 'redirect_url' => '/thanks', 'success_message' => 'Yay'];

        $block = $this->block(['form_id' => 4]);

        $this->assertSame('/thanks', $block->getSafeRedirectUrl());
        $this->assertSame('Yay', json_decode($block->getFormConfig(), true)['success_message']);
        $this->assertSame('Submit', json_decode($block->getFormConfig(), true)['submit_button_text']);
    }

    public function testAntiSpamFieldsHtml(): void
    {
        $this->storedForms[4] = ['form_id' => 4, 'is_active' => 1];
        $html = $this->block(['form_id' => 4])->getAntiSpamFieldsHtml();

        $this->assertStringContainsString(DynamicForm::ANTISPAM_MARKER . '="1"', $html);
        $this->assertStringContainsString('name="' . Submit::HONEYPOT_FIELD . '"', $html);
        $this->assertStringContainsString('id="pdf-hp-4"', $html);
        $this->assertStringContainsString('tabindex="-1"', $html);

        $this->honeypot = false;
        $this->assertSame('', $this->block(['form_id' => 4])->getAntiSpamFieldsHtml());
    }

    public function testAntiSpamFieldsAreInjectedIntoFirstFormOnlyOnce(): void
    {
        $this->storedForms[4] = ['form_id' => 4, 'is_active' => 1];
        $block = $this->block(['form_id' => 4]);

        $out = $this->callPrivate($block, 'withAntiSpamFields', '<form id="a"><input/></form><form id="b"></form>');
        $this->assertSame(1, substr_count($out, DynamicForm::ANTISPAM_MARKER));
        $this->assertStringStartsWith('<form id="a"><div ' . DynamicForm::ANTISPAM_MARKER, $out);

        $already = '<form>' . $block->getAntiSpamFieldsHtml() . '</form>';
        $this->assertSame($already, $this->callPrivate($block, 'withAntiSpamFields', $already));
        $this->assertSame('', $this->callPrivate($block, 'withAntiSpamFields', ''));
        $this->assertSame('<div>no form</div>', $this->callPrivate($block, 'withAntiSpamFields', '<div>no form</div>'));

        $this->honeypot = false;
        $plain = '<form></form>';
        $this->assertSame($plain, $this->callPrivate($this->block(['form_id' => 4]), 'withAntiSpamFields', $plain));
    }

    public function testToHtmlIsEmptyWhenDisabledOrWithoutForm(): void
    {
        $this->enabled = false;
        $this->storedForms[4] = ['form_id' => 4, 'is_active' => 1];
        $this->assertSame('', $this->callPrivate($this->block(['form_id' => 4]), '_toHtml'));

        $this->enabled = true;
        $this->assertSame('', $this->callPrivate($this->block(), '_toHtml'));
    }

    public function testDecodedFieldOptionsRulesAndWidths(): void
    {
        $block = $this->block();

        $this->assertSame(['a'], $block->getFieldOptions($this->field(['options' => '["a"]'])));
        $this->assertSame([], $block->getFieldOptions($this->field(['options' => 'nope'])));
        $this->assertSame([], $block->getFieldOptions($this->field()));
        $this->assertSame(['min' => 1], $block->getFieldValidationRules($this->field(['validation_rules' => '{"min":1}'])));
        $this->assertSame([], $block->getFieldValidationRules($this->field(['validation_rules' => '"str"'])));
        $this->assertSame([], $block->getFieldValidationRules($this->field()));
        $this->assertSame('panth-df-field--half', $block->getWidthClass('half'));
        $this->assertSame('panth-df-field--full', $block->getWidthClass('quarter'));
    }

    public function testFieldsJsonNormalisesEveryField(): void
    {
        $this->storedForms[4] = ['form_id' => 4, 'is_active' => 1];
        $block = $this->block(['form_id' => 4], [[
            'field_id' => '9',
            'name' => 'color',
            'field_type' => 'select',
            'label' => '<Color>',
            'is_required' => '1',
            'options' => '["red"]',
        ]]);

        $json = $block->getFieldsJson();
        $fields = json_decode($json, true);

        $this->assertStringNotContainsString('<Color>', $json);
        $this->assertSame([
            'field_id' => 9,
            'name' => 'color',
            'type' => 'select',
            'label' => '<Color>',
            'placeholder' => '',
            'default_value' => '',
            'is_required' => true,
            'options' => ['red'],
            'validation_rules' => [],
            'width' => 'full',
            'css_class' => '',
        ], $fields[0]);
        $this->assertSame('dynamicForm_4', $block->getFormIdentifier());
    }

    public function testFormStyleDecoding(): void
    {
        $this->storedForms[4] = ['form_id' => 4, 'is_active' => 1, 'form_style' => '{"bg":"#fff"}'];
        $this->assertSame(['bg' => '#fff'], $this->block(['form_id' => 4])->getFormStyle());

        $this->storedForms[4]['form_style'] = 'broken';
        $this->assertSame([], $this->block(['form_id' => 4])->getFormStyle());
    }

    public function testTitleAndDescriptionFlagsPreferWidgetSettings(): void
    {
        $block = $this->block();
        $this->assertFalse($block->showTitle());
        $this->assertTrue($block->showDescription());

        $block = $this->block(['show_title' => '1', 'show_description' => '0']);
        $this->assertTrue($block->showTitle());
        $this->assertFalse($block->showDescription());
    }

    public function testCmsContentRendering(): void
    {
        $block = $this->block();

        $this->assertSame('', $block->renderCmsContent(null));
        $this->assertSame('', $block->renderCmsContent(''));
        $this->assertSame('[filtered]<p>x</p>', $block->renderCmsContent('<p>x</p>'));

        $this->filterFailure = new \RuntimeException('bad directive');
        $this->assertSame('&lt;p&gt;x&lt;/p&gt;', $this->block()->renderCmsContent('<p>x</p>'));
    }

    public function testSimpleDelegates(): void
    {
        $block = $this->block();

        $this->assertSame('FK123', $block->getFormKey());
        $this->assertTrue($block->isHyvaTheme());
        $this->assertSame(Submit::HONEYPOT_FIELD, $block->getHoneypotFieldName());
        $this->assertSame('Sending...', $block->getLoadingText());
    }

    public function testFileHintsComeFromConfiguration(): void
    {
        $block = $this->block();

        $this->assertSame('Max size: 2 MB', $block->getMaxFileSizeLabel());
        $this->assertSame('.pdf', $block->getFileAcceptAttribute());
    }

    public function testClientMessagesAreSafeJsonWithPlaceholders(): void
    {
        $json = $this->block()->getClientMessagesJson();
        $messages = json_decode($json, true);

        $this->assertIsArray($messages);
        $this->assertSame('%1 is required.', $messages['required']);
        $this->assertSame('%1 must be at least %2 characters.', $messages['minLength']);
        $this->assertSame('%1 must be no more than %2.', $messages['max']);
        $this->assertArrayHasKey('fileTooBig', $messages);
        $this->assertStringNotContainsString('<', $json);
        $this->assertStringNotContainsString("'", $json);
    }
}
