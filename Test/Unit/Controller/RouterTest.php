<?php
declare(strict_types=1);

namespace Panth\DynamicForms\Test\Unit\Controller;

use Magento\Framework\App\Action\Forward;
use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Request\Http;
use Panth\DynamicForms\Controller\Router;
use Panth\DynamicForms\Helper\Data as Helper;
use Panth\DynamicForms\Model\ResourceModel\Form\Collection;
use Panth\DynamicForms\Model\ResourceModel\Form\CollectionFactory;
use Panth\DynamicForms\Test\Unit\Support\ModelBuilderTrait;
use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase
{
    use ModelBuilderTrait;

    private array $filters = [];
    private array $requestChanges = [];
    private ?string $createdAction = null;

    private function router(bool $enabled, array $matches): Router
    {
        $helper = $this->createStub(Helper::class);
        $helper->method('isEnabled')->willReturn($enabled);
        $helper->method('getCurrentStoreId')->willReturn(4);

        $this->filters = [];
        $collection = $this->collectionOf(Collection::class, $matches, $this->filters);
        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        $actionFactory = $this->createStub(ActionFactory::class);
        $actionFactory->method('create')->willReturnCallback(function ($class) {
            $this->createdAction = $class;
            return $this->createStub(ActionInterface::class);
        });

        return new Router($actionFactory, $factory, $helper);
    }

    private function request(string $path, ?string $module = null): Http
    {
        $this->requestChanges = [];
        $request = $this->createStub(Http::class);
        $request->method('getModuleName')->willReturn($module);
        $request->method('getPathInfo')->willReturn($path);
        foreach (['setModuleName', 'setControllerName', 'setActionName'] as $method) {
            $request->method($method)->willReturnCallback(function ($value) use ($request, $method) {
                $this->requestChanges[$method] = $value;
                return $request;
            });
        }
        $request->method('setParam')->willReturnCallback(function ($key, $value) use ($request) {
            $this->requestChanges['param:' . $key] = $value;
            return $request;
        });
        $request->method('setAlias')->willReturnCallback(function ($alias, $value) use ($request) {
            $this->requestChanges['alias:' . $alias] = $value;
            return $request;
        });

        return $request;
    }

    public function testDisabledModuleNeverMatches(): void
    {
        $this->assertNull($this->router(false, [$this->form()])->match($this->request('/pages/contact')));
        $this->assertSame([], $this->filters);
    }

    public function testAlreadyRoutedRequestIsIgnoredToAvoidLoops(): void
    {
        $result = $this->router(true, [$this->form()])->match($this->request('/pages/contact', 'dynamicforms'));

        $this->assertNull($result);
        $this->assertSame([], $this->requestChanges);
    }

    public function testNonMatchingPathsAreIgnored(): void
    {
        $router = $this->router(true, [$this->form()]);

        $this->assertNull($router->match($this->request('/contact')));
        $this->assertNull($router->match($this->request('/pages/contact/extra')));
        $this->assertNull($router->match($this->request('/pages/bad.key')));
        $this->assertNull($router->match($this->request('/pages/')));
        $this->assertSame([], $this->filters);
    }

    public function testUnknownUrlKeyDoesNotMatch(): void
    {
        $this->assertNull($this->router(true, [])->match($this->request('/pages/contact')));
        $this->assertSame('contact', $this->filters['url_key']);
    }

    public function testKnownUrlKeyIsForwardedToTheFormViewAction(): void
    {
        $result = $this->router(true, [$this->form(['form_id' => 1])])->match($this->request('/pages/contact-us_2/'));

        $this->assertInstanceOf(ActionInterface::class, $result);
        $this->assertSame(Forward::class, $this->createdAction);
        $this->assertSame([
            'setModuleName' => 'dynamicforms',
            'setControllerName' => 'form',
            'setActionName' => 'view',
            'param:url_key' => 'contact-us_2',
            'alias:' . \Magento\Framework\Url::REWRITE_REQUEST_PATH_ALIAS => 'pages/contact-us_2',
        ], $this->requestChanges);
        $this->assertSame([
            'url_key' => 'contact-us_2',
            'is_active' => 1,
            'form_type' => ['in' => ['page', 'both']],
            'store_id' => ['in' => [0, 4]],
        ], $this->filters);
    }
}
