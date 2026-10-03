<?php
declare(strict_types=1);

namespace Panth\DynamicForms\Test\Unit\Block\Adminhtml\Submission;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use Panth\DynamicForms\Block\Adminhtml\Submission\View;
use Panth\DynamicForms\Model\Config\Source\FormStatus;
use Panth\DynamicForms\Model\Form;
use Panth\DynamicForms\Model\FormFactory;
use Panth\DynamicForms\Model\ResourceModel\Form as FormResource;
use Panth\DynamicForms\Model\ResourceModel\Submission as SubmissionResource;
use Panth\DynamicForms\Model\ResourceModel\SubmissionValue\Collection;
use Panth\DynamicForms\Model\ResourceModel\SubmissionValue\CollectionFactory;
use Panth\DynamicForms\Model\Submission;
use Panth\DynamicForms\Model\SubmissionFactory;
use Panth\DynamicForms\Test\Unit\Support\BlockBuilderTrait;
use Panth\DynamicForms\Test\Unit\Support\ModelBuilderTrait;
use PHPUnit\Framework\TestCase;

class ViewTest extends TestCase
{
    use BlockBuilderTrait;
    use ModelBuilderTrait;

    private int $submissionLoads = 0;
    private array $valueFilters = [];

    private function block(): View
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturn('12');

        $submissionFactory = $this->createStub(SubmissionFactory::class);
        $submissionFactory->method('create')->willReturnCallback(fn() => $this->submission());
        $submissionResource = $this->createStub(SubmissionResource::class);
        $submissionResource->method('load')->willReturnCallback(function (Submission $s, $id) use ($submissionResource) {
            $this->submissionLoads++;
            if ((int) $id === 12) {
                $s->setData(['submission_id' => 12, 'form_id' => 3]);
            }
            return $submissionResource;
        });

        $formFactory = $this->createStub(FormFactory::class);
        $formFactory->method('create')->willReturnCallback(fn() => $this->form());
        $formResource = $this->createStub(FormResource::class);
        $formResource->method('load')->willReturnCallback(function (Form $f, $id) use ($formResource) {
            $f->setData(['form_id' => $id, 'name' => 'Form ' . $id]);
            return $formResource;
        });

        $this->valueFilters = [];
        $values = [
            $this->submissionValue(['value_id' => 1, 'field_label' => 'Name', 'value' => 'Ann']),
            $this->submissionValue(['value_id' => 2, 'field_label' => 'Mail', 'value' => 'a@x.test']),
        ];
        $collection = $this->collectionOf(Collection::class, $values, $this->valueFilters);
        $valueFactory = $this->createStub(CollectionFactory::class);
        $valueFactory->method('create')->willReturn($collection);

        $url = $this->createStub(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(
            static fn($route, $params = []) => $route . ($params ? '?' . http_build_query($params) : '')
        );

        return $this->buildBlock(View::class, [
            '_request' => $request,
            '_urlBuilder' => $url,
            'submissionFactory' => $submissionFactory,
            'submissionResource' => $submissionResource,
            'formFactory' => $formFactory,
            'formResource' => $formResource,
            'valueCollectionFactory' => $valueFactory,
            'formStatus' => new FormStatus(),
        ]);
    }

    public function testSubmissionIsLoadedOnceFromRequest(): void
    {
        $block = $this->block();

        $this->assertSame(12, $block->getSubmission()->getId());
        $this->assertSame($block->getSubmission(), $block->getSubmission());
        $this->assertSame(1, $this->submissionLoads);
    }

    public function testFormAndValuesBelongToTheSubmission(): void
    {
        $block = $this->block();

        $this->assertSame('Form 3', $block->getForm()->getData('name'));
        $this->assertSame(
            [
                ['value_id' => 1, 'field_label' => 'Name', 'value' => 'Ann'],
                ['value_id' => 2, 'field_label' => 'Mail', 'value' => 'a@x.test'],
            ],
            $block->getSubmissionValues()
        );
        $this->assertSame(['submission_id' => 12], $this->valueFilters);
    }

    public function testStatusHelpers(): void
    {
        $block = $this->block();

        $this->assertCount(4, $block->getStatusOptions());
        $this->assertSame('#79a22e', $block->getStatusColor('replied'));
        $this->assertSame('#333333', $block->getStatusColor('spam'));
    }

    public function testUrls(): void
    {
        $block = $this->block();

        $this->assertSame('panth_dynamicforms/submission/updateStatus', $block->getUpdateStatusUrl());
        $this->assertSame('panth_dynamicforms/submission/index?form_id=3', $block->getBackUrl());
        $this->assertSame(
            'panth_dynamicforms/submission/delete?submission_id=12&form_id=3',
            $block->getDeleteUrl()
        );
    }
}
