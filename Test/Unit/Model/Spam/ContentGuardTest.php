<?php
declare(strict_types=1);

namespace Panth\DynamicForms\Test\Unit\Model\Spam;

use Panth\DynamicForms\Model\Spam\ContentGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Panth\DynamicForms\Helper\Data;

class ContentGuardTest extends TestCase
{
    private function guard(bool $enabled = true, string $blockedTerms = ''): ContentGuard
    {
        $config = $this->createStub(Data::class);
        $config->method('isContentGuardEnabled')->willReturn($enabled);
        $config->method('getBlockedTerms')->willReturn($blockedTerms);

        return new ContentGuard($config);
    }

    #[DataProvider('spamProvider')]
    public function testSpamIsDetected(array $values, array $shortFields, string $expectedReasonPrefix): void
    {
        $reason = $this->guard()->detect($values, $shortFields);

        $this->assertNotNull($reason, 'expected this payload to be flagged');
        $this->assertStringStartsWith($expectedReasonPrefix, $reason);
    }

    public static function spamProvider(): array
    {
        return [
            'link shortener' => [
                ['name' => 'Bob', 'message' => 'click https://share.google/abc123'],
                ['name'],
                'blocked domain',
            ],
            'telegram link' => [
                ['name' => 'Bob', 'message' => 'write me on t.me/someone'],
                ['name'],
                'blocked domain',
            ],
            'money transfer with roubles' => [
                ['name' => 'Перевод зачислен', 'message' => 'средства готовы 5000 руб'],
                ['name'],
                'money transfer',
            ],
            'money transfer with a link' => [
                ['name' => 'x', 'message' => 'Новый перевод успешно зачислен на ваш счет http://example.test/a'],
                ['name'],
                'money transfer',
            ],
            'three links' => [
                ['message' => 'http://a.test http://b.test http://c.test'],
                [],
                'three or more links',
            ],
            'url in a short field' => [
                ['name' => 'https://cheap.example', 'message' => 'hi'],
                ['name'],
                'url in short field',
            ],
            'www url in a short field' => [
                ['subject' => 'www.cheap.example', 'message' => 'hi'],
                ['subject'],
                'url in short field',
            ],
            'nested array values are inspected' => [
                ['custom_options' => ['one', 'visit https://bit.ly/x']],
                [],
                'blocked domain',
            ],
        ];
    }

    #[DataProvider('legitimateProvider')]
    public function testGenuineEnquiriesPass(array $values, array $shortFields): void
    {
        $this->assertNull($this->guard()->detect($values, $shortFields));
    }

    public static function legitimateProvider(): array
    {
        return [
            'plain enquiry' => [
                ['name' => 'Jane Smith', 'subject' => 'Delivery', 'message' => 'When will my order ship?'],
                ['name', 'subject'],
            ],
            'one link in a long message' => [
                ['name' => 'Jane Smith', 'message' => 'The page at https://example.test/gear/bags.html returns an error for me, could you take a look?'],
                ['name'],
            ],
            'two links still pass' => [
                ['message' => 'compare https://example.test/a and https://example.test/b please'],
                [],
            ],
            'a website field may hold a url' => [
                ['name' => 'Jane', 'custom_website' => 'https://partner.example', 'message' => 'partnership'],
                ['name', 'custom_website'],
            ],
            'russian text without money wording' => [
                ['name' => 'Иван', 'message' => 'Здравствуйте, у меня вопрос о доставке.'],
                ['name'],
            ],
            'money wording without money context' => [
                ['message' => 'перевод текста на английский'],
                [],
            ],
            'empty submission' => [
                ['name' => '', 'message' => '   '],
                ['name'],
            ],
        ];
    }

    public function testDisabledGuardNeverFlags(): void
    {
        $spam = ['name' => 'x', 'message' => 'https://share.google/abc перевод зачислен 500 руб'];

        $this->assertNull($this->guard(false)->detect($spam, ['name']));
    }

    public function testExtraBlockedTermsAreHonoured(): void
    {
        $guard = $this->guard(true, "evil-domain.example\nanother-bad.example");

        $this->assertSame(
            'blocked domain (evil-domain.example)',
            $guard->detect(['message' => 'see evil-domain.example'], [])
        );
        $this->assertSame(
            'blocked domain (another-bad.example)',
            $guard->detect(['message' => 'see another-bad.example'], [])
        );
        $this->assertNull($guard->detect(['message' => 'see harmless.example'], []));
    }

    public function testBlockedTermsAreMatchedWholeWord(): void
    {
        $guard = $this->guard(true, 'spam.example');

        $this->assertNull($guard->detect(['message' => 'notspam.exampleish'], []));
    }

    public function testShortFieldRuleOnlyAppliesToNamedFields(): void
    {
        $values = ['name' => 'Jane', 'message' => 'https://example.test'];

        $this->assertNull($this->guard()->detect($values, ['name']));
        $this->assertNotNull($this->guard()->detect($values, ['name', 'message']));
    }

    public function testInvalidUtf8DoesNotSilenceTheGuard(): void
    {
        $reason = $this->guard()->detect(
            ['message' => "bad \xC3\x28 bytes https://share.google/x"],
            []
        );

        $this->assertSame('blocked domain (share.google)', $reason);
    }

    public function testSampleIsTruncatedAndSingleLine(): void
    {
        $sample = $this->guard()->sample(['a' => "line one\nline two", 'b' => str_repeat('x', 400)]);

        $this->assertLessThanOrEqual(200, mb_strlen($sample));
        $this->assertStringNotContainsString("\n", $sample);
    }
}
