<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2020 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper;

use Adyen\Payment\Exception\PaymentMethodException;
use Adyen\Payment\Model\Method\PaymentMethodInterface;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Ui\AdyenCcConfigProvider;
use Adyen\Payment\Model\Ui\AdyenOneclickConfigProvider;
use Adyen\Payment\Observer\AdyenPaymentMethodDataAssignObserver;
use Exception;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\ResourceModel\Order;

class PaymentResponseHandler
{
    const AUTHORISED = 'Authorised';
    const REFUSED = 'Refused';
    const REDIRECT_SHOPPER = 'RedirectShopper';
    const IDENTIFY_SHOPPER = 'IdentifyShopper';
    const CHALLENGE_SHOPPER = 'ChallengeShopper';
    const RECEIVED = 'Received';
    const PENDING = 'Pending';
    const PRESENT_TO_SHOPPER = 'PresentToShopper';
    const ERROR = 'Error';
    const CANCELLED = 'Cancelled';
    const ADYEN_TOKENIZATION = 'Adyen Tokenization';
    const VAULT = 'Magento Vault';

    /**
     * @var AdyenLogger
     */
    private $adyenLogger;

    /**
     * @var Data
     */
    private $adyenHelper;

    /**
     * @var Vault
     */
    private $vaultHelper;

    /**
     * @var Order
     */
    private $orderResourceModel;

    /**
     * @var Data
     */
    private $dataHelper;

    /**
     * @var Recurring
     */
    private $recurringHelper;
    /**
     * @var Quote
     */
    private $quoteHelper;

    /**
     * @var Config
     */
    private $configHelper;

    public function __construct(
        AdyenLogger $adyenLogger,
        Data $adyenHelper,
        Vault $vaultHelper,
        Order $orderResourceModel,
        Data $dataHelper,
        Recurring $recurringHelper,
        Quote $quoteHelper,
        Config $configHelper
    ) {
        $this->adyenLogger = $adyenLogger;
        $this->adyenHelper = $adyenHelper;
        $this->vaultHelper = $vaultHelper;
        $this->orderResourceModel = $orderResourceModel;
        $this->dataHelper = $dataHelper;
        $this->recurringHelper = $recurringHelper;
        $this->quoteHelper = $quoteHelper;
        $this->configHelper = $configHelper;
    }

    public function formatPaymentResponse($resultCode, $action = null, $additionalData = null)
    {
        switch ($resultCode) {
            case self::AUTHORISED:
                return [
                    "isFinal" => true,
                    "resultCode" => $resultCode
                ];
            case self::REFUSED:
            case self::ERROR:
                return [
                    "isFinal" => true,
                    "resultCode" => $resultCode
                ];
            case self::REDIRECT_SHOPPER:
            case self::IDENTIFY_SHOPPER:
            case self::CHALLENGE_SHOPPER:
            case self::PENDING:
                return [
                    "isFinal" => false,
                    "resultCode" => $resultCode,
                    "action" => $action
                ];
            case self::PRESENT_TO_SHOPPER:
                return [
                    "isFinal" => true,
                    "resultCode" => $resultCode,
                    "action" => $action
                ];
            case self::RECEIVED:
                return [
                    "isFinal" => true,
                    "resultCode" => $resultCode,
                    "additionalData" => $additionalData,
                ];
            default:
                return [
                    "isFinal" => true,
                    "resultCode" => self::ERROR,
                ];
        }
    }

