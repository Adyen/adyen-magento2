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

namespace Adyen\Payment\Model;

use Magento\Framework\Webapi\Exception;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;

class Cron
{

    /**
     * Logging instance
     * @var \Adyen\Payment\Logger\AdyenLogger
     */
    protected $_logger;

    /**
     * @var Resource\Notification\CollectionFactory
     */
    protected $_notificationFactory;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $_orderFactory;

    /**
     * @var \Magento\Sales\Model\Order
     */
    protected $_order;

    /**
     * Core store config
     *
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_scopeConfig;

    /**
     * @var \Adyen\Payment\Helper\Data
     */
    protected $_adyenHelper;

    /**
     * @var OrderSender
     */
    protected $_orderSender;

    /**
     * @var \Magento\Framework\DB\TransactionFactory
     */
    protected $_transactionFactory;

    /**
     * @var \Adyen\Payment\Model\Billing\AgreementFactory
     */
    protected $_billingAgreementFactory;

    /**
     * @var Resource\Billing\Agreement\CollectionFactory
     */
    protected $_billingAgreementCollectionFactory;

    /**
     * @var Api\PaymentRequest
     */
    protected $_adyenPaymentRequest;

    /**
     * notification attributes
     */
    protected $_pspReference;

    /**
     * @var
     */
    protected $_originalReference;

    /**
     * @var
     */
    protected $_merchantReference;

    /**
     * @var
     */
    protected $_eventCode;

    /**
     * @var
     */
    protected $_success;

    /**
     * @var
     */
    protected $_paymentMethod;

    /**
     * @var
     */
    protected $_reason;

    /**
     * @var
     */
    protected $_value;

    /**
     * @var
     */
    protected $_boletoOriginalAmount;

    /**
     * @var
     */
    protected $_boletoPaidAmount;

    /**
     * @var
     */
    protected $_modificationResult;

    /**
     * @var
     */
    protected $_klarnaReservationNumber;

    /**
     * @var
     */
    protected $_fraudManualReview;

    /**
     * @var Order\PaymentFactory
     */
    protected $_adyenOrderPaymentFactory;

    /**
     * @var Resource\Order\Payment\CollectionFactory
     */
    protected $_adyenOrderPaymentCollectionFactory;

    /**
     * Cron constructor.
     *
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Adyen\Payment\Logger\AdyenLogger $adyenLogger
     * @param Resource\Notification\CollectionFactory $notificationFactory
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     * @param OrderSender $orderSender
     * @param \Magento\Framework\DB\TransactionFactory $transactionFactory
     * @param Billing\AgreementFactory $billingAgreementFactory
     * @param Resource\Billing\Agreement\CollectionFactory $billingAgreementCollectionFactory
     * @param Api\PaymentRequest $paymentRequest
     * @param Order\PaymentFactory $adyenOrderPaymentFactory
     * @param Resource\Order\Payment\CollectionFactory $adyenOrderPaymentCollectionFactory
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Adyen\Payment\Logger\AdyenLogger $adyenLogger,
        \Adyen\Payment\Model\Resource\Notification\CollectionFactory $notificationFactory,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Adyen\Payment\Helper\Data $adyenHelper,
        OrderSender $orderSender,
        \Magento\Framework\DB\TransactionFactory $transactionFactory,
        \Adyen\Payment\Model\Billing\AgreementFactory $billingAgreementFactory,
        \Adyen\Payment\Model\Resource\Billing\Agreement\CollectionFactory $billingAgreementCollectionFactory,
        \Adyen\Payment\Model\Api\PaymentRequest $paymentRequest,
        \Adyen\Payment\Model\Order\PaymentFactory $adyenOrderPaymentFactory,
        \Adyen\Payment\Model\Resource\Order\Payment\CollectionFactory $adyenOrderPaymentCollectionFactory
    ) {
        $this->_scopeConfig = $scopeConfig;
        $this->_adyenLogger = $adyenLogger;
        $this->_notificationFactory = $notificationFactory;
        $this->_orderFactory = $orderFactory;
        $this->_adyenHelper = $adyenHelper;
        $this->_orderSender = $orderSender;
        $this->_transactionFactory = $transactionFactory;
        $this->_billingAgreementFactory = $billingAgreementFactory;
        $this->_billingAgreementCollectionFactory = $billingAgreementCollectionFactory;
        $this->_adyenPaymentRequest = $paymentRequest;
        $this->_adyenOrderPaymentFactory = $adyenOrderPaymentFactory;
        $this->_adyenOrderPaymentCollectionFactory = $adyenOrderPaymentCollectionFactory;
    }

    /**
     * Process the notification
     * @return void
     */
    public function processNotification()
    {
        $this->_order = null;

        // execute notifications from 2 minute or earlier because order could not yet been created by magento
        $dateStart = new \DateTime();
        $dateStart->modify('-5 day');
        $dateEnd = new \DateTime();
        $dateEnd->modify('-1 minute');
        $dateRange = ['from' => $dateStart, 'to' => $dateEnd, 'datetime' => true];

        // create collection
        $notifications = $this->_notificationFactory->create();
        $notifications->addFieldToFilter('done', 0);
        $notifications->addFieldToFilter('created_at', $dateRange);

        // loop over the notifications
        $count = 0;
        foreach ($notifications as $notification) {

            $this->_adyenLogger->addAdyenNotificationCronjob(
                sprintf("Processing notification %s", $notification->getEntityId())
            );

            /**
             *  If the event is a RECURRING_CONTRACT wait an extra 5 minutes
             * before processing so we are sure the RECURRING_CONTRACT
             */
            if (trim($notification->getEventCode()) == Notification::RECURRING_CONTRACT &&
                strtotime($notification->getCreatedAt()) >= strtotime('-5 minutes', time())) {
                $this->_adyenLogger->addAdyenNotificationCronjob(
                    "This is a recurring_contract notification wait an extra 5 minutes 
                    before processing this to make sure the contract exists"
                );
                continue;
            }

            // log the executed notification
            $this->_adyenLogger->addAdyenNotificationCronjob(print_r($notification->debug(), 1));

            // get order
            $incrementId = $notification->getMerchantReference();

            $this->_order = $this->_orderFactory->create()->loadByIncrementId($incrementId);
            if (!$this->_order->getId()) {

                // order does not exists remove from queue
                $notification->delete();
                continue;
            }

            // declare all variables that are needed
            $this->_declareVariables($notification);

            // add notification to comment history status is current status
            $this->_addStatusHistoryComment();

            $previousAdyenEventCode = $this->_order->getData('adyen_notification_event_code');

            // update order details
            $this->_updateAdyenAttributes($notification);

            // check if success is true of false
            if (strcmp($this->_success, 'false') == 0 || strcmp($this->_success, '0') == 0) {
                /*
                 * Only cancel the order when it is in state pending, payment review or
                 * if the ORDER_CLOSED is failed (means split payment has not be successful)
                 */
                if ($this->_order->getState() === \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT ||
                    $this->_order->getState() === \Magento\Sales\Model\Order::STATE_PAYMENT_REVIEW ||
                    $this->_eventCode == Notification::ORDER_CLOSED) {

                    $this->_adyenLogger->addAdyenNotificationCronjob('Going to cancel the order');

                    // if payment is API check, check if API result pspreference is the same as reference
                    if ($this->_eventCode == NOTIFICATION::AUTHORISATION && $this->_getPaymentMethodType() == 'api') {
                        // don't cancel the order becasue order was successfull through api
                        $this->_adyenLogger->addAdyenNotificationCronjob(
                            'order is not cancelled because api result was succesfull'
                        );
                    } else {
                        /*
                         * don't cancel the order if previous state is authorisation with success=true
                         * Split payments can fail if the second payment has failed the first payment is
                         * refund/cancelled as well so if it is a split payment that failed cancel the order as well
                         */
                        if ($previousAdyenEventCode != "AUTHORISATION : TRUE" ||
                            $this->_eventCode == Notification::ORDER_CLOSED) {
                            $this->_holdCancelOrder(false);
                        } else {
                            $this->_order->setData('adyen_notification_event_code', $previousAdyenEventCode);
                            $this->_adyenLogger->addAdyenNotificationCronjob(
                                'order is not cancelled because previous notification 
                                was an authorisation that succeeded'
                            );
                        }
                    }
                } else {
                    $this->_adyenLogger->addAdyenNotificationCronjob(
                        'Order is already processed so ignore this notification state is:' . $this->_order->getState()
                    );
                }
            } else {
                // Notification is successful
                $this->_processNotification();
            }

            $this->_order->save();

            // set done to true
            $dateEnd = new \DateTime();
            $notification->setDone(true);
            $notification->setUpdatedAt($dateEnd);
            $notification->save();
            $this->_adyenLogger->addAdyenNotificationCronjob(
                sprintf("Notification %s is processed", $notification->getEntityId())
            );
            ++$count;
        }

        if ($count > 0) {
            $this->_adyenLogger->addAdyenNotificationCronjob(sprintf("Cronjob updated %s notification(s)", $count));
        }
    }

