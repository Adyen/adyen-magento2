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

            // filter housenumber from streetLine1
            $requestBilling = ["street" => $billingAddress->getStreetLine1(),
                "postalCode" => $billingAddress->getPostcode(),
                "city" => $billingAddress->getCity(),
                "houseNumberOrName" => 'NA',
                "stateOrProvince" => $billingAddress->getRegionCode(),
                "country" => $billingAddress->getCountryId()
            ];

            // houseNumberOrName is mandatory
            if ($requestBilling['houseNumberOrName'] == "") {
                $requestBilling['houseNumberOrName'] = "NA";
            }

            $result['billingAddress'] = $requestBilling;
        }
        
        $shippingAddress = $order->getShippingAddress();
        if ($shippingAddress) {
            
            // filter housenumber from streetLine1
            $requestDelivery = ["street" => $shippingAddress->getStreetLine1(),
                "postalCode" => $shippingAddress->getPostcode(),
                "city" => $shippingAddress->getCity(),
                "houseNumberOrName" => 'NA',
                "stateOrProvince" => $shippingAddress->getRegionCode(),
                "country" => $shippingAddress->getCountryId()
            ];

            // houseNumberOrName is mandatory
            if ($requestDelivery['houseNumberOrName'] == "") {
                $requestDelivery['houseNumberOrName'] = "NA";
            }

            $result['deliveryAddress'] = $requestDelivery;
        }

        return $result;
    }
}
