<?php

namespace Adyen\Payment\Model;

use Magento\Sales\Model\Order\Email\Sender\OrderSender;

class Cron
{

    /**
     * Logging instance
     * @var \Adyen\Payment\Logger\AdyenLogger
     */
    protected $_logger;

    protected $_notificationFactory;

    protected $_datetime;

    protected $_localeDate;

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

    protected $_adyenHelper;

    /**
     * @var OrderSender
     */
    protected $_orderSender;


    // notification attributes
    protected $_pspReference;
    protected $_merchantReference;
    protected $_eventCode;
    protected $_success;
    protected $_paymentMethod;
    protected $_reason;
    protected $_value;
    protected $_boletoOriginalAmount;
    protected $_boletoPaidAmount;
    protected $_modificationResult;
    protected $_klarnaReservationNumber;
    protected $_fraudManualReview;

    /**
     * Collected debug information
     *
     * @var array
     */
    protected $_debugData = array();


    /**
     * Constructor
     * @param \Adyen\Payment\Logger\Logger $logger
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Adyen\Payment\Logger\AdyenLogger $adyenLogger,
        \Adyen\Payment\Model\Resource\Notification\CollectionFactory $notificationFactory,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Framework\Stdlib\DateTime $dateTime,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate,
        \Adyen\Payment\Helper\Data $adyenHelper,
        OrderSender $orderSender
    )
    {
        $this->_scopeConfig = $scopeConfig;
        $this->_logger = $adyenLogger;
        $this->_notificationFactory = $notificationFactory;
        $this->_orderFactory = $orderFactory;
        $this->_datetime = $dateTime;
        $this->_localeDate = $localeDate;
        $this->_adyenHelper = $adyenHelper;
        $this->_orderSender = $orderSender;
    }


    public function processNotification()
    {

        $this->_order = null;

        $this->_logger->info("START OF THE CRONJOB");

        //fixme somehow the created_at is saved in my timzone


        $dateStart = new \DateTime();

        // loop over notifications that are not processed and from 1 minute ago
        $dateStart = new \DateTime();
        $dateStart->modify('-1 day');

        // excecute notifications from 2 minute or earlier because order could not yet been created by mangento
        $dateEnd = new \DateTime();
        $dateEnd->modify('-2 minute');
        $dateRange = ['from' => $dateStart, 'to' => $dateEnd, 'datetime' => true];

        $notifications = $this->_notificationFactory->create();
        $notifications->addFieldToFilter('done', 0);
        $notifications->addFieldToFilter('created_at', $dateRange);

        foreach($notifications as $notification) {


            // get order
            $incrementId = $notification->getMerchantReference();

            $this->_order = $this->_orderFactory->create()->loadByIncrementId($incrementId);
            if (!$this->_order->getId()) {
                throw new Exception(sprintf('Wrong order ID: "%1".', $incrementId));
            }

            // declare all variables that are needed
            $this->_declareVariables($notification);

            // add notification to comment history status is current status
            $this->_addStatusHistoryComment();

//            $previousAdyenEventCode = $this->order->getAdyenNotificationEventCode();
            $previousAdyenEventCode = $this->_order->getData('adyen_notification_event_code');

            // set pspReference on payment object
            $this->_order->getPayment()->setAdditionalInformation('pspReference', $this->_pspReference);


            // check if success is true of false
            if (strcmp($this->_success, 'false') == 0 || strcmp($this->_success, '0') == 0) {
                // Only cancel the order when it is in state pending, payment review or if the ORDER_CLOSED is failed (means split payment has not be successful)
                if($this->_order->getState() === \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT || $this->_order->getState() === \Magento\Sales\Model\Order::STATE_PAYMENT_REVIEW || $this->_eventCode == \Magento\Sales\Model\Order::ADYEN_EVENT_ORDER_CLOSED) {
                    $this->_debugData['_updateOrder info'] = 'Going to cancel the order';

                    // if payment is API check, check if API result pspreference is the same as reference
                    if($this->_eventCode == Adyen_Payment_Model_Event::ADYEN_EVENT_AUTHORISATION && $this->_getPaymentMethodType() == 'api') {
                        if($this->_pspReference == $this->_order->getPayment()->getAdditionalInformation('pspReference')) {
                            // don't cancel the order if previous state is authorisation with success=true
                            if($previousAdyenEventCode != "AUTHORISATION : TRUE") {
                                $this->_holdCancelOrder(false);
                            } else {
                                //$this->_order->setAdyenEventCode($previousAdyenEventCode); // do not update the adyenEventCode
                                $this->_order->setData('adyen_notification_event_code', $previousAdyenEventCode);
                                $this->_debugData['_updateOrder warning'] = 'order is not cancelled because previous notification was a authorisation that succeeded';
                            }
                        } else {
                            $this->_debugData['_updateOrder warning'] = 'order is not cancelled because pspReference does not match with the order';
                        }
                    } else {
                        // don't cancel the order if previous state is authorisation with success=true
                        if($previousAdyenEventCode != "AUTHORISATION : TRUE") {
                            $this->_holdCancelOrder(false);
                        } else {
//                            $this->_order->setAdyenEventCode($previousAdyenEventCode); // do not update the adyenEventCode
                            $this->_order->setData('adyen_notification_event_code', $previousAdyenEventCode);
                            $this->_debugData['_updateOrder warning'] = 'order is not cancelled because previous notification was a authorisation that succeeded';
                        }
                    }
                } else {
                    $this->_debugData['_updateOrder info'] = 'Order is already processed so ignore this notification state is:' . $this->_order->getState();
                }
            } else {
                // Notification is successful
                $this->_processNotification();
            }

            $this->_order->save();

            foreach($this->_debugData as $debug) {
                $this->_logger->info($debug);
            }

            // set done to true
            $dateEnd = new \DateTime();
            $notification->setDone(true);
            $notification->setUpdatedAt($dateEnd);
            $notification->save();
        }
        $this->_logger->info("END OF THE CRONJOB");
    }

    protected function _declareVariables($notification)
    {
        //  declare the common parameters
        $this->_pspReference = $notification->getPspreference();
        $this->_merchantReference = $notification->getMerchantReference();
        $this->_eventCode = $notification->getEventCode();
        $this->_success = $notification->getSuccess();
        $this->_paymentMethod = $notification->getPaymentMethod();
        $this->_reason = $notification->getPaymentMethod();
        $this->_value = $notification->getAmountValue();


        $additionalData = unserialize($notification->getAdditionalData());

        // boleto data
        if($this->_paymentMethodCode() == "adyen_boleto") {
            if($additionalData && is_array($additionalData)) {
                $boletobancario = isset($additionalData['boletobancario']) ? $additionalData['boletobancario'] : null;
                if($boletobancario && is_array($boletobancario)) {
                    $this->_boletoOriginalAmount = isset($boletobancario['originalAmount']) ? trim($boletobancario['originalAmount']) : "";
                    $this->_boletoPaidAmount = isset($boletobancario['paidAmount']) ? trim($boletobancario['paidAmount']) : "";
                }
            }
        }

        if($additionalData && is_array($additionalData)) {

            // check if the payment is in status manual review
            $fraudManualReview = isset($additionalData['fraudManualReview']) ? $additionalData['fraudManualReview'] : "";
            if($fraudManualReview == "true") {
                $this->_fraudManualReview = true;
            } else {
                $this->_fraudManualReview = false;
            }

            // modification.action is it for JSON
            $modificationActionJson = isset($additionalData['modification.action']) ? $additionalData['modification.action'] : null;
            if($modificationActionJson != "") {
                $this->_modificationResult = $modificationActionJson;
            }

            $modification = isset($additionalData['modification']) ? $additionalData['modification'] : null;
            if($modification && is_array($modification)) {
                $this->_modificationResult = isset($modification['action']) ? trim($modification['action']) : "";
            }
            $additionalData2 = isset($additionalData['additionalData']) ? $additionalData['additionalData'] : null;
            if($additionalData2 && is_array($additionalData2)) {
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

    protected function _getPaymentMethodType() {
        return $this->_order->getPayment()->getPaymentMethodType();
    }

    /**
     * @desc order comments or history
     * @param type $order
     */
    protected function _addStatusHistoryComment()
    {
        $success_result = (strcmp($this->_success, 'true') == 0 || strcmp($this->_success, '1') == 0) ? 'true' : 'false';
        $success = (!empty($this->_reason)) ? "$success_result <br />reason:$this->_reason" : $success_result;

        if($this->_eventCode == Notification::REFUND || $this->_eventCode == Notification::CAPTURE) {

            $currency = $this->_order->getOrderCurrencyCode();

            // check if it is a full or partial refund
            $amount = $this->_value;
            $orderAmount = (int) $this->_adyenHelper->formatAmount($this->_order->getGrandTotal(), $currency);

            $this->_debugData['_addStatusHistoryComment amount'] = 'amount notification:'.$amount . ' amount order:'.$orderAmount;

            if($amount == $orderAmount) {
//                $this->_order->setAdyenEventCode($this->_eventCode . " : " . strtoupper($success_result));
                $this->_order->setData('adyen_notification_event_code', $this->_eventCode . " : " . strtoupper($success_result));
            } else {
//                $this->_order->setAdyenEventCode("(PARTIAL) " . $this->_eventCode . " : " . strtoupper($success_result));
                $this->_order->setData('adyen_notification_event_code', "(PARTIAL) " . $this->_eventCode . " : " . strtoupper($success_result));
            }
        } else {
//            $this->_order->setAdyenEventCode($this->_eventCode . " : " . strtoupper($success_result));
            $this->_order->setData('adyen_notification_event_code', $this->_eventCode . " : " . strtoupper($success_result));
        }

        // if payment method is klarna or openinvoice/afterpay show the reservartion number
        if(($this->_paymentMethod == "klarna" || $this->_paymentMethod == "afterpay_default" || $this->_paymentMethod == "openinvoice") && ($this->_klarnaReservationNumber != null && $this->_klarnaReservationNumber != "")) {
            $klarnaReservationNumberText = "<br /> reservationNumber: " . $this->_klarnaReservationNumber;
        } else {
            $klarnaReservationNumberText = "";
        }

        if($this->_boletoPaidAmount != null && $this->_boletoPaidAmount != "") {
            $boletoPaidAmountText = "<br /> Paid amount: " . $this->_boletoPaidAmount;
        } else {
            $boletoPaidAmountText = "";
        }

        $type = 'Adyen HTTP Notification(s):';
        $comment = __('%1 <br /> eventCode: %2 <br /> pspReference: %3 <br /> paymentMethod: %4 <br /> success: %5 %6 %7', $type, $this->_eventCode, $this->_pspReference, $this->_paymentMethod, $success, $klarnaReservationNumberText, $boletoPaidAmountText);

        // If notification is pending status and pending status is set add the status change to the comment history
        if($this->_eventCode == Notification::PENDING)
        {
            $pendingStatus = $this->_getConfigData('pending_status', 'adyen_abstract', $this->_order->getStoreId());
            if($pendingStatus != "") {
                $this->_order->addStatusHistoryComment($comment, $pendingStatus);
                $this->_debugData['_addStatusHistoryComment'] = 'Created comment history for this notification with status change to: ' . $pendingStatus;
                return;
            }
        }

        // if manual review is accepted and a status is selected. Change the status through this comment history item
        if($this->_eventCode == Notification::MANUAL_REVIEW_ACCEPT
            && $this->_getFraudManualReviewAcceptStatus() != "")
        {
            $manualReviewAcceptStatus = $this->_getFraudManualReviewAcceptStatus();
            $this->_order->addStatusHistoryComment($comment, $manualReviewAcceptStatus);
            $this->_debugData['_addStatusHistoryComment'] = 'Created comment history for this notification with status change to: ' . $manualReviewAcceptStatus;
            return;
        }

        $this->_order->addStatusHistoryComment($comment);
        $this->_debugData['_addStatusHistoryComment'] = 'Created comment history for this notification';
    }

