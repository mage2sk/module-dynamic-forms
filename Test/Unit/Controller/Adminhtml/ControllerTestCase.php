<?php
declare(strict_types=1);

namespace Panth\DynamicForms\Test\Unit\Controller\Adminhtml;

use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Page;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Backend\Model\View\Result\RedirectFactory;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\View\Page\Config as PageConfig;
use Magento\Framework\View\Page\Title;
use Magento\Framework\View\Result\PageFactory;
use Magento\Ui\Component\MassAction\Filter;
use Panth\DynamicForms\Test\Unit\Support\ModelBuilderTrait;
use PHPUnit\Framework\TestCase;

/**
 * Shared doubles for backend controller tests: records redirects, messages and page titles.
 */
abstract class ControllerTestCase extends TestCase
{
    use ModelBuilderTrait;

    /** @var array{0:string,1:array}|null */
    protected ?array $redirect = null;
    protected array $success = [];
    protected array $errors = [];
    protected array $titles = [];
    protected ?string $activeMenu = null;

    protected function context(array $params = [], $post = null): Context
    {
        $this->redirect = null;
        $this->success = $this->errors = [];

        $request = $this->createStub(HttpRequest::class);
        $request->method('getParam')->willReturnCallback(
            static fn($key, $default = null) => array_key_exists($key, $params) ? $params[$key] : $default
        );
        $request->method('getPostValue')->willReturn($post);

        $redirect = $this->createStub(Redirect::class);
        $redirect->method('setPath')->willReturnCallback(function ($path, $args = []) use ($redirect) {
            $this->redirect = [$path, $args];
            return $redirect;
        });
        $redirectFactory = $this->createStub(RedirectFactory::class);
        $redirectFactory->method('create')->willReturn($redirect);

        $messages = $this->createStub(ManagerInterface::class);
        $messages->method('addSuccessMessage')->willReturnCallback(function ($m) use ($messages) {
            $this->success[] = (string) $m;
            return $messages;
        });
        $messages->method('addErrorMessage')->willReturnCallback(function ($m) use ($messages) {
            $this->errors[] = (string) $m;
            return $messages;
        });

        $context = $this->createStub(Context::class);
        $context->method('getRequest')->willReturn($request);
        $context->method('getResultRedirectFactory')->willReturn($redirectFactory);
        $context->method('getMessageManager')->willReturn($messages);

        return $context;
    }

    protected function pageFactory(): PageFactory
    {
        $this->titles = [];
        $this->activeMenu = null;

        $title = $this->createStub(Title::class);
        $title->method('prepend')->willReturnCallback(function ($value) {
            $this->titles[] = (string) $value;
        });
        $config = $this->createStub(PageConfig::class);
        $config->method('getTitle')->willReturn($title);

        $page = $this->createStub(Page::class);
        $page->method('getConfig')->willReturn($config);
        $page->method('setActiveMenu')->willReturnCallback(function ($menu) use ($page) {
            $this->activeMenu = $menu;
            return $page;
        });

        $factory = $this->createStub(PageFactory::class);
        $factory->method('create')->willReturn($page);

        return $factory;
    }

    /**
     * A mass-action filter that hands back the given collection unchanged.
     */
    protected function filterReturning($collection): Filter
    {
        $filter = $this->createStub(Filter::class);
        $filter->method('getCollection')->willReturn($collection);

        return $filter;
    }
}
