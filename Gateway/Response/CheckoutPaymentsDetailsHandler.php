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

namespace Adyen\Payment\Gateway\Response;

use Adyen\Payment\Helper\PaymentResponseHandler;
use Adyen\Payment\Model\PaymentResponseFactory;
use Adyen\Payment\Model\ResourceModel\PaymentResponse;
use Adyen\Payment\Model\ResourceModel\PaymentResponse\Collection;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Payment\Gateway\Response\HandlerInterface;

class CheckoutPaymentsDetailsHandler implements HandlerInterface
{
    /**
     * @var \Adyen\Payment\Helper\Data
     */
    protected $adyenHelper;
    /**
     * @var PaymentResponseHandler
     */
    private $paymentResponseHandler;

    /**
     * @var PaymentResponseFactory
     */
    private $paymentResponseFactory;

    /**
     * @var \Adyen\Payment\Model\ResourceModel\PaymentResponse
     */
    private $paymentResponseResourceModel;

    /**
     * @var SerializerInterface
     */
    private $serializer;
    /**
     * @var Collection
     */
    private $paymentResponseCollection;

    public function __construct(
        \Adyen\Payment\Helper\Data $adyenHelper,
        PaymentResponseHandler $paymentResponseHandler,

        PaymentResponseFactory $paymentResponseFactory,
        PaymentResponse $paymentResponseResourceModel,
        Collection $paymentResponseCollection,
        SerializerInterface $serializer
    ) {
        $this->adyenHelper = $adyenHelper;
        $this->paymentResponseHandler = $paymentResponseHandler;

        $this->paymentResponseFactory = $paymentResponseFactory;
        $this->paymentResponseResourceModel = $paymentResponseResourceModel;
        $this->paymentResponseCollection = $paymentResponseCollection;
        $this->serializer = $serializer;
    }

    /**
     * This is being used for all checkout methods (adyen hpp payment method)
     *
     * @param array $handlingSubject
     * @param array $response
     */
    public function handle(array $handlingSubject, array $response)
    {
        $paymentDataObject = \Magento\Payment\Gateway\Helper\SubjectReader::readPayment($handlingSubject);

        $payment = $paymentDataObject->getPayment();

        // save payment response
        $this->paymentResponseHandler->saveAdyenResponseData($response, $payment);

        // set transaction not to processing by default wait for notification
        $payment->setIsTransactionPending(true);

        // Email sending is set at CheckoutDataBuilder for Boleto
        // Otherwise, we don't want to send a confirmation email
        if ($payment->getMethod() != \Adyen\Payment\Model\Ui\AdyenBoletoConfigProvider::CODE) {
            $payment->getOrder()->setCanSendNewEmailFlag(false);
        }

        if (!empty($response['pspReference'])) {
            // set pspReference as transactionId
            $payment->setCcTransId($response['pspReference']);
            $payment->setLastTransId($response['pspReference']);

            // set transaction
            $payment->setTransactionId($response['pspReference']);
        }

        if (!empty($response['additionalData']['recurring.recurringDetailReference']) &&
            !$this->adyenHelper->isCreditCardVaultEnabled() &&
            $payment->getMethodInstance()->getCode() !== \Adyen\Payment\Model\Ui\AdyenOneclickConfigProvider::CODE
        ) {
            $order = $payment->getOrder();
            $this->adyenHelper->createAdyenBillingAgreement($order, $response['additionalData']);
        }

        // do not close transaction so you can do a cancel() and void
        $payment->setIsTransactionClosed(false);
        $payment->setShouldCloseParentTransaction(false);
    }
}