    /**
     * Declare private variables for processing notification
     *
     * @param Object $notification
     * @return void
     */
    protected function _declareVariables($notification)
    {
        //  declare the common parameters
        $this->_pspReference = $notification->getPspreference();
        $this->_originalReference = $notification->getOriginalReference();
        $this->_merchantReference = $notification->getMerchantReference();
        $this->_eventCode = $notification->getEventCode();
        $this->_success = $notification->getSuccess();
        $this->_paymentMethod = $notification->getPaymentMethod();
        $this->_reason = $notification->getPaymentMethod();
        $this->_value = $notification->getAmountValue();


        $additionalData = unserialize($notification->getAdditionalData());

        // boleto data
        if ($this->_paymentMethodCode() == "adyen_boleto") {
            if ($additionalData && is_array($additionalData)) {
                $boletobancario = isset($additionalData['boletobancario']) ? $additionalData['boletobancario'] : null;
                if ($boletobancario && is_array($boletobancario)) {
                    $this->_boletoOriginalAmount =
                        isset($boletobancario['originalAmount']) ? trim($boletobancario['originalAmount']) : "";
                    $this->_boletoPaidAmount =
                        isset($boletobancario['paidAmount']) ? trim($boletobancario['paidAmount']) : "";
                }
            }
        }

        if ($additionalData && is_array($additionalData)) {

            // check if the payment is in status manual review
            $fraudManualReview = isset($additionalData['fraudManualReview']) ?
                $additionalData['fraudManualReview'] : "";
            if ($fraudManualReview == "true") {
                $this->_fraudManualReview = true;
            } else {
                $this->_fraudManualReview = false;
            }

            // modification.action is it for JSON
            $modificationActionJson = isset($additionalData['modification.action']) ?
                $additionalData['modification.action'] : null;
            if ($modificationActionJson != "") {
                $this->_modificationResult = $modificationActionJson;
            }

            $modification = isset($additionalData['modification']) ? $additionalData['modification'] : null;
            if ($modification && is_array($modification)) {
                $this->_modificationResult = isset($modification['action']) ? trim($modification['action']) : "";
            }
            $additionalData2 = isset($additionalData['additionalData']) ? $additionalData['additionalData'] : null;
            if ($additionalData2 && is_array($additionalData2)) {
                $this->_klarnaReservationNumber = isset($additionalData2['acquirerReference']) ? trim($additionalData2['acquirerReference']) : "";
            }
        }
    }

    /**
     * @return mixed
     */
    protected function _paymentMethodCode()
    {
        return $this->_order->getPayment()->getMethod();
    }

    /**
     * @return mixed
     */
    protected function _getPaymentMethodType()
    {
        return $this->_order->getPayment()->getPaymentMethodType();
    }

