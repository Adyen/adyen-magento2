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

use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\Vault;
use Adyen\Payment\Model\Ui\AdyenCcConfigProvider;
use Adyen\Payment\Model\Ui\AdyenHppConfigProvider;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Webapi\Exception;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Framework\App\Area;
use Magento\Framework\App\AreaList;
use Magento\Framework\Phrase\Renderer\Placeholder;
use Magento\Framework\Phrase;
use Magento\Sales\Model\OrderRepository;
use Magento\Vault\Api\Data\PaymentTokenFactoryInterface;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;
use Magento\Vault\Model\PaymentTokenManagement;
use DateInterval;
use DateTime;
use DateTimeZone;

class Cron
{
    /**
     * Logging instance
     *
     * @var \Adyen\Payment\Logger\AdyenLogger
     */
    protected $_logger;

    /**
     * @var ResourceModel\Notification\CollectionFactory
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
     * @var InvoiceSender
     */
    protected $_invoiceSender;

    /**
     * @var \Magento\Framework\DB\TransactionFactory
     */
    protected $_transactionFactory;

    /**
     * @var \Adyen\Payment\Model\Billing\AgreementFactory
     */
    protected $_billingAgreementFactory;

    /**
     * @var ResourceModel\Billing\Agreement\CollectionFactory
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
    protected $_acquirerReference;

    /**
     * @var
     */
    protected $ratepayDescriptor;

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
    protected $_currency;

    /**
     * @var
     */
    protected $orderAmount;

    /**
     * @var
     */
    protected $orderCurrency;

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
     * @var ResourceModel\Order\Payment\CollectionFactory
     */
    protected $_adyenOrderPaymentCollectionFactory;

    /**
     * @var ResourceModel\InvoiceFactory
     */
    protected $_adyenInvoiceFactory;

    /**
     * @var AreaList
     */
    protected $_areaList;

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory
     */
    protected $_orderStatusCollection;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var OrderRepository
     */
    private $orderRepository;

    /**
     * @var ResourceModel\Billing\Agreement
     */
    private $agreementResourceModel;

    /**
     * @var \Magento\Sales\Model\Order\Payment\Transaction\Builder
     */
    private $transactionBuilder;

    /**
     * @var \Magento\Framework\Serialize\SerializerInterface
     */
    private $serializer;

    /**
     * @var \Magento\Framework\Notification\NotifierInterface
     */
    private $notifierPool;

    /**
     * @var \Magento\Framework\Stdlib\DateTime\TimezoneInterface
     */
    private $timezone;

    /**
     * @var \Adyen\Payment\Helper\Config
     */
    protected $configHelper;

    /**
     * @var PaymentTokenManagement
     */
    private $paymentTokenManagement;

    /**
     * @var PaymentTokenFactoryInterface
     */
    protected $paymentTokenFactory;

    /**
     * @var PaymentTokenRepositoryInterface
     */
    protected $paymentTokenRepository;

    /**
     * @var EncryptorInterface
     */
    protected $encryptor;

    /**
     * @var ChargedCurrency
     */
    private $chargedCurrency;