    /**
     * @param $paymentsResponse
     * @param Payment $payment
     * @param OrderInterface|null $order
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function handlePaymentResponse($paymentsResponse, $payment, $order = null)
    {
        if (empty($paymentsResponse)) {
            $this->adyenLogger->error("Payment details call failed, paymentsResponse is empty");
            return false;
        }

        if (!empty($paymentsResponse['resultCode'])) {
            $payment->setAdditionalInformation('resultCode', $paymentsResponse['resultCode']);
        }

        if (!empty($paymentsResponse['action'])) {
            $payment->setAdditionalInformation('action', $paymentsResponse['action']);
        }

        if (!empty($paymentsResponse['additionalData'])) {
            $payment->setAdditionalInformation('additionalData', $paymentsResponse['additionalData']);
        }

        if (!empty($paymentsResponse['pspReference'])) {
            $payment->setAdditionalInformation('pspReference', $paymentsResponse['pspReference']);
        }

        if (!empty($paymentsResponse['details'])) {
            $payment->setAdditionalInformation('details', $paymentsResponse['details']);
        }

        switch ($paymentsResponse['resultCode']) {
            case self::PRESENT_TO_SHOPPER:
            case self::PENDING:
            case self::RECEIVED:
            case self::IDENTIFY_SHOPPER:
            case self::CHALLENGE_SHOPPER:
                break;
            //We don't need to handle these resultCodes
            case self::REDIRECT_SHOPPER:
                $this->adyenLogger->addAdyenResult("Customer was redirected.");
                if ($order) {
                    $order->addStatusHistoryComment(
                        __(
                            'Customer was redirected to an external payment page. (In case of card payments the shopper is redirected to the bank for 3D-secure validation.) Once the shopper is authenticated,
                        the order status will be updated accordingly.
                        <br />Make sure that your notifications are being processed!
                        <br />If the order is stuck on this status, the shopper abandoned the session.
                        The payment can be seen as unsuccessful.
                        <br />The order can be automatically cancelled based on the OFFER_CLOSED notification.
                        Please contact Adyen Support to enable this.'
                        ),
                        $order->getStatus()
                    )->save();
                }
                break;
            case self::AUTHORISED:
                if (!empty($paymentsResponse['pspReference'])) {
                    // set pspReference as transactionId
                    $payment->setCcTransId($paymentsResponse['pspReference']);
                    $payment->setLastTransId($paymentsResponse['pspReference']);

                    // set transaction
                    $payment->setTransactionId($paymentsResponse['pspReference']);
                }
                $paymentMethod = $payment->getMethodInstance();

                if ($this->vaultHelper->hasRecurringDetailReference($paymentsResponse) &&
                    $paymentMethod->getCode() !== AdyenOneclickConfigProvider::CODE) {
                    $storeId = $paymentMethod->getStore();
                    $paymentInstanceCode = $paymentMethod->getCode();
                    $storePaymentMethods = $this->configHelper->isStoreAlternativePaymentMethodEnabled($storeId);

                    if ($storePaymentMethods && $paymentMethod instanceof PaymentMethodInterface) {
                        $brand = $payment->getAdditionalInformation(AdyenPaymentMethodDataAssignObserver::BRAND_CODE);
                        try {
                            $this->vaultHelper->saveRecurringDetails($payment, $paymentsResponse['additionalData']);
                        } catch (PaymentMethodException $e) {
                            $this->adyenLogger->error(sprintf(
                                'Unable to create payment method with tx variant %s in details handler',
                                $brand
                            ));
                        }
                    } elseif ($paymentInstanceCode === AdyenCcConfigProvider::CODE) {
                        $order = $payment->getOrder();
                        $recurringMode = $this->configHelper->getCardRecurringMode($storeId);

                        // if Adyen Tokenization set up, create entry in paypal_billing_agreement table
                        if ($recurringMode === self::ADYEN_TOKENIZATION) {
                            $this->recurringHelper->createAdyenBillingAgreement(
                                $order,
                                $paymentsResponse['additionalData'],
                                $payment->getAdditionalInformation()
                            );
                        // if Vault set up, create entry in vault_payment_token table
                        } elseif ($recurringMode === self::VAULT) {
                            $this->vaultHelper->saveRecurringCardDetails($payment, $paymentsResponse['additionalData']);
                        }
                    }
                }

                if (!empty($paymentsResponse['donationToken'])) {
                    $payment->setAdditionalInformation('donationToken', $paymentsResponse['donationToken']);
                }

                $this->orderResourceModel->save($order);
                try {
                    $this->quoteHelper->disableQuote($order->getQuoteId());
                } catch (Exception $e) {
                    $this->adyenLogger->error('Failed to disable quote: ' . $e->getMessage(), [
                        'quoteId' => $order->getQuoteId()
                    ]);
                }
                break;
            case self::REFUSED:
                // Cancel order in case result is refused
                if (null !== $order) {
                    // Set order to new so it can be cancelled
                    $order->setState(\Magento\Sales\Model\Order::STATE_NEW);
                    $order->save();
                    $order->setActionFlag(\Magento\Sales\Model\Order::ACTION_FLAG_CANCEL, true);
                    $this->dataHelper->cancelOrder($order);
                }
                return false;
            case self::ERROR:
            default:
                $this->adyenLogger->error(
                    sprintf("Payment details call failed for action, resultCode is %s Raw API responds: %s",
                        $paymentsResponse['resultCode'],
                        json_encode($paymentsResponse)
                    ));

                return false;
        }
        return true;
    }
}
