<?php
declare(strict_types=1);

namespace Panth\DynamicForms\Test\Unit\Controller\Adminhtml\Form;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Panth\DynamicForms\Controller\Adminhtml\Form\Save;
use Panth\DynamicForms\Model\Field;
use Panth\DynamicForms\Model\FieldFactory;
use Panth\DynamicForms\Model\Form;
use Panth\DynamicForms\Model\Form\SaveDataPreparer;
use Panth\DynamicForms\Model\FormFactory;
use Panth\DynamicForms\Model\RedirectUrlValidator;
use Panth\DynamicForms\Model\ResourceModel\Field as FieldResource;
use Panth\DynamicForms\Model\ResourceModel\Field\Collection as FieldCollection;
use Panth\DynamicForms\Model\ResourceModel\Field\CollectionFactory as FieldCollectionFactory;
use Panth\DynamicForms\Model\ResourceModel\Form as FormResource;
use Panth\DynamicForms\Test\Unit\Controller\Adminhtml\ControllerTestCase;
use Psr\Log\LoggerInterface;

class SaveTest extends ControllerTestCase
{
    private array $existingForms = [];
    private array $existingFields = [];
    private bool $redirectAllowed = true;
    /** @var array<int,string|false> fetchOne results in call order */
    private array $fetchResults = [];
    private ?\Exception $formSaveFailure = null;
    private ?\Exception $fieldSaveFailure = null;

    private array $transaction = [];
    private array $savedForms = [];
    private array $savedFields = [];
    private array $deletedFields = [];
    private array $persisted = [];
    private array $criticalLogs = [];

