<?php
declare(strict_types=1);

namespace Panth\DynamicForms\Test\Unit\Ui\Component\Listing\Column;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Panth\DynamicForms\Ui\Component\Listing\Column\FormActions;
use Panth\DynamicForms\Ui\Component\Listing\Column\SubmissionActions;
use Panth\DynamicForms\Ui\Component\Listing\Column\SubmissionCount;
use Panth\DynamicForms\Ui\Component\Listing\Column\SubmissionStatus;
use PHPUnit\Framework\TestCase;

class ColumnsTest extends TestCase
{
    private function urlBuilder(): UrlInterface
    {
        $url = $this->createStub(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(
            static fn($route, $params = []) => $route . '?' . http_build_query($params)
        );
        return $url;
    }

    private function labels(array $actions): array
    {
        return array_map(static fn($a) => (string) $a['label'], $actions);
    }

    public function testFormActionsIncludeViewPageOnlyForPageForms(): void
    {
        $store = $this->createStub(Store::class);
        $store->method('getBaseUrl')->willReturn('https://shop.test/');
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $column = new FormActions(
            $this->createStub(ContextInterface::class),
            $this->createStub(UiComponentFactory::class),
            $this->urlBuilder(),
            $storeManager,
            [],
            ['name' => 'actions']
        );

        $result = $column->prepareDataSource(['data' => ['items' => [
            ['form_id' => 1, 'url_key' => 'contact', 'form_type' => 'page'],
            ['form_id' => 2, 'url_key' => 'quote', 'form_type' => 'widget'],
            ['form_id' => 3, 'url_key' => '', 'form_type' => 'both'],
            ['form_id' => 4, 'url_key' => 'faq'],
            ['name' => 'no id'],
        ]]]);
        $items = $result['data']['items'];

        $this->assertSame(['edit' => 'Edit', 'submissions' => 'View Submissions', 'view_page' => 'View Page', 'delete' => 'Delete'], $this->labels($items[0]['actions']));
        $this->assertSame('https://shop.test/pages/contact', $items[0]['actions']['view_page']['href']);
        $this->assertSame('_blank', $items[0]['actions']['view_page']['target']);
        $this->assertSame('panth_dynamicforms/form/edit?form_id=1', $items[0]['actions']['edit']['href']);
        $this->assertSame('panth_dynamicforms/form/delete?form_id=1', $items[0]['actions']['delete']['href']);
        $this->assertArrayNotHasKey('view_page', $items[1]['actions']);
        $this->assertArrayNotHasKey('view_page', $items[2]['actions']);
        $this->assertArrayHasKey('view_page', $items[3]['actions']);
        $this->assertArrayNotHasKey('actions', $items[4]);
    }

    public function testFormActionsLeaveEmptyDataSourceAlone(): void
    {
        $column = new FormActions(
            $this->createStub(ContextInterface::class),
            $this->createStub(UiComponentFactory::class),
            $this->urlBuilder(),
            $this->createStub(StoreManagerInterface::class),
            [],
            ['name' => 'actions']
        );

        $this->assertSame(['data' => []], $column->prepareDataSource(['data' => []]));
    }

    public function testSubmissionActionsLinkToViewAndDelete(): void
    {
        $column = new SubmissionActions(
            $this->createStub(ContextInterface::class),
            $this->createStub(UiComponentFactory::class),
            $this->urlBuilder(),
            [],
            ['name' => 'actions']
        );

        $items = $column->prepareDataSource(['data' => ['items' => [
            ['submission_id' => 7, 'form_id' => 2],
            ['submission_id' => 8],
            ['form_id' => 3],
        ]]])['data']['items'];

        $this->assertSame('panth_dynamicforms/submission/view?submission_id=7', $items[0]['actions']['view']['href']);
        $this->assertSame(
            'panth_dynamicforms/submission/delete?submission_id=7&form_id=2',
            $items[0]['actions']['delete']['href']
        );
        $this->assertSame('panth_dynamicforms/submission/delete?submission_id=8&form_id=0', $items[1]['actions']['delete']['href']);
        $this->assertArrayNotHasKey('actions', $items[2]);
    }

    public function testSubmissionCountFillsCountsAndDefaultsToZero(): void
    {
        $queriedIds = null;
        $select = $this->createStub(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('group')->willReturnSelf();
        $select->method('where')->willReturnCallback(function ($cond, $ids) use ($select, &$queriedIds) {
            $queriedIds = $ids;
            return $select;
        });
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchPairs')->willReturn([1 => '4', 3 => '1']);
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);

        $column = new SubmissionCount(
            $this->createStub(ContextInterface::class),
            $this->createStub(UiComponentFactory::class),
            $resource,
            [],
            ['name' => 'submission_count']
        );

        $items = $column->prepareDataSource(['data' => ['items' => [
            ['form_id' => 1], ['form_id' => 2], ['form_id' => 3],
        ]]])['data']['items'];

        $this->assertSame([1, 2, 3], $queriedIds);
        $this->assertSame([4, 0, 1], array_column($items, 'submission_count'));
    }

    public function testSubmissionCountSkipsQueryWithoutFormIds(): void
    {
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willThrowException(new \LogicException('must not query'));

        $column = new SubmissionCount(
            $this->createStub(ContextInterface::class),
            $this->createStub(UiComponentFactory::class),
            $resource,
            [],
            ['name' => 'submission_count']
        );

        $items = $column->prepareDataSource(['data' => ['items' => [['name' => 'x']]]])['data']['items'];

        $this->assertSame(0, $items[0]['submission_count']);
    }

    public function testSubmissionStatusRendersColouredBadge(): void
    {
        $column = new SubmissionStatus(
            $this->createStub(ContextInterface::class),
            $this->createStub(UiComponentFactory::class),
            [],
            ['name' => 'status']
        );

        $items = $column->prepareDataSource(['data' => ['items' => [
            ['status' => 'new'],
            ['status' => 'closed'],
            ['status' => 'other'],
            ['id' => 1],
        ]]])['data']['items'];

        $this->assertStringContainsString('background:#1979c3', $items[0]['status_html']);
        $this->assertStringContainsString('>New</span>', $items[0]['status_html']);
        $this->assertStringContainsString('background:#999999', $items[1]['status_html']);
        $this->assertStringContainsString('background:#333333', $items[2]['status_html']);
        $this->assertArrayNotHasKey('status_html', $items[3]);
    }
}
