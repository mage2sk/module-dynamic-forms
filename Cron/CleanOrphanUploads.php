<?php
declare(strict_types=1);

namespace Panth\DynamicForms\Cron;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Filesystem;
use Panth\DynamicForms\Helper\Data as Helper;
use Psr\Log\LoggerInterface;

class CleanOrphanUploads
{
    private const XML_PATH_LIFETIME = 'panth_dynamicforms/general/orphan_upload_lifetime';
    private const FILE_PATTERN = '/^[a-f0-9]{32}\.[a-z0-9]+$/';

    private Filesystem $filesystem;
    private ResourceConnection $resource;
    private Helper $helper;
    private ScopeConfigInterface $scopeConfig;
    private LoggerInterface $logger;

    public function __construct(
        Filesystem $filesystem,
        ResourceConnection $resource,
        Helper $helper,
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger
    ) {
        $this->filesystem = $filesystem;
        $this->resource = $resource;
        $this->helper = $helper;
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
    }

    public function execute(): int
    {
        $hours = (int) $this->scopeConfig->getValue(self::XML_PATH_LIFETIME);
        if ($hours <= 0) {
            return 0;
        }

        $media = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $path = $this->helper->getUploadRelativePath();
        if (!$media->isDirectory($path)) {
            return 0;
        }

        $referenced = $this->getReferencedFiles();
        $cutoff = time() - $hours * 3600;
        $deleted = 0;

        foreach ($media->read($path) as $entry) {
            $name = basename((string) $entry);
            if (!preg_match(self::FILE_PATTERN, $name) || isset($referenced[$name]) || !$media->isFile($entry)) {
                continue;
            }

            $stat = $media->stat($entry);
            if ((int) ($stat['mtime'] ?? time()) > $cutoff) {
                continue;
            }

            try {
                $media->delete($entry);
                $deleted++;
            } catch (\Exception $e) {
                $this->logger->warning('Panth DynamicForms: could not delete orphaned upload ' . $name);
            }
        }

        if ($deleted > 0) {
            $this->logger->info('Panth DynamicForms: deleted orphaned uploads', ['count' => $deleted]);
        }

        return $deleted;
    }

    private function getReferencedFiles(): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName('panth_dynamic_form_submission_value'), ['value'])
            ->where('field_type = ?', 'file');

        $names = [];
        foreach ($connection->fetchCol($select) as $value) {
            $name = basename((string) parse_url((string) $value, PHP_URL_PATH));
            if ($name !== '') {
                $names[$name] = true;
            }
        }

        return $names;
    }
}
