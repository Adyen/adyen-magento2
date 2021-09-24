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
 * Copyright (c) 2019 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Request;

use Magento\Payment\Gateway\Request\BuilderInterface;
use Adyen\Payment\Observer\AdyenCcDataAssignObserver;

class CcBackendAuthorizationDataBuilder implements BuilderInterface
{

    /**
     * @param array $buildSubject
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function build(array $buildSubject)
    {
        /** @var \Magento\Payment\Gateway\Data\PaymentDataObject $paymentDataObject */
        $paymentDataObject = \Magento\Payment\Gateway\Helper\SubjectReader::readPayment($buildSubject);
        $payment = $paymentDataObject->getPayment();
        $requestBody = $payment->getAdditionalInformation(AdyenCcDataAssignObserver::STATE_DATA);

        // if installments is set add it into the request
        if ($payment->getAdditionalInformation(AdyenCcDataAssignObserver::NUMBER_OF_INSTALLMENTS) &&
            $payment->getAdditionalInformation(AdyenCcDataAssignObserver::NUMBER_OF_INSTALLMENTS) > 0
        ) {
            $requestBody['installments']['value'] = $payment->getAdditionalInformation(
                AdyenCcDataAssignObserver::NUMBER_OF_INSTALLMENTS
            );
        }
        $request['body'] = $requestBody;

        return $request;
    }
}
