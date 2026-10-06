<?php
declare(strict_types=1);

namespace Panth\DynamicForms\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Sql\Expression;
use Magento\Store\Model\ScopeInterface;

class AutoReplyGuard
{
    private const XML_PATH_ENABLED = 'panth_dynamicforms/email/autoreply_enabled';
    private const XML_PATH_MAX_PER_RECIPIENT = 'panth_dynamicforms/email/autoreply_max_per_recipient';
    private const XML_PATH_MAX_PER_IP = 'panth_dynamicforms/email/autoreply_max_per_ip';
    private const DEFAULT_MAX_PER_RECIPIENT = 2;
    private const DEFAULT_MAX_PER_IP = 5;

    private ScopeConfigInterface $scopeConfig;
    private ResourceConnection $resource;

    public function __construct(ScopeConfigInterface $scopeConfig, ResourceConnection $resource)
    {
        $this->scopeConfig = $scopeConfig;
        $this->resource = $resource;
    }

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isAllowed(string $email, string $clientIp, ?int $storeId = null): bool
    {
        $email = trim($email);
        if ($email === '' || !$this->isEnabled($storeId)) {
            return false;
        }

        $maxPerRecipient = $this->readLimit(self::XML_PATH_MAX_PER_RECIPIENT, self::DEFAULT_MAX_PER_RECIPIENT, $storeId);
        if ($this->countRecent('customer_email', $email) > $maxPerRecipient) {
            return false;
        }

        $clientIp = trim($clientIp);
        if ($clientIp === '') {
            return true;
        }

        $maxPerIp = $this->readLimit(self::XML_PATH_MAX_PER_IP, self::DEFAULT_MAX_PER_IP, $storeId);

        return $this->countRecent('customer_ip', $clientIp) <= $maxPerIp;
    }

    private function readLimit(string $path, int $default, ?int $storeId): int
    {
        $value = $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);
        if ($value === null || trim((string) $value) === '') {
            return $default;
        }

        return max(0, (int) $value);
    }

    private function countRecent(string $column, string $value): int
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(
                $this->resource->getTableName('panth_dynamic_form_submission'),
                ['total' => new Expression('COUNT(*)')]
            )
            ->where($connection->quoteIdentifier($column) . ' = ?', $value)
            ->where('created_at >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 1 HOUR)');

        return (int) $connection->fetchOne($select);
    }
}