    /**
     * @param $order
     * @return bool
     * @deprecate not needed already cancelled in ProcessController
     */
    protected function _holdCancelOrder($ignoreHasInvoice)
    {
        $orderStatus = $this->_getConfigData('payment_cancelled', 'adyen_abstract', $this->_order->getStoreId());


        // check if order has in invoice only cancel/hold if this is not the case
        if ($ignoreHasInvoice || !$this->_order->hasInvoices()) {
            $this->_order->setActionFlag($orderStatus, true);

            if($orderStatus == \Magento\Sales\Model\Order::STATE_HOLDED) {
                if ($this->_order->canHold()) {
                    $this->_order->hold();
                } else {
                    $this->_debugData['warning'] = 'Order can not hold or is already on Hold';
                    return;
                }
            } else {
                if ($this->_order->canCancel()) {
                    $this->_order->cancel();
                } else {
                    $this->_debugData['warning'] = 'Order can not be canceled';
                    return;
                }
            }
        } else {
            $this->_debugData['warning'] = 'Order has already an invoice so cannot be canceled';
        }
    }

    /**
     * @param $params
     */
    protected function _processNotification()
    {
        $this->_debugData['_processNotification'] = 'Processing the notification';
        $_paymentCode = $this->_paymentMethodCode();

        switch ($this->_eventCode) {
            case Notification::REFUND_FAILED:
                // do nothing only inform the merchant with order comment history
                break;
            case Notification::REFUND:
                $ignoreRefundNotification = $this->_getConfigData('ignore_refund_notification', 'adyen_abstract', $this->_order->getStoreId());
                if($ignoreRefundNotification != true) {
                    $this->_refundOrder();
                    //refund completed
                    $this->_setRefundAuthorized();
                } else {
                    $this->_debugData['_processNotification info'] = 'Setting to ignore refund notification is enabled so ignore this notification';
                }
                break;
            case Notification::PENDING:
                if($this->_getConfigData('send_email_bank_sepa_on_pending', 'adyen_abstract', $this->_order->getStoreId())) {
                    // Check if payment is banktransfer or sepa if true then send out order confirmation email
                    $isBankTransfer = $this->_isBankTransfer($this->_paymentMethod);
                    if($isBankTransfer || $this->_paymentMethod == 'sepadirectdebit') {
//                        $this->_order->sendNewOrderEmail(); // send order email
                        $this->_orderSender->send($this->_order);

                        $this->_debugData['_processNotification send email'] = 'Send orderconfirmation email to shopper';
                    }
                }
                break;
            case Notification::HANDLED_EXTERNALLY:
            case Notification::AUTHORISATION:
                // for POS don't do anything on the AUTHORIZATION
                if($_paymentCode != "adyen_pos") {
                    $this->_authorizePayment();
                }
                break;
            case Notification::MANUAL_REVIEW_REJECT:
                // don't do anything it will send a CANCEL_OR_REFUND notification when this payment is captured
                break;
            case Notification::MANUAL_REVIEW_ACCEPT:
                // only process this if you are on auto capture. On manual capture you will always get Capture or CancelOrRefund notification
                if ($this->_isAutoCapture()) {
                    $this->_setPaymentAuthorized(false);
                }
                break;
            case Notification::CAPTURE:
                if($_paymentCode != "adyen_pos") {
                    // ignore capture if you are on auto capture (this could be called if manual review is enabled and you have a capture delay)
                    if (!$this->_isAutoCapture()) {
                        $this->_setPaymentAuthorized(false, true);
                    }
                } else {

                    // uncancel the order just to be sure that order is going trough
//                    $this->_uncancelOrder($this->_order);

                    // FOR POS authorize the payment on the CAPTURE notification
                    $this->_authorizePayment($this->_order, $this->_paymentMethod);
                }
                break;
            case Notification::CAPTURE_FAILED:
            case Notification::CANCELLATION:
            case Notification::CANCELLED:
                $this->_holdCancelOrder(true);
                break;
            case Notification::CANCEL_OR_REFUND:
                if(isset($this->_modificationResult) && $this->_modificationResult != "") {
                    if($this->_modificationResult == "cancel") {
                        $this->_holdCancelOrder(true);
                    } elseif($this->_modificationResult == "refund") {
                        $this->_refundOrder();
                        //refund completed
                        $this->_setRefundAuthorized();
                    }
                } else {
                    if ($this->_order->isCanceled() || $this->_order->getState() === \Magento\Sales\Model\Order::STATE_HOLDED) {
                        $this->_debugData['_processNotification info'] = 'Order is already cancelled or holded so do nothing';
                    } else if ($this->_order->canCancel() || $this->_order->canHold()) {
                        $this->_debugData['_processNotification info'] = 'try to cancel the order';
                        $this->_holdCancelOrder($this->_order, true);
                    } else {
                        $this->_debugData['_processNotification info'] = 'try to refund the order';
                        // refund
                        $this->_refundOrder();
                        //refund completed
                        $this->_setRefundAuthorized();
                    }
                }
                break;
            default:
                $this->_order->getPayment()->getMethodInstance()->writeLog('notification event not supported!');
                break;
        }
    }

