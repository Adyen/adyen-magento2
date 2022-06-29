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

use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Model\Ui\AdyenCcConfigProvider;
use Adyen\Payment\Model\Ui\AdyenPayByLinkConfigProvider;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

class ShopperInteractionDataBuilder implements BuilderInterface
{
    /** @var State  */
    private $appState;

    /** @var PaymentMethods */
    private $paymentMethodsHelper;

    /**
     * RecurringDataBuilder constructor.
     *
     * @param Context $context
     * @param PaymentMethods $paymentMethodsHelper
     */
    public function __construct(
        Context $context,
        PaymentMethods $paymentMethodsHelper
    ) {
        $this->appState = $context->getAppState();
        $this->paymentMethodsHelper = $paymentMethodsHelper;
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
        $shopperInteraction = 'Ecommerce';

        if ($paymentMethod == AdyenCcConfigProvider::CODE &&
            $this->appState->getAreaCode() == \Magento\Framework\App\Area::AREA_ADMINHTML) {
            // Backend CC orders are MOTO
            $shopperInteraction = "Moto";
        } elseif ($this->paymentMethodsHelper->isRecurringProvider($paymentMethod)) {
            $shopperInteraction = 'ContAuth';
        }

        $requestBody['body']['shopperInteraction'] = $shopperInteraction;
        return $requestBody;
    }
}
