<?php
declare(strict_types=1);

namespace Adyen\Payment\Exception;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;

class TxVariantValidationException extends LocalizedException
{
    public static function methodNotFound(string $txVariant, string $methodCode): self
    {
        return new self(
            new Phrase('TxVariant "%1" resolved to "%2" but no such payment method exists.', [$txVariant, $methodCode])
        );
    }

    public static function notWallet(string $txVariant, string $methodCode): self
    {
        return new self(
            new Phrase('TxVariant "%1" resolved to "%2" but it is not a wallet payment method.', [$txVariant, $methodCode])
        );
    }
}
