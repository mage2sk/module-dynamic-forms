<?php
declare(strict_types=1);

namespace Panth\DynamicForms\Test\Unit\Block\Adminhtml\Form;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Panth\DynamicForms\Block\Adminhtml\Form\FieldBuilder;
use Panth\DynamicForms\Model\Config\Source\FieldType;
use Panth\DynamicForms\Model\Config\Source\FieldWidth;
use Panth\DynamicForms\Model\ResourceModel\Field\Collection;
use Panth\DynamicForms\Model\ResourceModel\Field\CollectionFactory;
use Panth\DynamicForms\Test\Unit\Support\BlockBuilderTrait;
use Panth\DynamicForms\Test\Unit\Support\ModelBuilderTrait;
use PHPUnit\Framework\TestCase;

class FieldBuilderTest extends TestCase
{
    use BlockBuilderTrait;
    use ModelBuilderTrait;

    private array $filters = [];

    private function block(?string $formId, array $fields = []): FieldBuilder
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturn($formId);

        $this->filters = [];
        $collection = $this->collectionOf(Collection::class, array_map(fn($f) => $this->field($f), $fields), $this->filters);
        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        return $this->buildBlock(FieldBuilder::class, [
            '_request' => $request,
            'fieldCollectionFactory' => $factory,
            'json' => new Json(),
            'fieldTypeSource' => new FieldType(),
            'fieldWidthSource' => new FieldWidth(),
        ]);
    }

    public function testNewFormHasNoFields(): void
    {
        $this->assertSame('[]', $this->block(null)->getFieldsJson());
        $this->assertSame([], $this->filters);
    }

    public function testFieldsAreDecodedAndDefaulted(): void
    {
        $json = $this->block('4', [
            ['field_id' => 1, 'options' => '["a","b"]', 'validation_rules' => '{"max":3}'],
            ['field_id' => 2, 'options' => '{bad', 'validation_rules' => '{bad'],
            ['field_id' => 3],
        ])->getFieldsJson();

        $fields = json_decode($json, true);
        $this->assertSame(['a', 'b'], $fields[0]['options']);
        $this->assertSame(['max' => 3], $fields[0]['validation_rules']);
        $this->assertSame([], $fields[1]['options']);
        $this->assertSame([], $fields[1]['validation_rules']);
        $this->assertSame([], $fields[2]['options']);
        $this->assertSame([], $fields[2]['validation_rules']);
        $this->assertSame(['form_id' => 4], $this->filters);
    }

    public function testStaticJsonHelpers(): void
    {
        $block = $this->block(null);

        $this->assertSame('["select","multiselect","checkbox","radio"]', $block->getOptionFieldTypes());
        $this->assertCount(13, json_decode($block->getFieldTypesJson(), true));
        $this->assertSame(['full', 'half', 'third'], array_column(json_decode($block->getFieldWidthsJson(), true), 'value'));
    }
}
