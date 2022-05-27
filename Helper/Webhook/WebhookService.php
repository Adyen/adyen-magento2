<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2022 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper\Webhook;

use Adyen\Payment\Helper\AdyenOrderPayment;
use Adyen\Payment\Helper\CaseManagement;
use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\Invoice;
use Adyen\Payment\Helper\Invoice as InvoiceHelper;
use Adyen\Payment\Helper\Webhook;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Config\Source\Status\AdyenState;
use Adyen\Payment\Model\Notification;
use Exception;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Container\InvoiceIdentity;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Payment\Transaction\Builder;
use Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory as OrderStatusCollectionFactory;
use Magento\Store\Model\ScopeInterface;

/**
 * @TODO This should be renamed to WebhookHelper
 *
 *
 * Class WebhookService
 * @package Adyen\Payment\Helper\Webhook
 */
class WebhookService
{
    /** @var Config */
    private $configHelper;

    /** @var AdyenLogger */
    private $logger;

    /** @var Data */
    private $dataHelper;

    /** @var OrderStatusCollectionFactory $orderStatusCollectionFactory */
    private $orderStatusCollectionFactory;

    /** @var Builder */
    private $transactionBuilder;

    /** @var AdyenOrderPayment */
    private $adyenOrderPaymentHelper;

    /**
     * @var InvoiceRepositoryInterface
     */
    private $invoiceRepository;

    /**
     * @var InvoiceHelper
     */
    private $invoiceHelper;

    /**
     * @var InvoiceSender
     */
    private $invoiceSender;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /** @var CaseManagement */
    private $caseManagementHelper;

    /** @var ChargedCurrency */
    private $chargedCurrency;

    public function __construct(
        Config $configHelper,
        AdyenLogger $adyenLogger,
        Data $dataHelper,
        OrderStatusCollectionFactory $orderStatusCollectionFactory,
        Builder $transactionBuilder,
        AdyenOrderPayment $adyenOrderPayment,
        InvoiceRepositoryInterface $invoiceRepository,
        InvoiceHelper $invoiceHelper,
        InvoiceSender $invoiceSender,
        ScopeConfigInterface $scopeConfig,
        CaseManagement $caseManagementHelper,
        ChargedCurrency $chargedCurrency
    )
    {
        $this->configHelper = $configHelper;
        $this->logger = $adyenLogger;
        $this->dataHelper = $dataHelper;
        $this->orderStatusCollectionFactory = $orderStatusCollectionFactory;
        $this->transactionBuilder = $transactionBuilder;
        $this->adyenOrderPaymentHelper = $adyenOrderPayment;
        $this->invoiceRepository = $invoiceRepository;
        $this->invoiceHelper = $invoiceHelper;
        $this->invoiceSender = $invoiceSender;
        $this->scopeConfig = $scopeConfig;
        $this->caseManagementHelper = $caseManagementHelper;
        $this->chargedCurrency = $chargedCurrency;
    }

