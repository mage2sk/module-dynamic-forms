<?php
declare(strict_types=1);

namespace Panth\DynamicForms\Test\Unit\Block\Adminhtml\Form;

use Magento\Backend\Block\Widget\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Panth\DynamicForms\Block\Adminhtml\Form\BackButton;
use Panth\DynamicForms\Block\Adminhtml\Form\DeleteButton;
use Panth\DynamicForms\Block\Adminhtml\Form\SaveAndContinueButton;
use Panth\DynamicForms\Block\Adminhtml\Form\SaveButton;
use Panth\DynamicForms\Block\Adminhtml\Form\ViewPageButton;
use Panth\DynamicForms\Model\Form;
use Panth\DynamicForms\Model\FormFactory;
use Panth\DynamicForms\Model\ResourceModel\Form as FormResource;
use Panth\DynamicForms\Test\Unit\Support\ModelBuilderTrait;
use PHPUnit\Framework\TestCase;

class ButtonsTest extends TestCase
{
    use ModelBuilderTrait;

    private function request(?string $formId): RequestInterface
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(static fn($key) => $key === 'form_id' ? $formId : null);
        return $request;
    }

    private function context(?string $formId): Context
    {
        $url = $this->createStub(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(
            static fn($route, $params = []) => '/admin/' . $route . ($params ? '/id/' . implode('/', $params) : '')
        );
        $context = $this->createStub(Context::class);
        $context->method('getUrlBuilder')->willReturn($url);
        $context->method('getRequest')->willReturn($this->request($formId));
        $context->method('getEscaper')->willReturn(new Escaper());
        return $context;
    }

    public function testBackButtonPointsToGrid(): void
    {
        $data = (new BackButton($this->context(null)))->getButtonData();

        $this->assertSame("location.href = '/admin/*/*/';", $data['on_click']);
        $this->assertSame(10, $data['sort_order']);
    }

    public function testDeleteButtonOnlyForExistingForms(): void
    {
        $this->assertSame([], (new DeleteButton($this->context(null)))->getButtonData());

        $data = (new DeleteButton($this->context('6')))->getButtonData();
        $this->assertSame(
            "deleteConfirm('" . (new Escaper())->escapeJs('Are you sure you want to delete this form?')
            . "', '/admin/*/*/delete/id/6')",
            $data['on_click']
        );
        $this->assertSame('delete', $data['class']);
    }

    private function withApostropheTranslation(callable $callback)
    {
        $previous = \Magento\Framework\Phrase::getRenderer();
        \Magento\Framework\Phrase::setRenderer(new class implements \Magento\Framework\Phrase\RendererInterface {
            public function render(array $source, array $arguments)
            {
                return "It's gone: " . end($source);
            }
        });
        try {
            return $callback();
        } finally {
            \Magento\Framework\Phrase::setRenderer($previous);
        }
    }

    public function testDeleteButtonEscapesTranslatedTextForJavascript(): void
    {
        $data = $this->withApostropheTranslation(
            fn() => (new DeleteButton($this->context('6')))->getButtonData()
        );

        $this->assertStringNotContainsString("It's", $data['on_click']);
        $this->assertStringContainsString('It\u0027s', $data['on_click']);
        $this->assertStringEndsWith("', '/admin/*/*/delete/id/6')", $data['on_click']);
    }

    public function testSaveButtonsTriggerTheirFormEvents(): void
    {
        $save = (new SaveButton())->getButtonData();
        $continue = (new SaveAndContinueButton())->getButtonData();

        $this->assertSame('save', $save['data_attribute']['mage-init']['button']['event']);
        $this->assertSame('save', $save['data_attribute']['form-role']);
        $this->assertSame('saveAndContinueEdit', $continue['data_attribute']['mage-init']['button']['event']);
        $this->assertGreaterThan($continue['sort_order'], $save['sort_order']);
    }

    private function viewPageButton(?string $formId, array $formData): ViewPageButton
    {
        $store = $this->createStub(Store::class);
        $store->method('getBaseUrl')->willReturn('https://shop.test/');
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        $factory = $this->createStub(FormFactory::class);
        $factory->method('create')->willReturnCallback(fn() => $this->form());
        $resource = $this->createStub(FormResource::class);
        $resource->method('load')->willReturnCallback(function (Form $form) use ($formData, $resource) {
            $form->setData($formData);
            return $resource;
        });

        return new ViewPageButton($this->request($formId), $storeManager, $factory, $resource);
    }

    public function testViewPageButtonNeedsSavedFormWithUrlKey(): void
    {
        $this->assertSame([], $this->viewPageButton(null, ['url_key' => 'contact'])->getButtonData());
        $this->assertSame([], $this->viewPageButton('3', ['url_key' => ''])->getButtonData());

        $data = $this->viewPageButton('3', ['url_key' => 'contact'])->getButtonData();
        $this->assertSame("window.open('https://shop.test/pages/contact', '_blank')", $data['on_click']);
        $this->assertSame('View Page', (string) $data['label']);
    }
}