    /**
     * @desc order comments or history
     * @param type $order
     */
    protected function _addStatusHistoryComment()
    {
        $successResult = (strcmp($this->_success, 'true') == 0 ||
            strcmp($this->_success, '1') == 0) ? 'true' : 'false';
        $success = (!empty($this->_reason)) ? "$successResult <br />reason:$this->_reason" : $successResult;

        if ($this->_eventCode == Notification::REFUND || $this->_eventCode == Notification::CAPTURE) {

            $currency = $this->_order->getOrderCurrencyCode();

            // check if it is a full or partial refund
            $amount = $this->_value;
            $orderAmount = (int) $this->_adyenHelper->formatAmount($this->_order->getGrandTotal(), $currency);

            $this->_adyenLogger->addAdyenNotificationCronjob(
                'amount notification:'.$amount . ' amount order:'.$orderAmount
            );

            if ($amount == $orderAmount) {
                $this->_order->setData(
                    'adyen_notification_event_code', $this->_eventCode . " : " . strtoupper($successResult)
                );
            } else {
                $this->_order->setData(
                    'adyen_notification_event_code', "(PARTIAL) " .
                    $this->_eventCode . " : " . strtoupper($successResult)
                );
            }
        } else {
            $this->_order->setData(
                'adyen_notification_event_code', $this->_eventCode . " : " . strtoupper($successResult)
            );
        }

        // if payment method is klarna, ratepay or openinvoice/afterpay show the reservartion number
        if (($this->_paymentMethod == "klarna" || $this->_paymentMethod == "afterpay_default" ||
                $this->_paymentMethod == "openinvoice" || $this->_paymentMethod == "ratepay"
            ) && ($this->_klarnaReservationNumber != null &&
                $this->_klarnaReservationNumber != "")) {
            $klarnaReservationNumberText = "<br /> reservationNumber: " . $this->_klarnaReservationNumber;
        } else {
            $klarnaReservationNumberText = "";
        }

        if ($this->_boletoPaidAmount != null && $this->_boletoPaidAmount != "") {
            $boletoPaidAmountText = "<br /> Paid amount: " . $this->_boletoPaidAmount;
        } else {
            $boletoPaidAmountText = "";
        }

        $type = 'Adyen HTTP Notification(s):';
        $comment = __('%1 <br /> eventCode: %2 <br /> pspReference: %3 <br /> paymentMethod: %4 <br />' .
            ' success: %5 %6 %7', $type, $this->_eventCode, $this->_pspReference, $this->_paymentMethod,
            $success, $klarnaReservationNumberText, $boletoPaidAmountText);

        // If notification is pending status and pending status is set add the status change to the comment history
        if ($this->_eventCode == Notification::PENDING) {
            $pendingStatus = $this->_getConfigData(
                'pending_status', 'adyen_abstract', $this->_order->getStoreId()
            );
            if ($pendingStatus != "") {
                $this->_order->addStatusHistoryComment($comment, $pendingStatus);
                $this->_adyenLogger->addAdyenNotificationCronjob(
                    'Created comment history for this notification with status change to: ' . $pendingStatus
                );
                return;
            }
        }

        // if manual review is accepted and a status is selected. Change the status through this comment history item
        if ($this->_eventCode == Notification::MANUAL_REVIEW_ACCEPT
            && $this->_getFraudManualReviewAcceptStatus() != "") {
            $manualReviewAcceptStatus = $this->_getFraudManualReviewAcceptStatus();
            $this->_order->addStatusHistoryComment($comment, $manualReviewAcceptStatus);
            $this->_adyenLogger->addAdyenNotificationCronjob('Created comment history for this notification with status change to: ' . $manualReviewAcceptStatus);
            return;
        }

        $this->_order->addStatusHistoryComment($comment);
        $this->_adyenLogger->addAdyenNotificationCronjob('Created comment history for this notification');
    }

    /**
     * @param $notification
     */
    protected function _updateAdyenAttributes($notification)
    {
        $this->_adyenLogger->addAdyenNotificationCronjob('Updating the Adyen attributes of the order');

        $additionalData = unserialize($notification->getAdditionalData());
        $_paymentCode = $this->_paymentMethodCode();

        if ($this->_eventCode == Notification::AUTHORISATION
            || $this->_eventCode == Notification::HANDLED_EXTERNALLY
            || ($this->_eventCode == Notification::CAPTURE && $_paymentCode == "adyen_pos")) {

            /*
             * if current notification is authorisation : false and
             * the  previous notification was authorisation : true do not update pspreference
             */
            if (strcmp($this->_success, 'false') == 0 ||
                strcmp($this->_success, '0') == 0 ||
                strcmp($this->_success, '') == 0) {

                $previousAdyenEventCode = $this->_order->getData('adyen_notification_event_code');
                if ($previousAdyenEventCode != "AUTHORISATION : TRUE") {
                    $this->_updateOrderPaymentWithAdyenAttributes($additionalData);
                }
            } else {
                $this->_updateOrderPaymentWithAdyenAttributes($additionalData);
            }
        }
    }

    /**
     * @param $additionalData
     */
    protected function _updateOrderPaymentWithAdyenAttributes($additionalData)
    {
        if ($additionalData && is_array($additionalData)) {
            $avsResult = (isset($additionalData['avsResult'])) ? $additionalData['avsResult'] : "";
            $cvcResult = (isset($additionalData['cvcResult'])) ? $additionalData['cvcResult'] : "";
            $totalFraudScore = (isset($additionalData['totalFraudScore'])) ? $additionalData['totalFraudScore'] : "";
            $ccLast4 = (isset($additionalData['cardSummary'])) ? $additionalData['cardSummary'] : "";
            $refusalReasonRaw = (isset($additionalData['refusalReasonRaw'])) ? $additionalData['refusalReasonRaw'] : "";
            $acquirerReference = (isset($additionalData['acquirerReference'])) ?
                $additionalData['acquirerReference'] : "";
            $authCode = (isset($additionalData['authCode'])) ? $additionalData['authCode'] : "";
        }

        // if there is no server communication setup try to get last4 digits from reason field
        if (!isset($ccLast4) || $ccLast4 == "") {
            $ccLast4 = $this->_retrieveLast4DigitsFromReason($this->_reason);
        }

        $this->_order->getPayment()->setAdyenPspReference($this->_pspReference);
        $this->_order->getPayment()->setAdditionalInformation('pspReference', $this->_pspReference);

        if ($this->_klarnaReservationNumber != "") {
            $this->_order->getPayment()->setAdditionalInformation(
                'adyen_klarna_number', $this->_klarnaReservationNumber
            );
        }
        if (isset($ccLast4) && $ccLast4 != "") {
            // this field is column in db by core
            $this->_order->getPayment()->setccLast4($ccLast4);
        }
        if (isset($avsResult) && $avsResult != "") {
            $this->_order->getPayment()->setAdditionalInformation('adyen_avs_result', $avsResult);
        }
        if (isset($cvcResult) && $cvcResult != "") {
            $this->_order->getPayment()->setAdditionalInformation('adyen_cvc_result', $cvcResult);
        }
        if ($this->_boletoPaidAmount != "") {
            $this->_order->getPayment()->setAdditionalInformation('adyen_boleto_paid_amount', $this->_boletoPaidAmount);
        }
        if (isset($totalFraudScore) && $totalFraudScore != "") {
            $this->_order->getPayment()->setAdditionalInformation('adyen_total_fraud_score', $totalFraudScore);
        }
        if (isset($refusalReasonRaw) && $refusalReasonRaw != "") {
            $this->_order->getPayment()->setAdditionalInformation('adyen_refusal_reason_raw', $refusalReasonRaw);
        }
        if (isset($acquirerReference) && $acquirerReference != "") {
            $this->_order->getPayment()->setAdditionalInformation('adyen_acquirer_reference', $acquirerReference);
        }
        if (isset($authCode) && $authCode != "") {
            $this->_order->getPayment()->setAdditionalInformation('adyen_auth_code', $authCode);
        }
    }

