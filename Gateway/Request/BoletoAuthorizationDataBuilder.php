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

class BoletoAuthorizationDataBuilder implements BuilderInterface
{

    /**
     * @var \Adyen\Payment\Helper\Data
     */
    private $adyenHelper;

    /**
     * CaptureDataBuilder constructor.
     *
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     */
    public function __construct(\Adyen\Payment\Helper\Data $adyenHelper)
    {
        $this->adyenHelper = $adyenHelper;
    }
    
    /**
     * @param array $buildSubject
     * @return mixed
     */
    public function build(array $buildSubject)
    {
        /** @var \Magento\Payment\Gateway\Data\PaymentDataObject $paymentDataObject */
        /** @var \Magento\Payment\Gateway\Data\PaymentDataObject $paymentDataObject */
        $paymentDataObject = \Magento\Payment\Gateway\Helper\SubjectReader::readPayment($buildSubject);
        $payment = $paymentDataObject->getPayment();
        $order = $paymentDataObject->getOrder();
        $storeId = $order->getStoreId();

        $request = [];

        $request['socialSecurityNumber'] = $payment->getAdditionalInformation("social_security_number");
        $request['selectedBrand'] = $payment->getAdditionalInformation("boleto_type");

        $shopperName = [
            'firstName' => $payment->getAdditionalInformation("firstname"),
            'lastName' => $payment->getAdditionalInformation("lastname"),
        ];
        $request['shopperName'] = $shopperName;


        $deliveryDays = (int) $this->adyenHelper->getAdyenBoletoConfigData("delivery_days", $storeId);
        $deliveryDays = (!empty($deliveryDays)) ? $deliveryDays : 5;
        $deliveryDate = date(
            "Y-m-d\TH:i:s ",
            mktime(date("H"),
                date("i"),
                date("s"),
                date("m"),
                date("j") + $deliveryDays,
                date("Y"))
        );

        $request['deliveryDate'] = $deliveryDate;
        
        return $request;
    }
}