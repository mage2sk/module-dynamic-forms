<?php
declare(strict_types=1);

namespace Panth\DynamicForms\Test\Unit\Support;

use Panth\DynamicForms\Model\Field;
use Panth\DynamicForms\Model\Form;
use Panth\DynamicForms\Model\Submission;
use Panth\DynamicForms\Model\SubmissionValue;

/**
 * Builds real module models without touching the object manager or the database,
 * so getData()/setData()/getId() behave exactly like in production.
 */
trait ModelBuilderTrait
{
    private static function idFieldFor(string $class): string
    {
        $map = [
            Form::class => 'form_id',
            Field::class => 'field_id',
            Submission::class => 'submission_id',
            SubmissionValue::class => 'value_id',
        ];

        return $map[$class] ?? 'id';
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    protected function buildModel(string $class, array $data = [])
    {
        $reflection = new \ReflectionClass($class);
        $model = $reflection->newInstanceWithoutConstructor();

        $idField = new \ReflectionProperty(\Magento\Framework\Model\AbstractModel::class, '_idFieldName');
        $idField->setValue($model, self::idFieldFor($class));

        $model->setData($data);

        return $model;
    }

    protected function form(array $data = []): Form
    {
        return $this->buildModel(Form::class, $data);
    }

    protected function field(array $data = []): Field
    {
        return $this->buildModel(Field::class, $data);
    }

    protected function submission(array $data = []): Submission
    {
        return $this->buildModel(Submission::class, $data);
    }

    protected function submissionValue(array $data = []): SubmissionValue
    {
        return $this->buildModel(SubmissionValue::class, $data);
    }

    /**
     * An iterable collection double of the given class, yielding the given items.
     */
    protected function collectionOf(string $class, array $items, ?array &$filters = null)
    {
        $filters = $filters ?? [];
        $collection = $this->createStub($class);
        $collection->method('addFieldToFilter')->willReturnCallback(
            function ($field, $condition = null) use ($collection, &$filters) {
                $filters[$field] = $condition;
                return $collection;
            }
        );
        $collection->method('setOrder')->willReturnSelf();
        $collection->method('setPageSize')->willReturnSelf();
        $collection->method('getIterator')->willReturnCallback(static fn() => new \ArrayIterator($items));
        $collection->method('getItems')->willReturn($items);
        $collection->method('getSize')->willReturn(count($items));
        $collection->method('getFirstItem')->willReturnCallback(
            fn() => $items === [] ? $this->form() : reset($items)
        );

        return $collection;
    }
}
