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
 * Adyen Payment Module
 *
 * Copyright (c) 2022 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper;

use Adyen\Payment\Api\Data\OrderPaymentInterface;
use Adyen\Payment\Helper\Config as ConfigHelper;
use Adyen\Payment\Helper\Invoice as InvoiceHelper;
use Adyen\Payment\Helper\PaymentMethods as PaymentMethodsHelper;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Api\PaymentRequest;
use Adyen\Payment\Model\Billing\AgreementFactory;
use Adyen\Payment\Model\Config\Source\Status\AdyenState;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Model\Order\PaymentFactory;
use Adyen\Payment\Model\ResourceModel\Billing\Agreement;
use Adyen\Payment\Model\ResourceModel\Billing\Agreement\CollectionFactory as AgreementCollectionFactory;
use Adyen\Payment\Model\ResourceModel\Order\Payment\CollectionFactory as OrderPaymentCollectionFactory;
use Adyen\Payment\Model\Ui\AdyenCcConfigProvider;
use Adyen\Webhook\Exception\InvalidDataException;
use Adyen\Webhook\Notification as WebhookNotification;
use Adyen\Webhook\PaymentStates;
use Adyen\Webhook\Processor\ProcessorFactory;
use DateInterval;
use DateTime;
use DateTimeZone;
use Exception;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Notification\NotifierInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Container\InvoiceIdentity;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order\InvoiceFactory as MagentoInvoiceFactory;
use Magento\Sales\Model\Order\Payment\Transaction\Builder;
use Magento\Sales\Model\OrderRepository;
use Magento\Sales\Model\ResourceModel\Order\Invoice as InvoiceResourceModel;
use Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory as OrderStatusCollectionFactory;
use Magento\Store\Model\ScopeInterface;
use Magento\Vault\Api\Data\PaymentTokenFactoryInterface;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;
use Magento\Vault\Model\PaymentTokenManagement;

class Webhook
{
    const WEBHOOK_ORDER_STATE_MAPPING = [
        Order::STATE_NEW => PaymentStates::STATE_NEW,
        Order::STATE_PENDING_PAYMENT => PaymentStates::STATE_PENDING,
        Order::STATE_PAYMENT_REVIEW => PaymentStates::STATE_PENDING,
        Order::STATE_PROCESSING => PaymentStates::STATE_IN_PROGRESS,
        Order::STATE_COMPLETE => PaymentStates::STATE_PAID,
        Order::STATE_CANCELED => PaymentStates::STATE_CANCELLED
    ];

    /**
     * @var Order
     */
    private $order;
    /**
     * @var AdyenLogger
     */
    private $logger;
    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;
    /**
     * @var OrderRepository
     */
    private $orderRepository;
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;
    /**
     * @var Data
     */
    private $adyenHelper;
    /**
     * @var OrderSender
     */
    private $orderSender;
    /**
     * @var InvoiceSender
     */
    private $invoiceSender;
    /**
     * @var TransactionFactory
     */
    private $transactionFactory;
    /**
     * @var AgreementFactory
     */
    private $billingAgreementFactory;
    /**
     * @var AgreementCollectionFactory
     */
    private $billingAgreementCollectionFactory;
    /**
     * @var PaymentRequest
     */
    private $paymentRequest;
    /**
     * @var OrderPaymentCollectionFactory
     */
    private $adyenOrderPaymentCollectionFactory;
    /**
     * @var OrderStatusCollectionFactory
     */
    private $orderStatusCollection;
    /**
     * @var Agreement
     */
    private $agreementResourceModel;
    /**
     * @var Builder
     */
    private $transactionBuilder;
    /**
     * @var SerializerInterface
     */
    private $serializer;
    /**
     * @var NotifierInterface
     */
    private $notifierPool;
    /**
     * @var TimezoneInterface
     */
    private $timezone;
    /**
     * @var ConfigHelper
     */
    private $configHelper;
    /**
     * @var PaymentTokenManagement
     */
    private $paymentTokenManagement;
    /**
     * @var PaymentTokenFactoryInterface
     */
    private $paymentTokenFactory;
    /**
     * @var PaymentTokenRepositoryInterface
     */
    private $paymentTokenRepository;
    /**
     * @var EncryptorInterface
     */
    private $encryptor;
    /**
     * @var ChargedCurrency
     */
    private $chargedCurrency;
    /**
     * @var PaymentMethods
     */
    private $paymentMethodsHelper;
    /**
     * @var InvoiceResourceModel
     */
    private $invoiceResourceModel;
    /**
     * @var AdyenOrderPayment
     */
    private $adyenOrderPaymentHelper;
    /**
     * @var InvoiceHelper
     */
    private $invoiceHelper;
    /**
     * @var CaseManagement
     */
    private $caseManagementHelper;
    /**
     * @var PaymentFactory
     */
    private $adyenOrderPaymentFactory;

    /**
     * @var MagentoInvoiceFactory
     */
    private $magentoInvoiceFactory;

    private $boletoOriginalAmount;

    private $boletoPaidAmount;

    private $klarnaReservationNumber;

    private $requireFraudManualReview;