    /**
     * Check if order should be automatically captured
     *
     * @param Order $order
     * @param string $notificationPaymentMethod
     * @return bool
     */
    public function isAutoCapture(Order $order, string $notificationPaymentMethod): bool
    {
        // validate if payment methods allows manual capture
        if ($this->manualCaptureAllowed($notificationPaymentMethod)) {
            $captureMode = trim(
                $this->configHelper->getConfigData(
                    'capture_mode',
                    'adyen_abstract',
                    $order->getStoreId()
                )
            );
            $sepaFlow = trim(
                $this->configHelper->getConfigData(
                    'sepa_flow',
                    'adyen_abstract',
                    $order->getStoreId()
                )
            );
            $paymentCode = $order->getPayment()->getMethod();
            $captureModeOpenInvoice = $this->configHelper->getConfigData(
                'auto_capture_openinvoice',
                'adyen_abstract',
                $order->getStoreId()
            );
            $manualCapturePayPal = trim(
                $this->configHelper->getConfigData(
                    'paypal_capture_mode',
                    'adyen_abstract',
                    $order->getStoreId()
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
                $captureModePos = $this->dataHelper->getAdyenPosCloudConfigData(
                    'capture_mode_pos',
                    $order->getStoreId()
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
                $this->dataHelper->isPaymentMethodOpenInvoiceMethodValidForAutoCapture($notificationPaymentMethod)
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
            if ($this->dataHelper->isPaymentMethodOpenInvoiceMethod($notificationPaymentMethod)) {
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
     * Set status on authorisation
     *
     * @param Order $order
     * @return Order
     */
    public function setPrePaymentAuthorized(Order $order): Order
    {
        $eventLabel = "payment_pre_authorized";
        $status = $this->configHelper->getConfigData(
            $eventLabel,
            'adyen_abstract',
            $order->getStoreId()
        );
        $possibleStates = Webhook::STATE_TRANSITION_MATRIX[$eventLabel];

        // only do this if status in configuration is set
        if (!empty($status)) {
            $order->setStatus($status);
            $order = $this->setState($order, $status, $possibleStates);

            $this->logger->addAdyenNotificationCronjob(
                'Order status is changed to Pre-authorised status, status is ' . $status
            );
        } else {
            $this->logger->addAdyenNotificationCronjob('No pre-authorised status is used so ignore');
        }

        return $order;
    }

    /**
     * @param Order $order
     * @param Notification $notification
     * @param bool $isAutoCapture
     * @throws LocalizedException
     */
    public function createInvoice(Order $order, Notification $notification, bool $isAutoCapture)
    {
        $this->logger->addAdyenNotificationCronjob('Creating invoice for order');

        if ($order->canInvoice()) {
            /* We do not use this inside a transaction because order->save()
             * is always done on the end of the notification
             * and it could result in a deadlock see https://github.com/Adyen/magento/issues/334
             */
            try {
                $invoice = $order->prepareInvoice();
                $invoice->getOrder()->setIsInProcess(true);

                // set transaction id so you can do a online refund from credit memo
                $invoice->setTransactionId($notification->getPspreference());

                $createPendingInvoice = (bool)$this->configHelper->getConfigData(
                    'create_pending_invoice',
                    'adyen_abstract',
                    $order->getStoreId()
                );

                if ((!$isAutoCapture) && ($createPendingInvoice)) {
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

                $this->invoiceRepository->save($invoice);
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
                $order->getStoreId()
            );

            if ($invoiceAutoMail) {
                $this->invoiceSender->send($invoice);
            }
        } else {
            $this->logger->addAdyenNotificationCronjob(
                sprintf('Unable to create invoice when handling Notification %s', $notification->getEntityId()),
                array_merge($this->adyenOrderPaymentHelper->getLogOrderContext($order), [
                    'canUnhold' => $order->canUnhold(),
                    'isPaymentReview' => $order->isPaymentReview(),
                    'isCancelled' => $order->isCanceled(),
                    'invoiceActionFlag' => $order->getActionFlag(Order::ACTION_FLAG_INVOICE)
                ])
            );
        }
    }

    /**
     * Set order state, based on the passed status
     *
     * @param Order $order
     * @param $status
     * @param $possibleStates
     * @return Order
     */
    private function setState(Order $order, $status, $possibleStates): Order
    {
        // Loop over possible states, select first available status that fits this state
        foreach ($possibleStates as $state) {
            $statusObject = $this->orderStatusCollectionFactory->create()
                ->addFieldToFilter('main_table.status', $status)
                ->joinStates()
                ->addStateFilter($state)
                ->getFirstItem();

            if ($statusObject->getState() == $state) {
                // Exit function if fitting state is found
                $order->setState($statusObject->getState());
                $this->logger->addAdyenNotificationCronjob('State is changed to  ' . $statusObject->getState());

                return $order;
            }
        }

        $this->logger->addAdyenNotificationCronjob('No new state assigned, status should be connected to one of the following states: ' . json_encode($possibleStates));

        return $order;
    }

    /**
     * Validate if this payment methods allows manual capture
     * This is a default can be forced differently to overrule on acquirer level
     *
     * @param string $notificationPaymentMethod
     * @return bool
     */
    private function manualCaptureAllowed(string $notificationPaymentMethod): bool
    {
        $manualCaptureAllowed = false;
        // For all openinvoice methods manual capture is the default
        if ($this->dataHelper->isPaymentMethodOpenInvoiceMethod($notificationPaymentMethod)) {
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
     * Call the caseManagement helper function and log the passed message
     *
     * @param Order $order
     * @param string $pspReference
     * @param bool $autoCapture
     * @param string $logComment
     * @param ...$logValues
     */
    private function markPendingReviewAndLog(Order $order, string $pspReference, bool $autoCapture, string $logComment, ...$logValues): void
    {
        $this->caseManagementHelper->markCaseAsPendingReview($order, $pspReference, $autoCapture);
        $this->logger->addAdyenNotificationCronjob(sprintf($logComment, ...$logValues));
    }

    /**
     * Add a comment to the order once the webhook notification has been processed
     */
    public function addProcessedStatusHistoryComment(Order $order, Notification $notification): Order
    {
        $order->addStatusHistoryComment(__(sprintf(
            '%s webhook notification w/amount %s %s was processed',
            $notification->getEventCode(),
            $notification->getAmountCurrency(),
            $this->dataHelper->originalAmount($notification->getAmountValue(), $notification->getAmountCurrency())
        )), false);

        return $order;
    }

    /**
     * Finalize order by setting it to captured if manual capture is enabled, or authorized if auto capture is used
     * Full order will only NOT be finalized if the full amount has not been captured/authorized.
     */
    public function finalizeOrder(Order $order, Notification $notification)
    {
        $this->logger->addAdyenNotificationCronjob('Set order to authorised');
        $amount = $notification->getAmountValue();
        $orderAmountCurrency = $this->chargedCurrency->getOrderAmountCurrency($order, false);
        $formattedOrderAmount = $this->dataHelper->formatAmount($orderAmountCurrency->getAmount(), $orderAmountCurrency->getCurrencyCode());
        $fullAmountFinalized = $this->adyenOrderPaymentHelper->isFullAmountFinalized($order);

        $eventLabel = 'payment_authorized';
        $status = $this->configHelper->getConfigData(
            $eventLabel,
            'adyen_abstract',
            $order->getStoreId()
        );
        $possibleStates = Webhook::STATE_TRANSITION_MATRIX[$eventLabel];

        // Set state back to previous state to prevent update if 'maintain status' was configured
        $maintainingState = false;
        if ($status === AdyenState::STATE_MAINTAIN) {
            $maintainingState = true;
            $status = $order->getStatus();
        }

        /*
         * @TODO check for virtual orders
        // virtual order can have different status
        if ($order->getIsVirtual()) {
            $status = $this->getVirtualStatus($status);
        }
        */

        /*
         * @TODO Check for boleto specific stuff
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
        }*/

        $order = $this->addProcessedStatusHistoryComment($order, $notification);
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
                    $this->adyenOrderPaymentHelper->getLogOrderContext($order)
                );
            } else if (!empty($status)) {
                $order->addStatusHistoryComment(__($comment), $status);

                $this->setState($order, $status, $possibleStates);
                $this->logger->addAdyenNotificationCronjob(
                    'Order status was changed to authorised status: ' . $status,
                    $this->adyenOrderPaymentHelper->getLogOrderContext($order)
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
}