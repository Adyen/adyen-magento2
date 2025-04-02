<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2025 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Response;

use Adyen\Payment\Helper\Data;
use Adyen\Payment\Model\Ui\AdyenPayByLinkConfigProvider;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Model\Order\Payment;

class CheckoutPaymentLinksResponseHandler implements HandlerInterface
{
    /**
     * @param Data $adyenHelper
     */
    public function __construct(
        protected readonly Data $adyenHelper
    ) { }

    /**
     * @param array $handlingSubject
     * @param array $response
     * @return void
     * @throws LocalizedException
     */
    public function handle(array $handlingSubject, array $response): void
    {
        $paymentDataObject = SubjectReader::readPayment($handlingSubject);
        /** @var Payment $payment */
        $payment = $paymentDataObject->getPayment();

        // Set the transaction not to processing by default wait for notification
        $payment->setIsTransactionPending(true);
        // Do not close the transaction, so you can do a cancel() and void
        $payment->setIsTransactionClosed(false);
        // Do not close the parent transaction
        $payment->setShouldCloseParentTransaction(false);

        if (!empty($response['url'])) {
            $payment->setAdditionalInformation(AdyenPayByLinkConfigProvider::URL_KEY, $response['url']);
        }

        if (!empty($response['expiresAt'])) {
            $payment->setAdditionalInformation(AdyenPayByLinkConfigProvider::EXPIRES_AT_KEY, $response['expiresAt']);
        }

        if (!empty($response['id'])) {
            $payment->setAdditionalInformation(AdyenPayByLinkConfigProvider::ID_KEY, $response['id']);
        }
    }
}
