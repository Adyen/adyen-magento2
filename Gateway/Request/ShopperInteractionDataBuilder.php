<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2021 Adyen NV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Request;

use Adyen\Payment\Helper\StateData;
use Adyen\Payment\Model\Ui\Adminhtml\AdyenMotoConfigProvider;
use Adyen\Payment\Model\Ui\AdyenPayByLinkConfigProvider;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

class ShopperInteractionDataBuilder implements BuilderInterface
{
    const SHOPPER_INTERACTION_MOTO = 'Moto';
    const SHOPPER_INTERACTION_CONTAUTH = 'ContAuth';
    const SHOPPER_INTERACTION_ECOMMERCE = 'Ecommerce';

    private State $appState;
    private StateData $stateData;

    public function __construct(
        Context $context,
        StateData $stateData
    ) {
        $this->appState = $context->getAppState();
        $this->stateData = $stateData;
    }

    /**
     * @param array $buildSubject
     * @return array
     * @throws LocalizedException
     */
    public function build(array $buildSubject)
    {
        /** @var \Magento\Payment\Gateway\Data\PaymentDataObject $paymentDataObject */
        $paymentDataObject = SubjectReader::readPayment($buildSubject);
        $payment = $paymentDataObject->getPayment();
        $order = $payment->getOrder();
        $paymentMethod = $payment->getMethodInstance()->getCode();

        if ($paymentMethod == AdyenPayByLinkConfigProvider::CODE) {
            // Don't send shopperInteraction for PBL
            return [];
        }

        // Ecommerce is the default shopperInteraction
        $shopperInteraction = self::SHOPPER_INTERACTION_ECOMMERCE;

        // Check if it's a tokenised payment or not.
        $stateData = $this->stateData->getStateData($order->getQuoteId());
        $storedPaymentMethodId = $this->stateData->getStoredPaymentMethodIdFromStateData($stateData);

        if ($paymentMethod == AdyenMotoConfigProvider::CODE &&
            $this->appState->getAreaCode() == \Magento\Framework\App\Area::AREA_ADMINHTML) {
            // Backend CC orders are MOTO
            $shopperInteraction = self::SHOPPER_INTERACTION_MOTO;
        } elseif (str_contains($paymentMethod, '_vault') || isset($storedPaymentMethodId)) {
            // Vault is ContAuth
            $shopperInteraction = self::SHOPPER_INTERACTION_CONTAUTH;
        }

        $requestBody['body']['shopperInteraction'] = $shopperInteraction;
        return $requestBody;
    }
}
