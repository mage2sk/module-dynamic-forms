<?php
declare(strict_types=1);

namespace Panth\DynamicForms\Test\Unit\Cron;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Panth\DynamicForms\Cron\CleanOrphanUploads;
use Panth\DynamicForms\Helper\Data as Helper;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CleanOrphanUploadsTest extends TestCase
{
    private const OLD_A = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.pdf';
    private const OLD_B = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb.png';
    private const FRESH = 'cccccccccccccccccccccccccccccccc.pdf';
    private const REFERENCED = 'dddddddddddddddddddddddddddddddd.jpg';

    private array $deleted = [];
    private array $warnings = [];
    private array $infos = [];
    private bool $directoryExists = true;
    private array $failDelete = [];

    private function cron(?string $hours, array $entries, array $referencedValues = []): CleanOrphanUploads
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn($hours);

        $media = $this->createStub(WriteInterface::class);
        $media->method('isDirectory')->willReturnCallback(fn() => $this->directoryExists);
        $media->method('read')->willReturn(array_keys($entries));
        $media->method('isFile')->willReturnCallback(
            static fn($path) => ($entries[$path]['file'] ?? true) === true
        );
        $media->method('stat')->willReturnCallback(static fn($path) => ['mtime' => $entries[$path]['mtime']]);
        $media->method('delete')->willReturnCallback(function ($path) {
            if (in_array($path, $this->failDelete, true)) {
                throw new \RuntimeException('locked');
            }
            $this->deleted[] = $path;
            return true;
        });
        $filesystem = $this->createStub(Filesystem::class);
        $filesystem->method('getDirectoryWrite')->willReturn($media);

        $select = $this->createStub(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchCol')->willReturn($referencedValues);
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);

        $helper = $this->createStub(Helper::class);
        $helper->method('getUploadRelativePath')->willReturn('dynamicforms/uploads');

        $logger = $this->createStub(LoggerInterface::class);
        $logger->method('warning')->willReturnCallback(function ($m) {
            $this->warnings[] = $m;
        });
        $logger->method('info')->willReturnCallback(function ($m, $ctx = []) {
            $this->infos[] = [$m, $ctx];
        });

        return new CleanOrphanUploads($filesystem, $resource, $helper, $scopeConfig, $logger);
    }

    private function path(string $name): string
    {
        return 'dynamicforms/uploads/' . $name;
    }

    public function testDisabledLifetimeDoesNothing(): void
    {
        $entries = [$this->path(self::OLD_A) => ['mtime' => 1]];

        $this->assertSame(0, $this->cron('0', $entries)->execute());
        $this->assertSame(0, $this->cron(null, $entries)->execute());
        $this->assertSame([], $this->deleted);
    }

    public function testMissingUploadDirectoryDoesNothing(): void
    {
        $this->directoryExists = false;

        $this->assertSame(0, $this->cron('24', [$this->path(self::OLD_A) => ['mtime' => 1]])->execute());
        $this->assertSame([], $this->deleted);
    }

    public function testOnlyOldUnreferencedGeneratedFilesAreDeleted(): void
    {
        $old = time() - 3 * 3600;
        $entries = [
            $this->path(self::OLD_A) => ['mtime' => $old],
            $this->path(self::OLD_B) => ['mtime' => $old],
            $this->path(self::FRESH) => ['mtime' => time()],
            $this->path(self::REFERENCED) => ['mtime' => $old],
            $this->path('manual-upload.pdf') => ['mtime' => $old],
            $this->path('eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee.pdf') => ['mtime' => $old, 'file' => false],
        ];
        $referenced = ['https://shop.test/media/dynamicforms/uploads/' . self::REFERENCED . '?v=1', '', 'not a url'];

        $deleted = $this->cron('2', $entries, $referenced)->execute();

        $this->assertSame(2, $deleted);
        $this->assertSame([$this->path(self::OLD_A), $this->path(self::OLD_B)], $this->deleted);
        $this->assertSame([['Panth DynamicForms: deleted orphaned uploads', ['count' => 2]]], $this->infos);
    }

    public function testDeleteFailureIsLoggedAndOthersStillProcessed(): void
    {
        $old = time() - 10 * 3600;
        $this->failDelete = [$this->path(self::OLD_A)];
        $entries = [
            $this->path(self::OLD_A) => ['mtime' => $old],
            $this->path(self::OLD_B) => ['mtime' => $old],
        ];

        $this->assertSame(1, $this->cron('1', $entries)->execute());
        $this->assertSame([$this->path(self::OLD_B)], $this->deleted);
        $this->assertSame(['Panth DynamicForms: could not delete orphaned upload ' . self::OLD_A], $this->warnings);
    }

    public function testNothingDeletedLogsNoSummary(): void
    {
        $this->assertSame(0, $this->cron('1', [$this->path(self::FRESH) => ['mtime' => time()]])->execute());
        $this->assertSame([], $this->infos);
    }
}
