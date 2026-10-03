<?php
declare(strict_types=1);

namespace Panth\DynamicForms\Test\Unit\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Panth\DynamicForms\Model\AutoReplyGuard;
use PHPUnit\Framework\TestCase;

class AutoReplyGuardTest extends TestCase
{
    private array $config = [];
    private bool $enabled = true;
    /** @var array<string,int> keyed by "column=value" */
    private array $counts = [];
    private array $queried = [];
    private string $lastWhere = '';

    private function guard(): AutoReplyGuard
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->willReturnCallback(fn() => $this->enabled);
        $scopeConfig->method('getValue')->willReturnCallback(fn($path) => $this->config[$path] ?? null);

        $select = $this->createStub(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnCallback(function ($cond, $value = null) use ($select) {
            if ($value !== null) {
                $this->lastWhere = trim(str_replace(['`', ' = ?'], '', $cond)) . '=' . $value;
            }
            return $select;
        });

        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('quoteIdentifier')->willReturnCallback(static fn($id) => '`' . $id . '`');
        $connection->method('select')->willReturn($select);
        $connection->method('fetchOne')->willReturnCallback(function () {
            $this->queried[] = $this->lastWhere;
            return (string) ($this->counts[$this->lastWhere] ?? 0);
        });

        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);

        return new AutoReplyGuard($scopeConfig, $resource);
    }

    public function testDisabledOrEmptyEmailIsNeverAllowed(): void
    {
        $this->enabled = false;
        $this->assertFalse($this->guard()->isAllowed('a@b.test', '1.1.1.1'));

        $this->enabled = true;
        $this->assertFalse($this->guard()->isAllowed('   ', '1.1.1.1'));
        $this->assertSame([], $this->queried);
    }

    public function testDefaultsAllowUpToTwoPerRecipientAndFivePerIp(): void
    {
        $this->counts = ['customer_email=a@b.test' => 2, 'customer_ip=1.1.1.1' => 5];
        $this->assertTrue($this->guard()->isAllowed(' a@b.test ', ' 1.1.1.1 '));

        $this->counts['customer_email=a@b.test'] = 3;
        $this->assertFalse($this->guard()->isAllowed('a@b.test', '1.1.1.1'));

        $this->counts['customer_email=a@b.test'] = 0;
        $this->counts['customer_ip=1.1.1.1'] = 6;
        $this->assertFalse($this->guard()->isAllowed('a@b.test', '1.1.1.1'));
    }

    public function testMissingIpSkipsTheIpLimit(): void
    {
        $this->counts = ['customer_email=a@b.test' => 1];

        $this->assertTrue($this->guard()->isAllowed('a@b.test', ''));
        $this->assertSame(['customer_email=a@b.test'], $this->queried);
    }

    public function testConfiguredLimitsOverrideDefaultsAndNegativeClampsToZero(): void
    {
        $this->config = [
            'panth_dynamicforms/email/autoreply_max_per_recipient' => '-3',
            'panth_dynamicforms/email/autoreply_max_per_ip' => '10',
        ];
        $this->counts = ['customer_email=a@b.test' => 0, 'customer_ip=1.1.1.1' => 10];
        $this->assertTrue($this->guard()->isAllowed('a@b.test', '1.1.1.1'));

        $this->counts['customer_email=a@b.test'] = 1;
        $this->assertFalse($this->guard()->isAllowed('a@b.test', '1.1.1.1'));
    }

    public function testBlankConfiguredLimitUsesDefault(): void
    {
        $this->config = ['panth_dynamicforms/email/autoreply_max_per_recipient' => '  '];
        $this->counts = ['customer_email=a@b.test' => 2];

        $this->assertTrue($this->guard()->isAllowed('a@b.test', ''));
    }

    public function testIsEnabledReadsFlag(): void
    {
        $this->assertTrue($this->guard()->isEnabled(1));
        $this->enabled = false;
        $this->assertFalse($this->guard()->isEnabled(1));
    }
}
