<?php
declare(strict_types=1);

namespace Panth\DynamicForms\Test\Unit\Controller\Adminhtml\Submission;

use Magento\Backend\Model\View\Result\Page;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Panth\DynamicForms\Controller\Adminhtml\Submission\Delete;
use Panth\DynamicForms\Controller\Adminhtml\Submission\Index;
use Panth\DynamicForms\Controller\Adminhtml\Submission\MassDelete;
use Panth\DynamicForms\Controller\Adminhtml\Submission\MassStatus;
use Panth\DynamicForms\Controller\Adminhtml\Submission\UpdateStatus;
use Panth\DynamicForms\Controller\Adminhtml\Submission\View;
use Panth\DynamicForms\Model\Form;
use Panth\DynamicForms\Model\FormFactory;
use Panth\DynamicForms\Model\ResourceModel\Form as FormResource;
use Panth\DynamicForms\Model\ResourceModel\Submission as SubmissionResource;
use Panth\DynamicForms\Model\ResourceModel\Submission\Collection;
use Panth\DynamicForms\Model\ResourceModel\Submission\CollectionFactory;
use Panth\DynamicForms\Model\Submission;
use Panth\DynamicForms\Model\SubmissionFactory;
use Panth\DynamicForms\Test\Unit\Controller\Adminhtml\ControllerTestCase;

class SubmissionActionsTest extends ControllerTestCase
{
    private array $deleted = [];
    private array $saved = [];
    private ?\Exception $failure = null;
    private array $json = [];

    private function submissionFactory(): SubmissionFactory
    {
        $factory = $this->createStub(SubmissionFactory::class);
        $factory->method('create')->willReturnCallback(fn() => $this->submission());
        return $factory;
    }

    private function submissionResource(array $existing = []): SubmissionResource
    {
        $resource = $this->createStub(SubmissionResource::class);
        $resource->method('load')->willReturnCallback(function (Submission $s, $id) use ($existing, $resource) {
            if (isset($existing[(int) $id])) {
                $s->setData($existing[(int) $id]);
            }
            return $resource;
        });
        $resource->method('delete')->willReturnCallback(function (Submission $s) use ($resource) {
            if ($this->failure) {
                throw $this->failure;
            }
            $this->deleted[] = $s->getId();
            return $resource;
        });
        $resource->method('save')->willReturnCallback(function (Submission $s) use ($resource) {
            if ($this->failure) {
                throw $this->failure;
            }
            $this->saved[] = $s->getData();
            return $resource;
        });
        return $resource;
    }

    private function collectionFactory(): CollectionFactory
    {
        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturn($this->collectionOf(Collection::class, []));
        return $factory;
    }

    private function jsonFactory(): JsonFactory
    {
        $this->json = [];
        $json = $this->createStub(Json::class);
        $json->method('setData')->willReturnCallback(function ($data) use ($json) {
            $this->json = array_map(static fn($v) => is_object($v) ? (string) $v : $v, $data);
            return $json;
        });
        $factory = $this->createStub(JsonFactory::class);
        $factory->method('create')->willReturn($json);
        return $factory;
    }

    public function testDeleteWithoutIdReturnsToFormGrid(): void
    {
        (new Delete($this->context(['form_id' => 3]), $this->submissionFactory(), $this->submissionResource()))->execute();

        $this->assertSame(['We cannot find a submission to delete.'], $this->errors);
        $this->assertSame(['*/*/index', ['form_id' => 3]], $this->redirect);
    }

    public function testDeleteOfMissingSubmission(): void
    {
        (new Delete($this->context(['submission_id' => 8]), $this->submissionFactory(), $this->submissionResource()))->execute();

        $this->assertSame(['This submission no longer exists.'], $this->errors);
        $this->assertSame(['*/*/index', ['form_id' => 0]], $this->redirect);
    }