    /**
     * retrieve last 4 digits of card from the reason field
     * @param $reason
     * @return string
     */
    protected function _retrieveLast4DigitsFromReason($reason)
    {
        $result = "";

        if ($reason != "") {
            $reasonArray = explode(":", $reason);
            if ($reasonArray != null && is_array($reasonArray)) {
                if (isset($reasonArray[1])) {
                    $result = $reasonArray[1];
                }
            }
        }
        return $result;
    }

    /**
     * @param $ignoreHasInvoice
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _holdCancelOrder($ignoreHasInvoice)
    {
        $orderStatus = $this->_getConfigData(
            'payment_cancelled', 'adyen_abstract', $this->_order->getStoreId()
        );

        // check if order has in invoice only cancel/hold if this is not the case
        if ($ignoreHasInvoice || !$this->_order->hasInvoices()) {

            if ($orderStatus == \Magento\Sales\Model\Order::STATE_HOLDED) {

                // Allow magento to hold order
                $this->_order->setActionFlag(\Magento\Sales\Model\Order::ACTION_FLAG_HOLD, true);

                if ($this->_order->canHold()) {
                    $this->_order->hold();
                } else {
                    $this->_adyenLogger->addAdyenNotificationCronjob('Order can not hold or is already on Hold');
                    return;
                }
            } else {
                // Allow magento to cancel order
                $this->_order->setActionFlag(\Magento\Sales\Model\Order::ACTION_FLAG_CANCEL, true);

                if ($this->_order->canCancel()) {
                    $this->_order->cancel();
                } else {
                    $this->_adyenLogger->addAdyenNotificationCronjob('Order can not be canceled');
                    return;
                }
            }
        } else {
            $this->_adyenLogger->addAdyenNotificationCronjob('Order has already an invoice so cannot be canceled');
        }
    }

    /**
     * Process the Notification
     */
    protected function _processNotification()
    {
        $this->_adyenLogger->addAdyenNotificationCronjob('Processing the notification');
        $_paymentCode = $this->_paymentMethodCode();

        switch ($this->_eventCode) {
            case Notification::REFUND_FAILED:
                // do nothing only inform the merchant with order comment history
                break;
            case Notification::REFUND:
                $ignoreRefundNotification = $this->_getConfigData(
                    'ignore_refund_notification', 'adyen_abstract', $this->_order->getStoreId()
                );
                if ($ignoreRefundNotification != true) {
                    $this->_refundOrder();
                    //refund completed
                    $this->_setRefundAuthorized();
                } else {
                    $this->_adyenLogger->addAdyenNotificationCronjob(
                        'Setting to ignore refund notification is enabled so ignore this notification'
                    );
                }
                break;
            case Notification::PENDING:
                if ($this->_getConfigData(
                    'send_email_bank_sepa_on_pending', 'adyen_abstract', $this->_order->getStoreId())
                ) {
                    // Check if payment is banktransfer or sepa if true then send out order confirmation email
                    $isBankTransfer = $this->_isBankTransfer();
                    if ($isBankTransfer || $this->_paymentMethod == 'sepadirectdebit') {
                        if (!$this->_order->getEmailSent()) {
                            $this->_sendOrderMail();
                        }
                    }
                }
                break;
            case Notification::HANDLED_EXTERNALLY:
            case Notification::AUTHORISATION:
                // for POS don't do anything on the AUTHORIZATION
                if ($_paymentCode != "adyen_pos") {
                    $this->_authorizePayment();
                }
                break;
            case Notification::MANUAL_REVIEW_REJECT:
                // don't do anything it will send a CANCEL_OR_REFUND notification when this payment is captured
                break;
            case Notification::MANUAL_REVIEW_ACCEPT:
                /*
                 * only process this if you are on auto capture.
                 * On manual capture you will always get Capture or CancelOrRefund notification
                 */
                if ($this->_isAutoCapture()) {
                    $this->_setPaymentAuthorized(false);
                }
                break;
            case Notification::CAPTURE:
                if ($_paymentCode != "adyen_pos") {
                    /*
                     * ignore capture if you are on auto capture
                     * this could be called if manual review is enabled and you have a capture delay
                     */
                    if (!$this->_isAutoCapture()) {
                        $this->_setPaymentAuthorized(false, true);
                    }
                } else {
                    // FOR POS authorize the payment on the CAPTURE notification
                    $this->_authorizePayment();
                }
                break;
            case Notification::CAPTURE_FAILED:
            case Notification::CANCELLATION:
            case Notification::CANCELLED:
                $this->_holdCancelOrder(true);
                break;
            case Notification::CANCEL_OR_REFUND:
                if (isset($this->_modificationResult) && $this->_modificationResult != "") {
                    if ($this->_modificationResult == "cancel") {
                        $this->_holdCancelOrder(true);
                    } elseif ($this->_modificationResult == "refund") {
                        $this->_refundOrder();
                        //refund completed
                        $this->_setRefundAuthorized();
                    }
                } else {
                    if ($this->_order->isCanceled() ||
                        $this->_order->getState() === \Magento\Sales\Model\Order::STATE_HOLDED) {

                        $this->_adyenLogger->addAdyenNotificationCronjob(
                            'Order is already cancelled or holded so do nothing'
                        );
                    } else if ($this->_order->canCancel() || $this->_order->canHold()) {
                        $this->_adyenLogger->addAdyenNotificationCronjob('try to cancel the order');
                        $this->_holdCancelOrder(true);
                    } else {
                        $this->_adyenLogger->addAdyenNotificationCronjob('try to refund the order');
                        // refund
                        $this->_refundOrder();
                        //refund completed
                        $this->_setRefundAuthorized();
                    }
                }
                break;
            case Notification::RECURRING_CONTRACT:

                // storedReferenceCode
                $recurringDetailReference = $this->_pspReference;

                // check if there is already a BillingAgreement
                $billingAgreement = $this->_billingAgreementFactory->create();
                $billingAgreement->load($recurringDetailReference, 'reference_id');


                if ($billingAgreement && $billingAgreement->getAgreementId() > 0 && $billingAgreement->isValid()) {

                    try {
                        $billingAgreement->addOrderRelation($this->_order);
                        $billingAgreement->setStatus($billingAgreement::STATUS_ACTIVE);
                        $billingAgreement->setIsObjectChanged(true);
                        $this->_order->addRelatedObject($billingAgreement);
                        $message = __('Used existing billing agreement #%s.', $billingAgreement->getReferenceId());
                    } catch (Exception $e) {
                        // could be that it is already linked to this order
                        $message = __('Used existing billing agreement #%s.', $billingAgreement->getReferenceId());
                    }
                } else {

                    $this->_order->getPayment()->setBillingAgreementData(
                        [
                            'billing_agreement_id' => $recurringDetailReference,
                            'method_code' => $this->_order->getPayment()->getMethodCode(),
                        ]
                    );

                    // create new object
                    $billingAgreement = $this->_billingAgreementFactory->create();
                    $billingAgreement->setStoreId($this->_order->getStoreId());
                    $billingAgreement->importOrderPayment($this->_order->getPayment());

                    // get all data for this contract by doing a listRecurringCall
                    $customerReference = $billingAgreement->getCustomerReference();
                    $storeId = $billingAgreement->getStoreId();

                    /*
                     * for quest checkout users we can't save this in the billing agreement
                     * because it is linked to customer
                     */
                    if ($customerReference && $storeId) {

                        $listRecurringContracts = null;
                        try {
                            $listRecurringContracts = $this->_adyenPaymentRequest->getRecurringContractsForShopper(
                                $customerReference, $storeId
                            );
                        } catch(\Exception $exception) {
                            $this->_adyenLogger->addAdyenNotificationCronjob($exception->getMessage());
                        }

                        $contractDetail = null;
                        // get current Contract details and get list of all current ones
                        $recurringReferencesList = [];

                        if ($listRecurringContracts) {
                            foreach ($listRecurringContracts as $rc) {
                                $recurringReferencesList[] = $rc['recurringDetailReference'];
                                if (isset($rc['recurringDetailReference']) &&
                                    $rc['recurringDetailReference'] == $recurringDetailReference) {
                                    $contractDetail = $rc;
                                }
                            }
                        }

                        if ($contractDetail != null) {
                            // update status of all the current saved agreements in magento
                            $billingAgreements = $this->_billingAgreementCollectionFactory->create();
                            $billingAgreements->addFieldToFilter('customer_id', $customerReference);

                            // get collection

                            foreach ($billingAgreements as $updateBillingAgreement) {
                                if (!in_array($updateBillingAgreement->getReferenceId(), $recurringReferencesList)) {
                                    $updateBillingAgreement->setStatus(
                                        \Adyen\Payment\Model\Billing\Agreement::STATUS_CANCELED
                                    );
                                    $updateBillingAgreement->save();
                                } else {
                                    $updateBillingAgreement->setStatus(
                                        \Adyen\Payment\Model\Billing\Agreement::STATUS_ACTIVE
                                    );
                                    $updateBillingAgreement->save();
                                }
                            }

                            // add this billing agreement
                            $billingAgreement->parseRecurringContractData($contractDetail);
                            if ($billingAgreement->isValid()) {
                                $message = __('Created billing agreement #%1.', $billingAgreement->getReferenceId());

                                // save into sales_billing_agreement_order
                                $billingAgreement->addOrderRelation($this->_order);

                                // add to order to save agreement
                                $this->_order->addRelatedObject($billingAgreement);
                            } else {
                                $message = __('Failed to create billing agreement for this order.');
                            }


                        } else {
                            $this->_adyenLogger->addAdyenNotificationCronjob(
                                'Failed to create billing agreement for this order ' .
                                '(listRecurringCall did not contain contract)'
                            );
                            $this->_adyenLogger->addAdyenNotificationCronjob(
                                __('recurringDetailReference in notification is %1', $recurringDetailReference)
                            );
                            $this->_adyenLogger->addAdyenNotificationCronjob(
                                __('CustomerReference is: %1 and storeId is %2', $customerReference, $storeId)
                            );
                            $this->_adyenLogger->addAdyenNotificationCronjob(print_r($listRecurringContracts, 1));
                            $message = __(
                                'Failed to create billing agreement for this order ' .
                                '(listRecurringCall did not contain contract)'
                            );
                        }

                        $comment = $this->_order->addStatusHistoryComment($message);
                        $this->_order->addRelatedObject($comment);
                    }
                }
                break;
            default:
                $this->_adyenLogger->addAdyenNotificationCronjob(
                    sprintf('This notification event: %s is not supported so will be ignored', $this->_eventCode)
                );
                break;
        }
    }