    /**
     * Not implemented
     * @return bool
     */
    protected function _refundOrder()
    {
        $this->_debugData['_refundOrder'] = 'Refunding the order';

//        // Don't create a credit memo if refund is initialize in Magento because in this case the credit memo already exits
//        $result = Mage::getModel('adyen/event')
//            ->getEvent($this->_pspReference, '[refund-received]');
//        if (!empty($result)) {
//            $this->_debugData['_refundOrder ignore'] = 'Skip refund process because credit memo is already created';
//            return false;
//        }
//
//        $_mail = (bool) $this->_getConfigData('send_update_mail', 'adyen_abstract', $order->getStoreId());
//
//        $currency = $order->getOrderCurrencyCode(); // use orderCurrency because adyen respond in the same currency as in the request
//        $amount = Mage::helper('adyen')->originalAmount($this->_value, $currency);
//
//        if ($order->canCreditmemo()) {
//            $service = Mage::getModel('sales/service_order', $order);
//            $creditmemo = $service->prepareCreditmemo();
//            $creditmemo->getOrder()->setIsInProcess(true);
//
//            //set refund data on the order
//            $creditmemo->setGrandTotal($amount);
//            $creditmemo->setBaseGrandTotal($amount);
//            $creditmemo->save();
//
//            try {
//                Mage::getModel('core/resource_transaction')
//                    ->addObject($creditmemo)
//                    ->addObject($creditmemo->getOrder())
//                    ->save();
//                //refund
//                $creditmemo->refund();
//                $transactionSave = Mage::getModel('core/resource_transaction')
//                    ->addObject($creditmemo)
//                    ->addObject($creditmemo->getOrder());
//                if ($creditmemo->getInvoice()) {
//                    $transactionSave->addObject($creditmemo->getInvoice());
//                }
//                $transactionSave->save();
//                if ($_mail) {
//                    $creditmemo->getOrder()->setCustomerNoteNotify(true);
//                    $creditmemo->sendEmail();
//                }
//                $this->_debugData['_refundOrder done'] = 'Credit memo is created';
//            } catch (Exception $e) {
//                $this->_debugData['_refundOrder error'] = 'Error creating credit memo error message is: ' . $e->getMessage();
//                Mage::logException($e);
//            }
//        } else {
//            $this->_debugData['_refundOrder error'] = 'Order can not be refunded';
//        }
    }

