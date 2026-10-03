<?php
declare(strict_types=1);

namespace Panth\DynamicForms\Model;

use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

class RedirectUrlValidator
{
    private StoreManagerInterface $storeManager;

    private ?array $storeHosts = null;

    public function __construct(StoreManagerInterface $storeManager)
    {
        $this->storeManager = $storeManager;
    }

    public function isAllowed(?string $url): bool
    {
        $url = trim((string) $url);
        if ($url === '') {
            return true;
        }

        if (preg_match('/[\x00-\x20\x7F\\\\]/', $url) || str_starts_with($url, '//')) {
            return false;
        }

        if (!preg_match('/^[a-z][a-z0-9+.\-]*:/i', $url)) {
            return true;
        }

        $parts = parse_url($url);
        if (!is_array($parts) || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            return false;
        }

        return in_array($host, $this->getStoreHosts(), true);
    }

    public function sanitize(?string $url): string
    {
        $url = trim((string) $url);

        return $this->isAllowed($url) ? $url : '';
    }

    private function getStoreHosts(): array
    {
        if ($this->storeHosts !== null) {
            return $this->storeHosts;
        }

        $hosts = [];
        foreach ($this->storeManager->getStores(true) as $store) {
            foreach ([false, true] as $secure) {
                $host = parse_url((string) $store->getBaseUrl(UrlInterface::URL_TYPE_LINK, $secure), PHP_URL_HOST);
                if (is_string($host) && $host !== '') {
                    $hosts[] = strtolower($host);
                }
            }
        }

        $this->storeHosts = array_values(array_unique($hosts));

        return $this->storeHosts;
    }
}
