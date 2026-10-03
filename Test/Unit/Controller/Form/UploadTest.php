<?php
declare(strict_types=1);

namespace Panth\DynamicForms\Test\Unit\Controller\Form;

use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\MediaStorage\Model\File\Uploader;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Magento\Store\Model\StoreManagerInterface;
use Panth\Core\Security\UploadExtensionPolicy;
use Panth\DynamicForms\Controller\Form\Upload;
use Panth\DynamicForms\Helper\Data as Helper;
use Panth\DynamicForms\Model\Form;
use Panth\DynamicForms\Model\FormFactory;
use Panth\DynamicForms\Model\ResourceModel\Field\Collection as FieldCollection;
use Panth\DynamicForms\Model\ResourceModel\Field\CollectionFactory as FieldCollectionFactory;
use Panth\DynamicForms\Model\ResourceModel\Form as FormResource;
use Panth\DynamicForms\Test\Unit\Support\ModelBuilderTrait;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class UploadTest extends TestCase
{
    use ModelBuilderTrait;

    private const PNG_BYTES = "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x02\x00\x00\x00\x90wS\xde";

    private array $params = ['form_id' => 5, 'field_name' => 'cv'];
    private bool $enabled = true;
    private array $formData = ['form_id' => 5, 'is_active' => 1];
    private array $fileFields = [];
    private array $fieldFilters = [];
    private array $fileInfo = [];
    private string $extension = 'png';
    private int $fileSize = 100;
    private int $maxSize = 1024;
    private ?\Exception $validateFailure = null;
    private ?\Exception $policyFailure = null;
    private $saveResult = null;
    private array $savedTo = [];
    private bool $directoryExists = false;
    private array $createdDirs = [];
    private array $tempFiles = [];
    private array $logged = [];

    protected function setUp(): void
    {
        $this->fileFields = [$this->field(['field_id' => 1])];
        $this->fileInfo = ['name' => 'photo.png', 'tmp_name' => $this->tempFile(self::PNG_BYTES)];
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    private function tempFile(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'dfup');
        file_put_contents($path, $content);
        $this->tempFiles[] = $path;
        return $path;
    }

    private function execute(): array
    {
        $request = $this->createStub(Http::class);
        $request->method('getParam')->willReturnCallback(
            fn($key, $default = null) => $this->params[$key] ?? $default
        );

        $captured = [];
        $json = $this->createStub(Json::class);
        $json->method('setData')->willReturnCallback(function ($data) use ($json, &$captured) {
            $captured = $data;
            return $json;
        });
        $jsonFactory = $this->createStub(JsonFactory::class);
        $jsonFactory->method('create')->willReturn($json);

        $media = $this->createStub(WriteInterface::class);
        $media->method('isDirectory')->willReturnCallback(fn() => $this->directoryExists);
        $media->method('create')->willReturnCallback(function ($path) {
            $this->createdDirs[] = $path;
            return true;
        });
        $media->method('getAbsolutePath')->willReturnCallback(static fn($path) => '/media/' . $path);
        $filesystem = $this->createStub(Filesystem::class);
        $filesystem->method('getDirectoryWrite')->willReturn($media);

        $uploader = $this->createStub(Uploader::class);
        $uploader->method('validateFile')->willReturnCallback(function () {
            if ($this->validateFailure) {
                throw $this->validateFailure;
            }
            return $this->fileInfo;
        });
        $uploader->method('getFileExtension')->willReturnCallback(fn() => $this->extension);
        $uploader->method('getFileSize')->willReturnCallback(fn() => $this->fileSize);
        $uploader->method('save')->willReturnCallback(function ($dir, $name) {
            $this->savedTo = [$dir, $name];
            return $this->saveResult ?? ['file' => $name, 'size' => 321];
        });
        $uploaderFactory = $this->createStub(UploaderFactory::class);
        $uploaderFactory->method('create')->willReturn($uploader);

        $helper = $this->createStub(Helper::class);
        $helper->method('isEnabled')->willReturnCallback(fn() => $this->enabled);
        $helper->method('isFormAvailableInStore')->willReturn(true);
        $helper->method('getAllowedExtensions')->willReturn(['png', 'pdf', 'svg']);
        $helper->method('getMaxFileSize')->willReturnCallback(fn() => $this->maxSize);
        $helper->method('getUploadRelativePath')->willReturn('dynamicforms/uploads');
        $helper->method('getFileUrl')->willReturnCallback(static fn($f) => 'https://shop.test/media/' . $f);

        $logger = $this->createStub(LoggerInterface::class);
        $logger->method('error')->willReturnCallback(function ($m) {
            $this->logged[] = $m;
        });

        $policy = $this->createStub(UploadExtensionPolicy::class);
        $policy->method('assertSafeExtension')->willReturnCallback(function () {
            if ($this->policyFailure) {
                throw $this->policyFailure;
            }
        });

        $formFactory = $this->createStub(FormFactory::class);
        $formFactory->method('create')->willReturnCallback(fn() => $this->form());
        $formResource = $this->createStub(FormResource::class);
        $formResource->method('load')->willReturnCallback(function (Form $form, $id) use ($formResource) {
            if ((int) $id === (int) $this->formData['form_id']) {
                $form->setData($this->formData);
            }
            return $formResource;
        });

        $this->fieldFilters = [];
        $collection = $this->collectionOf(FieldCollection::class, $this->fileFields, $this->fieldFilters);
        $collectionFactory = $this->createStub(FieldCollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);

        $controller = new Upload(
            $request,
            $jsonFactory,
            $filesystem,
            $uploaderFactory,
            $this->createStub(StoreManagerInterface::class),
            $helper,
            $logger,
            $policy,
            $formFactory,
            $formResource,
            $collectionFactory
        );

        $this->assertSame($json, $controller->execute());

        return array_map(static fn($v) => $v instanceof \Magento\Framework\Phrase ? (string) $v : $v, $captured);
    }

    public function testDisabledModuleRejectsUploads(): void
    {
        $this->enabled = false;

        $this->assertSame(
            ['success' => false, 'message' => 'Form uploads are currently disabled.'],
            $this->execute()
        );
    }

    public function testUploadRequiresAnActiveFormWithThatFileField(): void
    {
        $expected = ['success' => false, 'message' => 'File uploads are not accepted for this form.'];

        $this->params = ['form_id' => 0, 'field_name' => 'cv'];
        $this->assertSame($expected, $this->execute());

        $this->params = ['form_id' => 5, 'field_name' => ''];
        $this->assertSame($expected, $this->execute());

        $this->params = ['form_id' => 6, 'field_name' => 'cv'];
        $this->assertSame($expected, $this->execute());

        $this->params = ['form_id' => 5, 'field_name' => 'cv'];
        $this->formData['is_active'] = 0;
        $this->assertSame($expected, $this->execute());

        $this->formData['is_active'] = 1;
        $this->fileFields = [];
        $this->assertSame($expected, $this->execute());
        $this->assertSame(
            ['form_id' => 5, 'name' => 'cv', 'field_type' => 'file', 'is_active' => 1],
            $this->fieldFilters
        );
        $this->assertSame([], $this->savedTo);
    }

    public function testValidPngIsStoredUnderRandomName(): void
    {
        $result = $this->execute();

        $this->assertTrue($result['success']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}\.png$/', $result['file']);
        $this->assertSame('https://shop.test/media/' . $result['file'], $result['url']);
        $this->assertSame('photo.png', $result['name']);
        $this->assertSame(321, $result['size']);
        $this->assertSame(['/media/dynamicforms/uploads', $result['file']], $this->savedTo);
        $this->assertSame(['dynamicforms/uploads'], $this->createdDirs);
    }

    public function testExistingDirectoryIsNotRecreatedAndNameFallsBackToStoredName(): void
    {
        $this->directoryExists = true;
        $this->fileInfo['name'] = null;

        $result = $this->execute();

        $this->assertSame([], $this->createdDirs);
        $this->assertSame($result['file'], $result['name']);
    }

    public function testBlockedOrDisallowedExtensionIsRejected(): void
    {
        $this->extension = 'svg';
        $this->assertSame(
            'File type is not allowed. Allowed types: png, pdf, svg',
            $this->execute()['message']
        );

        $this->extension = 'exe';
        $this->assertFalse($this->execute()['success']);
        $this->assertSame([], $this->savedTo);
    }

    public function testOversizedFileIsRejected(): void
    {
        $this->maxSize = 1024 * 1024;
        $this->fileSize = 2 * 1024 * 1024;

        $this->assertSame(
            ['success' => false, 'message' => 'File size exceeds the maximum allowed size of 1 MB.'],
            $this->execute()
        );
    }

    public function testContentMustMatchExtension(): void
    {
        $this->fileInfo['tmp_name'] = $this->tempFile("just some plain text\n");

        $this->assertSame(
            ['success' => false, 'message' => 'The file content does not match its extension.'],
            $this->execute()
        );
    }

    public function testMarkupContentIsRejectedEvenForUnmappedExtensions(): void
    {
        $this->extension = 'pdf';
        $this->fileInfo['tmp_name'] = $this->tempFile('<html><body><p>x</p></body></html>');

        $this->assertSame('The file content does not match its extension.', $this->execute()['message']);
    }

    public function testMissingTempFileIsRejected(): void
    {
        $this->fileInfo['tmp_name'] = '';

        $this->assertSame('The file content does not match its extension.', $this->execute()['message']);
    }

    public function testFailedSaveReturnsError(): void
    {
        $this->saveResult = ['size' => 1];

        $this->assertSame(
            ['success' => false, 'message' => 'File upload failed. Please try again.'],
            $this->execute()
        );
    }

    public function testExceptionsAreMappedToFriendlyMessages(): void
    {
        $this->validateFailure = new \Exception('Disallowed file type.');
        $this->assertSame('File type is not allowed. Allowed types: png, pdf, svg', $this->execute()['message']);

        $this->validateFailure = new \Exception('$_FILES array is empty / file was not uploaded');
        $this->assertSame('No file was uploaded. Please select a file and try again.', $this->execute()['message']);

        $this->validateFailure = new \Exception('disk full');
        $this->assertSame('An error occurred during file upload.', $this->execute()['message']);

        $this->assertSame(
            [
                'DynamicForms file upload error: Disallowed file type.',
                'DynamicForms file upload error: $_FILES array is empty / file was not uploaded',
                'DynamicForms file upload error: disk full',
            ],
            $this->logged
        );
    }

    public function testHardDeniedOriginalNameIsRejectedByPolicy(): void
    {
        $this->policyFailure = new \Magento\Framework\Exception\LocalizedException(__('Blocked extension'));

        $this->assertSame('File type is not allowed. Allowed types: png, pdf, svg', $this->execute()['message']);
        $this->assertSame([], $this->savedTo);
    }
}