    /**
     * @param $order
     */
    protected function _setRefundAuthorized()
    {
        $this->_debugData['_setRefundAuthorized'] = 'Status update to default status or refund_authorized status if this is set';
        $this->_order->addStatusHistoryComment(__('Adyen Refund Successfully completed'));
    }

    /**
     *
     */
    protected function _authorizePayment()
    {
        $this->_debugData['_authorizePayment'] = 'Authorisation of the order';

//        $this->_uncancelOrder($order); // not implemented in magento v2.0

        $fraudManualReviewStatus = $this->_getFraudManualReviewStatus();


        // If manual review is active and a seperate status is used then ignore the pre authorized status
        if($this->_fraudManualReview != true || $fraudManualReviewStatus == "") {
            $this->_setPrePaymentAuthorized();
        } else {
            $this->_debugData['_authorizePayment info'] = 'Ignore the pre authorized status because the order is under manual review and use the Manual review status';
        }

        $this->_prepareInvoice();

        $_paymentCode = $this->_paymentMethodCode();

        // for boleto confirmation mail is send on order creation
        if($this->_paymentMethod != "adyen_boleto") {
            // send order confirmation mail after invoice creation so merchant can add invoicePDF to this mail
//            $this->_order->sendNewOrderEmail(); // send order email
            $this->_orderSender->send($this->_order);
        }

        if(($this->_paymentMethod == "c_cash" && $this->_getConfigData('create_shipment', 'adyen_cash', $this->_order->getStoreId())) || ($this->_getConfigData('create_shipment', 'adyen_pos', $this->_order->getStoreId()) && $_paymentCode == "adyen_pos"))
        {
            $this->_createShipment();
        }
    }

