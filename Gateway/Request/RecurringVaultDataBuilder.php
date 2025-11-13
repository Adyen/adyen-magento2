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
use Adyen\Payment\Observer\AdyenCcDataAssignObserver;

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

        // Initialize the request body with the current state data
        $requestBody = $this->stateData->getStateData($order->getQuoteId());

        // Build base request for card token payments (including card wallets)
        if ($paymentToken->getType() === PaymentTokenFactoryInterface::TOKEN_TYPE_CREDIT_CARD) {
            /*
             * `allow3DS: true` flag is required to trigger the native 3DS challenge.
             * Otherwise, shopper will be redirected to the issuer for challenge.
             */
            $threeDSFlow = $this->configHelper->getThreeDSFlow($order->getStoreId());
            $requestBody['authenticationData']['threeDSRequestData']['nativeThreeDS'] =
                $threeDSFlow === ThreeDSFlow::THREEDS_NATIVE ?
                    ThreeDSFlow::THREEDS_PREFERRED :
                    ThreeDSFlow::THREEDS_DISABLED;

            // Due to new VISA compliance requirements, holderName is added to the payments call
            $requestBody['paymentMethod']['holderName'] = $details['cardHolderName'] ?? null;
        }

        /*
         * `paymentMethod.type` and `paymentMethod.storedPaymentMethodId` need to be manually populated
         * for all recurring alternative payment methods, recurring card payments where state data is missing and
         * `Instant Purchase` payments.
         */
        if (empty($requestBody['paymentMethod']['type'])) {
            $requestBody['paymentMethod']['type'] =
                $paymentToken->getType() === PaymentTokenFactoryInterface::TOKEN_TYPE_CREDIT_CARD
                    ? 'scheme'
                    : $details['type'];
        }
        if (empty($requestBody['paymentMethod']['storedPaymentMethodId'])) {
            $requestBody['paymentMethod']['storedPaymentMethodId'] = $paymentToken->getGatewayToken();
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

        $numberOfInstallments = $payment->getAdditionalInformation(
            AdyenCcDataAssignObserver::NUMBER_OF_INSTALLMENTS
        );

        if (!empty($numberOfInstallments)) {
            $requestBody['installments']['value'] = (int) $numberOfInstallments;
        }

        return [
            'body' => $requestBody
        ];
    }
}