    private $ratepayDescriptor;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        OrderRepository $orderRepository,
        Data $adyenHelper,
        OrderSender $orderSender,
        InvoiceSender $invoiceSender,
        TransactionFactory $transactionFactory,
        AgreementFactory $billingAgreementFactory,
        AgreementCollectionFactory $billingAgreementCollectionFactory,
        PaymentRequest $paymentRequest,
        OrderPaymentCollectionFactory $adyenOrderPaymentCollectionFactory,
        OrderStatusCollectionFactory $orderStatusCollection,
        Agreement $agreementResourceModel,
        Builder $transactionBuilder,
        SerializerInterface $serializer,
        NotifierInterface $notifierPool,
        TimezoneInterface $timezone,
        ConfigHelper $configHelper,
        PaymentTokenManagement $paymentTokenManagement,
        PaymentTokenFactoryInterface $paymentTokenFactory,
        PaymentTokenRepositoryInterface $paymentTokenRepository,
        EncryptorInterface $encryptor,
        ChargedCurrency $chargedCurrency,
        PaymentMethodsHelper $paymentMethodsHelper,
        InvoiceResourceModel $invoiceResourceModel,
        AdyenOrderPayment $adyenOrderPaymentHelper,
        InvoiceHelper $invoiceHelper,
        CaseManagement $caseManagementHelper,
        PaymentFactory $adyenOrderPaymentFactory,
        AdyenLogger $logger,
        MagentoInvoiceFactory $magentoInvoiceFactory
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->orderRepository = $orderRepository;
        $this->adyenHelper = $adyenHelper;
        $this->orderSender = $orderSender;
        $this->invoiceSender = $invoiceSender;
        $this->transactionFactory = $transactionFactory;
        $this->billingAgreementFactory = $billingAgreementFactory;
        $this->billingAgreementCollectionFactory = $billingAgreementCollectionFactory;
        $this->paymentRequest = $paymentRequest;
        $this->adyenOrderPaymentCollectionFactory = $adyenOrderPaymentCollectionFactory;
        $this->orderStatusCollection = $orderStatusCollection;
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
        $this->paymentMethodsHelper = $paymentMethodsHelper;
        $this->invoiceResourceModel = $invoiceResourceModel;
        $this->adyenOrderPaymentHelper = $adyenOrderPaymentHelper;
        $this->invoiceHelper = $invoiceHelper;
        $this->caseManagementHelper = $caseManagementHelper;
        $this->adyenOrderPaymentFactory = $adyenOrderPaymentFactory;
        $this->logger = $logger;
        $this->magentoInvoiceFactory = $magentoInvoiceFactory;
    }

    /**
     * @param Notification $notification
     * @return bool
     */
    public function processNotification(Notification $notification): bool
    {
        $this->order = null;
        // set notification processing to true
        $this->updateNotification($notification, true, false);
        $this->logger
            ->addAdyenNotificationCronjob(sprintf("Processing notification %s", $notification->getEntityId()));

        try {
            // log the executed notification
            $this->logger->addAdyenNotificationCronjob(json_encode($notification->debug()));
            $this->setOrderByIncrementId($notification);
            if (!$this->order) {
                // order does not exists remove from queue
                $notification->delete();

                return false;
            }

            $this->logger->addAdyenNotificationCronjob(
                sprintf("Notification %s will be processed", $notification->getEntityId()),
                $this->adyenOrderPaymentHelper->getLogOrderContext($this->order)
            );

            // declare all variables that are needed
            $this->declareVariables($this->order, $notification);

            // add notification to comment history status is current status
            $this->addNotificationDetailsHistoryComment($this->order, $notification);

            // update order details
            $this->updateAdyenAttributes($notification);

            // Get transition state
            $currentState = $this->getCurrentState($this->order->getState());
            if (!$currentState) {
                $this->logger->addAdyenNotificationCronjob(
                    sprintf("ERROR: Unhandled order state '%s'.", $this->order->getState())
                );
                return false;
            }

            $transitionState = $this->getTransitionState($notification, $currentState);

            if ($transitionState !== $currentState) {
                $this->handleOrderTransition($notification, $transitionState);
            } else {
                $this->handleUnchangedStates($this->order, $notification);
            }

            try {
                // set done to true
                $this->order->save();
            } catch (Exception $e) {
                $this->logger->addAdyenNotificationCronjob($e->getMessage());
            }

            $this->updateNotification($notification, false, true);
            $this->logger->addAdyenNotificationCronjob(
                sprintf("Notification %s was processed", $notification->getEntityId()),
                $this->adyenOrderPaymentHelper->getLogOrderContext($this->order)
            );

            return true;
        } catch (Exception $e) {
            $this->updateNotification($notification, false, false);
            $this->handleNotificationError($notification, $e->getMessage());
            $this->logger->addAdyenNotificationCronjob(
                sprintf(
                    "Notification %s had an error: %s \n %s",
                    $notification->getEntityId(),
                    $e->getMessage(),
                    $e->getTraceAsString()
                ),
                $this->adyenOrderPaymentHelper->getLogOrderContext($this->order)
            );

            return false;
        }
    }

    /**
     * Remove OFFER_CLOSED and AUTHORISATION success=false notifications for some time from the processing list
     * to ensure they won't close any order which has an AUTHORISED notification arrived a bit later than the
     * OFFER_CLOSED or the AUTHORISATION success=false one.
     * @param Notification $notification
     * @return bool
     */
    public function shouldSkipProcessingNotification(Notification $notification): bool
    {
        if ((
                Notification::OFFER_CLOSED === $notification->getEventCode() ||
                (Notification::AUTHORISATION === $notification->getEventCode() && !$notification->isSuccessful())
            ) &&
            $notification->isLessThan10MinutesOld()
        ) {
            $this->logger->addAdyenNotificationCronjob(
                sprintf(
                    '%s notification (entity_id: %s) for merchant_reference: %s is skipped! Wait 10 minute before processing.',
                    $notification->getEventCode(),
                    $notification->getEntityId(),
                    $notification->getMerchantReference()
                )
            );

            return true;
        }

        return false;
    }

    private function getCurrentState($orderState)
    {
        return self::WEBHOOK_ORDER_STATE_MAPPING[$orderState] ?? null;
    }

    /**
     * @param Notification $notification
     * @param $currentOrderState
     * @return string
     * @throws InvalidDataException
     */
    private function getTransitionState(Notification $notification, $currentOrderState): string
    {
        $webhookNotificationItem = WebhookNotification::createItem([
            'eventCode' => $notification->getEventCode(),
            'success' => $notification->getSuccess(),
            'additionalData' => !empty($notification->getAdditionalData())
                ? $this->serializer->unserialize($notification->getAdditionalData()) : null,
        ]);
        $processor = ProcessorFactory::create($webhookNotificationItem, $currentOrderState, $this->logger);

        return $processor->process();
    }

    /**
     * @param Notification $notification
     * @param $transitionState
     * @throws LocalizedException
     */
    private function handleOrderTransition(Notification $notification, $transitionState): void
    {
        $previousAdyenEventCode = $this->order->getData('adyen_notification_event_code');
        switch ($transitionState) {
            case PaymentStates::STATE_PAID:
                if (Notification::CAPTURE == $notification->getEventCode()) {
                    /*
                     * ignore capture if you are on auto capture
                     * this could be called if manual review is enabled and you have a capture delay
                     */
                    if (!$this->isAutoCapture($notification->getPaymentMethod())) {
                        $this->handleManualCapture($notification);
                    }
                } elseif (in_array($notification->getEventCode(), [Notification::REFUND, Notification::REFUND_FAILED])) {
                    // Webhook module returns PAID for failed refunds, trigger admin notice
                    $this->addRefundFailedNotice($notification);
                } else {
                    $this->authorizePayment($notification);
                }
                break;
            case PaymentStates::STATE_FAILED:
            case PaymentStates::STATE_CANCELLED:
                $this->cancelPayment($notification, $previousAdyenEventCode);
                break;
            case PaymentStates::STATE_REFUNDED:
                $this->refundPayment($notification);
                break;
            default:
                break;
        }
    }

    private function handleUnchangedStates(Order $order, Notification $notification): void
    {
        switch ($notification->getEventCode()) {
            case Notification::PENDING:
                $sendEmailSepaOnPending = $this->configHelper->getConfigData(
                    'send_email_bank_sepa_on_pending',
                    'adyen_abstract',
                    $this->order->getStoreId()
                );
                // Check if payment is banktransfer or sepa if true then send out order confirmation email
                if ($sendEmailSepaOnPending &&
                    !$this->order->getEmailSent() &&
                    ($this->isBankTransfer($notification->getPaymentMethod()) ||
                        $notification->getPaymentMethod() == 'sepadirectdebit')) {
                    $this->sendOrderMail();
                }
                break;
            case Notification::MANUAL_REVIEW_ACCEPT:
                $this->caseManagementHelper->markCaseAsAccepted($this->order, sprintf(
                    'Manual review accepted for order w/pspReference: %s',
                    $notification->getOriginalReference()
                ));

                // Finalize order only in case of auto capture. For manual capture the capture notification will initiate this call
                if ($this->isAutoCapture($notification->getPaymentMethod())) {
                    $this->finalizeOrder($order, $notification);
                }
                break;
            case Notification::MANUAL_REVIEW_REJECT:
                // Do not do any processing. Order should be cancelled/refunded when the CANCEL_OR_REFUND notification is received
                $this->caseManagementHelper->markCaseAsRejected($this->order, $notification->getOriginalReference(), $this->isAutoCapture($notification->getPaymentMethod()));
                break;
            case Notification::RECURRING_CONTRACT:
                // only store billing agreements if Vault is disabled
                if (!$this->adyenHelper->isCreditCardVaultEnabled()) {
                    // storedReferenceCode
                    $recurringDetailReference = $notification->getPspreference();

                    $storeId = $this->order->getStoreId();
                    $customerReference = $this->order->getCustomerId();
                    $this->logger->addAdyenNotificationCronjob(
                        __(
                            'CustomerReference is: %1 and storeId is %2 and RecurringDetailsReference is %3',
                            $customerReference,
                            $storeId,
                            $recurringDetailReference
                        )
                    );
                    try {
                        $listRecurringContracts = $this->paymentRequest->getRecurringContractsForShopper(
                            $customerReference,
                            $storeId
                        );
                        $contractDetail = null;
                        // get current Contract details and get list of all current ones
                        $recurringReferencesList = [];

                        if (!$listRecurringContracts) {
                            throw new Exception("Empty list recurring contracts");
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
                            $this->logger->addAdyenNotificationCronjob(json_encode($listRecurringContracts));
                            $message = __(
                                'Failed to create billing agreement for this order ' .
                                '(listRecurringCall did not contain contract)'
                            );
                            throw new Exception($message);
                        }

                        $billingAgreements = $this->billingAgreementCollectionFactory->create();
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
                        $billingAgreement = $this->billingAgreementFactory->create();
                        $billingAgreement->load($recurringDetailReference, 'reference_id');
                        // check if BA exists
                        if (!($billingAgreement && $billingAgreement->getAgreementId() > 0
                            && $billingAgreement->isValid())) {
                            // create new
                            $this->logger->addAdyenNotificationCronjob("Creating new Billing Agreement");
                            $this->order->getPayment()->setBillingAgreementData(
                                [
                                    'billing_agreement_id' => $recurringDetailReference,
                                    'method_code' => $this->order->getPayment()->getMethodCode(),
                                ]
                            );

                            $billingAgreement = $this->billingAgreementFactory->create();
                            $billingAgreement->setStoreId($this->order->getStoreId());
                            $billingAgreement->importOrderPaymentWithRecurringDetailReference($this->order->getPayment(), $recurringDetailReference);
                            $message = __('Created billing agreement #%1.', $recurringDetailReference);
                        } else {
                            $this->logger->addAdyenNotificationCronjob(
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
                                $this->order->getId()
                            )) {
                                // save into sales_billing_agreement_order
                                $billingAgreement->addOrderRelation($this->order);

                                // add to order to save agreement
                                $this->order->addRelatedObject($billingAgreement);
                            }
                        } else {
                            $message = __('Failed to create billing agreement for this order.');
                            throw new Exception($message);
                        }
                    } catch (Exception $exception) {
                        $message = $exception->getMessage();
                    }

                    $this->logger->addAdyenNotificationCronjob($message);
                    $comment = $this->order->addStatusHistoryComment($message, $this->order->getStatus());
                    $this->order->addRelatedObject($comment);
                }
                //store recurring contract for alternative payments methods
                if ($order->getPayment()->getMethod() === PaymentMethods::ADYEN_HPP && $this->configHelper->isStoreAlternativePaymentMethodEnabled()) {
                    try {
                        //get the payment
                        $payment = $this->order->getPayment();
                        $customerId = $this->order->getCustomerId();

                        $this->logger->addAdyenNotificationCronjob(
                            '$paymentMethodCode ' . $notification->getPaymentMethod()
                        );
                        if (!empty($notification->getPspreference())) {
                            // Check if $paymentTokenAlternativePaymentMethod exists already
                            $paymentTokenAlternativePaymentMethod = $this->paymentTokenManagement->getByGatewayToken(
                                $notification->getPspreference(),
                                $payment->getMethodInstance()->getCode(),
                                $payment->getOrder()->getCustomerId()
                            );


                            // In case the payment token for this payment method does not exist, create it based on the additionalData
                            if ($paymentTokenAlternativePaymentMethod === null) {
                                $this->logger->addAdyenNotificationCronjob('Creating new gateway token');
                                $paymentTokenAlternativePaymentMethod = $this->paymentTokenFactory->create(
                                    PaymentTokenFactoryInterface::TOKEN_TYPE_ACCOUNT
                                );

                                $details = [
                                    'type' => $notification->getPaymentMethod(),
                                    'maskedCC' => $payment->getAdditionalInformation()['ibanNumber'],
                                    'expirationDate' => 'N/A'
                                ];

                                $paymentTokenAlternativePaymentMethod->setCustomerId($customerId)
                                    ->setGatewayToken($notification->getPspreference())
                                    ->setPaymentMethodCode(AdyenCcConfigProvider::CODE)
                                    ->setPublicHash($this->encryptor->getHash($customerId . $notification->getPspreference()))
                                    ->setTokenDetails(json_encode($details));
                            } else {
                                $this->logger->addAdyenNotificationCronjob('Gateway token already exists');
                            }

                            //SEPA tokens don't expire. The expiration date is set 10 years from now
                            $expDate = new DateTime('now', new DateTimeZone('UTC'));
                            $expDate->add(new DateInterval('P10Y'));
                            $paymentTokenAlternativePaymentMethod->setExpiresAt($expDate->format('Y-m-d H:i:s'));

                            $this->paymentTokenRepository->save($paymentTokenAlternativePaymentMethod);
                            $this->logger->addAdyenNotificationCronjob('New gateway token saved');
                        }
                    } catch (Exception $exception) {
                        $message = $exception->getMessage();
                        $this->logger->addAdyenNotificationCronjob(
                            "An error occurred while saving the payment method " . $message
                        );
                    }
                } else {
                    $this->logger->addAdyenNotificationCronjob(
                        'Ignore recurring_contract notification because Vault feature is enabled'
                    );
                }
                break;
            default:
                break;
        }
    }

    /**
     * @param Notification $notification
     * @param $previousAdyenEventCode
     * @throws LocalizedException
     */
    private function cancelPayment(Notification $notification, $previousAdyenEventCode): void
    {
        $ignoreHasInvoice = true;
        // if payment is API check, check if API result pspreference is the same as reference
        if ($notification->getEventCode() == Notification::AUTHORISATION) {
            if ('api' === $this->order->getPayment()->getPaymentMethodType()) {
                // don't cancel the order because order was successful through api
                $this->logger->addAdyenNotificationCronjob(
                    'order is not cancelled because api result was successful'
                );
                return;
            }
            $ignoreHasInvoice = false;
        }

        /*
         * Don't cancel the order if the payment has been captured.
         * Partial payments can fail, if the second payment has failed then the first payment is
         * refund/cancelled as well. So if it is a partial payment that failed cancel the order as well
         */
        $paymentPreviouslyCaptured = $this->order->getData('adyen_notification_payment_captured');

        if ($previousAdyenEventCode == "AUTHORISATION : TRUE" || !empty($paymentPreviouslyCaptured)) {
            $this->logger->addAdyenNotificationCronjob(
                'order is not cancelled because previous notification
                                    was an authorisation that succeeded and payment was captured'
            );
            return;
        }

        // Order is already Cancelled
        if ($this->order->isCanceled() || $this->order->getState() === Order::STATE_HOLDED) {
            $this->logger->addAdyenNotificationCronjob(
                "Order is already cancelled or holded, do nothing"
            );
            return;
        }

        if (Notification::OFFER_CLOSED == $notification->getEventCode()) {
            /*
            * For cards, it can be 'visa', 'maestro',...
            * For alternatives, it can be 'ideal', 'directEbanking',...
            */
            $notificationPaymentMethod = $notification->getPaymentMethod();

            /*
            * For cards, it can be 'VI', 'MI',...
            * For alternatives, it can be 'ideal', 'directEbanking',...
            */
            $orderPaymentMethod = $this->order->getPayment()->getCcType();

            /*
             * Returns if the payment method is wallet like wechatpayWeb, amazonpay, applepay, paywithgoogle
             */
            $isWalletPaymentMethod = $this->paymentMethodsHelper->isWalletPaymentMethod($orderPaymentMethod);

            /*
             * Return if payment method is cc like VI, MI
             */
            $isCCPaymentMethod = $this->order->getPayment()->getMethod() === 'adyen_cc';

            /*
            * If the order was made with an Alternative payment method,
            *  continue with the cancellation only if the payment method of
            * the notification matches the payment method of the order.
            */
            if (!$isWalletPaymentMethod && !$isCCPaymentMethod && strcmp($notificationPaymentMethod, $orderPaymentMethod) !== 0) {
                $this->logger->addAdyenNotificationCronjob(
                    "The notification does not match the payment method of the order,
                    skipping OFFER_CLOSED"
                );
                return;
            }
        }

        // Move the order from PAYMENT_REVIEW to NEW, so that can be cancelled
        if (!$this->order->canCancel() && $this->configHelper->getNotificationsCanCancel($this->order->getStoreId())) {
            $this->order->setState(Order::STATE_NEW);
        }

        $this->holdCancelOrder($ignoreHasInvoice);
    }

    /**
     * @param $ignoreHasInvoice
     * @throws LocalizedException
     */
    private function holdCancelOrder($ignoreHasInvoice)
    {
        if (!$this->configHelper->getNotificationsCanCancel($this->order->getStoreId())) {
            $this->logger->addAdyenNotificationCronjob(
                'Order cannot be canceled based on the plugin configuration'
            );
            return;
        }

        $orderStatus = $this->configHelper->getConfigData(
            'payment_cancelled',
            'adyen_abstract',
            $this->order->getStoreId()
        );

        // check if order has in invoice only cancel/hold if this is not the case
        if ($ignoreHasInvoice || !$this->order->hasInvoices()) {
            if ($orderStatus == Order::STATE_HOLDED) {
                // Allow magento to hold order
                $this->order->setActionFlag(Order::ACTION_FLAG_HOLD, true);

                if ($this->order->canHold()) {
                    $this->order->hold();
                } else {
                    $this->logger->addAdyenNotificationCronjob('Order can not hold or is already on Hold');
                }
            } else {
                // Allow magento to cancel order
                $this->order->setActionFlag(Order::ACTION_FLAG_CANCEL, true);

                if ($this->order->canCancel()) {
                    $this->order->cancel();
                } else {
                    $this->logger->addAdyenNotificationCronjob('Order can not be canceled');
                }
            }
        } else {
            $this->logger->addAdyenNotificationCronjob(
                'Order has already an invoice so cannot be canceled'
            );
        }
    }

    /**
     * @param Notification $notification
     */
    private function refundOrder(Notification $notification)
    {
        $this->logger->addAdyenNotificationCronjob('Refunding the order');

        // check if it is a partial payment if so save the refunded data
        if ($notification->getOriginalReference() != "") {
            $this->logger->addAdyenNotificationCronjob(
                'Going to update the refund to partial payments table'
            );

            $orderPayment = $this->adyenOrderPaymentCollectionFactory
                ->create()
                ->addFieldToFilter(Notification::PSPREFRENCE, $notification->getOriginalReference())
                ->getFirstItem();

            if ($orderPayment->getId() > 0) {
                $amountRefunded = $orderPayment->getTotalRefunded() +
                    $this->adyenHelper->originalAmount($notification->getAmountValue(), $notification->getAmountCurrency());
                $orderPayment->setUpdatedAt(new DateTime());
                $orderPayment->setTotalRefunded($amountRefunded);
                $orderPayment->save();
                $this->logger->addAdyenNotificationCronjob(
                    'Update the refund in the partial payments table'
                );
            } else {
                $this->logger->addAdyenNotificationCronjob('Payment not found in partial payment table');
            }
        }

        /*
         * Don't create a credit memo if refund is initialized in Magento
         * because in this case the credit memo already exists.
         * Refunds initialized in Magento have a suffix such as '-refund', '-capture' or '-capture-refund' appended
         * to the original reference.
         */
        $lastTransactionId = $this->order->getPayment()->getLastTransId();
        $matches = $this->adyenHelper->parseTransactionId($lastTransactionId);
        if (($matches['pspReference'] ?? '') == $notification->getOriginalReference() && empty($matches['suffix'])) {
            // refund is done through adyen backoffice so create a credit memo
            if ($this->order->canCreditmemo()) {
                $amount = $this->adyenHelper->originalAmount($notification->getAmountValue(), $notification->getAmountCurrency());
                $this->order->getPayment()->registerRefundNotification($amount);

                $this->logger->addAdyenNotificationCronjob('Created credit memo for order');
                $this->order->addStatusHistoryComment(__('Adyen Refund Successfully completed'), $this->order->getStatus());
            } else {
                $this->logger->addAdyenNotificationCronjob('Could not create a credit memo for order');
            }
        } else {
            $this->logger->addAdyenNotificationCronjob(
                'Did not create a credit memo for this order because refund is done through Magento'
            );
        }
    }

    private function authorizePayment(Notification $notification)
    {
        $this->logger->addAdyenNotificationCronjob('Authorisation of the order');

        // Set adyen_notification_payment_captured to true so that we ignore a possible OFFER_CLOSED
        if ($notification->isSuccessful() && $this->isAutoCapture($notification->getPaymentMethod())) {
            $this->order->setData('adyen_notification_payment_captured', 1);
        }

        $this->adyenOrderPaymentHelper->createAdyenOrderPayment($this->order, $notification, $this->isAutoCapture($notification->getPaymentMethod()));
        $isFullAmountAuthorized = $this->adyenOrderPaymentHelper->isFullAmountAuthorized($this->order);

        if ($isFullAmountAuthorized) {
            $this->setPrePaymentAuthorized();
            $this->prepareInvoice($notification);
            // For Boleto confirmation mail is sent on order creation
            // Send order confirmation mail after invoice creation so merchant can add invoicePDF to this mail
            if ($notification->getPaymentMethod() != "adyen_boleto" && !$this->order->getEmailSent()) {
                $this->sendOrderMail();
            }
        } else {
            $this->addProcessedStatusHistoryComment($notification);
        }

        if ($notification->getPaymentMethod() == "c_cash" &&
            $this->configHelper->getConfigData('create_shipment', 'adyen_cash', $this->order->getStoreId())
        ) {
            $this->createShipment();
        }
    }

    private function refundPayment(Notification $notification)
    {
        $ignoreRefundNotification = $this->configHelper->getConfigData(
            'ignore_refund_notification',
            'adyen_abstract',
            $this->order->getStoreId()
        );
        if (!$ignoreRefundNotification) {
            $this->refundOrder($notification);
        } else {
            $this->logger->addAdyenNotificationCronjob(
                'Setting to ignore refund notification is enabled so ignore this notification'
            );
        }
    }

    /**
     * @param Notification $notification
     * @param $processing
     * @param $done
     */
    private function updateNotification(Notification $notification, $processing, $done)
    {
        if ($done) {
            $notification->setDone(true);
        }
        $notification->setProcessing($processing);
        $notification->setUpdatedAt(new DateTime());
        $notification->save();
    }

    /**
     * Declare private variables for processing notification
     *
     * @param Order $order
     * @param Notification $notification
     * @return void
     */
    private function declareVariables(Order $order, Notification $notification)
    {
        $additionalData = !empty($notification->getAdditionalData()) ? $this->serializer->unserialize(
            $notification->getAdditionalData()
        ) : "";

        if ($additionalData && is_array($additionalData)) {
            // boleto data
            if ($order->getPayment()->getMethod() == "adyen_boleto") {
                $boletobancario = $additionalData['boletobancario'] ?? null;
                if ($boletobancario && is_array($boletobancario)) {
                    $this->boletoOriginalAmount =
                        isset($boletobancario['originalAmount']) ? trim($boletobancario['originalAmount']) : "";
                    $this->boletoPaidAmount =
                        isset($boletobancario['paidAmount']) ? trim($boletobancario['paidAmount']) : "";
                }
            }
            $this->requireFraudManualReview = $this->caseManagementHelper->requiresManualReview($additionalData);
            $additionalData2 = $additionalData['additionalData'] ?? null;
            if ($additionalData2 && is_array($additionalData2)) {
                $this->klarnaReservationNumber = isset($additionalData2['acquirerReference']) ? trim(
                    $additionalData2['acquirerReference']
                ) : "";
            }
            $ratepayDescriptor = $additionalData['openinvoicedata.descriptor'] ?? "";
            if ($ratepayDescriptor !== "") {
                $this->ratepayDescriptor = $ratepayDescriptor;
            }
        }
    }

    /**
     * @desc order comments or history
     */
    private function addNotificationDetailsHistoryComment(Order $order, Notification $notification)
    {
        $successResult = $notification->isSuccessful() ? 'true' : 'false';
        $reason = $notification->getReason();
        $success = (!empty($reason)) ? "$successResult <br />reason:$reason" : $successResult;

        $eventCode = $notification->getEventCode();
        if ($eventCode == Notification::REFUND || $eventCode == Notification::CAPTURE) {
            // check if it is a full or partial refund
            $amount = $notification->getAmountValue();
            $orderAmountCurrency = $this->chargedCurrency->getOrderAmountCurrency($order, false);
            $formattedOrderAmount = $this->adyenHelper
                ->formatAmount($orderAmountCurrency->getAmount(), $orderAmountCurrency->getCurrencyCode());

            $this->logger->addAdyenNotificationCronjob(
                'amount notification:' . $amount . ' amount order:' . $formattedOrderAmount
            );

            if ($amount == $formattedOrderAmount) {
                $order->setData(
                    'adyen_notification_event_code',
                    $eventCode . " : " . strtoupper($successResult)
                );
            } else {
                $order->setData(
                    'adyen_notification_event_code',
                    "(PARTIAL) " .
                    $eventCode . " : " . strtoupper($successResult)
                );
            }
        } else {
            $order->setData(
                'adyen_notification_event_code',
                $eventCode . " : " . strtoupper($successResult)
            );
        }

        // if payment method is klarna, ratepay or openinvoice/afterpay show the reservartion number
        if ($this->adyenHelper->isPaymentMethodOpenInvoiceMethod(
            $notification->getPaymentMethod()
        ) && !empty($this->klarnaReservationNumber)) {
            $klarnaReservationNumberText = "<br /> reservationNumber: " . $this->klarnaReservationNumber;
        } else {
            $klarnaReservationNumberText = "";
        }

        if ($this->boletoPaidAmount != null && $this->boletoPaidAmount != "") {
            $boletoPaidAmountText = "<br /> Paid amount: " . $this->boletoPaidAmount;
        } else {
            $boletoPaidAmountText = "";
        }

        $type = 'Adyen HTTP Notification(s):';
        $comment = __(
            '%1 <br /> eventCode: %2 <br /> pspReference: %3 <br /> paymentMethod: %4 <br />' .
            ' success: %5 %6 %7',
            $type,
            $eventCode,
            $notification->getPspreference(),
            $notification->getPaymentMethod(),
            $success,
            $klarnaReservationNumberText,
            $boletoPaidAmountText
        );

        // If notification is pending status and pending status is set add the status change to the comment history
        if ($eventCode == Notification::PENDING) {
            $pendingStatus = $this->configHelper->getConfigData(
                'pending_status',
                'adyen_abstract',
                $order->getStoreId()
            );
            if ($pendingStatus != "") {
                $order->addStatusHistoryComment($comment, $pendingStatus);
                $this->logger->addAdyenNotificationCronjob(
                    'Created comment history for this notification with status change to: ' . $pendingStatus
                );
                return;
            }
        }

        $order->addStatusHistoryComment($comment, $order->getStatus());
        $this->logger->addAdyenNotificationCronjob('Created comment history for this notification');
    }

    /**
     * @param Notification $notification
     */
    private function updateAdyenAttributes(Notification $notification)
    {
        $this->logger->addAdyenNotificationCronjob('Updating the Adyen attributes of the order');

        $additionalData = !empty($notification->getAdditionalData()) ? $this->serializer->unserialize(
            $notification->getAdditionalData()
        ) : "";

        if ($notification->getEventCode() == Notification::AUTHORISATION
            || $notification->getEventCode() == Notification::HANDLED_EXTERNALLY
        ) {
            /*
             * if current notification is authorisation : false and
             * the  previous notification was authorisation : true do not update pspreference
             */
            if (!$notification->isSuccessful()) {
                $previousAdyenEventCode = $this->order->getData('adyen_notification_event_code');
                if ($previousAdyenEventCode != "AUTHORISATION : TRUE") {
                    $this->updateOrderPaymentWithAdyenAttributes($notification, $additionalData);
                }
            } else {
                $this->updateOrderPaymentWithAdyenAttributes($notification, $additionalData);
            }
        }
    }

    /**
     * @param Notification $notification
     * @param $additionalData
     */
    private function updateOrderPaymentWithAdyenAttributes(Notification $notification, $additionalData)
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
            $ccLast4 = $this->retrieveLast4DigitsFromReason($notification->getReason());
        }

        $this->order->getPayment()->setAdyenPspReference($notification->getPspreference());
        $this->order->getPayment()->setAdditionalInformation('pspReference', $notification->getPspreference());

        if ($this->klarnaReservationNumber != "") {
            $this->order->getPayment()->setAdditionalInformation(
                'adyen_klarna_number',
                $this->klarnaReservationNumber
            );
        }
        if (isset($ccLast4) && $ccLast4 != "") {
            // this field is column in db by core
            $this->order->getPayment()->setccLast4($ccLast4);
        }
        if (isset($avsResult) && $avsResult != "") {
            $this->order->getPayment()->setAdditionalInformation('adyen_avs_result', $avsResult);
        }
        if (isset($cvcResult) && $cvcResult != "") {
            $this->order->getPayment()->setAdditionalInformation('adyen_cvc_result', $cvcResult);
        }
        if ($this->boletoPaidAmount != "") {
            $this->order->getPayment()->setAdditionalInformation('adyen_boleto_paid_amount', $this->boletoPaidAmount);
        }
        if (isset($totalFraudScore) && $totalFraudScore != "") {
            $this->order->getPayment()->setAdditionalInformation('adyen_total_fraud_score', $totalFraudScore);
        }
        if (isset($refusalReasonRaw) && $refusalReasonRaw != "") {
            $this->order->getPayment()->setAdditionalInformation('adyen_refusal_reason_raw', $refusalReasonRaw);
        }
        if (isset($acquirerReference) && $acquirerReference != "") {
            $this->order->getPayment()->setAdditionalInformation('adyen_acquirer_reference', $acquirerReference);
        }
        if (isset($authCode) && $authCode != "") {
            $this->order->getPayment()->setAdditionalInformation('adyen_auth_code', $authCode);
        }
        if (!empty($cardBin)) {
            $this->order->getPayment()->setAdditionalInformation('adyen_card_bin', $cardBin);
        }
        if (!empty($expiryDate)) {
            $this->order->getPayment()->setAdditionalInformation('adyen_expiry_date', $expiryDate);
        }
        if ($this->ratepayDescriptor !== "") {
            $this->order->getPayment()->setAdditionalInformation(
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
    private function retrieveLast4DigitsFromReason($reason)
    {
        $result = "";

        if ($reason != "") {
            $reasonArray = explode(":", $reason);
            if ($reasonArray != null && is_array($reasonArray) && isset($reasonArray[1])) {
                $result = $reasonArray[1];
            }
        }
        return $result;
    }


    /**
     * Send order Mail
     *
     * @return void
     */
    private function sendOrderMail()
    {
        try {
            $this->orderSender->send($this->order);
            $this->logger->addAdyenNotificationCronjob('Send order confirmation email to shopper');
        } catch (Exception $exception) {
            $this->logger->addAdyenNotificationCronjob(
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
    private function setPrePaymentAuthorized()
    {
        $status = $this->configHelper->getConfigData(
            'payment_pre_authorized',
            'adyen_abstract',
            $this->order->getStoreId()
        );

        // only do this if status in configuration is set
        if (!empty($status)) {
            $this->order->setStatus($status);
            $this->setState($status);

            $this->logger->addAdyenNotificationCronjob(
                'Order status is changed to Pre-authorised status, status is ' . $status
            );
        } else {
            $this->logger->addAdyenNotificationCronjob('No pre-authorised status is used so ignore');
        }
    }

    /**
     * This function will only be called after we have verified that the full amount of the order has been AUTHORISED
     *
     * @param Notification $notification
     * @return void
     * @throws Exception
     */
    private function prepareInvoice(Notification $notification)
    {
        $this->logger->addAdyenNotificationCronjob('Prepare invoice for order');

        //Set order state to new because with order state payment_review it is not possible to create an invoice
        if (strcmp($this->order->getState(), Order::STATE_PAYMENT_REVIEW) == 0) {
            $this->order->setState(Order::STATE_NEW);
        }

        $paymentObj = $this->order->getPayment();

        // set pspReference as transactionId
        $paymentObj->setCcTransId($notification->getPspreference());
        $paymentObj->setLastTransId($notification->getPspreference());

        // set transaction
        $paymentObj->setTransactionId($notification->getPspreference());
        // Prepare transaction
        $transaction = $this->transactionBuilder->setPayment($paymentObj)
            ->setOrder($this->order)
            ->setTransactionId($notification->getPspreference())
            ->build(TransactionInterface::TYPE_AUTH);

        $transaction->setIsClosed(false);
        $transaction->save();

        // If this is auto capture, create invoice and check for case management. If not required, finalize order
        if ($this->isAutoCapture($notification->getPaymentMethod())) {
            $this->createInvoice($notification);
            // If manual review is required AND this order was auto captured, mark it AFTER creating the invoice
            if ($this->requireFraudManualReview) {
                $this->markPendingReviewAndLog(
                    $notification->getPspreference(),
                    true,
                    'Order %s was marked as pending manual review, AFTER the invoice was created',
                    $this->order->getIncrementId()
                );
            } else {
                $this->finalizeOrder($this->order, $notification);
            }
        } else {
            $this->addProcessedStatusHistoryComment($notification);
            $this->order->addStatusHistoryComment(__('Capture Mode set to Manual'), $this->order->getStatus());
            $this->logger->addAdyenNotificationCronjob('Capture mode is set to Manual');

            if ($this->requireFraudManualReview) {
                $this->markPendingReviewAndLog(
                    $notification->getPspreference(),
                    false,
                    'Order %s was marked as pending manual review without creating the invoice',
                    $this->order->getIncrementId()
                );
            }
        }
    }

    /**
     * @param $notificationPaymentMethod
     * @return bool
     */
    private function isAutoCapture($notificationPaymentMethod): bool
    {
        // validate if payment methods allows manual capture
        if ($this->manualCaptureAllowed($notificationPaymentMethod)) {
            $captureMode = trim(
                $this->configHelper->getConfigData(
                    'capture_mode',
                    'adyen_abstract',
                    $this->order->getStoreId()
                )
            );
            $sepaFlow = trim(
                $this->configHelper->getConfigData(
                    'sepa_flow',
                    'adyen_abstract',
                    $this->order->getStoreId()
                )
            );
            $paymentCode = $this->order->getPayment()->getMethod();
            $captureModeOpenInvoice = $this->configHelper->getConfigData(
                'auto_capture_openinvoice',
                'adyen_abstract',
                $this->order->getStoreId()
            );
            $manualCapturePayPal = trim(
                $this->configHelper->getConfigData(
                    'paypal_capture_mode',
                    'adyen_abstract',
                    $this->order->getStoreId()
                )
            );

            /*
             * if you are using authcap the payment method is manual.
             * There will be a capture send to indicate if payment is successful
             */
            if ($notificationPaymentMethod == "sepadirectdebit") {
                if ($sepaFlow == "authcap") {
                    $this->logger->addAdyenNotificationCronjob(
                        'Manual Capture is applied for sepa because it is in authcap flow'
                    );
                    return false;
                } else {
                    // payment method ideal, cash adyen_boleto has direct capture
                    $this->logger->addAdyenNotificationCronjob(
                        'This payment method does not allow manual capture.(2) paymentCode:' .
                        $paymentCode . ' paymentMethod:' . $notificationPaymentMethod . ' sepaFLow:' . $sepaFlow
                    );
                    return true;
                }
            }

            if ($paymentCode == "adyen_pos_cloud") {
                $captureModePos = $this->adyenHelper->getAdyenPosCloudConfigData(
                    'capture_mode_pos',
                    $this->order->getStoreId()
                );
                if (strcmp($captureModePos, 'auto') === 0) {
                    $this->logger->addAdyenNotificationCronjob(
                        'This payment method is POS Cloud and configured to be working as auto capture '
                    );
                    return true;
                } elseif (strcmp($captureModePos, 'manual') === 0) {
                    $this->logger->addAdyenNotificationCronjob(
                        'This payment method is POS Cloud and configured to be working as manual capture '
                    );
                    return false;
                }
            }

            // if auto capture mode for openinvoice is turned on then use auto capture
            if ($captureModeOpenInvoice &&
                $this->adyenHelper->isPaymentMethodOpenInvoiceMethodValidForAutoCapture($notificationPaymentMethod)
            ) {
                $this->logger->addAdyenNotificationCronjob(
                    'This payment method is configured to be working as auto capture '
                );
                return true;
            }

            // if PayPal capture modues is different from the default use this one
            if (strcmp($notificationPaymentMethod, 'paypal') === 0) {
                if ($manualCapturePayPal) {
                    $this->logger->addAdyenNotificationCronjob(
                        'This payment method is paypal and configured to work as manual capture'
                    );
                    return false;
                } else {
                    $this->logger->addAdyenNotificationCronjob(
                        'This payment method is paypal and configured to work as auto capture'
                    );
                    return true;
                }
            }
            if (strcmp($captureMode, 'manual') === 0) {
                $this->logger->addAdyenNotificationCronjob(
                    'Capture mode for this payment is set to manual'
                );
                return false;
            }

            /*
             * online capture after delivery, use Magento backend to online invoice
             * (if the option auto capture mode for openinvoice is not set)
             */
            if ($this->adyenHelper->isPaymentMethodOpenInvoiceMethod($notificationPaymentMethod)) {
                $this->logger->addAdyenNotificationCronjob(
                    'Capture mode for klarna is by default set to manual'
                );
                return false;
            }

            $this->logger->addAdyenNotificationCronjob('Capture mode is set to auto capture');
            return true;
        } else {
            // does not allow manual capture so is always immediate capture
            $this->logger->addAdyenNotificationCronjob(
                sprintf('Payment method %s, does not allow manual capture', $notificationPaymentMethod)
            );

            return true;
        }
    }

    /**
     * Validate if this payment methods allows manual capture
     * This is a default can be forced differently to overrule on acquirer level
     *
     * @param $notificationPaymentMethod
     * @return bool
     */
    private function manualCaptureAllowed($notificationPaymentMethod): bool
    {
        $manualCaptureAllowed = false;
        // For all openinvoice methods manual capture is the default
        if ($this->adyenHelper->isPaymentMethodOpenInvoiceMethod($notificationPaymentMethod)) {
            return true;
        }

        switch ($notificationPaymentMethod) {
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
            case 'svs':
            case 'givex':
            case 'valuelink':
            case 'twint':
                $manualCaptureAllowed = true;
                break;
            default:
                break;
        }

        return $manualCaptureAllowed;
    }

    /**
     * @return bool
     */
    private function isBankTransfer($paymentMethod)
    {
        if (strlen($paymentMethod) >= 12 && substr($paymentMethod, 0, 12) == "bankTransfer") {
            $isBankTransfer = true;
        } else {
            $isBankTransfer = false;
        }
        return $isBankTransfer;
    }

    /**
     * @param Notification $notification
     * @throws Exception
     */
    private function createInvoice(Notification $notification)
    {
        $this->logger->addAdyenNotificationCronjob('Creating invoice for order');

        if ($this->order->canInvoice()) {
            /* We do not use this inside a transaction because order->save()
             * is always done on the end of the notification
             * and it could result in a deadlock see https://github.com/Adyen/magento/issues/334
             */
            try {
                $invoice = $this->order->prepareInvoice();
                $invoice->getOrder()->setIsInProcess(true);

                // set transaction id so you can do a online refund from credit memo
                $invoice->setTransactionId($notification->getPspreference());


                $autoCapture = $this->isAutoCapture($notification->getPaymentMethod());
                $createPendingInvoice = (bool)$this->configHelper->getConfigData(
                    'create_pending_invoice',
                    'adyen_abstract',
                    $this->order->getStoreId()
                );

                if ((!$autoCapture) && ($createPendingInvoice)) {
                    // if amount is zero create a offline invoice
                    $value = (int)$notification->getAmountValue();
                    if ($value == 0) {
                        $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE);
                    } else {
                        $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::NOT_CAPTURE);
                    }

                    $invoice->register();
                } else {
                    $invoice->register()->pay();
                }

                $this->invoiceResourceModel->save($invoice);
                $this->logger->addAdyenNotificationCronjob(
                    sprintf('Notification %s created an invoice.', $notification->getEntityId()),
                    $this->invoiceHelper->getLogInvoiceContext($invoice)
                );
            } catch (Exception $e) {
                $this->logger->addAdyenNotificationCronjob('Error saving invoice: ' . $e->getMessage());
                throw $e;
            }

            $invoiceAutoMail = (bool)$this->scopeConfig->isSetFlag(
                InvoiceIdentity::XML_PATH_EMAIL_ENABLED,
                ScopeInterface::SCOPE_STORE,
                $this->order->getStoreId()
            );

            if ($invoiceAutoMail) {
                $this->invoiceSender->send($invoice);
            }
        } else {
            $this->logger->addAdyenNotificationCronjob(
                sprintf('Unable to create invoice when handling Notification %s', $notification->getEntityId()),
                array_merge($this->adyenOrderPaymentHelper->getLogOrderContext($this->order), [
                    'canUnhold' => $this->order->canUnhold(),
                    'isPaymentReview' => $this->order->isPaymentReview(),
                    'isCancelled' => $this->order->isCanceled(),
                    'invoiceActionFlag' => $this->order->getActionFlag(Order::ACTION_FLAG_INVOICE)
                ])
            );
        }
    }

    /**
     * Finalize order by setting it to captured if manual capture is enabled, or authorized if auto capture is used
     * Full order will only NOT be finalized if the full amount has not been captured/authorized.
     */
    private function finalizeOrder(Order $order, Notification $notification)
    {
        $this->logger->addAdyenNotificationCronjob('Set order to authorised');
        $amount = $notification->getAmountValue();
        $orderAmountCurrency = $this->chargedCurrency->getOrderAmountCurrency($order, false);
        $formattedOrderAmount = $this->adyenHelper
            ->formatAmount($orderAmountCurrency->getAmount(), $orderAmountCurrency->getCurrencyCode());
        $fullAmountFinalized = $this->adyenOrderPaymentHelper->isFullAmountFinalized($order);

        $status = $this->configHelper->getConfigData(
            'payment_authorized',
            'adyen_abstract',
            $order->getStoreId()
        );

        // Set state back to previous state to prevent update if 'maintain status' was configured
        $maintainingState = false;
        if ($status === AdyenState::STATE_MAINTAIN) {
            $maintainingState = true;
            $status = $order->getStatus();
        }

        // virtual order can have different status
        if ($order->getIsVirtual()) {
            $status = $this->getVirtualStatus($status);
        }

        // check for boleto if payment is totally paid
        if ($order->getPayment()->getMethod() == "adyen_boleto") {
            // check if paid amount is the same as orginal amount
            $originalAmount = $this->boletoOriginalAmount;
            $paidAmount = $this->boletoPaidAmount;

            if ($originalAmount != $paidAmount) {
                // not the full amount is paid. Check if it is underpaid or overpaid
                // strip the  BRL of the string
                $originalAmount = str_replace("BRL", "", $originalAmount);
                $originalAmount = floatval(trim($originalAmount));

                $paidAmount = str_replace("BRL", "", $paidAmount);
                $paidAmount = floatval(trim($paidAmount));

                if ($paidAmount > $originalAmount) {
                    $overpaidStatus = $this->configHelper->getConfigData(
                        'order_overpaid_status',
                        'adyen_boleto',
                        $order->getStoreId()
                    );
                    // check if there is selected a status if not fall back to the default
                    $status = (!empty($overpaidStatus)) ? $overpaidStatus : $status;
                } else {
                    $underpaidStatus = $this->configHelper->getConfigData(
                        'order_underpaid_status',
                        'adyen_boleto',
                        $order->getStoreId()
                    );
                    // check if there is selected a status if not fall back to the default
                    $status = (!empty($underpaidStatus)) ? $underpaidStatus : $status;
                }
            }
        }

        $this->addProcessedStatusHistoryComment($notification);
        if ($fullAmountFinalized) {
            $this->logger->addAdyenNotificationCronjob(sprintf(
                'Notification w/amount %s has completed the capturing of order %s w/amount %s',
                $amount,
                $order->getIncrementId(),
                $formattedOrderAmount
            ));
            $comment = "Adyen Payment Successfully completed";
            // If a status is set, add comment, set status and update the state based on the status
            // Else add comment
            if (!empty($status) && $maintainingState) {
                $order->addStatusHistoryComment(__($comment), $status);
                $this->logger->addAdyenNotificationCronjob(
                    'Maintaining current status: ' . $status,
                    $this->adyenOrderPaymentHelper->getLogOrderContext($this->order)
                );
            } else if (!empty($status)) {
                $order->addStatusHistoryComment(__($comment), $status);
                $this->setState($status);
                $this->logger->addAdyenNotificationCronjob(
                    'Order status was changed to authorised status: ' . $status,
                    $this->adyenOrderPaymentHelper->getLogOrderContext($this->order)
                );
            } else {
                $order->addStatusHistoryComment(__($comment));
                $this->logger->addAdyenNotificationCronjob(sprintf(
                    'Order %s was finalized. Authorised status not set',
                    $order->getIncrementId()
                ));
            }
        }
    }
    /**
     * Set State from Status
     */
    private function setState($status)
    {
        $statusObject = $this->orderStatusCollection->create()
            ->addFieldToFilter('main_table.status', $status)
            ->joinStates()
            ->getFirstItem();

        $this->order->setState($statusObject->getState());
        $this->logger->addAdyenNotificationCronjob('State is changed to  ' . $statusObject->getState());
    }

    /**
     * Create shipment
     *
     * @throws bool
     */
    private function createShipment()
    {
        $this->logger->addAdyenNotificationCronjob('Creating shipment for order');
        // create shipment for cash payment
        if ($this->order->canShip()) {
            $itemQty = [];
            $shipment = $this->order->prepareShipment($itemQty);
            if ($shipment) {
                $shipment->register();
                $shipment->getOrder()->setIsInProcess(true);
                $comment = __('Shipment created by Adyen');
                $shipment->addComment($comment);

                $transaction = $this->transactionFactory->create();
                $transaction->addObject($shipment)
                    ->addObject($shipment->getOrder())
                    ->save();

                $this->logger->addAdyenNotificationCronjob('Order is shipped');
            }
        } else {
            $this->logger->addAdyenNotificationCronjob('Order can\'t be shipped');
        }
    }

    /**
     * Add admin notice message for refund failed notification
     *
     * @return void
     */
    private function addRefundFailedNotice(Notification $notification)
    {
        $this->notifierPool->addNotice(
            __("Adyen: Refund for order #%1 has failed", $notification->getMerchantReference()),
            __(
                "Reason: %1 | PSPReference: %2 | You can go to Adyen Customer Area
                and trigger this refund manually or contact our support.",
                $notification->getReason(),
                $notification->getPspreference()
            ),
            $this->adyenHelper->getPspReferenceSearchUrl($notification->getPspreference(), $notification->getLive())
        );
    }

    /**
     * Add/update info on notification processing errors
     *
     * @param Notification $notification
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
     * @param Notification $notification
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
        if ($this->order) {
            $this->order->addStatusHistoryComment($comment, $this->order->getStatus());
            $this->order->save();
        }
    }

    /**
     * If the payment_authorized_virtual config is set, return the virtual status
     *
     * @param $status
     * @return mixed
     */
    private function getVirtualStatus($status)
    {
        $this->logger->addAdyenNotificationCronjob('Product is a virtual product');
        $virtualStatus = $this->configHelper->getConfigData(
            'payment_authorized_virtual',
            'adyen_abstract',
            $this->order->getStoreId()
        );
        if ($virtualStatus != "") {
            $status = $virtualStatus;
        }

        return $status;
    }

    /**
     * Call the caseManagement helper function and log the passed message
     *
     * @param bool $autoCapture
     * @param string $logComment
     * @param ...$logValues
     */
    private function markPendingReviewAndLog(string $pspReference, bool $autoCapture, string $logComment, ...$logValues): void
    {
        $this->caseManagementHelper->markCaseAsPendingReview($this->order, $pspReference, $autoCapture);
        $this->logger->addAdyenNotificationCronjob(sprintf($logComment, ...$logValues));
    }

    /**
     * Add a comment to the order once the webhook notification has been processed
     */
    private function addProcessedStatusHistoryComment(Notification $notification): void
    {
        $this->order->addStatusHistoryComment(__(sprintf(
            '%s webhook notification w/amount %s %s was processed',
            $notification->getEventCode(),
            $notification->getAmountCurrency(),
            $this->adyenHelper->originalAmount($notification->getAmountValue(), $notification->getAmountCurrency())
        )), false);
    }

    /**
     * Handle the webhook by updating invoice related entities, refresh capture status of adyen_order_payment and
     * attempt to finalize order
     */
    private function handleManualCapture(Notification $notification)
    {
        try {
            $adyenInvoice = $this->invoiceHelper->handleCaptureWebhook($this->order, $notification);
            // Refresh the order by fetching it from the db
            $this->setOrderByIncrementId($notification);
            $adyenOrderPayment = $this->adyenOrderPaymentFactory->create()->load($adyenInvoice->getAdyenPaymentOrderId(), OrderPaymentInterface::ENTITY_ID);
            $this->adyenOrderPaymentHelper->refreshPaymentCaptureStatus($adyenOrderPayment, $notification->getAmountCurrency());
            $this->logger->addAdyenNotificationCronjob(sprintf(
                'adyen_invoice %s linked to invoice %s and adyen_order_payment %s was updated',
                $adyenInvoice->getEntityId(),
                $adyenInvoice->getInvoiceId(),
                $adyenInvoice->getAdyenPaymentOrderId()
            ));

            $magentoInvoice = $this->magentoInvoiceFactory->create()->load($adyenInvoice->getInvoiceId(), Order\Invoice::ENTITY_ID);
            $this->logger->addAdyenNotificationCronjob(
                sprintf('Notification %s updated invoice %s.', $notification->getEntityId(), $magentoInvoice->getEntityid()),
                $this->invoiceHelper->getLogInvoiceContext($magentoInvoice)
            );
        } catch (Exception $e) {
            $this->logger->addAdyenNotificationCronjob($e->getMessage());
        }

        $this->finalizeOrder($this->order, $notification);
    }

    /**
     * Set the order data member by fetching the entity from the database.
     * This should be moved out of this file in the future.
     * @param Notification $notification
     */
    private function setOrderByIncrementId(Notification $notification)
    {
        $incrementId = $notification->getMerchantReference();

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('increment_id', $incrementId, 'eq')
            ->create();

        $orderList = $this->orderRepository->getList($searchCriteria)->getItems();

        /** @var Order $order */
        $order = reset($orderList);
        $this->order = $order;
    }
}
