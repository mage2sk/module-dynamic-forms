<?php
declare(strict_types=1);

namespace Panth\DynamicForms\Model\Spam;

use Panth\DynamicForms\Helper\Data as Config;

class ContentGuard
{
    private const SHORTENER_DOMAINS = [
        'share.google',
        't.me',
        'bit.ly',
        'tinyurl.com',
        'goo.gl',
        'is.gd',
        'cutt.ly',
        'rebrand.ly',
        'rb.gy',
        'shorturl.at',
    ];

    private const MONEY_PHRASE_PATTERN = '~(перевод|зачислен|средства готов|на ваш (счет|счёт|баланс)|пополнен)~iu';

    private const MONEY_CONTEXT_PATTERN = '~(руб|₽|https?://)~iu';

    private const LINK_PATTERN = '~https?://~i';

    private const MAX_LINKS = 3;

    private const SHORT_FIELD_MAX_LENGTH = 40;

    private const URL_FIELD_HINTS = ['website', 'url', 'site', 'link', 'domain', 'profile'];

    public function __construct(
        private readonly Config $config
    ) {
    }

    public function detect(array $values, array $shortFieldKeys = []): ?string
    {
        if (!$this->config->isContentGuardEnabled()) {
            return null;
        }

        $scalars = [];
        foreach ($values as $key => $value) {
            foreach ($this->flatten($value) as $text) {
                $text = $this->toUtf8($text);
                if (trim($text) !== '') {
                    $scalars[] = [(string) $key, $text];
                }
            }
        }

        if ($scalars === []) {
            return null;
        }

        $blob = implode("\n", array_column($scalars, 1));

        $domain = $this->matchBlockedDomain($blob);
        if ($domain !== null) {
            return 'blocked domain (' . $domain . ')';
        }

        if ($this->matchesMoneyTransferPhrasing($blob)) {
            return 'money transfer phrasing';
        }

        if ($this->countLinks($blob) >= self::MAX_LINKS) {
            return 'three or more links';
        }

        $shortField = $this->findUrlInShortField($scalars, $shortFieldKeys);
        if ($shortField !== null) {
            return 'url in short field (' . $shortField . ')';
        }

        return null;
    }

    public function sample(array $values, int $length = 200): string
    {
        $parts = [];
        foreach ($values as $value) {
            foreach ($this->flatten($value) as $text) {
                $text = trim($this->toUtf8($text));
                if ($text !== '') {
                    $parts[] = $text;
                }
            }
        }

        $blob = preg_replace('~\s+~u', ' ', implode(' | ', $parts)) ?? '';

        return mb_substr($blob, 0, $length);
    }

    private function flatten(mixed $value): array
    {
        if (is_scalar($value)) {
            return [(string) $value];
        }

        if (!is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $item) {
            foreach ($this->flatten($item) as $text) {
                $out[] = $text;
            }
        }

        return $out;
    }

    private function toUtf8(string $value): string
    {
        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
    }

    private function matchBlockedDomain(string $blob): ?string
    {
        foreach ($this->getBlockedTerms() as $term) {
            if ($term === '') {
                continue;
            }

            $pattern = '~\b' . preg_quote($term, '~') . '\b~i';
            if (preg_match($pattern, $blob) === 1) {
                return $term;
            }
        }

        return null;
    }

    private function getBlockedTerms(): array
    {
        $terms = self::SHORTENER_DOMAINS;

        $extra = preg_split('~\r\n|\r|\n|,~', $this->config->getBlockedTerms()) ?: [];
        foreach ($extra as $term) {
            $term = trim((string) $term);
            if ($term !== '') {
                $terms[] = $term;
            }
        }

        return array_values(array_unique($terms));
    }

    private function matchesMoneyTransferPhrasing(string $blob): bool
    {
        return preg_match(self::MONEY_PHRASE_PATTERN, $blob) === 1
            && preg_match(self::MONEY_CONTEXT_PATTERN, $blob) === 1;
    }

    private function countLinks(string $blob): int
    {
        return (int) preg_match_all(self::LINK_PATTERN, $blob);
    }

    private function findUrlInShortField(array $scalars, array $shortFieldKeys): ?string
    {
        if ($shortFieldKeys === []) {
            return null;
        }

        foreach ($scalars as [$key, $text]) {
            if (!in_array($key, $shortFieldKeys, true)) {
                continue;
            }

            if ($this->looksLikeUrlField($key)) {
                continue;
            }

            if (mb_strlen($text) > self::SHORT_FIELD_MAX_LENGTH) {
                continue;
            }

            if (preg_match('~(https?://|www\.)~i', $text) === 1) {
                return $key;
            }
        }

        return null;
    }

    private function looksLikeUrlField(string $key): bool
    {
        $key = strtolower($key);
        foreach (self::URL_FIELD_HINTS as $hint) {
            if (str_contains($key, $hint)) {
                return true;
            }
        }

        return false;
    }
}
