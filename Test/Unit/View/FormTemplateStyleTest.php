<?php
declare(strict_types=1);

namespace Panth\DynamicForms\Test\Unit\View;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FormTemplateStyleTest extends TestCase
{
    private const TEMPLATES = [
        'view/frontend/templates/widget/form.phtml',
        'view/frontend/templates/widget/form_hyva.phtml',
    ];

    private function styleBlock(string $template): string
    {
        $path = dirname(__DIR__, 3) . '/' . $template;
        $this->assertTrue(is_file($path), $template . ' is missing');
        $source = (string) file_get_contents($path);
        $this->assertSame(1, preg_match('#<style>(.*?)</style>#s', $source, $match));

        return $match[1];
    }

    public static function templateProvider(): array
    {
        return [
            'luma' => [self::TEMPLATES[0]],
            'hyva' => [self::TEMPLATES[1]],
        ];
    }

    #[DataProvider('templateProvider')]
    public function testInputsFollowTheSharedInputStyle(string $template): void
    {
        $css = $this->styleBlock($template);

        $this->assertStringContainsString('--df-border:#D4D4D4', $css);
        $this->assertStringContainsString('--df-focus:#0F766E', $css);
        $this->assertStringContainsString('--df-error:#B91C1C', $css);
        $this->assertStringContainsString(
            '.pdf .pdf-input,.pdf .pdf-select,.pdf .pdf-textarea{font-size:16px!important;'
            . 'color:var(--df-text)!important;background-color:#FFFFFF;'
            . 'border:1px solid var(--df-border)!important;border-radius:8px!important;',
            $css
        );
        $this->assertStringContainsString(
            '.pdf .pdf-input,.pdf .pdf-select:not(.pdf-multi){height:46px!important;',
            $css
        );
        $this->assertStringContainsString('box-shadow:0 0 0 2px var(--df-focus)!important', $css);
    }

    #[DataProvider('templateProvider')]
    public function testLabelsHelpAndErrorsUseFourteenPixelText(string $template): void
    {
        $css = $this->styleBlock($template);

        $this->assertStringContainsString('.pdf .pdf-label{font-size:14px;font-weight:600;', $css);
        $this->assertStringContainsString('.pdf .pdf-err,.pdf .pdf-hint{font-size:14px;line-height:1.4}', $css);
    }

    #[DataProvider('templateProvider')]
    public function testSubmitButtonIsThePrimaryButton(string $template): void
    {
        $css = $this->styleBlock($template);

        $this->assertStringContainsString(
            '.pdf .pdf-btn{height:44px;min-height:44px;padding:0 24px;font-size:15px;font-weight:600;',
            $css
        );
        $this->assertStringContainsString('.pdf .pdf-btn{height:48px;min-height:48px;width:100%}', $css);
        $this->assertStringContainsString(
            '.pdf .pdf-card{border:1px solid #E5E5E5;border-radius:12px;box-shadow:none}',
            $css
        );
    }

    #[DataProvider('templateProvider')]
    public function testHeadingScale(string $template): void
    {
        $css = $this->styleBlock($template);

        $this->assertStringContainsString('.pdf .pdf-title{font-size:28px;font-weight:700;', $css);
        $this->assertStringContainsString('.pdf .pdf-title{font-size:24px}', $css);
        $this->assertStringContainsString('.pdf-h1{font-size:36px;', $css);
    }

    public function testBothTemplatesShareTheSameOverrideBlock(): void
    {
        $tail = static function (string $css): string {
            return substr($css, (int) strpos($css, '.pdf{--df-primary:#0F766E'));
        };

        $this->assertSame(
            $tail($this->styleBlock(self::TEMPLATES[0])),
            $tail($this->styleBlock(self::TEMPLATES[1]))
        );
    }
}