    /**
     * Cron constructor.
     *
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Adyen\Payment\Logger\AdyenLogger $adyenLogger
     * @param ResourceModel\Notification\CollectionFactory $notificationFactory
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     * @param OrderSender $orderSender
     * @param InvoiceSender $invoiceSender
     * @param \Magento\Framework\DB\TransactionFactory $transactionFactory
     * @param Billing\AgreementFactory $billingAgreementFactory
     * @param ResourceModel\Billing\Agreement\CollectionFactory $billingAgreementCollectionFactory
     * @param Api\PaymentRequest $paymentRequest
     * @param Order\PaymentFactory $adyenOrderPaymentFactory
     * @param ResourceModel\Order\Payment\CollectionFactory $adyenOrderPaymentCollectionFactory
     * @param InvoiceFactory $adyenInvoiceFactory
     * @param AreaList $areaList
     * @param \Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory $orderStatusCollection
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param OrderRepository $orderRepository
     * @param ResourceModel\Billing\Agreement $agreementResourceModel
     * @param \Magento\Sales\Model\Order\Payment\Transaction\Builder $transactionBuilder
     * @param \Magento\Framework\Serialize\SerializerInterface $serializer
     * @param \Magento\Framework\Notification\NotifierInterface $notifierPool
     * @param \Magento\Framework\Stdlib\DateTime\TimezoneInterface $timezone
     * @param \Adyen\Payment\Helper\Config $configHelper
     * @param PaymentTokenManagement $paymentTokenManagement
     * @param PaymentTokenFactoryInterface $paymentTokenFactory
     * @param PaymentTokenRepositoryInterface $paymentTokenRepository
     * @param EncryptorInterface $encryptor
     * @param ChargedCurrency $chargedCurrency
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Adyen\Payment\Logger\AdyenLogger $adyenLogger,
        \Adyen\Payment\Model\ResourceModel\Notification\CollectionFactory $notificationFactory,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Adyen\Payment\Helper\Data $adyenHelper,
        OrderSender $orderSender,
        InvoiceSender $invoiceSender,
        \Magento\Framework\DB\TransactionFactory $transactionFactory,
        \Adyen\Payment\Model\Billing\AgreementFactory $billingAgreementFactory,
        \Adyen\Payment\Model\ResourceModel\Billing\Agreement\CollectionFactory $billingAgreementCollectionFactory,
        \Adyen\Payment\Model\Api\PaymentRequest $paymentRequest,
        \Adyen\Payment\Model\Order\PaymentFactory $adyenOrderPaymentFactory,
        \Adyen\Payment\Model\ResourceModel\Order\Payment\CollectionFactory $adyenOrderPaymentCollectionFactory,
        \Adyen\Payment\Model\InvoiceFactory $adyenInvoiceFactory,
        AreaList $areaList,
        \Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory $orderStatusCollection,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        OrderRepository $orderRepository,
        \Adyen\Payment\Model\ResourceModel\Billing\Agreement $agreementResourceModel,
        \Magento\Sales\Model\Order\Payment\Transaction\Builder $transactionBuilder,
        \Magento\Framework\Serialize\SerializerInterface $serializer,
        \Magento\Framework\Notification\NotifierInterface $notifierPool,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $timezone,
        \Adyen\Payment\Helper\Config $configHelper,
        PaymentTokenManagement $paymentTokenManagement,
        PaymentTokenFactoryInterface $paymentTokenFactory,
        PaymentTokenRepositoryInterface $paymentTokenRepository,
        EncryptorInterface $encryptor,
        ChargedCurrency $chargedCurrency
    ) {
        $this->_scopeConfig = $scopeConfig;
        $this->_adyenLogger = $adyenLogger;
        $this->_notificationFactory = $notificationFactory;
        $this->_orderFactory = $orderFactory;
        $this->_adyenHelper = $adyenHelper;
        $this->_orderSender = $orderSender;
        $this->_invoiceSender = $invoiceSender;
        $this->_transactionFactory = $transactionFactory;
        $this->_billingAgreementFactory = $billingAgreementFactory;
        $this->_billingAgreementCollectionFactory = $billingAgreementCollectionFactory;
        $this->_adyenPaymentRequest = $paymentRequest;
        $this->_adyenOrderPaymentFactory = $adyenOrderPaymentFactory;
        $this->_adyenOrderPaymentCollectionFactory = $adyenOrderPaymentCollectionFactory;
        $this->_adyenInvoiceFactory = $adyenInvoiceFactory;
        $this->_areaList = $areaList;
        $this->_orderStatusCollection = $orderStatusCollection;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->orderRepository = $orderRepository;
        $this->agreementResourceModel = $agreementResourceModel;
        $this->transactionBuilder = $transactionBuilder;
        $this->serializer = $serializer;
        $this->notifierPool = $notifierPool;
        $this->timezone = $timezone;
        $this->configHelper = $configHelper;
        $this->paymentTokenManagement = $paymentTokenManagement;
        $this->paymentTokenFactory = $paymentTokenFactory;
        $this->paymentTokenRepository = $paymentTokenRepository;
        $this->encryptor = $encryptor;
        $this->chargedCurrency = $chargedCurrency;
    }

    /**
     * Process the notification
     *
     * @return void
     */
    public function processNotification()
    {
        try {
            $this->execute();
        } catch (\Exception $e) {
            $this->_adyenLogger->addAdyenNotificationCronjob($e->getMessage() . "\n" . $e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * @param $notification
     * @return bool
     */
    private function shouldSkipProcessingNotification($notification)
    {
        switch ($notification['event_code']) {
            // Remove OFFER_CLOSED and AUTHORISATION success=false notifications for some time from the processing list
            // to ensure they won't close any order which has an AUTHORISED notification arrived a bit later than the
            // OFFER_CLOSED or the AUTHORISATION success=false one.
            case Notification::OFFER_CLOSED:
                // OFFER_CLOSED notifications needs to be at least 10 minutes old to be processed
                $offerClosedMinDate = new \DateTime('-10 minutes');
                $createdAt = \DateTime::createFromFormat('Y-m-d H:i:s', $notification['created_at']);

                $minutesUntilProcessing = $createdAt->diff($offerClosedMinDate)->i;

                if ($minutesUntilProcessing > 0) {
                    $this->_adyenLogger->addAdyenNotificationCronjob(
                        sprintf(
                            'OFFER_CLOSED notification (entity_id: %s) for merchant_reference: %s is skipped! Wait %s minute(s) before processing.',
                            $notification->getEntityId(),
                            $notification->getMerchantReference(),
                            $minutesUntilProcessing
                        )
                    );

                    return true;
                }

                break;
            case Notification::AUTHORISATION:
                // Only delay success=false notifications processing
                if (
                    strcmp($notification['success'], 'true') == 0 ||
                    strcmp($notification['success'], '1') == 0
                ) {
                    // do not skip this notification but process it now
                    return false;
                }

                // AUTHORISATION success=false notifications needs to be at least 10 minutes old to be processed
                $authorisationSuccessFalseMinDate = new \DateTime('-10 minutes');
                $createdAt = \DateTime::createFromFormat('Y-m-d H:i:s', $notification['created_at']);

                $minutesUntilProcessing = $createdAt->diff($authorisationSuccessFalseMinDate)->i;

                if ($minutesUntilProcessing > 0) {
                    $this->_adyenLogger->addAdyenNotificationCronjob(
                        sprintf(
                            'AUTHORISATION success=false notification (entity_id: %s) for merchant_reference: %s is skipped! Wait %s minute(s) before processing.',
                            $notification->getEntityId(),
                            $notification->getMerchantReference(),
                            $minutesUntilProcessing
                        )
                    );

                    return true;
                }

                break;
        }

        return false;
    }

    public function execute()
    {
        // needed for Magento < 2.2.0 https://github.com/magento/magento2/pull/8413
        $renderer = Phrase::getRenderer();
        if ($renderer instanceof Placeholder) {
            $this->_areaList->getArea(Area::AREA_CRONTAB)->load(Area::PART_TRANSLATE);
        }

        $this->_order = null;

        $notifications = $this->_notificationFactory->create();
        $notifications->notificationsToProcessFilter();

        // Loop thorugh notifications to set processing to true if notifiaction should not be skipped
        foreach ($notifications as $notification) {
            // Check if notification should be processed or not
            if ($this->shouldSkipProcessingNotification($notification)) {
                // Remove notification from collection which will be processed
                $notifications->removeItemByKey($notification->getId());
                continue;
            }

            // set notification processing to true
            $this->_updateNotification($notification, true, false);
        }

        // loop over the notifications
        $count = 0;
        foreach ($notifications as $notification) {
            try {
                $this->_adyenLogger->addAdyenNotificationCronjob(
                    sprintf("Processing notification %s", $notification->getEntityId())
                );

                // ignore duplicate notification
                if ($this->_isDuplicate($notification)) {
                    $this->_adyenLogger->addAdyenNotificationCronjob(
                        "This is a duplicate notification and will be ignored"
                    );
                    $this->_updateNotification($notification, false, true);
                    ++$count;
                    continue;
                }

                // log the executed notification
                $this->_adyenLogger->addAdyenNotificationCronjob(print_r($notification->debug(), 1));

                // get order
                $incrementId = $notification->getMerchantReference();

                $searchCriteria = $this->searchCriteriaBuilder
                    ->addFilter('increment_id', $incrementId, 'eq')
                    ->create();

                $orderList = $this->orderRepository->getList($searchCriteria)->getItems();

                /** @var \Magento\Sales\Model\Order $order */
                $order = reset($orderList);
                $this->_order = $order;

                if (!$this->_order) {
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
                     * Only cancel the order when it is in state new, pending_payment, or payment review
                     * After order creation alternative payment methods (HPP) has state new and status pending
                     * while card payments has payment_review state and status
                     * if the ORDER_CLOSED is failed (means split payment has not be successful)
                     */
                    if ($this->_order->getState() === \Magento\Sales\Model\Order::STATE_NEW ||
                        $this->_order->getState() === \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT ||
                        $this->_order->getState() === \Magento\Sales\Model\Order::STATE_PAYMENT_REVIEW ||
                        $this->_eventCode == Notification::ORDER_CLOSED
                    ) {
                        $this->_adyenLogger->addAdyenNotificationCronjob('Going to cancel the order');

                        // if payment is API check, check if API result pspreference is the same as reference
                        if ($this->_eventCode == NOTIFICATION::AUTHORISATION
                            && $this->_getPaymentMethodType() == 'api') {
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
                                $this->_eventCode == Notification::ORDER_CLOSED
                            ) {
                                if ($this->configHelper->getNotificationsCanCancel($this->_order->getStoreId())) {
                                    // Move the order from PAYMENT_REVIEW to NEW, so that can be cancelled
                                    if ($this->_order->getState() === \Magento\Sales\Model\Order::STATE_PAYMENT_REVIEW
                                    ) {
                                        $this->_order->setState(\Magento\Sales\Model\Order::STATE_NEW);
                                    }

                                    $this->_holdCancelOrder(false);
                                } else {
                                    $this->_adyenLogger->addAdyenNotificationCronjob(
                                        'order is not cancelled because "notifications_can_cancel" configuration' .
                                        'is false.'
                                    );
                                }
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
                            'Order is already processed so ignore this notification state is:'
                            . $this->_order->getState()
                        );
                    }
                    //Trigger admin notice for unsuccessful REFUND notifications
                    if ($this->_eventCode == Notification::REFUND) {
                        $this->addRefundFailedNotice();
                    }
                } else {
                    // Notification is successful
                    $this->_processNotification();
                }

                try {
                    // set done to true
                    $this->_order->save();
                } catch (\Exception $e) {
                    $this->_adyenLogger->addAdyenNotificationCronjob($e->getMessage());
                }

                $this->_updateNotification($notification, false, true);
                $this->_adyenLogger->addAdyenNotificationCronjob(
                    sprintf("Notification %s is processed", $notification->getEntityId())
                );
                ++$count;
            } catch (\Exception $e) {
                $this->_updateNotification($notification, false, false);
                $this->handleNotificationError($notification, $e->getMessage());
                $this->_adyenLogger->addAdyenNotificationCronjob(
                    sprintf(
                        "Notification %s had an error: %s \n %s",
                        $notification->getEntityId(),
                        $e->getMessage(),
                        $e->getTraceAsString()
                    )
                );
            }
        }
        if ($count > 0) {
            $this->_adyenLogger->addAdyenNotificationCronjob(
                sprintf(
                    "Cronjob updated %s notification(s)",
                    $count
                )
            );
        }
    }

    /**
     * @param $notification
     * @param $processing
     * @param $done
     */
    protected function _updateNotification($notification, $processing, $done)
    {
        if ($done) {
            $notification->setDone(true);
        }
        $notification->setProcessing($processing);
        $notification->setUpdatedAt(new \DateTime());
        $notification->save();
    }

    /**
     * Check if the notification is already executed if so this is a duplicate and ignore this one
     *
     * @param $notification
     * @return bool
     */
    protected function _isDuplicate($notification)
    {
        return $notification->isDuplicate(
            $notification->getPspreference(),
            $notification->getEventCode(),
            $notification->getSuccess(),
            $notification->getOriginalReference(),
            true
        );
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
        $this->_reason = $notification->getReason();
        $this->_value = $notification->getAmountValue();
        $this->_currency = $notification->getAmountCurrency();
        $this->_live = $notification->getLive();

        $additionalData = !empty($notification->getAdditionalData()) ? $this->serializer->unserialize(
            $notification->getAdditionalData()
        ) : "";

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
                $this->_klarnaReservationNumber = isset($additionalData2['acquirerReference']) ? trim(
                    $additionalData2['acquirerReference']
                ) : "";
            }
            $acquirerReference = isset($additionalData['acquirerReference']) ?
                $additionalData['acquirerReference'] : null;
            if ($acquirerReference != "") {
                $this->_acquirerReference = $acquirerReference;
            }
            $ratepayDescriptor = isset($additionalData['openinvoicedata.descriptor']) ?
                $additionalData['openinvoicedata.descriptor'] : "";
            if ($ratepayDescriptor !== "") {
                $this->ratepayDescriptor = $ratepayDescriptor;
            }
        }

        $this->declareOrderVariables();
    }

    /**
     * Declare private variables with order charged amount and currency
     *
     * @return void
     */
    private function declareOrderVariables()
    {
        $orderAmountCurrency = $this->chargedCurrency->getOrderAmountCurrency($this->_order, false);
        $this->orderAmount = $orderAmountCurrency->getAmount();
        $this->orderCurrency = $orderAmountCurrency->getCurrencyCode();
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
            // check if it is a full or partial refund
            $amount = $this->_value;
            $formattedOrderAmount = (int)$this->_adyenHelper->formatAmount($this->orderAmount, $this->orderCurrency);

            $this->_adyenLogger->addAdyenNotificationCronjob(
                'amount notification:' . $amount . ' amount order:' . $formattedOrderAmount
            );

            if ($amount == $formattedOrderAmount) {
                $this->_order->setData(
                    'adyen_notification_event_code',
                    $this->_eventCode . " : " . strtoupper($successResult)
                );
            } else {
                $this->_order->setData(
                    'adyen_notification_event_code',
                    "(PARTIAL) " .
                    $this->_eventCode . " : " . strtoupper($successResult)
                );
            }
        } else {
            $this->_order->setData(
                'adyen_notification_event_code',
                $this->_eventCode . " : " . strtoupper($successResult)
            );
        }

        // if payment method is klarna, ratepay or openinvoice/afterpay show the reservartion number
        if ($this->_adyenHelper->isPaymentMethodOpenInvoiceMethod(
                $this->_paymentMethod
            ) && !empty($this->_klarnaReservationNumber)) {
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
        $comment = __(
            '%1 <br /> eventCode: %2 <br /> pspReference: %3 <br /> paymentMethod: %4 <br />' .
            ' success: %5 %6 %7',
            $type,
            $this->_eventCode,
            $this->_pspReference,
            $this->_paymentMethod,
            $success,
            $klarnaReservationNumberText,
            $boletoPaidAmountText
        );

        // If notification is pending status and pending status is set add the status change to the comment history
        if ($this->_eventCode == Notification::PENDING) {
            $pendingStatus = $this->_getConfigData(
                'pending_status',
                'adyen_abstract',
                $this->_order->getStoreId()
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
            && $this->_getFraudManualReviewAcceptStatus() != ""
        ) {
            $manualReviewAcceptStatus = $this->_getFraudManualReviewAcceptStatus();
            $this->_order->addStatusHistoryComment($comment, $manualReviewAcceptStatus);
            $this->_adyenLogger->addAdyenNotificationCronjob(
                'Created comment history for this notification with status change to: '
                . $manualReviewAcceptStatus
            );
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

        $additionalData = !empty($notification->getAdditionalData()) ? $this->serializer->unserialize(
            $notification->getAdditionalData()
        ) : "";

        $_paymentCode = $this->_paymentMethodCode();

        if ($this->_eventCode == Notification::AUTHORISATION
            || $this->_eventCode == Notification::HANDLED_EXTERNALLY
        ) {
            /*
             * if current notification is authorisation : false and
             * the  previous notification was authorisation : true do not update pspreference
             */
            if (strcmp($this->_success, 'false') == 0 ||
                strcmp($this->_success, '0') == 0 ||
                strcmp($this->_success, '') == 0
            ) {
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
            $cardBin = (isset($additionalData['cardBin'])) ? $additionalData['cardBin'] : "";
            $expiryDate = (isset($additionalData['expiryDate'])) ? $additionalData['expiryDate'] : "";
        }

        // if there is no server communication setup try to get last4 digits from reason field
        if (!isset($ccLast4) || $ccLast4 == "") {
            $ccLast4 = $this->_retrieveLast4DigitsFromReason($this->_reason);
        }

        $this->_order->getPayment()->setAdyenPspReference($this->_pspReference);
        $this->_order->getPayment()->setAdditionalInformation('pspReference', $this->_pspReference);

        if ($this->_klarnaReservationNumber != "") {
            $this->_order->getPayment()->setAdditionalInformation(
                'adyen_klarna_number',
                $this->_klarnaReservationNumber
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
        if (!empty($cardBin)) {
            $this->_order->getPayment()->setAdditionalInformation('adyen_card_bin', $cardBin);
        }
        if (!empty($expiryDate)) {
            $this->_order->getPayment()->setAdditionalInformation('adyen_expiry_date', $expiryDate);
        }
        if ($this->ratepayDescriptor !== "") {
            $this->_order->getPayment()->setAdditionalInformation(
                'adyen_ratepay_descriptor',
                $this->ratepayDescriptor
            );
        }
    }

    /**
     * retrieve last 4 digits of card from the reason field
     *
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
        if (!$this->configHelper->getNotificationsCanCancel($this->_order->getStoreId())) {
            $this->_adyenLogger->addAdyenNotificationCronjob(
                'Order cannot be canceled based on the plugin configuration'
            );
            return;
        }

        $orderStatus = $this->_getConfigData(
            'payment_cancelled',
            'adyen_abstract',
            $this->_order->getStoreId()
        );

        // check if order has in invoice only cancel/hold if this is not the case
        if ($ignoreHasInvoice || !$this->_order->hasInvoices()) {
            if ($orderStatus == \Magento\Sales\Model\Order::STATE_HOLDED) {
                // Allow magento to hold order
                $this->_order->setActionFlag(\Magento\Sales\Model\Order::ACTION_FLAG_HOLD, true);

                if ($this->_order->canHold()) {
                    $this->_order->hold();
                } else {
                    $this->_adyenLogger->addAdyenNotificationCronjob(
                        'Order can not hold or is already on Hold'
                    );
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
            $this->_adyenLogger->addAdyenNotificationCronjob(
                'Order has already an invoice so cannot be canceled'
            );
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
                //Trigger admin notice for REFUND_FAILED notifications
                $this->addRefundFailedNotice();
                break;
            case Notification::REFUND:
                $ignoreRefundNotification = $this->_getConfigData(
                    'ignore_refund_notification',
                    'adyen_abstract',
                    $this->_order->getStoreId()
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
                    'send_email_bank_sepa_on_pending',
                    'adyen_abstract',
                    $this->_order->getStoreId()
                )
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
                $this->_authorizePayment();
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
                /*
                 * ignore capture if you are on auto capture
                 * this could be called if manual review is enabled and you have a capture delay
                 */
                if (!$this->_isAutoCapture()) {
                    $this->_setPaymentAuthorized(false, true);

                    /*
                     * Add invoice in the adyen_invoice table
                     */
                    $invoiceCollection = $this->_order->getInvoiceCollection();
                    foreach ($invoiceCollection as $invoice) {
                        if ($invoice->getTransactionId() == $this->_pspReference) {
                            $this->_adyenInvoiceFactory->create()
                                ->setInvoiceId($invoice->getEntityId())
                                ->setPspreference($this->_pspReference)
                                ->setOriginalReference($this->_originalReference)
                                ->setAcquirerReference($this->_acquirerReference)
                                ->save();
                            $this->_adyenLogger->addAdyenNotificationCronjob(
                                'Created invoice entry in the Adyen table'
                            );
                        }
                    }
                }
                break;
            case Notification::OFFER_CLOSED:
                $previousSuccess = $this->_order->getData('adyen_notification_event_code_success');

                // Order is already Authorised
                if (!empty($previousSuccess)) {
                    $this->_adyenLogger->addAdyenNotificationCronjob(
                        "Order is already authorised, skipping OFFER_CLOSED"
                    );
                    break;
                }

                // Order is already Cancelled
                if ($this->_order->isCanceled()) {
                    $this->_adyenLogger->addAdyenNotificationCronjob(
                        "Order is already cancelled, skipping OFFER_CLOSED"
                    );
                    break;
                }

                /*
                 * For cards, it can be 'visa', 'maestro',...
                 * For alternatives, it can be 'ideal', 'directEbanking',...
                 */
                $notificationPaymentMethod = $this->_paymentMethod;

                /*
                * For cards, it can be 'VI', 'MI',...
                * For alternatives, it can be 'ideal', 'directEbanking',...
                */
                $orderPaymentMethod = $this->_order->getPayment()->getCcType();

                $isOrderCc = strcmp(
                        $this->_paymentMethodCode(),
                        'adyen_cc'
                    ) == 0 || strcmp($this->_paymentMethodCode(), 'adyen_oneclick') == 0;

                /*
                 * If the order was made with an Alternative payment method,
                 * continue with the cancellation only if the payment method of
                 * the notification matches the payment method of the order.
                 */
                if (!$isOrderCc && strcmp($notificationPaymentMethod, $orderPaymentMethod) !== 0) {
                    $this->_adyenLogger->addAdyenNotificationCronjob(
                        "Order is not a credit card,
                    or the payment method in the notification does not match the payment method of the order,
                    skipping OFFER_CLOSED"
                    );
                    break;
                }

                if (!$this->_order->canCancel() && $this->configHelper->getNotificationsCanCancel(
                        $this->_order->getStoreId()
                    )) {
                    // Move the order from PAYMENT_REVIEW to NEW, so that can be cancelled
                    $this->_order->setState(\Magento\Sales\Model\Order::STATE_NEW);
                }
                $this->_holdCancelOrder(true);
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
                        $this->_order->getState() === \Magento\Sales\Model\Order::STATE_HOLDED
                    ) {
                        $this->_adyenLogger->addAdyenNotificationCronjob(
                            'Order is already cancelled or holded so do nothing'
                        );
                    } else {
                        if ($this->_order->canCancel() || $this->_order->canHold()) {
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
                }
                break;

            case Notification::RECURRING_CONTRACT:
                // only store billing agreements if Vault is disabled
                if (!$this->_adyenHelper->isCreditCardVaultEnabled()) {
                    // storedReferenceCode
                    $recurringDetailReference = $this->_pspReference;

                    $storeId = $this->_order->getStoreId();
                    $customerReference = $this->_order->getCustomerId();
                    $listRecurringContracts = null;
                    $this->_adyenLogger->addAdyenNotificationCronjob(
                        __(
                            'CustomerReference is: %1 and storeId is %2 and RecurringDetailsReference is %3',
                            $customerReference,
                            $storeId,
                            $recurringDetailReference
                        )
                    );
                    try {
                        $listRecurringContracts = $this->_adyenPaymentRequest->getRecurringContractsForShopper(
                            $customerReference,
                            $storeId
                        );
                        $contractDetail = null;
                        // get current Contract details and get list of all current ones
                        $recurringReferencesList = [];

                        if (!$listRecurringContracts) {
                            throw new \Exception("Empty list recurring contracts");
                        }
                        // Find the reference on the list
                        foreach ($listRecurringContracts as $rc) {
                            $recurringReferencesList[] = $rc['recurringDetailReference'];
                            if (isset($rc['recurringDetailReference']) &&
                                $rc['recurringDetailReference'] == $recurringDetailReference
                            ) {
                                $contractDetail = $rc;
                            }
                        }

                        if ($contractDetail == null) {
                            $this->_adyenLogger->addAdyenNotificationCronjob(print_r($listRecurringContracts, 1));
                            $message = __(
                                'Failed to create billing agreement for this order ' .
                                '(listRecurringCall did not contain contract)'
                            );
                            throw new \Exception($message);
                        }

                        $billingAgreements = $this->_billingAgreementCollectionFactory->create();
                        $billingAgreements->addFieldToFilter('customer_id', $customerReference);

                        // Get collection and update existing agreements

                        foreach ($billingAgreements as $updateBillingAgreement) {
                            if (!in_array($updateBillingAgreement->getReferenceId(), $recurringReferencesList)) {
                                $updateBillingAgreement->setStatus(
                                    \Adyen\Payment\Model\Billing\Agreement::STATUS_CANCELED
                                );
                            } else {
                                $updateBillingAgreement->setStatus(
                                    \Adyen\Payment\Model\Billing\Agreement::STATUS_ACTIVE
                                );
                            }
                            $updateBillingAgreement->save();
                        }

                        // Get or create billing agreement
                        $billingAgreement = $this->_billingAgreementFactory->create();
                        $billingAgreement->load($recurringDetailReference, 'reference_id');
                        // check if BA exists
                        if (!($billingAgreement && $billingAgreement->getAgreementId() > 0
                            && $billingAgreement->isValid())) {
                            // create new
                            $this->_adyenLogger->addAdyenNotificationCronjob("Creating new Billing Agreement");
                            $this->_order->getPayment()->setBillingAgreementData(
                                [
                                    'billing_agreement_id' => $recurringDetailReference,
                                    'method_code' => $this->_order->getPayment()->getMethodCode(),
                                ]
                            );

                            $billingAgreement = $this->_billingAgreementFactory->create();
                            $billingAgreement->setStoreId($this->_order->getStoreId());
                            $billingAgreement->importOrderPaymentWithRecurringDetailReference($this->_order->getPayment(), $recurringDetailReference);
                            $message = __('Created billing agreement #%1.', $recurringDetailReference);
                        } else {
                            $this->_adyenLogger->addAdyenNotificationCronjob
                            (
                                "Using existing Billing Agreement"
                            );
                            $billingAgreement->setIsObjectChanged(true);
                            $message = __('Updated billing agreement #%1.', $recurringDetailReference);
                        }

                        // Populate billing agreement data
                        $billingAgreement->parseRecurringContractData($contractDetail);
                        if ($billingAgreement->isValid()) {
                            if (!$this->agreementResourceModel->getOrderRelation(
                                $billingAgreement->getAgreementId(),
                                $this->_order->getId()
                            )) {
                                // save into sales_billing_agreement_order
                                $billingAgreement->addOrderRelation($this->_order);

                                // add to order to save agreement
                                $this->_order->addRelatedObject($billingAgreement);
                            }
                        } else {
                            $message = __('Failed to create billing agreement for this order.');
                            throw new \Exception($message);
                        }
                    } catch (\Exception $exception) {
                        $message = $exception->getMessage();
                    }

                    $this->_adyenLogger->addAdyenNotificationCronjob($message);
                    $comment = $this->_order->addStatusHistoryComment($message);
                    $this->_order->addRelatedObject($comment);
                }
                //store recurring contract for alternative payments methods
                if ($_paymentCode == 'adyen_hpp' && $this->configHelper->isStoreAlternativePaymentMethodEnabled()) {
                    $paymentTokenAlternativePaymentMethod = null;
                    try {
                        //get the payment
                        $payment = $this->_order->getPayment();
                        $customerId = $this->_order->getCustomerId();

                        $this->_adyenLogger->addAdyenNotificationCronjob(
                            '$paymentMethodCode ' . $this->_paymentMethod
                        );
                        if (!empty($this->_pspReference)) {
                            // Check if $paymentTokenAlternativePaymentMethod exists already
                            $paymentTokenAlternativePaymentMethod = $this->paymentTokenManagement->getByGatewayToken(
                                $this->_pspReference,
                                $payment->getMethodInstance()->getCode(),
                                $payment->getOrder()->getCustomerId()
                            );


                            // In case the payment token for this payment method does not exist, create it based on the additionalData
                            if ($paymentTokenAlternativePaymentMethod === null) {
                                $this->_adyenLogger->addAdyenNotificationCronjob('Creating new gateway token');
                                /** @var PaymentTokenInterface $paymentTokenAlternativePaymentMethod */
                                $paymentTokenAlternativePaymentMethod = $this->paymentTokenFactory->create(
                                    PaymentTokenFactoryInterface::TOKEN_TYPE_ACCOUNT
                                );

                                $details = [
                                    'type' => $this->_paymentMethod,
                                    'maskedCC' => $payment->getAdditionalInformation()['ibanNumber'],
                                    'expirationDate' => 'N/A'
                                ];

                                $paymentTokenAlternativePaymentMethod->setCustomerId($customerId)
                                    ->setGatewayToken($this->_pspReference)
                                    ->setPaymentMethodCode(AdyenCcConfigProvider::CODE)
                                    ->setPublicHash($this->encryptor->getHash($customerId . $this->_pspReference))
                                    ->setTokenDetails(json_encode($details));
                            } else {
                                $this->_adyenLogger->addAdyenNotificationCronjob('Gateway token already exists');
                            }

                            //SEPA tokens don't expire. The expiration date is set 10 years from now
                            $expDate = new DateTime('now', new DateTimeZone('UTC'));
                            $expDate->add(new DateInterval('P10Y'));
                            $paymentTokenAlternativePaymentMethod->setExpiresAt($expDate->format('Y-m-d H:i:s'));

                            $this->paymentTokenRepository->save($paymentTokenAlternativePaymentMethod);
                            $this->_adyenLogger->addAdyenNotificationCronjob('New gateway token saved');
                        }
                    } catch (\Exception $exception) {
                        $message = $exception->getMessage();
                        $this->_adyenLogger->addAdyenNotificationCronjob(
                            "An error occurred while saving the payment method " . $message
                        );
                    }
                } else {
                    $this->_adyenLogger->addAdyenNotificationCronjob(
                        'Ignore recurring_contract notification because Vault feature is enabled'
                    );
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
     *
     * @return bool
     */
    protected function _refundOrder()
    {
        $this->_adyenLogger->addAdyenNotificationCronjob('Refunding the order');

        // check if it is a split payment if so save the refunded data
        if ($this->_originalReference != "") {
            $this->_adyenLogger->addAdyenNotificationCronjob(
                'Going to update the refund to split payments table'
            );

            $orderPayment = $this->_adyenOrderPaymentCollectionFactory
                ->create()
                ->addFieldToFilter(\Adyen\Payment\Model\Notification::PSPREFRENCE, $this->_originalReference)
                ->getFirstItem();

            if ($orderPayment->getId() > 0) {
                $amountRefunded = $orderPayment->getTotalRefunded() +
                    $this->_adyenHelper->originalAmount($this->_value, $this->_currency);
                $orderPayment->setUpdatedAt(new \DateTime());
                $orderPayment->setTotalRefunded($amountRefunded);
                $orderPayment->save();
                $this->_adyenLogger->addAdyenNotificationCronjob(
                    'Update the refund in the split payments table'
                );
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
            // refund is done through adyen backoffice so create a credit memo
            $order = $this->_order;
            if ($order->canCreditmemo()) {
                $amount = $this->_adyenHelper->originalAmount($this->_value, $this->_currency);
                $order->getPayment()->registerRefundNotification($amount);

                $this->_adyenLogger->addAdyenNotificationCronjob('Created credit memo for order');
            } else {
                $this->_adyenLogger->addAdyenNotificationCronjob('Could not create a credit memo for order');
            }
        } else {
            $this->_adyenLogger->addAdyenNotificationCronjob(
                'Did not create a credit memo for this order because refund is done through Magento'
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

        // Set adyen_notification_event_code_success to true so that we ignore a possible OFFER_CLOSED
        if (strcmp($this->_success, 'true') == 0) {
            $this->_order->setData('adyen_notification_event_code_success', 1);
        }
        $fraudManualReviewStatus = $this->_getFraudManualReviewStatus();

        // If manual review is active and a separate status is used then ignore the pre authorized status
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

        if ($this->_paymentMethod == "c_cash" &&
            $this->_getConfigData('create_shipment', 'adyen_cash', $this->_order->getStoreId())
        ) {
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
        } catch (\Exception $exception) {
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
            'payment_pre_authorized',
            'adyen_abstract',
            $this->_order->getStoreId()
        );

        // only do this if status in configuration is set
        if (!empty($status)) {
            $this->_order->addStatusHistoryComment(__('Payment is authorised waiting for capture'), $status);
            $this->_setState($status);

            $this->_adyenLogger->addAdyenNotificationCronjob(
                'Order status is changed to Pre-authorised status, status is ' . $status
            );
        } else {
            $this->_adyenLogger->addAdyenNotificationCronjob('No pre-authorised status is used so ignore');
        }
    }

    /**
     * @return void
     * @throws Exception
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

        // Prepare transaction
        $transaction = $this->transactionBuilder->setPayment($paymentObj)
            ->setOrder($this->_order)
            ->setTransactionId($this->_pspReference)
            ->build(\Magento\Sales\Api\Data\TransactionInterface::TYPE_AUTH);

        $transaction->setIsClosed(false);

        $transaction->save();

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

            $createPendingInvoice = (bool)$this->_getConfigData(
                'create_pending_invoice',
                'adyen_abstract',
                $this->_order->getStoreId()
            );

            if (!$createPendingInvoice) {
                $this->_adyenLogger->addAdyenNotificationCronjob(
                    'Setting pending invoice is off so don\'t create
                    an invoice wait for the capture notification'
                );
                return;
            }
        }

        // validate if amount is total amount
        $amount = $this->_adyenHelper->originalAmount($this->_value, $this->_currency);

        try {
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
        } catch (\Exception $e) {
            $this->_adyenLogger->addError(
                'While processing a notification an exception occured. The payment has already been saved in the ' .
                'adyen_order_payment table but something went wrong later. Please check your logs for potential ' .
                'error messages regarding the merchant reference (order id): "' . $this->_merchantReference .
                '" and PSP reference: "' . $this->_pspReference . '"'
            );
        }

        if ($this->_isTotalAmount($paymentObj->getEntityId(), $this->orderCurrency)) {
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
        // validate if payment methods allows manual capture
        if ($this->_manualCaptureAllowed()) {
            $captureMode = trim(
                $this->_getConfigData(
                    'capture_mode',
                    'adyen_abstract',
                    $this->_order->getStoreId()
                )
            );
            $sepaFlow = trim(
                $this->_getConfigData(
                    'sepa_flow',
                    'adyen_abstract',
                    $this->_order->getStoreId()
                )
            );
            $_paymentCode = $this->_paymentMethodCode();
            $captureModeOpenInvoice = $this->_getConfigData(
                'auto_capture_openinvoice',
                'adyen_abstract',
                $this->_order->getStoreId()
            );
            $manualCapturePayPal = trim(
                $this->_getConfigData(
                    'paypal_capture_mode',
                    'adyen_abstract',
                    $this->_order->getStoreId()
                )
            );

            /*
             * if you are using authcap the payment method is manual.
             * There will be a capture send to indicate if payment is successful
             */
            if ($this->_paymentMethod == "sepadirectdebit" && $sepaFlow == "authcap") {
                $this->_adyenLogger->addAdyenNotificationCronjob(
                    'Manual Capture is applied for sepa because it is in authcap flow'
                );
                return false;
            }

            // payment method ideal, cash adyen_boleto has direct capture
            if ($this->_paymentMethod == "sepadirectdebit" && $sepaFlow != "authcap") {
                $this->_adyenLogger->addAdyenNotificationCronjob(
                    'This payment method does not allow manual capture.(2) paymentCode:' .
                    $_paymentCode . ' paymentMethod:' . $this->_paymentMethod . ' sepaFLow:' . $sepaFlow
                );
                return true;
            }

            if ($_paymentCode == "adyen_pos_cloud") {
                $captureModePos = $this->_adyenHelper->getAdyenPosCloudConfigData(
                    'capture_mode_pos',
                    $this->_order->getStoreId()
                );
                if (strcmp($captureModePos, 'auto') === 0) {
                    $this->_adyenLogger->addAdyenNotificationCronjob(
                        'This payment method is POS Cloud and configured to be working as auto capture '
                    );
                    return true;
                } elseif (strcmp($captureModePos, 'manual') === 0) {
                    $this->_adyenLogger->addAdyenNotificationCronjob(
                        'This payment method is POS Cloud and configured to be working as manual capture '
                    );
                    return false;
                }
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
            if (strcmp($this->_paymentMethod, 'paypal') === 0) {
                if ($manualCapturePayPal) {
                    $this->_adyenLogger->addAdyenNotificationCronjob(
                        'This payment method is paypal and configured to work as manual capture'
                    );
                    return false;
                } else {
                    $this->_adyenLogger->addAdyenNotificationCronjob(
                        'This payment method is paypal and configured to work as auto capture'
                    );
                    return true;
                }
            }
            if (strcmp($captureMode, 'manual') === 0) {
                $this->_adyenLogger->addAdyenNotificationCronjob
                (
                    'Capture mode for this payment is set to manual'
                );
                return false;
            }

            /*
             * online capture after delivery, use Magento backend to online invoice
             * (if the option auto capture mode for openinvoice is not set)
             */
            if ($this->_adyenHelper->isPaymentMethodOpenInvoiceMethod($this->_paymentMethod)) {
                $this->_adyenLogger->addAdyenNotificationCronjob
                (
                    'Capture mode for klarna is by default set to manual'
                );
                return false;
            }

            $this->_adyenLogger->addAdyenNotificationCronjob('Capture mode is set to auto capture');
            return true;
        } else {
            // does not allow manual capture so is always immediate capture
            $this->_adyenLogger->addAdyenNotificationCronjob
            (
                'This payment method does not allow manual capture'
            );
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

        // For all openinvoice methods manual capture is the default
        if ($this->_adyenHelper->isPaymentMethodOpenInvoiceMethod($paymentMethod)) {
            return true;
        }

        switch ($paymentMethod) {
            case 'cup':
            case 'cartebancaire':
            case 'visa':
            case 'visadankort':
            case 'mc':
            case 'uatp':
            case 'amex':
            case 'maestro':
            case 'maestrouk':
            case 'diners':
            case 'discover':
            case 'jcb':
            case 'laser':
            case 'paypal':
            case 'sepadirectdebit':
            case 'dankort':
            case 'elo':
            case 'hipercard':
            case 'mc_applepay':
            case 'visa_applepay':
            case 'amex_applepay':
            case 'discover_applepay':
            case 'maestro_applepay':
            case 'paywithgoogle':
                $manualCaptureAllowed = true;
                break;
            default:
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
            'fraud_manual_review_status',
            'adyen_abstract',
            $this->_order->getStoreId()
        );
    }

    /**
     * @return mixed
     */
    protected function _getFraudManualReviewAcceptStatus()
    {
        return $this->_getConfigData(
            'fraud_manual_review_accept_status',
            'adyen_abstract',
            $this->_order->getStoreId()
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
        $grandTotal = (int)$this->_adyenHelper->formatAmount(
            $this->orderAmount,
            $this->orderCurrency
        );

        // check if total amount of the order is authorised
        $res = $this->_adyenOrderPaymentCollectionFactory
            ->create()
            ->getTotalAmount($paymentId);

        if ($res && isset($res[0]) && is_array($res[0])) {
            $amount = $res[0]['total_amount'];
            $formattedOrderAmount = $this->_adyenHelper->formatAmount($amount, $orderCurrencyCode);
            $this->_adyenLogger->addAdyenNotificationCronjob(
                sprintf(
                    'The grandtotal amount is %s and the total order amount that is authorised is: %s',
                    $grandTotal,
                    $formattedOrderAmount
                )
            );

            if ($grandTotal == $formattedOrderAmount) {
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
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws Exception
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
                $createPendingInvoice = (bool)$this->_getConfigData(
                    'create_pending_invoice',
                    'adyen_abstract',
                    $this->_order->getStoreId()
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

                /*
                 * Add invoice in the adyen_invoice table
                 */
                $this->_adyenInvoiceFactory->create()
                    ->setInvoiceId($invoice->getEntityId())
                    ->setPspreference($this->_pspReference)
                    ->setOriginalReference($this->_pspReference)
                    ->setAcquirerReference($this->_acquirerReference)
                    ->save();

                $this->_adyenLogger->addAdyenNotificationCronjob('Created invoice entry in the Adyen table');
            } catch (Exception $e) {
                $this->_adyenLogger->addAdyenNotificationCronjob(
                    'Error saving invoice. The error message is: ' . $e->getMessage()
                );
                throw new Exception(sprintf('Error saving invoice. The error message is:', $e->getMessage()));
            }

            $this->_setPaymentAuthorized();

            $invoiceAutoMail = (bool)$this->_scopeConfig->isSetFlag(
                \Magento\Sales\Model\Order\Email\Container\InvoiceIdentity::XML_PATH_EMAIL_ENABLED,
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                $this->_order->getStoreId()
            );

            if ($invoiceAutoMail) {
                $this->_invoiceSender->send($invoice);
            }
        } else {
            $this->_adyenLogger->addAdyenNotificationCronjob
            (
                'It is not possible to create invoice for this order',
                [
                    'orderId' => $this->_order->getId(),
                    'orderState' => $this->_order->getState(),
                    'orderStatus' => $this->_order->getStatus()
                ]
            );
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
        $amount = $this->_value;
        $formattedOrderAmount = (int)$this->_adyenHelper->formatAmount($this->orderAmount, $this->orderCurrency);

        // create invoice for the capture notification if you are on manual capture
        if ($createInvoice && $amount == $formattedOrderAmount) {
            $this->_adyenLogger->addAdyenNotificationCronjob(
                'amount notification:' . $amount . ' amount order:' . $formattedOrderAmount
            );
            $this->_createInvoice();
        }

        $status = $this->_getConfigData(
            'payment_authorized',
            'adyen_abstract',
            $this->_order->getStoreId()
        );

        // virtual order can have different status
        if ($this->_order->getIsVirtual()) {
            $this->_adyenLogger->addAdyenNotificationCronjob('Product is a virtual product');
            $virtualStatus = $this->_getConfigData(
                'payment_authorized_virtual',
                'adyen_abstract',
                $this->_order->getStoreId()
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
                    $overpaidStatus = $this->_getConfigData(
                        'order_overpaid_status',
                        'adyen_boleto',
                        $this->_order->getStoreId()
                    );
                    // check if there is selected a status if not fall back to the default
                    $status = (!empty($overpaidStatus)) ? $overpaidStatus : $status;
                } else {
                    $underpaidStatus = $this->_getConfigData(
                        'order_underpaid_status',
                        'adyen_boleto',
                        $this->_order->getStoreId()
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
        $this->_setState($status);

        $this->_adyenLogger->addAdyenNotificationCronjob(
            'Order status is changed to authorised status, status is ' . $status
        );
    }

    /**
     * Set State from Status
     */

    protected function _setState($status)
    {
        $statusObject = $this->_orderStatusCollection->create()
            ->addFieldToFilter('main_table.status', $status)
            ->joinStates()
            ->getFirstItem();

        $this->_order->setState($statusObject->getState());
        $this->_adyenLogger->addAdyenNotificationCronjob('State is changed to  ' . $statusObject->getState());
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

    /**
     * Add admin notice message for refund failed notification
     *
     * @return void
     */
    protected function addRefundFailedNotice()
    {
        $this->notifierPool->addNotice(
            __("Adyen: Refund for order #%1 has failed", $this->_merchantReference),
            __(
                "Reason: %1 | PSPReference: %2 | You can go to Adyen Customer Area
                and trigger this refund manually or contact our support.",
                $this->_reason,
                $this->_pspReference
            ),
            $this->_adyenHelper->getPspReferenceSearchUrl($this->_pspReference, $this->_live)
        );
    }

    /**
     * Add/update info on notification processing errors
     *
     * @param \Adyen\Payment\Model\Notification $notification
     * @param string $errorMessage
     * @return void
     */
    private function handleNotificationError($notification, $errorMessage)
    {
        $this->setNotificationError($notification, $errorMessage);
        $this->addNotificationErrorComment($errorMessage);
    }

    /**
     * Increases error count and appends error message to notification
     *
     * @param \Adyen\Payment\Model\Notification $notification
     * @param string $errorMessage
     * @return void
     */
    private function setNotificationError($notification, $errorMessage)
    {
        $notification->setErrorCount($notification->getErrorCount() + 1);
        $oldMessage = $notification->getErrorMessage();
        $newMessage = sprintf(
            "[%s]: %s",
            $this->timezone->formatDateTime($notification->getUpdatedAt()),
            $errorMessage
        );
        if (empty($oldMessage)) {
            $notification->setErrorMessage($newMessage);
        } else {
            $notification->setErrorMessage($oldMessage . "\n" . $newMessage);
        }
        $notification->save();
    }

    /**
     * Adds a comment to the order history with the notification processing error
     *
     * @param string $errorMessage
     * @return void
     */
    private function addNotificationErrorComment($errorMessage)
    {
        $comment = __('The order failed to update: %1', $errorMessage);
        if ($this->_order) {
            $this->_order->addStatusHistoryComment($comment);
            $this->_order->save();
        }
    }
}
