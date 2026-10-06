<?php
declare(strict_types=1);

namespace Panth\DynamicForms\Test\Unit\Controller\Adminhtml\Submission;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\ResponseInterface;
use Panth\DynamicForms\Controller\Adminhtml\Submission\Download;
use Panth\DynamicForms\Helper\Data as Helper;
use Panth\DynamicForms\Model\ResourceModel\SubmissionValue as SubmissionValueResource;
use Panth\DynamicForms\Model\SubmissionValue;
use Panth\DynamicForms\Model\SubmissionValueFactory;
use Panth\DynamicForms\Test\Unit\Controller\Adminhtml\ControllerTestCase;

class DownloadTest extends ControllerTestCase
{
    private array $rows = [];
    private array $files = [];
    private array $sent = [];
    private array $loads = [];

    private function download(array $params)
    {
        $this->sent = [];
        $this->loads = [];

        $factory = $this->createStub(SubmissionValueFactory::class);
        $factory->method('create')->willReturnCallback(fn() => $this->submissionValue());
        $resource = $this->createStub(SubmissionValueResource::class);
        $resource->method('load')->willReturnCallback(function (SubmissionValue $value, $id) use ($resource) {
            $this->loads[] = (int) $id;
            if (isset($this->rows[(int) $id])) {
                $value->setData($this->rows[(int) $id]);
            }
            return $resource;
        });

        $helper = $this->createStub(Helper::class);
        $helper->method('getUploadRoot')->willReturn('var');
        $helper->method('getUploadedFilePath')->willReturnCallback(
            fn($name) => in_array($name, $this->files, true) ? 'dynamicforms/uploads/' . $name : null
        );

        $response = $this->createStub(ResponseInterface::class);
        $fileFactory = $this->createStub(FileFactory::class);
        $fileFactory->method('create')->willReturnCallback(function (...$args) use ($response) {
            $this->sent[] = array_slice($args, 0, 4);
            return $response;
        });

        $controller = new Download($this->context($params), $fileFactory, $factory, $resource, $helper);

        return [$controller->execute(), $response];
    }

    public function testIsGetOnlyAndUsesTheSubmissionAcl(): void
    {
        $this->assertTrue(is_subclass_of(Download::class, HttpGetActionInterface::class));
        $this->assertSame('Panth_DynamicForms::submission', Download::ADMIN_RESOURCE);
    }

    public function testStoredFileIsStreamedFromVar(): void
    {
        $this->rows = [4 => ['value_id' => 4, 'field_type' => 'file', 'value' => 'abc.pdf']];
        $this->files = ['abc.pdf'];

        [$result, $response] = $this->download(['value_id' => '4']);

        $this->assertSame($response, $result);
        $this->assertSame(
            [['abc.pdf', ['type' => 'filename', 'value' => 'dynamicforms/uploads/abc.pdf'], 'var', 'application/octet-stream']],
            $this->sent
        );
        $this->assertSame([], $this->errors);
    }

    public function testMissingWrongTypeOrUnknownFilesRedirectWithError(): void
    {
        $this->rows = [
            5 => ['value_id' => 5, 'field_type' => 'text', 'value' => 'abc.pdf'],
            6 => ['value_id' => 6, 'field_type' => 'file', 'value' => 'gone.pdf'],
        ];
        $this->files = ['abc.pdf'];

        foreach (['5', '6', '99'] as $id) {
            $this->download(['value_id' => $id]);
            $this->assertSame([], $this->sent);
            $this->assertSame(['*/*/index', []], $this->redirect);
            $this->assertSame(['The requested file could not be found.'], $this->errors);
        }

        $this->download([]);
        $this->assertSame([], $this->loads);
        $this->assertSame([], $this->sent);
    }
}
