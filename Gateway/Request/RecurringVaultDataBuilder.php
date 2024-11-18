<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2022 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Request;

use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\StateData;
use Adyen\Payment\Helper\Vault;
use Adyen\Payment\Model\Config\Source\ThreeDSFlow;
use Adyen\Payment\Model\Ui\AdyenCcConfigProvider;
use Adyen\Payment\Model\Ui\AdyenHppConfigProvider;
use Adyen\Payment\Model\Ui\AdyenOneclickConfigProvider;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

class RecurringVaultDataBuilder implements BuilderInterface
{
    /**
     * @var StateData
     */
    private $stateData;

    /**
     * @var Config
     */
    private $configHelper;

    public function __construct(
        StateData $stateData,
        Config $configHelper
    ) {
        $this->stateData = $stateData;
        $this->configHelper = $configHelper;
    }

    /**
     * @param array $buildSubject
     * @return array
     * @throws LocalizedException
     */
    public function build(array $buildSubject)
    {
        /** @var PaymentDataObject $paymentDataObject */
        $paymentDataObject = SubjectReader::readPayment($buildSubject);
        $payment = $paymentDataObject->getPayment();
        $paymentMethod = $payment->getMethodInstance();
        $order = $payment->getOrder();
        $extensionAttributes = $payment->getExtensionAttributes();
        $paymentToken = $extensionAttributes->getVaultPaymentToken();
        $details = json_decode($paymentToken->getTokenDetails() ?: '{}', true);

        // Initialize the request body with the current state data
        $requestBody = $this->stateData->getStateData($order->getQuoteId());

        // For now this will only be used by tokens created trough adyen_hpp payment methods
        if (array_key_exists(Vault::TOKEN_TYPE, $details)) {
            $requestBody['recurringProcessingModel'] = $details[Vault::TOKEN_TYPE];
        } else if ($paymentMethod->getCode() === AdyenCcConfigProvider::CC_VAULT_CODE ||
            $paymentMethod->getCode() === AdyenOneclickConfigProvider::CODE) {
            $requestBody['recurringProcessingModel'] = $this->configHelper->getCardRecurringType(
                $order->getStoreId()
            );
        } else if ($paymentMethod->getCode() === AdyenHppConfigProvider::class ) {
            $requestBody['recurringProcessingModel'] = $this->configHelper->getAlternativePaymentMethodTokenType(
                $order->getStoreId()
            );
        }

        /*
         * allow3DS flag is required to trigger the native 3DS challenge.
         * Otherwise, shopper will be redirected to the issuer for challenge.
         * Due to new VISA compliance requirements, holderName is added to the payments call
         */
        if ($paymentMethod->getCode() === AdyenCcConfigProvider::CC_VAULT_CODE ||
            $paymentMethod->getCode() === AdyenOneclickConfigProvider::CODE) {
            $requestBody['additionalData']['allow3DS2'] =
                $this->configHelper->getThreeDSFlow($order->getStoreId()) === ThreeDSFlow::THREEDS_NATIVE;
            $requestBody['paymentMethod']['holderName'] = $details['cardHolderName'] ?? null;
        }

        $request['body'] = $requestBody;

        return $request;
    }
}