    public function testDeleteUsesTheSubmissionsOwnFormForTheRedirect(): void
    {
        $resource = $this->submissionResource([8 => ['submission_id' => 8, 'form_id' => 5]]);

        (new Delete($this->context(['submission_id' => 8, 'form_id' => 1]), $this->submissionFactory(), $resource))->execute();

        $this->assertSame([8], $this->deleted);
        $this->assertSame(['The submission has been deleted.'], $this->success);
        $this->assertSame(['*/*/index', ['form_id' => 5]], $this->redirect);
    }

    public function testDeleteFailureIsReported(): void
    {
        $this->failure = new \RuntimeException('fk');
        $resource = $this->submissionResource([8 => ['submission_id' => 8, 'form_id' => 5]]);

        (new Delete($this->context(['submission_id' => 8]), $this->submissionFactory(), $resource))->execute();

        $this->assertSame(['fk'], $this->errors);
    }

    public function testMassDeleteInfersFormFromFirstSubmission(): void
    {
        $collection = $this->collectionOf(Collection::class, [
            $this->submission(['submission_id' => 1, 'form_id' => 6]),
            $this->submission(['submission_id' => 2, 'form_id' => 7]),
        ]);

        (new MassDelete($this->context(), $this->filterReturning($collection), $this->collectionFactory(), $this->submissionResource()))
            ->execute();

        $this->assertSame([1, 2], $this->deleted);
        $this->assertSame(['A total of 2 submission(s) have been deleted.'], $this->success);
        $this->assertSame(['*/*/index', ['form_id' => 6]], $this->redirect);
    }

    public function testMassDeleteKeepsRequestedFormAndReportsFailure(): void
    {
        $this->failure = new \RuntimeException('x');
        $collection = $this->collectionOf(Collection::class, [$this->submission(['submission_id' => 1, 'form_id' => 6])]);

        (new MassDelete($this->context(['form_id' => 2]), $this->filterReturning($collection), $this->collectionFactory(), $this->submissionResource()))
            ->execute();

        $this->assertSame(['x'], $this->errors);
        $this->assertSame(['*/*/index', ['form_id' => 2]], $this->redirect);
    }

    public function testMassStatusValidatesAndUpdates(): void
    {
        $collection = $this->collectionOf(Collection::class, [$this->submission(['submission_id' => 1, 'status' => 'new'])]);

        (new MassStatus($this->context(['status' => 'spam']), $this->filterReturning($collection), $this->collectionFactory(), $this->submissionResource()))
            ->execute();
        $this->assertSame(['Invalid status.'], $this->errors);
        $this->assertSame([], $this->saved);

        (new MassStatus($this->context(['status' => 'replied']), $this->filterReturning($collection), $this->collectionFactory(), $this->submissionResource()))
            ->execute();
        $this->assertSame('replied', $this->saved[0]['status']);
        $this->assertSame(['A total of 1 submission(s) have been updated.'], $this->success);
        $this->assertSame(['*/*/', []], $this->redirect);
    }

    public function testMassStatusFailureIsReported(): void
    {
        $this->failure = new \RuntimeException('down');
        $collection = $this->collectionOf(Collection::class, [$this->submission(['submission_id' => 1])]);

        (new MassStatus($this->context(['status' => 'closed']), $this->filterReturning($collection), $this->collectionFactory(), $this->submissionResource()))
            ->execute();

        $this->assertSame(['down'], $this->errors);
    }

    public function testUpdateStatusRejectsBadParameters(): void
    {
        foreach ([['status' => 'read'], ['submission_id' => 1, 'status' => 'bogus']] as $params) {
            (new UpdateStatus($this->context($params), $this->jsonFactory(), $this->submissionFactory(), $this->submissionResource()))
                ->execute();
            $this->assertSame(['success' => false, 'message' => 'Invalid parameters.'], $this->json);
        }
    }

    public function testUpdateStatusUnknownSubmission(): void
    {
        (new UpdateStatus($this->context(['submission_id' => 4, 'status' => 'read']), $this->jsonFactory(), $this->submissionFactory(), $this->submissionResource()))
            ->execute();

        $this->assertSame(['success' => false, 'message' => 'Submission not found.'], $this->json);
    }

