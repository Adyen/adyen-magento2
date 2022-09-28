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

use Adyen\Payment\Model\Ui\Adminhtml\AdyenMotoConfigProvider;
use Adyen\Payment\Model\Ui\AdyenCcConfigProvider;
use Adyen\Payment\Model\Ui\AdyenOneclickConfigProvider;
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

    /**
     * @var State
     */
    private $appState;

    public function __construct(Context $context)
    {
        $this->appState = $context->getAppState();
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
        $paymentMethod = $payment->getMethodInstance()->getCode();

        if ($paymentMethod == AdyenPayByLinkConfigProvider::CODE) {
            // Don't send shopperInteraction for PBL
            return [];
        }

        // Ecommerce is the default shopperInteraction
        $shopperInteraction = self::SHOPPER_INTERACTION_ECOMMERCE;

        if ($paymentMethod == AdyenMotoConfigProvider::CODE &&
            $this->appState->getAreaCode() == \Magento\Framework\App\Area::AREA_ADMINHTML) {
            // Backend CC orders are MOTO
            $shopperInteraction = self::SHOPPER_INTERACTION_MOTO;
        } elseif ($paymentMethod == AdyenOneclickConfigProvider::CODE
            || $paymentMethod == AdyenCcConfigProvider::CC_VAULT_CODE) {
            // OneClick and Vault are ContAuth
            $shopperInteraction = self::SHOPPER_INTERACTION_CONTAUTH;
        }

        $requestBody['body']['shopperInteraction'] = $shopperInteraction;
        return $requestBody;
    }
}
