<?php
declare(strict_types=1);

namespace Adyen\Payment\Model\Method;

use Magento\Framework\Exception\LocalizedException;
use UnexpectedValueException;
use Adyen\Payment\Helper\PaymentMethods as PaymentMethodsHelper;
use Magento\Payment\Helper\Data as DataHelper;
use Magento\Payment\Model\MethodInterface;

class TxVariantInterpreter
{
    private ?string $card = null;
    private MethodInterface $methodInstance;

    /**
     * @param string $txVariant
     * @param DataHelper $dataHelper
     * @param PaymentMethodsHelper $paymentMethodsHelper
     * @throws LocalizedException
     */
    public function __construct(
        private readonly string $txVariant,
        private readonly DataHelper $dataHelper,
        private readonly PaymentMethodsHelper $paymentMethodsHelper,
    ) {
        $this->methodInstance = $this->resolveMethodInstance();

        if (
            $this->paymentMethodsHelper->isWalletPaymentMethod($this->methodInstance)
            && str_contains($this->txVariant, '_')
        ) {
            $this->card = $this->extractCardPartIfPresent();
        }
    }

    /**
     * @throws LocalizedException
     */
    private function resolveMethodInstance(): MethodInterface
    {
        // 1) Try full txVariant
        $methodCode = $this->paymentMethodsHelper::ADYEN_PREFIX . $this->txVariant;

        try {
            return $this->dataHelper->getMethodInstance($methodCode);
        } catch (UnexpectedValueException $e) {
            // 2) Fallback: part after underscore
            $paymentMethodPart = $this->extractPaymentMethodPart();
            $methodCode = $this->paymentMethodsHelper::ADYEN_PREFIX . $paymentMethodPart;

            try {
                return $this->dataHelper->getMethodInstance($methodCode);
            } catch (UnexpectedValueException $e1) {
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

    private function extractPaymentMethodPart(): string
    {
        $pos = strpos($this->txVariant, '_');
        return $pos === false
            ? $this->txVariant
            : substr($this->txVariant, $pos + 1);
    }

    private function extractCardPartIfPresent(): ?string
    {
        $pos = strpos($this->txVariant, '_');
        return $pos === false
            ? null
            : substr($this->txVariant, 0, $pos);
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