    /**
     * Not implemented
     * @return bool
     */
    protected function _refundOrder()
    {
        $this->_adyenLogger->addAdyenNotificationCronjob('Refunding the order');

        // check if it is a split payment if so save the refunded data
        if ($this->_originalReference != "") {

            $this->_adyenLogger->addAdyenNotificationCronjob('Going to update the refund to split payments table');

            $orderPayment = $this->_adyenOrderPaymentCollectionFactory
                ->create()
                ->addFieldToFilter(\Adyen\Payment\Model\Notification::PSPREFRENCE, $this->_originalReference)
                ->getFirstItem();

            if ($orderPayment->getId() > 0) {
                $currency = $this->_order->getOrderCurrencyCode();
                $amountRefunded = $amountRefunded =  $orderPayment->getTotalRefunded() +
                    $this->_adyenHelper->originalAmount($this->_value, $currency);
                $orderPayment->setUpdatedAt(new \DateTime());
                $orderPayment->setTotalRefunded($amountRefunded);
                $orderPayment->save();
                $this->_adyenLogger->addAdyenNotificationCronjob('Update the refund in the split payments table');
            } else {
                $this->_adyenLogger->addAdyenNotificationCronjob('Payment not found in split payment table');
            }
        }

        /*
         * Don't create a credit memo if refund is initialize in Magento
         * because in this case the credit memo already exists
         */
        $lastTransactionId = $this->_order->getPayment()->getLastTransId();
        if ($lastTransactionId != $this->_pspReference) {

            // refund is done through adyen backoffice so create an invoice
            $order = $this->_order;
            if ($order->canCreditmemo()) {

                // there is a bug in this function of Magento see #2656 magento\magento2 repo
                // Invalid method Magento\Sales\Model\Order\Creditmemo::register
                /*
                $currency = $this->_order->getOrderCurrencyCode();
                $amount = $this->_adyenHelper->originalAmount($this->_value, $currency);
                $order->getPayment()->registerRefundNotification($amount);
                */

                $this->_adyenLogger->addAdyenNotificationCronjob('Please create your credit memo inside magento');
            } else {
                $this->_adyenLogger->addAdyenNotificationCronjob('Could not create a credit memo for order');
            }
        } else {
            $this->_adyenLogger->addAdyenNotificationCronjob(
                'Did not create a credit memo for this order becasue refund is done through Magento'
            );
        }
    }

