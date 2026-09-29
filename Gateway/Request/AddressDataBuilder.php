<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2015 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Request;

use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Helper\Requests;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

class AddressDataBuilder implements BuilderInterface
{
    /**
     * AddressDataBuilder constructor.
     *
     * @param Requests $adyenRequestsHelper
     */
    public function __construct(
        private readonly Requests $adyenRequestsHelper
    ) { }

    /**
     * Add delivery\billing details into request
     *
     * @param array $buildSubject
     * @return array
     * @throws LocalizedException
     */
    public function build(array $buildSubject): array
    {
        /** @var PaymentDataObject $paymentDataObject */
        $paymentDataObject = SubjectReader::readPayment($buildSubject);
        $order = $paymentDataObject->getOrder();
        $billingAddress = $order->getBillingAddress();
        $shippingAddress = $order->getShippingAddress();

        $addressRequest = $this->adyenRequestsHelper->buildAddressData(
            $billingAddress,
            $shippingAddress,
            $order->getStoreId()
        );

        // Add delivery customer information for Riverty payment method.
        if (strcmp($paymentDataObject->getPayment()->getMethodInstance()->getCode(),
                PaymentMethods::ADYEN_RIVERTY) === 0 && !empty($addressRequest['deliveryAddress'])) {
            $addressRequest['deliveryAddress']['firstName'] = $shippingAddress->getFirstname();
            $addressRequest['deliveryAddress']['lastName'] = $shippingAddress->getLastname();
        }

        return [
            'body' => $addressRequest
        ];
    }
}
