<?php
declare(strict_types=1);

namespace Panth\DynamicForms\Test\Unit\Model;

use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Panth\DynamicForms\Model\RedirectUrlValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RedirectUrlValidatorTest extends TestCase
{
    private RedirectUrlValidator $validator;

    protected function setUp(): void
    {
        $store = $this->createStub(Store::class);
        $store->method('getBaseUrl')->willReturn('https://shop.example.test/');
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStores')->willReturn([$store]);
        $this->validator = new RedirectUrlValidator($storeManager);
    }

    public static function urlProvider(): array
    {
        return [
            'empty' => ['', true],
            'relative path' => ['/thank-you', true],
            'relative with query' => ['/thank-you?form=1', true],
            'bare relative' => ['thank-you', true],
            'same store https' => ['https://shop.example.test/thank-you', true],
            'same store upper case host' => ['HTTPS://SHOP.EXAMPLE.TEST/x', true],
            'javascript' => ['javascript:alert(1)', false],
            'javascript mixed case' => ['JaVaScRiPt:alert(1)', false],
            'javascript with tab' => ["java\tscript:alert(1)", false],
            'data uri' => ['data:text/html,x', false],
            'protocol relative' => ['//evil.example/x', false],
            'backslash trick' => ['/\\evil.example', false],
            'other host' => ['https://evil.example/x', false],
            'userinfo trick' => ['https://shop.example.test@evil.example/', false],
            'credentials on own host' => ['https://user:pass@shop.example.test/', false],
            'ftp own host' => ['ftp://shop.example.test/', false],
        ];
    }

    #[DataProvider('urlProvider')]
    public function testIsAllowed(string $url, bool $expected): void
    {
        $this->assertSame($expected, $this->validator->isAllowed($url));
    }

    public function testSanitizeDropsUnsafeValue(): void
    {
        $this->assertSame('', $this->validator->sanitize('javascript:alert(1)'));
        $this->assertSame('/ok', $this->validator->sanitize(' /ok '));
        $this->assertSame('', $this->validator->sanitize(null));
    }
}
