<?php
declare(strict_types=1);

namespace Panth\DynamicForms\Test\Unit\Ui\DataProvider;

use Magento\Framework\Api\Filter;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\ReportingInterface;
use Magento\Framework\Api\Search\SearchCriteria;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;
use Panth\DynamicForms\Model\ResourceModel\Field\Collection as FieldCollection;
use Panth\DynamicForms\Model\ResourceModel\Field\CollectionFactory as FieldCollectionFactory;
use Panth\DynamicForms\Model\ResourceModel\Form\Collection as FormCollection;
use Panth\DynamicForms\Model\ResourceModel\Form\CollectionFactory as FormCollectionFactory;
use Panth\DynamicForms\Model\ResourceModel\Submission\Collection as SubmissionCollection;
use Panth\DynamicForms\Model\ResourceModel\Submission\CollectionFactory as SubmissionCollectionFactory;
use Panth\DynamicForms\Test\Unit\Support\ModelBuilderTrait;
use Panth\DynamicForms\Ui\DataProvider\FormDataProvider;
use Panth\DynamicForms\Ui\DataProvider\FormListingDataProvider;
use Panth\DynamicForms\Ui\DataProvider\SubmissionDataProvider;
use Panth\DynamicForms\Ui\DataProvider\SubmissionListingDataProvider;
use PHPUnit\Framework\TestCase;

class DataProvidersTest extends TestCase
{
    use ModelBuilderTrait;

    private array $persistorCalls = [];

    private function formProvider(array $forms, array $fieldsByForm, $persisted = null): FormDataProvider
    {
        $formCollection = $this->collectionOf(FormCollection::class, $forms);
        $formCollection->method('getNewEmptyItem')->willReturnCallback(fn() => $this->form());
        $formFactory = $this->createStub(FormCollectionFactory::class);
        $formFactory->method('create')->willReturn($formCollection);

        $fieldFactory = $this->createStub(FieldCollectionFactory::class);
        $fieldFactory->method('create')->willReturnCallback(function () use ($fieldsByForm) {
            $filters = [];
            $collection = $this->createStub(FieldCollection::class);
            $collection->method('addFieldToFilter')->willReturnCallback(
                function ($field, $value) use ($collection, &$filters) {
                    $filters[$field] = $value;
                    return $collection;
                }
            );
            $collection->method('setOrder')->willReturnSelf();
            $collection->method('getIterator')->willReturnCallback(function () use (&$filters, $fieldsByForm) {
                $rows = $fieldsByForm[$filters['form_id'] ?? 0] ?? [];
                return new \ArrayIterator(array_map(fn($row) => $this->field($row), $rows));
            });
            return $collection;
        });

        $persistor = $this->createStub(DataPersistorInterface::class);
        $persistor->method('get')->willReturn($persisted);
        $persistor->method('clear')->willReturnCallback(function ($key) {
            $this->persistorCalls[] = 'clear:' . $key;
        });

        return new FormDataProvider('form', 'form_id', 'form_id', $formFactory, $fieldFactory, $persistor, new Json());
    }

    public function testFormDataIncludesDecodedFieldsAsJson(): void
    {
        $provider = $this->formProvider(
            [$this->form(['form_id' => 3, 'name' => 'Contact'])],
            [3 => [
                ['field_id' => 1, 'options' => '[{"label":"A"}]', 'validation_rules' => '{"min_length":2}'],
                ['field_id' => 2, 'options' => '{broken', 'validation_rules' => '{broken'],
                ['field_id' => 3, 'options' => '', 'validation_rules' => null],
            ]]
        );

        $data = $provider->getData();

        $this->assertSame('Contact', $data[3]['name']);
        $this->assertSame('Form ID: 3', $data[3]['widget_usage_info']);
        $fields = json_decode($data[3]['fields_json'], true);
        $this->assertSame([['label' => 'A']], $fields[0]['options']);
        $this->assertSame(['min_length' => 2], $fields[0]['validation_rules']);
        $this->assertSame([], $fields[1]['options']);
        $this->assertSame([], $fields[1]['validation_rules']);
        $this->assertSame('', $fields[2]['options']);
        $this->assertSame($data, $provider->getData());
    }

    public function testPersistedDataOverridesAndIsCleared(): void
    {
        $provider = $this->formProvider(
            [$this->form(['form_id' => 3, 'name' => 'Old'])],
            [],
            ['form_id' => 3, 'name' => 'Unsaved edit']
        );

        $data = $provider->getData();

        $this->assertSame(['form_id' => 3, 'name' => 'Unsaved edit'], $data[3]);
        $this->assertSame(['clear:panth_dynamicforms_form'], $this->persistorCalls);
    }