    private function _setPrePaymentAuthorized()
    {
        $status = $this->_getConfigData('payment_pre_authorized', 'adyen_abstract', $this->_order->getStoreId());

        // only do this if status in configuration is set
        if(!empty($status)) {
            $this->_order->addStatusHistoryComment(__('Payment is pre authorised waiting for capture'), $status);
            $this->_debugData['_setPrePaymentAuthorized'] = 'Order status is changed to Pre-authorised status, status is ' . $status;
        } else {
            $this->_debugData['_setPrePaymentAuthorized'] = 'No pre-authorised status is used so ignore';
        }
    }

    /**
     * @param $order
     */
    protected function _prepareInvoice()
    {
        $this->_debugData['_prepareInvoice'] = 'Prepare invoice for order';
        $payment = $this->_order->getPayment()->getMethodInstance();


        //Set order state to new because with order state payment_review it is not possible to create an invoice
        if (strcmp($this->_order->getState(), \Magento\Sales\Model\Order::STATE_PAYMENT_REVIEW) == 0) {
            $this->_order->setState(\Magento\Sales\Model\Order::STATE_NEW);
        }

        //capture mode
        if (!$this->_isAutoCapture()) {
            $this->_order->addStatusHistoryComment(__('Capture Mode set to Manual'));
            $this->_debugData['_prepareInvoice capture mode'] = 'Capture mode is set to Manual';

            // show message if order is in manual review
            if($this->_fraudManualReview) {
                // check if different status is selected
                $fraudManualReviewStatus = $this->_getFraudManualReviewStatus();
                if($fraudManualReviewStatus != "") {
                    $status = $fraudManualReviewStatus;
                    $comment = "Adyen Payment is in Manual Review check the Adyen platform";
                    $this->_order->addStatusHistoryComment(__($comment), $status);
                }
            }

            $createPendingInvoice = (bool) $this->_getConfigData('create_pending_invoice', 'adyen_abstract', $this->_order->getStoreId());
            if(!$createPendingInvoice) {
                $this->_debugData['_prepareInvoice done'] = 'Setting pending invoice is off so don\'t create an invoice wait for the capture notification';
                return;
            }
        }

        // validate if amount is total amount
        $orderCurrencyCode = $this->_order->getOrderCurrencyCode();
        $orderAmount = (int) $this->_adyenHelper->formatAmount($this->_order->getGrandTotal(), $orderCurrencyCode);

        if($this->_isTotalAmount($orderAmount)) {
            $this->_createInvoice();
        } else {
            $this->_debugData['_prepareInvoice partial authorisation step1'] = 'This is a partial AUTHORISATION';

            // Check if this is the first partial authorisation or if there is already been an authorisation
            $paymentObj = $this->_order->getPayment();
            $authorisationAmount = $paymentObj->getAdyenAuthorisationAmount();
            if($authorisationAmount != "") {
                $this->_debugData['_prepareInvoice partial authorisation step2'] = 'There is already a partial AUTHORISATION received check if this combined with the previous amounts match the total amount of the order';
                $authorisationAmount = (int) $authorisationAmount;
                $currentValue = (int) $this->_value;
                $totalAuthorisationAmount = $authorisationAmount + $currentValue;

                // update amount in column
                $paymentObj->setAdyenAuthorisationAmount($totalAuthorisationAmount);

                if($totalAuthorisationAmount == $orderAmount) {
                    $this->_debugData['_prepareInvoice partial authorisation step3'] = 'The full amount is paid. This is the latest AUTHORISATION notification. Create the invoice';
                    $this->_createInvoice();
                } else {
                    // this can be multiple times so use envenData as unique key
                    $this->_debugData['_prepareInvoice partial authorisation step3'] = 'The full amount is not reached. Wait for the next AUTHORISATION notification. The current amount that is authorized is:' . $totalAuthorisationAmount;
                }
            } else {
                $this->_debugData['_prepareInvoice partial authorisation step2'] = 'This is the first partial AUTHORISATION save this into the adyen_authorisation_amount field';
                $paymentObj->setAdyenAuthorisationAmount($this->_value);
            }
        }
    }

