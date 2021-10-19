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
 * Copyright (c) 2021 Adyen NV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Response;

use Adyen\Payment\Helper\Data;
use Adyen\Payment\Model\Ui\AdyenPayByLinkConfigProvider;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;

class PayByLinkResponseHandler implements HandlerInterface
{
    /**
     * @var Data
     */
    protected $adyenHelper;

    public function __construct(
        Data $adyenHelper
    ) {
        $this->adyenHelper = $adyenHelper;
    }

    /**
     * @param array $handlingSubject
     * @param array $response
     */
    public function handle(array $handlingSubject, array $response)
    {
        $paymentDataObject = SubjectReader::readPayment($handlingSubject);

        $payment = $paymentDataObject->getPayment();

        // Set transaction to pending by default, wait for notification.
        // This must match the status checked for expired orders/payments in
        // Adyen\Payment\Cron\Providers\PayByLinkExpiredPaymentOrdersProvider::provide
        $payment->setIsTransactionPending(true);

        if (!empty($response['url'])) {
            $payment->setAdditionalInformation(AdyenPayByLinkConfigProvider::URL_KEY, $response['url']);
        }

        if (!empty($response['expiresAt'])) {
            $payment->setAdditionalInformation(AdyenPayByLinkConfigProvider::EXPIRES_AT_KEY, $response['expiresAt']);
        }

        if (!empty($response['id'])) {
            $payment->setAdditionalInformation(AdyenPayByLinkConfigProvider::ID_KEY, $response['id']);
        }

        // do not close transaction so you can do a cancel() and void
        $payment->setIsTransactionClosed(false);
        $payment->setShouldCloseParentTransaction(false);
    }
}
