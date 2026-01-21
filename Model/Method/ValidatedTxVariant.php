<?php
declare(strict_types=1);

namespace Adyen\Payment\Model\Method;

use Adyen\Payment\Exception\TxVariantValidationException;
use Adyen\Payment\Helper\PaymentMethods as PaymentMethodsHelper;
use Magento\Payment\Helper\Data as DataHelper;
use Magento\Payment\Model\MethodInterface;

class ValidatedTxVariant
{
    private const METHOD_PREFIX = 'adyen_';

    private string $txVariant;
    private ?string $card = null;
    private string $paymentMethod;
    private ?MethodInterface $methodInstance = null;

    public function __construct(
        string $txVariant,
        DataHelper $dataHelper,
        PaymentMethodsHelper $paymentMethodsHelper
    ) {
        $this->txVariant = $txVariant;

        $splitVariant = explode('_', $txVariant, 2);
        if (count($splitVariant) > 1) {
            $this->card = $splitVariant[0];
            $this->paymentMethod = $splitVariant[1];

            // Validate: paymentMethod part must map to a real Adyen method AND be a wallet method
            $methodCode = self::METHOD_PREFIX . $this->paymentMethod;

            try {
                $this->methodInstance = $dataHelper->getMethodInstance($methodCode);
            } catch (\Throwable $e) {
                throw TxVariantValidationException::methodNotFound($txVariant, $methodCode);
            }

            if (!$paymentMethodsHelper->isWalletPaymentMethod($this->methodInstance)) {
                throw TxVariantValidationException::notWallet($txVariant, $methodCode);
            }
        } else {
            // Not a wallet-looking txVariant; no wallet validation required
            $this->paymentMethod = $splitVariant[0];

            $methodCode = self::METHOD_PREFIX . $this->paymentMethod;
            try {
                $this->methodInstance = $dataHelper->getMethodInstance($methodCode);
            } catch (\Throwable $e) {
                $this->methodInstance = null;
            }
        }
    }

    public function getCard(): ?string
    {
        return $this->card;
    }

    public function getPaymentMethod(): string
    {
        return $this->paymentMethod;
    }

    public function isWalletVariant(): bool
    {
        return $this->card !== null;
    }

    public function getMethodInstance(): ?MethodInterface
    {
        return $this->methodInstance;
    }

    public function getTxVariant(): string
    {
        return $this->txVariant;
    }
}