    /**
     * @param $order
     * @return bool
     */
    protected function _isAutoCapture()
    {
        $captureMode = trim($this->_getConfigData('capture_mode', 'adyen_abstract', $this->_order->getStoreId()));
        $sepaFlow = trim($this->_getConfigData('flow', 'adyen_sepa', $this->_order->getStoreId()));
        $_paymentCode = $this->_paymentMethodCode();
        $captureModeOpenInvoice = $this->_getConfigData('auto_capture_openinvoice', 'adyen_abstract', $this->_order->getStoreId());
        $captureModePayPal = trim($this->_getConfigData('paypal_capture_mode', 'adyen_abstract', $this->_order->getStoreId()));

        //check if it is a banktransfer. Banktransfer only a Authorize notification is send.
        $isBankTransfer = $this->_isBankTransfer();

        // if you are using authcap the payment method is manual. There will be a capture send to indicate if payment is succesfull
        if($_paymentCode == "adyen_sepa" && $sepaFlow == "authcap") {
            return false;
        }

        // payment method ideal, cash adyen_boleto or adyen_pos has direct capture
        if (strcmp($this->_paymentMethod, 'ideal') === 0 || strcmp($this->_paymentMethod, 'c_cash' ) === 0 || $_paymentCode == "adyen_pos" || $isBankTransfer == true || ($_paymentCode == "adyen_sepa" && $sepaFlow != "authcap") || $_paymentCode == "adyen_boleto") {
            return true;
        }
        // if auto capture mode for openinvoice is turned on then use auto capture
        if ($captureModeOpenInvoice == true && (strcmp($this->_paymentMethod, 'openinvoice') === 0 || strcmp($this->_paymentMethod, 'afterpay_default') === 0 || strcmp($this->_paymentMethod, 'klarna') === 0)) {
            return true;
        }
        // if PayPal capture modues is different from the default use this one
        if(strcmp($this->_paymentMethod, 'paypal' ) === 0 && $captureModePayPal != "") {
            if(strcmp($captureModePayPal, 'auto') === 0 ) {
                return true;
            } elseif(strcmp($captureModePayPal, 'manual') === 0 ) {
                return false;
            }
        }
        if (strcmp($captureMode, 'manual') === 0) {
            return false;
        }
        //online capture after delivery, use Magento backend to online invoice (if the option auto capture mode for openinvoice is not set)
        if (strcmp($this->_paymentMethod, 'openinvoice') === 0 || strcmp($this->_paymentMethod, 'afterpay_default') === 0 || strcmp($this->_paymentMethod, 'klarna') === 0) {
            return false;
        }
        return true;
    }