    /**
     * @param $order
     */
    protected function _setRefundAuthorized()
    {
        $this->_adyenLogger->addAdyenNotificationCronjob(
            'Status update to default status or refund_authorized status if this is set'
        );
        $this->_order->addStatusHistoryComment(__('Adyen Refund Successfully completed'));
    }

    /**
     * authorize payment
     */
    protected function _authorizePayment()
    {
        $this->_adyenLogger->addAdyenNotificationCronjob('Authorisation of the order');
        $fraudManualReviewStatus = $this->_getFraudManualReviewStatus();

        // If manual review is active and a seperate status is used then ignore the pre authorized status
        if ($this->_fraudManualReview != true || $fraudManualReviewStatus == "") {
            $this->_setPrePaymentAuthorized();
        } else {
            $this->_adyenLogger->addAdyenNotificationCronjob(
                'Ignore the pre authorized status because the order is ' .
                'under manual review and use the Manual review status'
            );
        }

        $this->_prepareInvoice();
        $_paymentCode = $this->_paymentMethodCode();

        // for boleto confirmation mail is send on order creation
        if ($this->_paymentMethod != "adyen_boleto") {
            // send order confirmation mail after invoice creation so merchant can add invoicePDF to this mail
            if (!$this->_order->getEmailSent()) {
                $this->_sendOrderMail();
            }

        }

        if (($this->_paymentMethod == "c_cash" &&
                $this->_getConfigData('create_shipment', 'adyen_cash', $this->_order->getStoreId())) ||
            ($this->_getConfigData('create_shipment', 'adyen_pos', $this->_order->getStoreId()) &&
                $_paymentCode == "adyen_pos")) {

            $this->_createShipment();
        }
    }

    /**
     * Send order Mail
     *
     * @return void
     */
    private function _sendOrderMail()
    {
        try {
            $this->_orderSender->send($this->_order);
            $this->_adyenLogger->addAdyenNotificationCronjob('Send orderconfirmation email to shopper');
        }  catch(\Exception $exception) {
            $this->_adyenLogger->addAdyenNotificationCronjob(
                "Exception in Send Mail in Magento. This is an issue in the the core of Magento" .
                $exception->getMessage()
            );
        }
    }

    /**
     * Set status on authorisation
     *
     * @return void
     */
    private function _setPrePaymentAuthorized()
    {
        $status = $this->_getConfigData(
            'payment_pre_authorized', 'adyen_abstract', $this->_order->getStoreId()
        );

        // only do this if status in configuration is set
        if (!empty($status)) {
            $this->_order->addStatusHistoryComment(__('Payment is authorised waiting for capture'), $status);
            $this->_adyenLogger->addAdyenNotificationCronjob(
                'Order status is changed to Pre-authorised status, status is ' . $status
            );
        } else {
            $this->_adyenLogger->addAdyenNotificationCronjob('No pre-authorised status is used so ignore');
        }
    }

    /**
     * @throws Exception
     * @return void
     */
    protected function _prepareInvoice()
    {
        $this->_adyenLogger->addAdyenNotificationCronjob('Prepare invoice for order');

        //Set order state to new because with order state payment_review it is not possible to create an invoice
        if (strcmp($this->_order->getState(), \Magento\Sales\Model\Order::STATE_PAYMENT_REVIEW) == 0) {
            $this->_order->setState(\Magento\Sales\Model\Order::STATE_NEW);
        }

        $paymentObj = $this->_order->getPayment();

        // set pspReference as transactionId
        $paymentObj->setCcTransId($this->_pspReference);
        $paymentObj->setLastTransId($this->_pspReference);

        // set transaction
        $paymentObj->setTransactionId($this->_pspReference);

        //capture mode
        if (!$this->_isAutoCapture()) {
            $this->_order->addStatusHistoryComment(__('Capture Mode set to Manual'));
            $this->_adyenLogger->addAdyenNotificationCronjob('Capture mode is set to Manual');

            // show message if order is in manual review
            if ($this->_fraudManualReview) {
                // check if different status is selected
                $fraudManualReviewStatus = $this->_getFraudManualReviewStatus();
                if ($fraudManualReviewStatus != "") {
                    $status = $fraudManualReviewStatus;
                    $comment = "Adyen Payment is in Manual Review check the Adyen platform";
                    $this->_order->addStatusHistoryComment(__($comment), $status);
                }
            }

            $createPendingInvoice = (bool) $this->_getConfigData(
                'create_pending_invoice', 'adyen_abstract', $this->_order->getStoreId()
            );

            if (!$createPendingInvoice) {
                $this->_adyenLogger->addAdyenNotificationCronjob(
                    'Setting pending invoice is off so don\'t create an invoice wait for the capture notification'
                );
                return;
            }
        }

        // validate if amount is total amount
        $orderCurrencyCode = $this->_order->getOrderCurrencyCode();
        $amount = $this->_adyenHelper->originalAmount($this->_value, $orderCurrencyCode);

        // add to order payment
        $date = new \DateTime();
        $this->_adyenOrderPaymentFactory->create()
            ->setPspreference($this->_pspReference)
            ->setMerchantReference($this->_merchantReference)
            ->setPaymentId($paymentObj->getId())
            ->setPaymentMethod($this->_paymentMethod)
            ->setAmount($amount)
            ->setTotalRefunded(0)
            ->setCreatedAt($date)
            ->setUpdatedAt($date)
            ->save();


        if ($this->_isTotalAmount($paymentObj->getEntityId(), $orderCurrencyCode)) {
            $this->_createInvoice();
        } else {
            $this->_adyenLogger->addAdyenNotificationCronjob(
                'This is a partial AUTHORISATION and the full amount is not reached'
            );
        }
    }