    private function controller(array $post, array $params = []): Save
    {
        $connection = $this->createStub(AdapterInterface::class);
        $select = $this->createStub(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $select->method('limit')->willReturnSelf();
        $connection->method('select')->willReturn($select);
        $connection->method('getTableName')->willReturnArgument(0);
        $connection->method('fetchOne')->willReturnCallback(fn() => array_shift($this->fetchResults) ?? false);
        foreach (['beginTransaction', 'commit', 'rollBack'] as $method) {
            $connection->method($method)->willReturnCallback(function () use ($connection, $method) {
                $this->transaction[] = $method;
                return $connection;
            });
        }

        $formFactory = $this->createStub(FormFactory::class);
        $formFactory->method('create')->willReturnCallback(fn() => $this->form());
        $formResource = $this->createStub(FormResource::class);
        $formResource->method('getConnection')->willReturn($connection);
        $formResource->method('getMainTable')->willReturn('panth_dynamic_form');
        $formResource->method('load')->willReturnCallback(function (Form $form, $id) use ($formResource) {
            if (isset($this->existingForms[(int) $id])) {
                $form->setData($this->existingForms[(int) $id]);
            }
            return $formResource;
        });
        $formResource->method('save')->willReturnCallback(function (Form $form) use ($formResource) {
            if ($this->formSaveFailure) {
                throw $this->formSaveFailure;
            }
            if (!$form->getId()) {
                $form->setId(50);
            }
            $this->savedForms[] = $form->getData();
            return $formResource;
        });

        $fieldFactory = $this->createStub(FieldFactory::class);
        $fieldFactory->method('create')->willReturnCallback(fn() => $this->field());
        $fieldResource = $this->createStub(FieldResource::class);
        $fieldResource->method('load')->willReturnCallback(function (Field $field, $id) use ($fieldResource) {
            if (isset($this->existingFields[(int) $id])) {
                $field->setData(['field_id' => (int) $id, 'label' => 'old']);
            }
            return $fieldResource;
        });
        $fieldResource->method('save')->willReturnCallback(function (Field $field) use ($fieldResource) {
            if ($this->fieldSaveFailure) {
                throw $this->fieldSaveFailure;
            }
            $this->savedFields[] = $field->getData();
            return $fieldResource;
        });
        $fieldResource->method('delete')->willReturnCallback(function (Field $field) use ($fieldResource) {
            $this->deletedFields[] = $field->getId();
            return $fieldResource;
        });

        $existing = array_map(fn($id) => $this->field(['field_id' => $id]), array_keys($this->existingFields));
        $fieldCollectionFactory = $this->createStub(FieldCollectionFactory::class);
        $fieldCollectionFactory->method('create')->willReturnCallback(
            fn() => $this->collectionOf(FieldCollection::class, $existing)
        );

        $persistor = $this->createStub(DataPersistorInterface::class);
        $persistor->method('set')->willReturnCallback(function ($key, $value) {
            $this->persisted[$key] = $value;
        });
        $persistor->method('clear')->willReturnCallback(function ($key) {
            $this->persisted[$key] = 'cleared';
        });

        $logger = $this->createStub(LoggerInterface::class);
        $logger->method('critical')->willReturnCallback(function ($m, $ctx = []) {
            $this->criticalLogs[] = $ctx['message'] ?? $m;
        });

        $redirectValidator = $this->createStub(RedirectUrlValidator::class);
        $redirectValidator->method('isAllowed')->willReturnCallback(fn() => $this->redirectAllowed);

        return new Save(
            $this->context($params, $post),
            $formFactory,
            $formResource,
            $fieldFactory,
            $fieldResource,
            $fieldCollectionFactory,
            $persistor,
            new Json(),
            $logger,
            new SaveDataPreparer(),
            $redirectValidator
        );
    }

    public function testEmptyPostRedirectsToGrid(): void
    {
        $this->controller([])->execute();

        $this->assertSame(['*/*/', []], $this->redirect);
        $this->assertSame([], $this->transaction);
    }

    public function testUnknownFormIdIsRejected(): void
    {
        $this->controller(['form_id' => 3, 'name' => 'x'])->execute();

        $this->assertSame(['This form no longer exists.'], $this->errors);
        $this->assertSame(['*/*/', []], $this->redirect);
    }

    public function testPageFormRequiresUrlKey(): void
    {
        $this->controller(['name' => 'x', 'form_type' => 'page', 'url_key' => ' !!! '])->execute();

        $this->assertSame(['URL Key is required for forms with a standalone page.'], $this->errors);
        $this->assertSame(['*/*/new', []], $this->redirect);
        $this->assertSame('', $this->persisted['panth_dynamicforms_form']['url_key']);

        $this->existingForms[4] = ['form_id' => 4];
        $this->controller(['form_id' => 4, 'form_type' => 'both'])->execute();
        $this->assertSame(['*/*/edit', ['form_id' => 4]], $this->redirect);
    }

    public function testDisallowedRedirectUrlIsRejected(): void
    {
        $this->redirectAllowed = false;

        $this->controller(['name' => 'x', 'form_type' => 'widget', 'redirect_url' => ' https://evil.test '])->execute();

        $this->assertStringContainsString('Redirect URL must be a relative path', $this->errors[0]);
        $this->assertSame('https://evil.test', $this->persisted['panth_dynamicforms_form']['redirect_url']);
        $this->assertSame(['*/*/new', []], $this->redirect);
    }

    public function testDuplicateUrlKeyIsRejected(): void
    {
        $this->fetchResults = ['12'];

        $this->controller(['name' => 'x', 'url_key' => 'Contact Us'])->execute();

        $this->assertSame(
            ['The URL key "contact-us" is already used by another form (ID: 12). Please choose a different URL key.'],
            $this->errors
        );
        $this->assertSame([], $this->savedForms);
    }

    public function testUrlKeyClashingWithRewriteIsRejected(): void
    {
        $this->existingForms[4] = ['form_id' => 4];
        $this->fetchResults = [false, '901'];

        $this->controller(['form_id' => 4, 'url_key' => 'sale'])->execute();

        $this->assertSame(
            ['The URL key "sale" conflicts with an existing URL rewrite. Please choose a different URL key.'],
            $this->errors
        );
        $this->assertSame(['*/*/edit', ['form_id' => 4]], $this->redirect);
    }

    public function testNewFormIsSavedWithFieldsAndRedirectsToGrid(): void
    {
        $fields = [
            ['label' => 'Name', 'name' => 'name', 'is_required' => '1'],
            ['label' => 'Color', 'name' => 'color', 'field_type' => 'select',
                'options' => [['label' => 'Red', 'value' => 'red']], 'validation_rules' => ['min_length' => 1],
                'sort_order' => 7, 'is_active' => 0],
            'garbage',
            ['label' => 'Raw', 'name' => 'raw', 'options' => '[]', 'validation_rules' => '{}'],
        ];

        $this->controller([
            'name' => 'Contact',
            'form_type' => 'widget',
            'url_key' => 'ignored',
            'form_key' => 'secret',
            'fields_json' => json_encode($fields),
        ])->execute();

        $this->assertSame(['beginTransaction', 'commit'], $this->transaction);
        $this->assertSame(['The form has been saved.'], $this->success);
        $this->assertSame(['*/*/', []], $this->redirect);
        $this->assertSame('cleared', $this->persisted['panth_dynamicforms_form']);

        $this->assertNull($this->savedForms[0]['url_key']);
        $this->assertArrayNotHasKey('form_key', $this->savedForms[0]);
        $this->assertArrayNotHasKey('fields_json', $this->savedForms[0]);

        $this->assertCount(3, $this->savedFields);
        $this->assertSame([
            'form_id' => 50,
            'field_type' => 'text',
            'label' => 'Name',
            'name' => 'name',
            'placeholder' => '',
            'default_value' => '',
            'is_required' => 1,
            'css_class' => '',
            'width' => 'full',
            'sort_order' => 0,
            'is_active' => 1,
        ], $this->savedFields[0]);
        $this->assertSame('[{"label":"Red","value":"red"}]', $this->savedFields[1]['options']);
        $this->assertSame('{"min_length":1}', $this->savedFields[1]['validation_rules']);
        $this->assertSame(7, $this->savedFields[1]['sort_order']);
        $this->assertSame(0, $this->savedFields[1]['is_active']);
        $this->assertSame('[]', $this->savedFields[2]['options']);
        $this->assertSame(3, $this->savedFields[2]['sort_order']);
    }

    public function testExistingFieldsAreUpdatedAndRemovedOnesDeleted(): void
    {
        $this->existingForms[4] = ['form_id' => 4];
        $this->existingFields = [10 => true, 11 => true, 12 => true];

        $this->controller(
            [
                'form_id' => 4,
                'url_key' => 'contact',
                'fields_json' => json_encode([
                    ['field_id' => 10, 'label' => 'Kept', 'name' => 'kept'],
                    ['field_id' => 999, 'label' => 'Foreign id', 'name' => 'foreign'],
                ]),
            ],
            ['back' => 'edit']
        )->execute();

        $this->assertSame(10, $this->savedFields[0]['field_id']);
        $this->assertSame('Kept', $this->savedFields[0]['label']);
        $this->assertArrayNotHasKey('field_id', $this->savedFields[1]);
        $this->assertSame([11, 12], $this->deletedFields);
        $this->assertSame(4, $this->savedForms[0]['form_id']);
        $this->assertSame(['*/*/edit', ['form_id' => 4]], $this->redirect);
    }

    public function testInvalidFieldsJsonKeepsExistingFields(): void
    {
        $this->existingForms[4] = ['form_id' => 4];
        $this->existingFields = [10 => true];

        $this->controller(['form_id' => 4, 'form_type' => 'widget', 'fields_json' => '{not json'])->execute();

        $this->assertSame([], $this->savedFields);
        $this->assertSame([], $this->deletedFields);
        $this->assertSame([], $this->success);
        $this->assertSame(['beginTransaction', 'rollBack'], $this->transaction);
        $this->assertSame(['*/*/edit', ['form_id' => 4]], $this->redirect);
    }

    public function testMissingFieldsJsonKeepsExistingFields(): void
    {
        $this->existingForms[4] = ['form_id' => 4];
        $this->existingFields = [10 => true, 11 => true];

        $this->controller(['form_id' => 4, 'form_type' => 'widget'])->execute();

        $this->assertSame([], $this->deletedFields);
        $this->assertSame([], $this->success);
        $this->assertCount(1, $this->errors);
        $this->assertSame(['beginTransaction', 'rollBack'], $this->transaction);
    }

    public function testNewFormWithoutFieldsJsonIsSaved(): void
    {
        $this->controller(['name' => 'x', 'form_type' => 'widget'])->execute();

        $this->assertSame([], $this->deletedFields);
        $this->assertSame(['The form has been saved.'], $this->success);
    }

    public function testLocalizedFailureRollsBackAndPersistsData(): void
    {
        $this->formSaveFailure = new LocalizedException(__('Title is too long'));

        $this->controller(['name' => 'x', 'form_type' => 'widget'])->execute();

        $this->assertSame(['beginTransaction', 'rollBack'], $this->transaction);
        $this->assertSame(['Title is too long'], $this->errors);
        $this->assertSame('x', $this->persisted['panth_dynamicforms_form']['name']);
        $this->assertSame(['*/*/new', []], $this->redirect);
    }

    public function testUnexpectedFieldFailureRollsBackWithGenericMessage(): void
    {
        $this->existingForms[4] = ['form_id' => 4];
        $this->fieldSaveFailure = new \RuntimeException('Duplicate entry');

        $this->controller([
            'form_id' => 4,
            'form_type' => 'widget',
            'fields_json' => json_encode([['label' => 'A', 'name' => 'a']]),
        ])->execute();

        $this->assertSame(['beginTransaction', 'rollBack'], $this->transaction);
        $this->assertSame(
            ['Something went wrong while saving the form. Check var/log/system.log for details.'],
            $this->errors
        );
        $this->assertSame(['Duplicate entry'], $this->criticalLogs);
        $this->assertSame(['*/*/edit', ['form_id' => 4]], $this->redirect);
    }
}
