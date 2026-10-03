<?php
declare(strict_types=1);

namespace Panth\DynamicForms\Test\Unit\Model\Config\Source;

use Panth\DynamicForms\Model\Config\Source\FieldType;
use Panth\DynamicForms\Model\Config\Source\FieldWidth;
use Panth\DynamicForms\Model\Config\Source\FormLayout;
use Panth\DynamicForms\Model\Config\Source\FormList;
use Panth\DynamicForms\Model\Config\Source\FormStatus;
use Panth\DynamicForms\Model\ResourceModel\Form\Collection;
use Panth\DynamicForms\Model\ResourceModel\Form\CollectionFactory;
use Panth\DynamicForms\Test\Unit\Support\ModelBuilderTrait;
use PHPUnit\Framework\TestCase;

class SourceModelsTest extends TestCase
{
    use ModelBuilderTrait;

    private function values(array $options): array
    {
        return array_column($options, 'value');
    }

    public function testFieldTypesMatchTheTypesTheFrontendRenders(): void
    {
        $this->assertSame(
            ['text', 'textarea', 'email', 'phone', 'number', 'select', 'multiselect', 'checkbox', 'radio', 'file', 'date', 'hidden', 'wysiwyg'],
            $this->values((new FieldType())->toOptionArray())
        );
    }

    public function testFieldWidthsMatchWidgetCssClasses(): void
    {
        $this->assertSame(['full', 'half', 'third'], $this->values((new FieldWidth())->toOptionArray()));
    }

    public function testFormLayoutsMatchTheHelperWhitelist(): void
    {
        $this->assertSame(
            ['1column', '2columns-left', '2columns-right'],
            $this->values((new FormLayout())->toOptionArray())
        );
    }

    public function testFormStatusesUseTheirConstants(): void
    {
        $options = (new FormStatus())->toOptionArray();

        $this->assertSame(
            [FormStatus::STATUS_NEW, FormStatus::STATUS_READ, FormStatus::STATUS_REPLIED, FormStatus::STATUS_CLOSED],
            $this->values($options)
        );
        $this->assertSame('Replied', (string) $options[2]['label']);
    }

    public function testFormListHasPlaceholderPlusActiveFormsAndIsCached(): void
    {
        $filters = [];
        $collection = $this->collectionOf(Collection::class, [
            $this->form(['form_id' => 2, 'name' => 'Contact']),
            $this->form(['form_id' => 5, 'name' => 'Quote']),
        ], $filters);
        $created = 0;
        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturnCallback(function () use ($collection, &$created) {
            $created++;
            return $collection;
        });

        $source = new FormList($factory);
        $options = $source->toOptionArray();

        $this->assertSame(['', 2, 5], $this->values($options));
        $this->assertSame('-- Please Select --', (string) $options[0]['label']);
        $this->assertSame('Quote', $options[2]['label']);
        $this->assertSame(['is_active' => 1], $filters);
        $this->assertSame($options, $source->toOptionArray());
        $this->assertSame(1, $created);
    }
}