    /**
     * @return bool
     */
    protected function _isAutoCapture()
    {
        // validate if payment methods allowes manual capture
        if ($this->_manualCaptureAllowed()) {
            $captureMode = trim($this->_getConfigData(
                'capture_mode', 'adyen_abstract', $this->_order->getStoreId())
            );
            $sepaFlow = trim($this->_getConfigData(
                'sepa_flow', 'adyen_abstract', $this->_order->getStoreId())
            );
            $_paymentCode = $this->_paymentMethodCode();
            $captureModeOpenInvoice = $this->_getConfigData(
                'auto_capture_openinvoice', 'adyen_abstract', $this->_order->getStoreId()
            );
            $captureModePayPal = trim($this->_getConfigData(
                'paypal_capture_mode', 'adyen_abstract', $this->_order->getStoreId())
            );

            /*
             * if you are using authcap the payment method is manual.
             * There will be a capture send to indicate if payment is successful
             */
            if (($_paymentCode == "adyen_sepa" || $this->_paymentMethod == "sepadirectdebit") &&
                $sepaFlow == "authcap") {
                $this->_adyenLogger->addAdyenNotificationCronjob(
                    'Manual Capture is applied for sepa because it is in authcap flow'
                );
                return false;
            }

            // payment method ideal, cash adyen_boleto or adyen_pos has direct capture
            if ($_paymentCode == "adyen_pos" || (($_paymentCode == "adyen_sepa" ||
                        $this->_paymentMethod == "sepadirectdebit") && $sepaFlow != "authcap")) {
                $this->_adyenLogger->addAdyenNotificationCronjob(
                    'This payment method does not allow manual capture.(2) paymentCode:' .
                    $_paymentCode . ' paymentMethod:' . $this->_paymentMethod . ' sepaFLow:'.$sepaFlow
                );
                return true;
            }

            // if auto capture mode for openinvoice is turned on then use auto capture
            if ($captureModeOpenInvoice == true &&
                $this->_adyenHelper->isPaymentMethodOpenInvoiceMethod($this->_paymentMethod)
            ) {
                $this->_adyenLogger->addAdyenNotificationCronjob(
                    'This payment method is configured to be working as auto capture '
                );
                return true;
            }
            // if PayPal capture modues is different from the default use this one
            if (strcmp($this->_paymentMethod, 'paypal' ) === 0 && $captureModePayPal != "") {
                if (strcmp($captureModePayPal, 'auto') === 0 ) {
                    $this->_adyenLogger->addAdyenNotificationCronjob(
                        'This payment method is paypal and configured to work as auto capture'
                    );
                    return true;
                } elseif (strcmp($captureModePayPal, 'manual') === 0 ) {
                    $this->_adyenLogger->addAdyenNotificationCronjob(
                        'This payment method is paypal and configured to work as manual capture'
                    );
                    return false;
                }
            }
            if (strcmp($captureMode, 'manual') === 0) {
                $this->_adyenLogger->addAdyenNotificationCronjob('Capture mode for this payment is set to manual');
                return false;
            }

            /*
             * online capture after delivery, use Magento backend to online invoice
             * (if the option auto capture mode for openinvoice is not set)
             */
            if ($this->_adyenHelper->isPaymentMethodOpenInvoiceMethod($this->_paymentMethod)) {
                $this->_adyenLogger->addAdyenNotificationCronjob('Capture mode for klarna is by default set to manual');
                return false;
            }

            $this->_adyenLogger->addAdyenNotificationCronjob('Capture mode is set to auto capture');
            return true;

        } else {
            // does not allow manual capture so is always immediate capture
            $this->_adyenLogger->addAdyenNotificationCronjob('This payment method does not allow manual capture');
            return true;
        }

    }

    /**
     * Validate if this payment methods allows manual capture
     * This is a default can be forced differently to overrule on acquirer level
     *
     * @return bool|null
     */
    protected function _manualCaptureAllowed()
    {
        $manualCaptureAllowed = null;
        $paymentMethod = $this->_paymentMethod;

        switch($paymentMethod) {
            case 'cup':
            case 'cartebancaire':
            case 'visa':
            case 'mc':
            case 'uatp':
            case 'amex':
            case 'bcmc':
            case 'maestro':
            case 'maestrouk':
            case 'diners':
            case 'discover':
            case 'jcb':
            case 'laser':
            case 'paypal':
            case 'klarna':
            case 'afterpay_default':
            case 'ratepay':
            case 'sepadirectdebit':
                $manualCaptureAllowed = true;
                break;
            default:
                // To be sure check if it payment method starts with afterpay_ then manualCapture is allowed
                if (strlen($this->_paymentMethod) >= 9 && substr($this->_paymentMethod, 0, 9) == "afterpay_") {
                    $manualCaptureAllowed = true;
                }
                $manualCaptureAllowed = false;
        }

        return $manualCaptureAllowed;
    }

    /**
     * @return bool
     */
    protected function _isBankTransfer()
    {
        if (strlen($this->_paymentMethod) >= 12 && substr($this->_paymentMethod, 0, 12) == "bankTransfer") {
            $isBankTransfer = true;
        } else {
            $isBankTransfer = false;
        }
        return $isBankTransfer;
    }

    /**
     * @return mixed
     */
    protected function _getFraudManualReviewStatus()
    {
        return $this->_getConfigData(
            'fraud_manual_review_status', 'adyen_abstract', $this->_order->getStoreId()
        );
    }

    /**
     * @return mixed
     */
    protected function _getFraudManualReviewAcceptStatus()
    {
        return $this->_getConfigData(
            'fraud_manual_review_accept_status', 'adyen_abstract', $this->_order->getStoreId()
        );
    }

    /**
     * @param int $paymentId
     * @param string $orderCurrencyCode
     * @return bool
     */
    protected function _isTotalAmount($paymentId, $orderCurrencyCode)
    {
        $this->_adyenLogger->addAdyenNotificationCronjob(
            'Validate if AUTHORISATION notification has the total amount of the order'
        );

        // get total amount of the order
        $grandTotal = (int) $this->_adyenHelper->formatAmount($this->_order->getGrandTotal(), $orderCurrencyCode);

        // check if total amount of the order is authorised
        $res = $this->_adyenOrderPaymentCollectionFactory
            ->create()
            ->getTotalAmount($paymentId);

        if ($res && isset($res[0]) && is_array($res[0])) {
            $amount = $res[0]['total_amount'];
            $orderAmount = $this->_adyenHelper->formatAmount($amount, $orderCurrencyCode);
            $this->_adyenLogger->addAdyenNotificationCronjob(
                sprintf('The grandtotal amount is %s and the total order amount that is authorised is: %s',
                    $grandTotal,
                    $orderAmount
                )
            );

            if ($grandTotal == $orderAmount) {
                $this->_adyenLogger->addAdyenNotificationCronjob('AUTHORISATION has the full amount');
                return true;
            } else {
                $this->_adyenLogger->addAdyenNotificationCronjob(
                    'This is a partial AUTHORISATION, the amount is ' . $this->_value
                );
                return false;
            }
        }
        return false;
    }