    /**
     * @param $paymentMethod
     * @return bool
     */
    protected function _isBankTransfer() {
        if(strlen($this->_paymentMethod) >= 12 &&  substr($this->_paymentMethod, 0, 12) == "bankTransfer") {
            $isBankTransfer = true;
        } else {
            $isBankTransfer = false;
        }
        return $isBankTransfer;
    }


    protected function _getFraudManualReviewStatus()
    {
        return $this->_getConfigData('fraud_manual_review_status', 'adyen_abstract', $this->_order->getStoreId());
    }

    protected function _getFraudManualReviewAcceptStatus()
    {
        return $this->_getConfigData('fraud_manual_review_accept_status', 'adyen_abstract', $this->_order->getStoreId());
    }

    protected function _isTotalAmount($orderAmount) {

        $this->_debugData['_isTotalAmount'] = 'Validate if AUTHORISATION notification has the total amount of the order';
        $value = (int)$this->_value;

        if($value == $orderAmount) {
            $this->_debugData['_isTotalAmount result'] = 'AUTHORISATION has the full amount';
            return true;
        } else {
            $this->_debugData['_isTotalAmount result'] = 'This is a partial AUTHORISATION, the amount is ' . $this->_value;
            return false;
        }

    }

    protected function _createInvoice()
    {
        $this->_debugData['_createInvoice'] = 'Creating invoice for order';

        if ($this->_order->canInvoice()) {

            /* We do not use this inside a transaction because order->save() is always done on the end of the notification
             * and it could result in a deadlock see https://github.com/Adyen/magento/issues/334
             */
            try {
                $invoice = $this->_order->prepareInvoice();
                $invoice->getOrder()->setIsInProcess(true);

                // set transaction id so you can do a online refund from credit memo
                $invoice->setTransactionId(1);

                $autoCapture = $this->_isAutoCapture($this->_order);
                $createPendingInvoice = (bool) $this->_getConfigData('create_pending_invoice', 'adyen_abstract', $this->_order->getStoreId());

                if((!$autoCapture) && ($createPendingInvoice)) {

                    // if amount is zero create a offline invoice
                    $value = (int)$this->_value;
                    if($value == 0) {
                        $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE);
                    } else {
                        $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::NOT_CAPTURE);
                    }

                    $invoice->register();
                } else {
                    $invoice->register()->pay();
                }

                $invoice->save();

                $this->_debugData['_createInvoice done'] = 'Created invoice';
            } catch (Exception $e) {
                $this->_debugData['_createInvoice error'] = 'Error saving invoice. The error message is: ' . $e->getMessage();
                throw new Exception(sprintf('Error saving invoice. The error message is:', $e->getMessage()));
            }

            $this->_setPaymentAuthorized();

