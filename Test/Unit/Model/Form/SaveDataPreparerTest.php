<?php
declare(strict_types=1);

namespace Panth\DynamicForms\Test\Unit\Model\Form;

use Panth\DynamicForms\Model\Form\SaveDataPreparer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SaveDataPreparerTest extends TestCase
{
    private SaveDataPreparer $preparer;

    protected function setUp(): void
    {
        $this->preparer = new SaveDataPreparer();
    }

    public function testNewFormHasNoIdInPreparedData(): void
    {
        $post = $this->post(['form_id' => '']);

        $this->assertSame(0, $this->preparer->getFormId($post));
        $this->assertArrayNotHasKey('form_id', $this->preparer->prepare($post));
    }

    public function testExistingFormIdIsReadAsInt(): void
    {
        $this->assertSame(12, $this->preparer->getFormId($this->post(['form_id' => '12'])));
        $this->assertSame(0, $this->preparer->getFormId([]));
    }

    public function testRequestOnlyKeysAreStripped(): void
    {
        $data = $this->preparer->prepare($this->post([
            'form_id' => '3',
            'fields_json' => '[]',
            'fields_note' => 'x',
            'form_key' => 'k',
            'back' => 'edit',
        ]));

        foreach (['form_id', 'fields_json', 'fields_note', 'form_key', 'back'] as $key) {
            $this->assertArrayNotHasKey($key, $data);
        }
        $this->assertSame('Issue 1 Form', $data['name']);
        $this->assertSame('1', $data['is_active']);
    }

    #[DataProvider('urlKeyProvider')]
    public function testUrlKeyRules(array $overrides, string $expectedUrlKey, ?string $expectedData): void
    {
        $post = $this->post($overrides);

        $this->assertSame($expectedUrlKey, $this->preparer->getUrlKey($post));
        $this->assertSame($expectedData, $this->preparer->prepare($post)['url_key']);
    }

    public static function urlKeyProvider(): array
    {
        return [
            'page keeps key' => [['url_key' => 'issue-one-form'], 'issue-one-form', 'issue-one-form'],
            'sanitized' => [['url_key' => ' My Form Key! '], 'my-form-key', 'my-form-key'],
            'collapsed dashes' => [['url_key' => '--a---b--'], 'a-b', 'a-b'],
            'only symbols' => [['url_key' => '###'], '', null],
            'empty' => [['url_key' => ''], '', null],
            'missing' => [['url_key' => null], '', null],
            'widget clears key' => [['url_key' => 'kept', 'form_type' => 'widget'], '', null],
            'both keeps key' => [['url_key' => 'Both', 'form_type' => 'both'], 'both', 'both'],
        ];
    }

    public function testRequiresUrlKey(): void
    {
        $this->assertTrue($this->preparer->requiresUrlKey('page'));
        $this->assertTrue($this->preparer->requiresUrlKey('both'));
        $this->assertFalse($this->preparer->requiresUrlKey('widget'));
    }

    public function testFormTypeDefaultsToPage(): void
    {
        $this->assertSame('page', $this->preparer->getFormType([]));
        $this->assertSame('widget', $this->preparer->getFormType(['form_type' => 'widget']));
    }

    private function post(array $overrides): array
    {
        $post = [
            'form_id' => '',
            'name' => 'Issue 1 Form',
            'url_key' => 'issue-one-form',
            'form_type' => 'page',
            'is_active' => '1',
            'fields_json' => '[]',
            'form_key' => 'abc',
        ];
        foreach ($overrides as $key => $value) {
            if ($value === null) {
                unset($post[$key]);
            } else {
                $post[$key] = $value;
            }
        }

        return $post;
    }
}