    /**
     * @throws Exception
     * @throws \Magento\Framework\Exception\LocalizedException
     * @return void
     */
    protected function _createInvoice()
    {
        $this->_adyenLogger->addAdyenNotificationCronjob('Creating invoice for order');

        if ($this->_order->canInvoice()) {

            /* We do not use this inside a transaction because order->save()
             * is always done on the end of the notification
             * and it could result in a deadlock see https://github.com/Adyen/magento/issues/334
             */
            try {
                $invoice = $this->_order->prepareInvoice();
                $invoice->getOrder()->setIsInProcess(true);

                // set transaction id so you can do a online refund from credit memo
                $invoice->setTransactionId($this->_pspReference);


                $autoCapture = $this->_isAutoCapture();
                $createPendingInvoice = (bool) $this->_getConfigData(
                    'create_pending_invoice', 'adyen_abstract', $this->_order->getStoreId()
                );

                if ((!$autoCapture) && ($createPendingInvoice)) {

                    // if amount is zero create a offline invoice
                    $value = (int)$this->_value;
                    if ($value == 0) {
                        $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE);
                    } else {
                        $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::NOT_CAPTURE);
                    }

                    $invoice->register();
                } else {
                    $invoice->register()->pay();
                }

                $invoice->save();
                $this->_adyenLogger->addAdyenNotificationCronjob('Created invoice');
            } catch (Exception $e) {
                $this->_adyenLogger->addAdyenNotificationCronjob(
                    'Error saving invoice. The error message is: ' . $e->getMessage()
                );
                throw new Exception(sprintf('Error saving invoice. The error message is:', $e->getMessage()));
            }

            $this->_setPaymentAuthorized();

            $invoiceAutoMail = (bool) $this->_getConfigData(
                'send_invoice_update_mail', 'adyen_abstract', $this->_order->getStoreId()
            );

            if ($invoiceAutoMail) {
                $invoice->sendEmail();
            }
        } else {
            $this->_adyenLogger->addAdyenNotificationCronjob('It is not possible to create invoice for this order');
        }
    }

    /**
     * @param bool $manualReviewComment
     * @param bool $createInvoice
     * @throws Exception
     */
    protected function _setPaymentAuthorized($manualReviewComment = true, $createInvoice = false)
    {
        $this->_adyenLogger->addAdyenNotificationCronjob('Set order to authorised');

        // if full amount is captured create invoice
        $currency = $this->_order->getOrderCurrencyCode();
        $amount = $this->_value;
        $orderAmount = (int) $this->_adyenHelper->formatAmount($this->_order->getGrandTotal(), $currency);

        // create invoice for the capture notification if you are on manual capture
        if ($createInvoice == true && $amount == $orderAmount) {
            $this->_adyenLogger->addAdyenNotificationCronjob(
                'amount notification:'.$amount . ' amount order:'.$orderAmount
            );
            $this->_createInvoice();
        }
        
        $status = $this->_getConfigData(
            'payment_authorized', 'adyen_abstract', $this->_order->getStoreId()
        );

        // virtual order can have different status
        if ($this->_order->getIsVirtual()) {
            $this->_adyenLogger->addAdyenNotificationCronjob('Product is a virtual product');
            $virtualStatus = $this->_getConfigData(
                'payment_authorized_virtual', 'adyen_abstract', $this->_order->getStoreId()
            );
            if ($virtualStatus != "") {
                $status = $virtualStatus;
            }
        }

        // check for boleto if payment is totally paid
        if ($this->_paymentMethodCode() == "adyen_boleto") {

            // check if paid amount is the same as orginal amount
            $orginalAmount = $this->_boletoOriginalAmount;
            $paidAmount = $this->_boletoPaidAmount;

            if ($orginalAmount != $paidAmount) {

                // not the full amount is paid. Check if it is underpaid or overpaid
                // strip the  BRL of the string
                $orginalAmount = str_replace("BRL", "", $orginalAmount);
                $orginalAmount = floatval(trim($orginalAmount));

                $paidAmount = str_replace("BRL", "", $paidAmount);
                $paidAmount = floatval(trim($paidAmount));

                if ($paidAmount > $orginalAmount) {
                    $overpaidStatus =  $this->_getConfigData(
                        'order_overpaid_status', 'adyen_boleto', $this->_order->getStoreId()
                    );
                    // check if there is selected a status if not fall back to the default
                    $status = (!empty($overpaidStatus)) ? $overpaidStatus : $status;
                } else {
                    $underpaidStatus = $this->_getConfigData(
                        'order_underpaid_status', 'adyen_boleto', $this->_order->getStoreId()
                    );
                    // check if there is selected a status if not fall back to the default
                    $status = (!empty($underpaidStatus)) ? $underpaidStatus : $status;
                }
            }
        }

        $comment = "Adyen Payment Successfully completed";

        // if manual review is true use the manual review status if this is set
        if ($manualReviewComment == true && $this->_fraudManualReview) {
            // check if different status is selected
            $fraudManualReviewStatus = $this->_getFraudManualReviewStatus();
            if ($fraudManualReviewStatus != "") {
                $status = $fraudManualReviewStatus;
                $comment = "Adyen Payment is in Manual Review check the Adyen platform";
            }
        }

        $status = (!empty($status)) ? $status : $this->_order->getStatus();
        $this->_order->addStatusHistoryComment(__($comment), $status);
        $this->_adyenLogger->addAdyenNotificationCronjob(
            'Order status is changed to authorised status, status is ' . $status
        );
    }


    /**
     * Create shipment
     *
     * @throws bool
     */
    protected function _createShipment()
    {
        $this->_adyenLogger->addAdyenNotificationCronjob('Creating shipment for order');
        // create shipment for cash payment
        $payment = $this->_order->getPayment()->getMethodInstance();
        if ($this->_order->canShip()) {
            $itemQty = [];
            $shipment = $this->_order->prepareShipment($itemQty);
            if ($shipment) {
                $shipment->register();
                $shipment->getOrder()->setIsInProcess(true);
                $comment = __('Shipment created by Adyen');
                $shipment->addComment($comment);

                /** @var \Magento\Framework\DB\Transaction $transaction */
                $transaction = $this->_transactionFactory->create();
                $transaction->addObject($shipment)
                    ->addObject($shipment->getOrder())
                    ->save();

                $this->_adyenLogger->addAdyenNotificationCronjob('Order is shipped');
            }
        } else {
            $this->_adyenLogger->addAdyenNotificationCronjob('Order can\'t be shipped');
        }
    }

    /**
     * Retrieve information from payment configuration
     *
     * @param $field
     * @param string $paymentMethodCode
     * @param $storeId
     * @return mixed
     */
    protected function _getConfigData($field, $paymentMethodCode = 'adyen_cc', $storeId)
    {
        $path = 'payment/' . $paymentMethodCode . '/' . $field;
        return $this->_scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
    }
}