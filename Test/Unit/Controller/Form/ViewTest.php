<?php
declare(strict_types=1);

namespace Panth\DynamicForms\Test\Unit\Controller\Form;

use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\Forward;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\Registry;
use Magento\Framework\View\Page\Config as PageConfig;
use Magento\Framework\View\Page\Title;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Panth\DynamicForms\Controller\Form\View;
use Panth\DynamicForms\Helper\Data as Helper;
use Panth\DynamicForms\Model\ResourceModel\Form\Collection;
use Panth\DynamicForms\Model\ResourceModel\Form\CollectionFactory;
use Panth\DynamicForms\Test\Unit\Support\ModelBuilderTrait;
use PHPUnit\Framework\TestCase;

class ViewTest extends TestCase
{
    use ModelBuilderTrait;

    private array $page = [];
    private array $registered = [];
    private ?string $forwardedTo = null;
    private array $filters = [];

    private function controller(bool $enabled, ?string $urlKey, array $forms): View
    {
        $this->page = $this->registered = $this->filters = [];
        $this->forwardedTo = null;

        $request = $this->createStub(Http::class);
        $request->method('getParam')->willReturnCallback(
            static fn($key) => $key === 'url_key' ? $urlKey : null
        );
        $request->method('getDistroBaseUrl')->willReturn('https://shop.test/');

        $title = $this->createStub(Title::class);
        $title->method('set')->willReturnCallback(function ($value) {
            $this->page['title'] = (string) $value;
        });
        $config = $this->createStub(PageConfig::class);
        $config->method('getTitle')->willReturn($title);
        foreach (['setPageLayout', 'setDescription', 'setKeywords', 'setRobots'] as $method) {
            $config->method($method)->willReturnCallback(function ($value) use ($method) {
                $this->page[$method] = $value;
            });
        }
        $config->method('addRemotePageAsset')->willReturnCallback(function ($url, $type, $props) use ($config) {
            $this->page['canonical'] = [$url, $type, $props];
            return $config;
        });
        $page = $this->createStub(Page::class);
        $page->method('getConfig')->willReturn($config);
        $pageFactory = $this->createStub(PageFactory::class);
        $pageFactory->method('create')->willReturn($page);

        $forward = $this->createStub(Forward::class);
        $forward->method('forward')->willReturnCallback(function ($action) use ($forward) {
            $this->forwardedTo = $action;
            return $forward;
        });
        $forwardFactory = $this->createStub(ForwardFactory::class);
        $forwardFactory->method('create')->willReturn($forward);

        $collection = $this->collectionOf(Collection::class, $forms, $this->filters);
        $collectionFactory = $this->createStub(CollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);

        $helper = $this->createStub(Helper::class);
        $helper->method('isEnabled')->willReturn($enabled);
        $helper->method('getCurrentStoreId')->willReturn(2);
        $helper->method('getPageLayout')->willReturn('2columns-left');

        $registry = $this->createStub(Registry::class);
        $registry->method('register')->willReturnCallback(function ($key, $value) {
            $this->registered[$key] = $value;
        });

        return new View($request, $pageFactory, $forwardFactory, $collectionFactory, $helper, $registry);
    }

    public function testDisabledModuleForwardsToNoRoute(): void
    {
        $result = $this->controller(false, 'contact', [$this->form(['form_id' => 1])])->execute();

        $this->assertInstanceOf(Forward::class, $result);
        $this->assertSame('noroute', $this->forwardedTo);
    }

    public function testMissingUrlKeyForwardsToNoRoute(): void
    {
        $this->controller(true, '', [$this->form(['form_id' => 1])])->execute();

        $this->assertSame('noroute', $this->forwardedTo);
        $this->assertSame([], $this->filters);
    }

    public function testUnknownFormForwardsToNoRoute(): void
    {
        $this->controller(true, 'nope', [])->execute();

        $this->assertSame('noroute', $this->forwardedTo);
        $this->assertSame([], $this->registered);
        $this->assertSame(['in' => [0, 2]], $this->filters['store_id']);
    }

    public function testPageUsesMetaFieldsAndCanonical(): void
    {
        $form = $this->form([
            'form_id' => 7,
            'url_key' => 'contact',
            'title' => 'Contact us',
            'meta_title' => 'Contact | Shop',
            'meta_description' => 'Reach us',
            'meta_keywords' => 'contact,help',
            'meta_robots' => 'noindex,nofollow',
        ]);

        $result = $this->controller(true, 'contact', [$form])->execute();

        $this->assertInstanceOf(Page::class, $result);
        $this->assertSame($form, $this->registered['current_dynamic_form']);
        $this->assertSame('2columns-left', $this->page['setPageLayout']);
        $this->assertSame('Contact | Shop', $this->page['title']);
        $this->assertSame('Reach us', $this->page['setDescription']);
        $this->assertSame('contact,help', $this->page['setKeywords']);
        $this->assertSame('noindex,nofollow', $this->page['setRobots']);
        $this->assertSame(
            ['https://shop.test/pages/contact', 'canonical', ['attributes' => ['rel' => 'canonical']]],
            $this->page['canonical']
        );
    }

    public function testFallbacksForTitleDescriptionAndRobots(): void
    {
        $form = $this->form([
            'form_id' => 7,
            'url_key' => 'contact',
            'name' => 'Internal name',
            'description' => '<p>' . str_repeat('a', 300) . '</p>',
        ]);

        $this->controller(true, 'contact', [$form])->execute();

        $this->assertSame('Internal name', $this->page['title']);
        $this->assertSame(str_repeat('a', 255), $this->page['setDescription']);
        $this->assertArrayNotHasKey('setKeywords', $this->page);
        $this->assertSame('index,follow', $this->page['setRobots']);
    }

    public function testNoDescriptionAtAllSkipsMetaDescription(): void
    {
        $this->controller(true, 'contact', [$this->form(['form_id' => 7, 'url_key' => 'contact', 'title' => 'T'])])->execute();

        $this->assertArrayNotHasKey('setDescription', $this->page);
    }
}
