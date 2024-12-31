<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2024 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Request;

use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\StateData;
use Adyen\Payment\Helper\Vault;
use Adyen\Payment\Model\Config\Source\ThreeDSFlow;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Vault\Api\Data\PaymentTokenFactoryInterface;

class RecurringVaultDataBuilder implements BuilderInterface
{
    /**
     * @param StateData $stateData
     * @param Vault $vaultHelper
     * @param Config $configHelper
     */
    public function __construct(
        private readonly StateData $stateData,
        private readonly Vault $vaultHelper,
        private readonly Config $configHelper
    ) { }

    /**
     * @throws LocalizedException
     */
    public function build(array $buildSubject): array
    {
        /** @var PaymentDataObject $paymentDataObject */
        $paymentDataObject = SubjectReader::readPayment($buildSubject);

        $payment = $paymentDataObject->getPayment();
        $paymentMethod = $payment->getMethodInstance();

        $order = $payment->getOrder();
        $extensionAttributes = $payment->getExtensionAttributes();

        $paymentToken = $extensionAttributes->getVaultPaymentToken();
        $details = json_decode((string) ($paymentToken->getTokenDetails() ?: '{}'), true);

        if ($paymentToken->getType() === PaymentTokenFactoryInterface::TOKEN_TYPE_CREDIT_CARD) {
            // Build base request for card token payments (including card wallets)

            $isInstantPurchase = (bool) $payment->getAdditionalInformation('instant-purchase');

            if ($isInstantPurchase) {
                // `Instant Purchase` doesn't have the component and state data. Build the `paymentMethod` object.
                $requestBody['paymentMethod']['type'] = 'scheme';
                $requestBody['paymentMethod']['storedPaymentMethodId'] = $paymentToken->getGatewayToken();
            } else {
                // Initialize the request body with the current state data if it's not `Instant Purchase`.
                $requestBody = $this->stateData->getStateData($order->getQuoteId());
            }

        if ($paymentMethod->getCode() === AdyenCcConfigProvider::CC_VAULT_CODE) {
            $requestBody['authenticationData']['threeDSRequestData']['nativeThreeDS'] =
                $this->configHelper->getThreeDSFlow($order->getStoreId());
            $requestBody['paymentMethod']['holderName'] = $details['cardHolderName'] ?? null;
        }  else {
            // Build base request for alternative payment methods for regular checkout and Instant Purchase

            $requestBody['paymentMethod'] = [
                'type' => $details['type'],
                'storedPaymentMethodId' => $paymentToken->getGatewayToken()
            ];
        }

        // Check the `stateData` if `recurringProcessingModel` is added through a headless request.
        if (array_key_exists(Vault::TOKEN_TYPE, $details)) {
            $requestBody['recurringProcessingModel'] = $details[Vault::TOKEN_TYPE];
        } else {
            // If recurringProcessingModel doesn't exist in the token details, use the default value from config.
            $requestBody['recurringProcessingModel'] = $this->vaultHelper->getPaymentMethodRecurringProcessingModel(
                $paymentMethod->getProviderCode(),
                $order->getStoreId()
            );
        }

        return [
            'body' => $requestBody
        ];
    }
}