    public function testUpdateStatusSavesStatusAndOptionalNotes(): void
    {
        $resource = $this->submissionResource([4 => ['submission_id' => 4, 'status' => 'new', 'admin_notes' => 'old']]);

        (new UpdateStatus($this->context(['submission_id' => 4, 'status' => 'read']), $this->jsonFactory(), $this->submissionFactory(), $resource))
            ->execute();
        $this->assertSame(['success' => true, 'message' => 'Status updated successfully.'], $this->json);
        $this->assertSame('read', $this->saved[0]['status']);
        $this->assertSame('old', $this->saved[0]['admin_notes']);

        (new UpdateStatus(
            $this->context(['submission_id' => 4, 'status' => 'closed', 'admin_notes' => '']),
            $this->jsonFactory(),
            $this->submissionFactory(),
            $resource
        ))->execute();
        $this->assertSame('', $this->saved[1]['admin_notes']);
    }

    public function testUpdateStatusFailureReturnsMessage(): void
    {
        $this->failure = new \RuntimeException('deadlock');
        $resource = $this->submissionResource([4 => ['submission_id' => 4]]);

        (new UpdateStatus($this->context(['submission_id' => 4, 'status' => 'read']), $this->jsonFactory(), $this->submissionFactory(), $resource))
            ->execute();

        $this->assertSame(['success' => false, 'message' => 'deadlock'], $this->json);
    }

    public function testViewOfMissingSubmissionRedirects(): void
    {
        $result = (new View($this->context(['submission_id' => 3]), $this->pageFactory(), $this->submissionFactory(), $this->submissionResource()))
            ->execute();

        $this->assertInstanceOf(Redirect::class, $result);
        $this->assertSame(['This submission no longer exists.'], $this->errors);
        $this->assertSame(['*/*/index', []], $this->redirect);
    }

    public function testViewMarksNewSubmissionAsRead(): void
    {
        $resource = $this->submissionResource([3 => ['submission_id' => 3, 'status' => 'new']]);

        $result = (new View($this->context(['submission_id' => 3]), $this->pageFactory(), $this->submissionFactory(), $resource))->execute();

        $this->assertInstanceOf(Page::class, $result);
        $this->assertSame('read', $this->saved[0]['status']);
        $this->assertSame(['Submission #3'], $this->titles);
        $this->assertSame('Panth_DynamicForms::submission', $this->activeMenu);
    }

    public function testViewLeavesOtherStatusesUntouched(): void
    {
        $resource = $this->submissionResource([3 => ['submission_id' => 3, 'status' => 'replied']]);

        (new View($this->context(['submission_id' => 3]), $this->pageFactory(), $this->submissionFactory(), $resource))->execute();

        $this->assertSame([], $this->saved);
    }

    public function testIndexTitleDependsOnForm(): void
    {
        $formFactory = $this->createStub(FormFactory::class);
        $formFactory->method('create')->willReturnCallback(fn() => $this->form());
        $formResource = $this->createStub(FormResource::class);
        $formResource->method('load')->willReturnCallback(function (Form $form, $id) use ($formResource) {
            if ((int) $id === 2) {
                $form->setData(['form_id' => 2, 'name' => 'Quote']);
            }
            return $formResource;
        });

        (new Index($this->context(['form_id' => 2]), $this->pageFactory(), $formFactory, $formResource))->execute();
        $this->assertSame(['Submissions for: Quote'], $this->titles);

        (new Index($this->context(['form_id' => 99]), $this->pageFactory(), $formFactory, $formResource))->execute();
        $this->assertSame(['Form Submissions'], $this->titles);

        (new Index($this->context(), $this->pageFactory(), $formFactory, $formResource))->execute();
        $this->assertSame(['Form Submissions'], $this->titles);
    }
}
