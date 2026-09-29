<?php
declare(strict_types=1);

namespace Digitalway\SocialLogin\Model;

use Magento\Framework\Url\DecoderInterface;
use Magento\Framework\Url\HostChecker;

/**
 * Accepts only absolute http(s) URLs to a store host (no open redirect).
 */
class ReturnUrlValidator
{
    public function __construct(
        private readonly HostChecker $hostChecker,
        private readonly DecoderInterface $urlDecoder
    ) {
    }

    public function sanitize(?string $url): string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }

        $parts = parse_url($url);
        // HostChecker::isOwnOrigin() returns true for URLs without a host: rule them out first.
        if ($parts === false
            || empty($parts['host'])
            || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
        ) {
            return '';
        }

        return $this->hostChecker->isOwnOrigin($url) ? $url : '';
    }

    /**
     * "referer" parameter encoded the way the core does (Magento\Framework\Url\EncoderInterface).
     */
    public function fromEncodedReferer(?string $encoded): string
    {
        $encoded = trim((string) $encoded);

        return $encoded === '' ? '' : $this->sanitize($this->urlDecoder->decode($encoded));
    }
}