    public function testNoFormsAndNoPersistedDataGivesEmptyArray(): void
    {
        $this->assertSame([], $this->formProvider([], [])->getData());
        $this->assertSame([], $this->persistorCalls);
    }

    public function testSubmissionDataIsFilteredByRequestedForm(): void
    {
        $filters = [];
        $collection = $this->collectionOf(SubmissionCollection::class, [
            $this->submission(['submission_id' => 5, 'status' => 'new']),
        ], $filters);
        $factory = $this->createStub(SubmissionCollectionFactory::class);
        $factory->method('create')->willReturn($collection);
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturn('9');

        $provider = new SubmissionDataProvider('s', 'submission_id', 'submission_id', $factory, $request);

        $this->assertSame([5 => ['submission_id' => 5, 'status' => 'new']], $provider->getData());
        $this->assertSame(['form_id' => 9], $filters);
    }

    public function testSubmissionDataWithoutFormIsUnfiltered(): void
    {
        $filters = [];
        $collection = $this->collectionOf(SubmissionCollection::class, [], $filters);
        $factory = $this->createStub(SubmissionCollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        $provider = new SubmissionDataProvider(
            's',
            'submission_id',
            'submission_id',
            $factory,
            $this->createStub(RequestInterface::class)
        );

        $this->assertSame([], $provider->getData());
        $this->assertSame([], $filters);
    }

    private function listing(string $class, array &$whereClauses, array &$criteriaFilters)
    {
        $select = $this->createStub(Select::class);
        $select->method('where')->willReturnCallback(function ($cond) use ($select, &$whereClauses) {
            $whereClauses[] = $cond;
            return $select;
        });
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('quoteIdentifier')->willReturnCallback(static fn($id) => '`' . $id . '`');
        $connection->method('quoteInto')->willReturnCallback(
            static fn($text, $value) => str_replace('?', "'" . $value . "'", $text)
        );
        $searchResult = $this->createStub(SearchResult::class);
        $searchResult->method('getConnection')->willReturn($connection);
        $searchResult->method('getSelect')->willReturn($select);

        $reporting = $this->createStub(ReportingInterface::class);
        $reporting->method('search')->willReturn($searchResult);

        $criteriaBuilder = $this->createStub(SearchCriteriaBuilder::class);
        $criteriaBuilder->method('create')->willReturn($this->createStub(SearchCriteria::class));
        $criteriaBuilder->method('addFilter')->willReturnCallback(
            function ($filter) use ($criteriaBuilder, &$criteriaFilters) {
                $criteriaFilters[] = $filter->getField();
                return $criteriaBuilder;
            }
        );

        return new $class(
            'listing',
            'id',
            'id',
            $reporting,
            $criteriaBuilder,
            $this->createStub(RequestInterface::class),
            $this->createStub(FilterBuilder::class)
        );
    }

    private function filter(string $field, $value, string $type): Filter
    {
        return new Filter(['field' => $field, 'value' => $value, 'condition_type' => $type]);
    }

    public function testFulltextSearchBecomesEscapedLikeAcrossFormFields(): void
    {
        $where = $criteria = [];
        $provider = $this->listing(FormListingDataProvider::class, $where, $criteria);

        $provider->addFilter($this->filter('fulltext', ' 50%_off ', 'fulltext'));
        $provider->getSearchResult();

        $this->assertSame([], $criteria);
        $this->assertCount(1, $where);
        $this->assertSame(
            "`main_table.name` LIKE '%50\\%\\_off%' OR `main_table.title` LIKE '%50\\%\\_off%'"
            . " OR `main_table.url_key` LIKE '%50\\%\\_off%' OR `main_table.admin_email` LIKE '%50\\%\\_off%'",
            $where[0]
        );
    }

    public function testSubmissionListingSearchesCustomerColumns(): void
    {
        $where = $criteria = [];
        $provider = $this->listing(SubmissionListingDataProvider::class, $where, $criteria);

        $provider->addFilter($this->filter('fulltext', 'ann', 'fulltext'));
        $provider->getSearchResult();

        foreach (['customer_name', 'customer_email', 'customer_ip', 'admin_notes'] as $column) {
            $this->assertStringContainsString('`main_table.' . $column . "` LIKE '%ann%'", $where[0]);
        }
    }

    public function testRegularFiltersPassThroughAndBlankKeywordAddsNothing(): void
    {
        $where = $criteria = [];
        $provider = $this->listing(FormListingDataProvider::class, $where, $criteria);

        $provider->addFilter($this->filter('is_active', '1', 'eq'));
        $provider->addFilter($this->filter('fulltext', ['array'], 'fulltext'));
        $provider->getSearchResult();

        $this->assertSame(['is_active'], $criteria);
        $this->assertSame([], $where);
    }
}
