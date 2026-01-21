<?php
declare(strict_types=1);

namespace Adyen\Payment\Model\Method;

use Adyen\Payment\Exception\TxVariantValidationException;
use Adyen\Payment\Helper\PaymentMethods as PaymentMethodsHelper;
use Magento\Payment\Helper\Data as DataHelper;
use Magento\Payment\Model\MethodInterface;

class ValidatedTxVariant
{
    private ?string $card = null;
    private MethodInterface $methodInstance;

    /**
     * @throws TxVariantValidationException
     */
    public function __construct(
        string $txVariant,
        DataHelper $dataHelper,
        PaymentMethodsHelper $paymentMethodsHelper
    ) {
        // Determine the PaymentMethod part WITHOUT assuming underscore means wallet.
        // If there is underscore, PaymentMethod method part is after underscore; otherwise it is the whole txVariant.
        $paymentMethodPart = $this->extractPaymentMethodPart($txVariant);

        // Resolve method instance for *all* cases. Throw if not found.
        $methodCode = $paymentMethodsHelper::ADYEN_PREFIX . $paymentMethodPart;

        try {
            $this->methodInstance = $dataHelper->getMethodInstance($methodCode);
        } catch (\Throwable $e) {
            throw TxVariantValidationException::methodNotFound($txVariant, $methodCode);
        }

        // Only if the resolved method is a wallet, THEN parse the card part (if present).
        if ($paymentMethodsHelper->isWalletPaymentMethod($this->methodInstance)) {
            // Now we treat it as a wallet txVariant.
            $this->card = $this->extractCardPartIfPresent($txVariant);
        } else {
            // If it looks like wallet format (has underscore) but resolved method isn't wallet -> throw
            if (str_contains($txVariant, '_')) {
                throw TxVariantValidationException::notWallet($txVariant, $methodCode);
            }
        }
    }

    private function extractPaymentMethodPart(string $txVariant): string
    {
        $pos = strpos($txVariant, '_');
        if ($pos === false) {
            return $txVariant;
        }
        // payment method part is everything after first underscore
        return substr($txVariant, $pos + 1);
    }

    private function extractCardPartIfPresent(string $txVariant): ?string
    {
        $pos = strpos($txVariant, '_');
        if ($pos === false) {
            return null;
        }
        return substr($txVariant, 0, $pos);
    }

    public function getCard(): ?string
    {
        return $this->card;
    }

    public function getMethodInstance(): MethodInterface
    {
        return $this->methodInstance;
    }
}
