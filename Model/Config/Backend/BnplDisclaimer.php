<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2026 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Config\Backend;

use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;

class BnplDisclaimer extends Value
{
    private const LINK_PATTERN = '/(?<!!)\[([^\[\]\n]+)\]\(([^()\s]+)\)/';
    private const MARKDOWN_PATTERNS = [
        '/[\[\]`]/',
        '/(\*{1,3}|_{1,3}|~~)(?=\S).*?(?<=\S)\1/',
        '/^\s{0,3}(#{1,6}(\s|$)|>|[*+-]\s|\d{1,9}[.)]\s)/m',
        '/^\s{0,3}([-*_=])(\s*\1){2,}\s*$/m',
        '/^\s*\|.*\|\s*$/m',
    ];

    /**
     * Strips all HTML tags and only accepts plain text with Markdown links pointing to HTTPS URLs.
     *
     * @return $this
     * @throws LocalizedException
     */
    public function beforeSave(): static
    {
        $value = trim(strip_tags((string) $this->getValue()));

        $plainText = preg_replace_callback(self::LINK_PATTERN, function (array $match): string {
            if (!$this->isHttpsUrl($match[2])) {
                throw new LocalizedException(__(
                    'The BNPL disclaimer message contains an invalid link "%1". Only absolute HTTPS URLs are allowed.',
                    $match[2]
                ));
            }

            return $match[1];
        }, $value);

        foreach (self::MARKDOWN_PATTERNS as $pattern) {
            if ($plainText === null || preg_match($pattern, $plainText)) {
                throw new LocalizedException(__(
                    'The BNPL disclaimer message only supports plain text and markdown links, e.g. [Terms](https://example.com).'
                ));
            }
        }

        $this->setValue($value);

        return parent::beforeSave();
    }

    private function isHttpsUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https';
    }
}
