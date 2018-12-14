<?php
/**
 *                       ######
 *                       ######
 * ############    ####( ######  #####. ######  ############   ############
 * #############  #####( ######  #####. ######  #############  #############
 *        ######  #####( ######  #####. ######  #####  ######  #####  ######
 * ###### ######  #####( ######  #####. ######  #####  #####   #####  ######
 * ###### ######  #####( ######  #####. ######  #####          #####  ######
 * #############  #############  #############  #############  #####  ######
 *  ############   ############  #############   ############  #####  ######
 *                                      ######
 *                               #############
 *                               ############
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2015 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Request;

use Magento\Payment\Gateway\Request\BuilderInterface;

/**
 * Class AddressDataBuilder
 */
class AddressDataBuilder implements BuilderInterface
{
    /**
     * @var \Adyen\Payment\Helper\Data
     */
    private $adyenHelper;

    /**
     * AddressDataBuilder constructor.
     *
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     */
    public function __construct(\Adyen\Payment\Helper\Data $adyenHelper)
    {
        $this->adyenHelper = $adyenHelper;
    }
    
    /**
     * Add delivery\billing details into request
     * 
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject)
    {
        /** @var \Magento\Payment\Gateway\Data\PaymentDataObject $paymentDataObject */
        $paymentDataObject = \Magento\Payment\Gateway\Helper\SubjectReader::readPayment($buildSubject);
        $order = $paymentDataObject->getOrder();

        $result = [];

        $billingAddress = $order->getBillingAddress();
        if ($billingAddress) {

            $requestBilling = ["street" => "N/A",
                "postalCode" => 'N/A',
                "city" => "N/A",
                "houseNumberOrName" => 'N/A',
                "stateOrProvince" => 'N/A',
                "country" => "ZZ"
            ];

			if ($billingAddress->getStreetLine1()) {
				$address = $this->adyenHelper->getStreet($billingAddress->getStreetLine1());

				if ($address) {
					$requestBilling["street"] = $address["street"];
					$requestBilling["houseNumberOrName"] = $address["house_number"];
				} else {
					$requestBilling["street"] = $billingAddress->getStreetLine1();
				}
			}

            if ($billingAddress->getPostcode()) {
                $requestBilling["postalCode"] = $billingAddress->getPostcode();
            }

            if ($billingAddress->getCity()) {
                $requestBilling["city"] = $billingAddress->getCity();
            }

            if ($billingAddress->getRegionCode()) {
                $requestBilling["stateOrProvince"] = $billingAddress->getRegionCode();
            }

            if ($billingAddress->getCountryId()) {
                $requestBilling["country"] = $billingAddress->getCountryId();
            }

            $result['billingAddress'] = $requestBilling;
        }
        
        $shippingAddress = $order->getShippingAddress();

        if ($shippingAddress) {

			if ($shippingAddress->getStreetLine1()) {
				$address = $this->adyenHelper->getStreet($shippingAddress->getStreetLine1());

				if ($address) {
					$requestDelivery["street"] = $address["street"];
					$requestDelivery["houseNumberOrName"] = $address["house_number"];
				} else {
					$requestDelivery["street"] = $shippingAddress->getStreetLine1();
				}
			}

			if ($shippingAddress->getPostcode()) {
				$requestDelivery["postalCode"] = $shippingAddress->getPostcode();
			}

			if ($shippingAddress->getCity()) {
				$requestDelivery["city"] = $shippingAddress->getCity();
			}

			if ($shippingAddress->getRegionCode()) {
				$requestDelivery["stateOrProvince"] = $shippingAddress->getRegionCode();
			}

			if ($shippingAddress->getCountryId()) {
				$requestDelivery["country"] = $shippingAddress->getCountryId();
			}

            $result['deliveryAddress'] = $requestDelivery;
        }

        return $result;
    }
}
