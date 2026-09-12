<?php
declare(strict_types=1);

namespace Panth\DynamicForms\Test\Unit\Block\Widget;

use Panth\DynamicForms\Block\Widget\DynamicForm;
use PHPUnit\Framework\TestCase;

/**
 * The injection must survive a theme template override, and must never double up
 * when the shipped template already calls getAntiSpamFieldsHtml().
 */
class AntiSpamInjectionTest extends TestCase
{
    private function inject(string $html, string $fields): string
    {
        if ($html === '' || str_contains($html, DynamicForm::ANTISPAM_MARKER)) {
            return $html;
        }
        if ($fields === '') {
            return $html;
        }

        return preg_replace_callback(
            '~<form\b[^>]*>~i',
            static fn (array $m): string => $m[0] . $fields,
            $html,
            1
        ) ?? $html;
    }

    private const FIELDS = '<div data-panth-antispam="1"><input name="contact_url" value=""/></div>';

    public function testFieldsAreInjectedIntoATemplateThatOmitsThem(): void
    {
        $html = '<div><form id="f" method="post"><input name="name"/></form></div>';

        $out = $this->inject($html, self::FIELDS);

        $this->assertStringContainsString('data-panth-antispam', $out);
        $this->assertMatchesRegularExpression('~<form[^>]*><div data-panth-antispam~', $out);
    }

    public function testNothingIsInjectedWhenTheTemplateAlreadyRenderedThem(): void
    {
        $html = '<form id="f">' . self::FIELDS . '<input name="name"/></form>';

        $out = $this->inject($html, self::FIELDS);

        $this->assertSame($html, $out);
        $this->assertSame(1, substr_count($out, 'data-panth-antispam'));
    }

    public function testOnlyTheFirstFormIsTouched(): void
    {
        $html = '<form id="a"></form><form id="b"></form>';

        $out = $this->inject($html, self::FIELDS);

        $this->assertSame(1, substr_count($out, 'data-panth-antispam'));
        $this->assertMatchesRegularExpression('~<form id="a"><div data-panth-antispam~', $out);
    }

    public function testMarkupWithNoFormIsLeftAlone(): void
    {
        $html = '<div>no form here</div>';

        $this->assertSame($html, $this->inject($html, self::FIELDS));
    }

    public function testEmptyOutputIsLeftAlone(): void
    {
        $this->assertSame('', $this->inject('', self::FIELDS));
    }

    public function testNothingIsInjectedWhenTheHoneypotIsDisabled(): void
    {
        $html = '<form id="f"></form>';

        $this->assertSame($html, $this->inject($html, ''));
    }

    public function testADollarSignInTheFieldsIsNotEatenByTheReplacement(): void
    {
        $fields = '<div data-panth-antispam="1"><input name="a" value="$1 \\ x"/></div>';

        $out = $this->inject('<form id="f"></form>', $fields);

        $this->assertStringContainsString('value="$1', $out);
    }

    public function testUppercaseFormTagIsMatched(): void
    {
        $out = $this->inject('<FORM ID="f">', self::FIELDS);

        $this->assertStringContainsString('data-panth-antispam', $out);
    }
}
