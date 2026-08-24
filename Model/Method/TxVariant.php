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
declare(strict_types=1);

namespace Adyen\Payment\Model\Method;

use Magento\Framework\Exception\LocalizedException;
use UnexpectedValueException;
use Adyen\Payment\Helper\PaymentMethods as PaymentMethodsHelper;
use Magento\Payment\Helper\Data as DataHelper;
use Magento\Payment\Model\MethodInterface;

/**
 * Tries to resolve the method instance for the given txVariant of an alternative payment method or wallet.
 */
class TxVariant
{
    private ?string $card = null;
    private MethodInterface $methodInstance;

    /**
     * @param string $txVariant
     * @param DataHelper $dataHelper
     * @param PaymentMethodsHelper $paymentMethodsHelper
     *
     * @throws UnexpectedValueException
     */
    public function __construct(
        private readonly string $txVariant,
        private readonly DataHelper $dataHelper,
        private readonly PaymentMethodsHelper $paymentMethodsHelper,
    ) {
        $this->methodInstance = $this->resolveMethodInstance();

        if ($this->paymentMethodsHelper->isWalletPaymentMethod($this->methodInstance)) {
            $this->card = $this->extractCardPartIfPresent();
        }
    }

    /**
     * @return string|null
     */
    public function getCard(): ?string
    {
        return $this->card;
    }

    /**
     * @return MethodInterface
     */
    public function getMethodInstance(): MethodInterface
    {
        return $this->methodInstance;
    }

    /**
     * @throws UnexpectedValueException
     */
    private function resolveMethodInstance(): MethodInterface
    {
        // 1) Try full txVariant
        $methodCode = $this->paymentMethodsHelper::ADYEN_PREFIX . $this->txVariant;

        try {
            return $this->dataHelper->getMethodInstance($methodCode);
        } catch (UnexpectedValueException|LocalizedException $e) {
            // 2) Fallback: part after underscore
            $paymentMethodPart = $this->extractPaymentMethodPart();
            $methodCode = $this->paymentMethodsHelper::ADYEN_PREFIX . $paymentMethodPart;

            try {
                return $this->dataHelper->getMethodInstance($methodCode);
            } catch (UnexpectedValueException|LocalizedException $e1) {
                throw new UnexpectedValueException(
                    sprintf(
                        'Payment method instance not found for txVariant "%s" (attempted "%s").',
                        $this->txVariant,
                        $methodCode
                    ),
                    0,
                    $e1
                );
            }
        }
    }

    /**
     * @return string
     */
    private function extractPaymentMethodPart(): string
    {
        $pos = strpos($this->txVariant, '_');
        return $pos === false
            ? $this->txVariant
            : substr($this->txVariant, $pos + 1);
    }

    /**
     * @return string|null
     */
    private function extractCardPartIfPresent(): ?string
    {
        $pos = strpos($this->txVariant, '_');
        return $pos === false
            ? null
            : substr($this->txVariant, 0, $pos);
    }
}
