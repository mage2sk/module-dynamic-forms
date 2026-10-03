<?php
declare(strict_types=1);

namespace Panth\DynamicForms\Test\Unit\Controller\Adminhtml\Form;

use Magento\Backend\Model\View\Result\Page;
use Magento\Backend\Model\View\Result\Redirect;
use Panth\DynamicForms\Controller\Adminhtml\Form\Delete;
use Panth\DynamicForms\Controller\Adminhtml\Form\Edit;
use Panth\DynamicForms\Controller\Adminhtml\Form\MassDelete;
use Panth\DynamicForms\Controller\Adminhtml\Form\MassStatus;
use Panth\DynamicForms\Model\Form;
use Panth\DynamicForms\Model\FormFactory;
use Panth\DynamicForms\Model\ResourceModel\Form as FormResource;
use Panth\DynamicForms\Model\ResourceModel\Form\Collection;
use Panth\DynamicForms\Model\ResourceModel\Form\CollectionFactory;
use Panth\DynamicForms\Test\Unit\Controller\Adminhtml\ControllerTestCase;

class SimpleActionsTest extends ControllerTestCase
{
    private array $deleted = [];
    private array $saved = [];
    private ?\Exception $resourceFailure = null;

    private function formFactory(): FormFactory
    {
        $factory = $this->createStub(FormFactory::class);
        $factory->method('create')->willReturnCallback(fn() => $this->form());
        return $factory;
    }

    private function formResource(array $existing = []): FormResource
    {
        $resource = $this->createStub(FormResource::class);
        $resource->method('load')->willReturnCallback(function (Form $form, $id) use ($existing, $resource) {
            if (isset($existing[(int) $id])) {
                $form->setData($existing[(int) $id]);
            }
            return $resource;
        });
        $resource->method('delete')->willReturnCallback(function (Form $form) use ($resource) {
            if ($this->resourceFailure) {
                throw $this->resourceFailure;
            }
            $this->deleted[] = $form->getId();
            return $resource;
        });
        $resource->method('save')->willReturnCallback(function (Form $form) use ($resource) {
            if ($this->resourceFailure) {
                throw $this->resourceFailure;
            }
            $this->saved[] = [$form->getId(), $form->getData('is_active')];
            return $resource;
        });
        return $resource;
    }

    private function collectionFactory(array $forms): CollectionFactory
    {
        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturn($this->collectionOf(Collection::class, $forms));
        return $factory;
    }

    public function testDeleteWithoutIdShowsError(): void
    {
        $result = (new Delete($this->context(), $this->formFactory(), $this->formResource()))->execute();

        $this->assertInstanceOf(Redirect::class, $result);
        $this->assertSame(['We cannot find a form to delete.'], $this->errors);
        $this->assertSame(['*/*/', []], $this->redirect);
    }

    public function testDeleteOfMissingFormShowsError(): void
    {
        (new Delete($this->context(['form_id' => 4]), $this->formFactory(), $this->formResource()))->execute();

        $this->assertSame(['This form no longer exists.'], $this->errors);
        $this->assertSame([], $this->deleted);
    }

    public function testDeleteRemovesForm(): void
    {
        $resource = $this->formResource([4 => ['form_id' => 4]]);
        (new Delete($this->context(['form_id' => '4']), $this->formFactory(), $resource))->execute();

        $this->assertSame([4], $this->deleted);
        $this->assertSame(['The form has been deleted.'], $this->success);
        $this->assertSame(['*/*/', []], $this->redirect);
    }

    public function testDeleteFailureIsReported(): void
    {
        $this->resourceFailure = new \RuntimeException('constraint');
        $resource = $this->formResource([4 => ['form_id' => 4]]);

        (new Delete($this->context(['form_id' => 4]), $this->formFactory(), $resource))->execute();

        $this->assertSame(['constraint'], $this->errors);
        $this->assertSame([], $this->success);
    }