            $invoiceAutoMail = (bool) $this->_getConfigData('send_invoice_update_mail', 'adyen_abstract', $this->_order->getStoreId());
            if ($invoiceAutoMail) {
                $invoice->sendEmail();
            }
        } else {
            $this->_debugData['_createInvoice error'] = 'It is not possible to create invoice for this order';
        }
    }

    /**
     *
     */
    protected function _setPaymentAuthorized($manualReviewComment = true, $createInvoice = false)
    {
        $this->_debugData['_setPaymentAuthorized start'] = 'Set order to authorised';

        // if full amount is captured create invoice
        $currency = $this->_order->getOrderCurrencyCode();
        $amount = $this->_value;
        $orderAmount = (int) $this->_adyenHelper->formatAmount($this->_order->getGrandTotal(), $currency);

        // create invoice for the capture notification if you are on manual capture
        if($createInvoice == true && $amount == $orderAmount) {
            $this->_debugData['_setPaymentAuthorized amount'] = 'amount notification:'.$amount . ' amount order:'.$orderAmount;
            $this->_createInvoice($this->_order);
        }

        // if you have capture on shipment enabled don't set update the status of the payment
        $captureOnShipment = $this->_getConfigData('capture_on_shipment', 'adyen_abstract', $this->_order->getStoreId());
        if(!$captureOnShipment) {
            $status = $this->_getConfigData('payment_authorized', 'adyen_abstract', $this->_order->getStoreId());
        }

        // virtual order can have different status
        if($this->_order->getIsVirtual()) {
            $this->_debugData['_setPaymentAuthorized virtual'] = 'Product is a virtual product';
            $virtual_status = $this->_getConfigData('payment_authorized_virtual');
            if($virtual_status != "") {
                $status = $virtual_status;
            }
        }

        // check for boleto if payment is totally paid
        if($this->_paymentMethodCode($this->_order) == "adyen_boleto") {

            // check if paid amount is the same as orginal amount
            $orginalAmount = $this->_boletoOriginalAmount;
            $paidAmount = $this->_boletoPaidAmount;

            if($orginalAmount != $paidAmount) {

                // not the full amount is paid. Check if it is underpaid or overpaid
                // strip the  BRL of the string
                $orginalAmount = str_replace("BRL", "",  $orginalAmount);
                $orginalAmount = floatval(trim($orginalAmount));

                $paidAmount = str_replace("BRL", "",  $paidAmount);
                $paidAmount = floatval(trim($paidAmount));

                if($paidAmount > $orginalAmount) {
                    $overpaidStatus =  $this->_getConfigData('order_overpaid_status', 'adyen_boleto');
                    // check if there is selected a status if not fall back to the default
                    $status = (!empty($overpaidStatus)) ? $overpaidStatus : $status;
                } else {
                    $underpaidStatus = $this->_getConfigData('order_underpaid_status', 'adyen_boleto');
                    // check if there is selected a status if not fall back to the default
                    $status = (!empty($underpaidStatus)) ? $underpaidStatus : $status;
                }
            }
        }

        $comment = "Adyen Payment Successfully completed";

        // if manual review is true use the manual review status if this is set
        if($manualReviewComment == true && $this->_fraudManualReview) {
            // check if different status is selected
            $fraudManualReviewStatus = $this->_getFraudManualReviewStatus();
            if($fraudManualReviewStatus != "") {
                $status = $fraudManualReviewStatus;
                $comment = "Adyen Payment is in Manual Review check the Adyen platform";
            }
        }

        $status = (!empty($status)) ? $status : $this->_order->getStatus();
        $this->_order->addStatusHistoryComment(__($comment), $status);
        $this->_debugData['_setPaymentAuthorized end'] = 'Order status is changed to authorised status, status is ' . $status;
    }

    /**
     *
     */
    protected function _createShipment() {
        $this->_debugData['_createShipment'] = 'Creating shipment for order';
        // create shipment for cash payment
        $payment = $this->_order->getPayment()->getMethodInstance();
        if($this->_order->canShip())
        {
            $itemQty = array();
            $shipment = $this->_order->prepareShipment($itemQty);
            if($shipment) {
                $shipment->register();
                $shipment->getOrder()->setIsInProcess(true);
                $comment = __('Shipment created by Adyen');
                $shipment->addComment($comment);
                Mage::getModel('core/resource_transaction')
                    ->addObject($shipment)
                    ->addObject($shipment->getOrder())
                    ->save();
                $this->_debugData['_createShipment done'] = 'Order is shipped';
            }
        } else {
            $this->_debugData['_createShipment error'] = 'Order can\'t be shipped';
        }
    }


    /**
     * Retrieve information from payment configuration
     *
     * @param string $field
     * @param int|string|null|\Magento\Store\Model\Store $storeId
     *
     * @return mixed
     */
    protected function _getConfigData($field, $paymentMethodCode = 'adyen_cc', $storeId)
    {
        // replace for now settings should moved from adyen_cc to adyen_abstract
        if($paymentMethodCode == 'adyen_abstract') {
            $paymentMethodCode = "adyen_cc";
        }
        $path = 'payment/' . $paymentMethodCode . '/' . $field;
        return $this->_scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
    }


}