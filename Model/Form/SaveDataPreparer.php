<?php
declare(strict_types=1);

namespace Panth\DynamicForms\Model\Form;

class SaveDataPreparer
{
    public const FORM_TYPE_PAGE = 'page';
    public const FORM_TYPE_WIDGET = 'widget';
    public const FORM_TYPE_BOTH = 'both';

    private const REQUEST_ONLY_KEYS = ['form_id', 'fields_json', 'fields_note', 'form_key', 'back'];

    public function getFormId(array $post): int
    {
        return (int) ($post['form_id'] ?? 0);
    }

    public function getFormType(array $post): string
    {
        return (string) ($post['form_type'] ?? self::FORM_TYPE_PAGE);
    }

    public function requiresUrlKey(string $formType): bool
    {
        return in_array($formType, [self::FORM_TYPE_PAGE, self::FORM_TYPE_BOTH], true);
    }

    public function getUrlKey(array $post): string
    {
        if ($this->getFormType($post) === self::FORM_TYPE_WIDGET) {
            return '';
        }

        return $this->sanitizeUrlKey((string) ($post['url_key'] ?? ''));
    }

    public function sanitizeUrlKey(string $urlKey): string
    {
        $urlKey = trim($urlKey);
        if ($urlKey === '') {
            return '';
        }

        $urlKey = strtolower((string) preg_replace('/[^a-zA-Z0-9_-]/', '-', $urlKey));

        return (string) preg_replace('/-+/', '-', trim($urlKey, '-'));
    }

    public function prepare(array $post): array
    {
        $data = $post;
        foreach (self::REQUEST_ONLY_KEYS as $key) {
            unset($data[$key]);
        }

        $urlKey = $this->getUrlKey($post);
        $data['url_key'] = $urlKey === '' ? null : $urlKey;

        return $data;
    }
}