    public function testMassDeleteDeletesEverySelectedForm(): void
    {
        $collection = $this->collectionOf(Collection::class, [$this->form(['form_id' => 1]), $this->form(['form_id' => 2])]);
        $controller = new MassDelete(
            $this->context(),
            $this->filterReturning($collection),
            $this->collectionFactory([]),
            $this->formResource()
        );

        $controller->execute();

        $this->assertSame([1, 2], $this->deleted);
        $this->assertSame(['A total of 2 form(s) have been deleted.'], $this->success);
    }

    public function testMassDeleteFailureIsReported(): void
    {
        $this->resourceFailure = new \RuntimeException('nope');
        $collection = $this->collectionOf(Collection::class, [$this->form(['form_id' => 1])]);

        (new MassDelete($this->context(), $this->filterReturning($collection), $this->collectionFactory([]), $this->formResource()))
            ->execute();

        $this->assertSame(['nope'], $this->errors);
        $this->assertSame(['*/*/', []], $this->redirect);
    }

    public function testMassStatusRejectsUnknownStatus(): void
    {
        $collection = $this->collectionOf(Collection::class, [$this->form(['form_id' => 1])]);

        foreach ([[], ['status' => '2'], ['status' => 'yes']] as $params) {
            (new MassStatus($this->context($params), $this->filterReturning($collection), $this->collectionFactory([]), $this->formResource()))
                ->execute();
            $this->assertSame(['Invalid status.'], $this->errors);
        }
        $this->assertSame([], $this->saved);
    }

    public function testMassStatusEnablesAndDisables(): void
    {
        $forms = [$this->form(['form_id' => 1, 'is_active' => 0]), $this->form(['form_id' => 2, 'is_active' => 0])];
        $collection = $this->collectionOf(Collection::class, $forms);

        (new MassStatus($this->context(['status' => '1']), $this->filterReturning($collection), $this->collectionFactory([]), $this->formResource()))
            ->execute();
        $this->assertSame([[1, 1], [2, 1]], $this->saved);
        $this->assertSame(['A total of 2 form(s) have been enabled.'], $this->success);

        $this->saved = [];
        (new MassStatus($this->context(['status' => '0']), $this->filterReturning($collection), $this->collectionFactory([]), $this->formResource()))
            ->execute();
        $this->assertSame([[1, 0], [2, 0]], $this->saved);
        $this->assertSame(['A total of 2 form(s) have been disabled.'], $this->success);
    }

    public function testMassStatusFailureIsReported(): void
    {
        $this->resourceFailure = new \RuntimeException('locked');
        $collection = $this->collectionOf(Collection::class, [$this->form(['form_id' => 1])]);

        (new MassStatus($this->context(['status' => '1']), $this->filterReturning($collection), $this->collectionFactory([]), $this->formResource()))
            ->execute();

        $this->assertSame(['locked'], $this->errors);
    }

    public function testEditOfMissingFormRedirectsBack(): void
    {
        $result = (new Edit($this->context(['form_id' => 9]), $this->pageFactory(), $this->formFactory(), $this->formResource()))
            ->execute();

        $this->assertInstanceOf(Redirect::class, $result);
        $this->assertSame(['This form no longer exists.'], $this->errors);
        $this->assertSame(['*/*/', []], $this->redirect);
    }

    public function testEditTitles(): void
    {
        $resource = $this->formResource([9 => ['form_id' => 9, 'name' => 'Contact']]);

        $result = (new Edit($this->context(['form_id' => 9]), $this->pageFactory(), $this->formFactory(), $resource))->execute();
        $this->assertInstanceOf(Page::class, $result);
        $this->assertSame(['Edit Form: Contact'], $this->titles);
        $this->assertSame('Panth_DynamicForms::form', $this->activeMenu);

        (new Edit($this->context(), $this->pageFactory(), $this->formFactory(), $resource))->execute();
        $this->assertSame(['New Form'], $this->titles);
    }
}
