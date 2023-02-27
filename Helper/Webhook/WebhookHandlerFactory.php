<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2023 Adyen N.V.
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
use Adyen\Payment\Helper\Invoice;
use Adyen\Payment\Helper\Order;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Model\ResourceModel\Order\Payment\CollectionFactory as OrderPaymentCollectionFactory;
use Adyen\Payment\Model\Order\PaymentFactory;
use Adyen\Payment\Model\ResourceModel\Order\Payment as OrderPaymentResourceModel;
use Adyen\Webhook\Exception\InvalidDataException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Sales\Model\Order\InvoiceFactory as MagentoInvoiceFactory;
use Magento\Vault\Api\PaymentTokenManagementInterface;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;

class WebhookHandlerFactory
{
    /** @var AdyenOrderPayment */
    private static AdyenOrderPayment $adyenOrderPayment;

    /** @var Order */
    private static Order $orderHelper;

    /** @var CaseManagement */
    private static CaseManagement $caseManagementHelper;

    /** @var SerializerInterface */
    private static SerializerInterface $serializer;

    /** @var AdyenLogger $adyenLogger */
    private static AdyenLogger $adyenLogger;

    /** @var ChargedCurrency */
    private static ChargedCurrency $chargedCurrency;

    /** @var Config */
    private static Config $configHelper;

    /** @var Invoice */
    private static Invoice $invoiceHelper;

    /** @var PaymentFactory */
    private static PaymentFactory $adyenOrderPaymentFactory;

    /** @var MagentoInvoiceFactory */
    private static MagentoInvoiceFactory $magentoInvoiceFactory;

    /** @var PaymentMethods */
    private static PaymentMethods $paymentMethodsHelper;

    /** @var OrderPaymentCollectionFactory */
    private static OrderPaymentCollectionFactory $adyenOrderPaymentCollectionFactory;

    /** @var PaymentTokenManagementInterface */
    private static PaymentTokenManagementInterface $tokenManagement;

    /** @var PaymentTokenRepositoryInterface */
    private static PaymentTokenRepositoryInterface $paymentTokenRepository;

    /** @var OrderPaymentResourceModel */
    private static OrderPaymentResourceModel $orderPaymentResourceModel;

    /**
     * @param AdyenOrderPayment $adyenOrderPayment
     * @param Order $orderHelper
     * @param CaseManagement $caseManagementHelper
     * @param SerializerInterface $serializer
     * @param AdyenLogger $adyenLogger
     * @param ChargedCurrency $chargedCurrency
     * @param Config $configHelper
     * @param Invoice $invoiceHelper
     * @param PaymentFactory $adyenOrderPaymentFactory
     * @param MagentoInvoiceFactory $magentoInvoiceFactory
     * @param PaymentMethods $paymentMethodsHelper
     * @param OrderPaymentCollectionFactory $adyenOrderPaymentCollectionFactory
     * @param PaymentTokenManagementInterface $paymentTokenManagement
     * @param PaymentTokenRepositoryInterface $paymentTokenRepository
     * @param OrderPaymentResourceModel $orderPaymentResourceModel
     */
    public function __construct(
        AdyenOrderPayment $adyenOrderPayment,
        Order $orderHelper,
        CaseManagement $caseManagementHelper,
        SerializerInterface $serializer,
        AdyenLogger $adyenLogger,
        ChargedCurrency $chargedCurrency,
        Config $configHelper,
        Invoice $invoiceHelper,
        PaymentFactory $adyenOrderPaymentFactory,
        MagentoInvoiceFactory $magentoInvoiceFactory,
        PaymentMethods $paymentMethodsHelper,
        OrderPaymentCollectionFactory $adyenOrderPaymentCollectionFactory,
        PaymentTokenManagementInterface $paymentTokenManagement,
        PaymentTokenRepositoryInterface $paymentTokenRepository,
        OrderPaymentResourceModel $orderPaymentResourceModel
    ) {
        self::$adyenOrderPayment = $adyenOrderPayment;
        self::$orderHelper = $orderHelper;
        self::$caseManagementHelper = $caseManagementHelper;
        self::$serializer = $serializer;
        self::$adyenLogger = $adyenLogger;
        self::$chargedCurrency = $chargedCurrency;
        self::$configHelper = $configHelper;
        self::$invoiceHelper = $invoiceHelper;
        self::$adyenOrderPaymentFactory = $adyenOrderPaymentFactory;
        self::$magentoInvoiceFactory = $magentoInvoiceFactory;
        self::$paymentMethodsHelper = $paymentMethodsHelper;
        self::$adyenOrderPaymentCollectionFactory = $adyenOrderPaymentCollectionFactory;
        self::$tokenManagement = $paymentTokenManagement;
        self::$paymentTokenRepository = $paymentTokenRepository;
        self::$orderPaymentResourceModel = $orderPaymentResourceModel;
    }

    /**
     * @param string $eventCode
     * @return WebhookHandlerInterface
     * @throws InvalidDataException
     */
    public static function create(string $eventCode): WebhookHandlerInterface
    {
        switch ($eventCode) {
            case Notification::HANDLED_EXTERNALLY:
            case Notification::AUTHORISATION:
                return new AuthorisationWebhookHandler(
                    self::$adyenOrderPayment,
                    self::$orderHelper,
                    self::$caseManagementHelper,
                    self::$serializer,
                    self::$adyenLogger,
                    self::$chargedCurrency,
                    self::$configHelper,
                    self::$invoiceHelper,
                    self::$paymentMethodsHelper
                );
            case Notification::CAPTURE:
                return new CaptureWebhookHandler(
                    self::$invoiceHelper,
                    self::$adyenOrderPaymentFactory,
                    self::$adyenOrderPayment,
                    self::$adyenLogger,
                    self::$magentoInvoiceFactory,
                    self::$orderHelper,
                    self::$paymentMethodsHelper
                );
            case Notification::OFFER_CLOSED:
                return new OfferClosedWebhookHandler(
                    self::$paymentMethodsHelper,
                    self::$adyenLogger,
                    self::$configHelper,
                    self::$orderHelper,
                    self::$orderPaymentResourceModel,
                );
            case Notification::REFUND:
                return new RefundWebhookHandler(
                    self::$orderHelper,
                    self::$configHelper,
                    self::$adyenLogger,
                );
            case Notification::REFUND_FAILED:
                return new RefundFailedWebhookHandler(
                    self::$orderHelper
                );
            case Notification::MANUAL_REVIEW_ACCEPT:
                return new ManualReviewAcceptWebhookHandler(
                    self::$caseManagementHelper,
                    self::$paymentMethodsHelper,
                    self::$orderHelper
                );
            case Notification::MANUAL_REVIEW_REJECT:
                return new ManualReviewRejectWebhookHandler(
                    self::$caseManagementHelper,
                    self::$paymentMethodsHelper
                );
            case Notification::RECURRING_CONTRACT:
                return new RecurringContractWebhookHandler(
                    self::$adyenLogger,
                    self::$tokenManagement,
                    self::$paymentTokenRepository
                );
            case Notification::PENDING:
                return new PendingWebhookHandler(
                    self::$configHelper,
                    self::$orderHelper,
                    self::$paymentMethodsHelper
                );
            case Notification::CANCELLATION:
                return new CancellationWebhookHandler(
                    self::$orderHelper
                );
            case Notification::CANCEL_OR_REFUND:
                return new CancelOrRefundWebhookHandler(
                    self::$adyenLogger,
                    self::$serializer,
                    self::$orderHelper
                );
            case Notification::ORDER_CLOSED:
                return new OrderClosedWebhookHandler(
                    self::$adyenOrderPayment,
                    self::$orderHelper,
                    self::$configHelper,
                    self::$adyenOrderPaymentCollectionFactory,
                    self::$adyenLogger
                );
        }

        $exceptionMessage = sprintf(
            'Unknown webhook type: %s. This type is not yet handled by the Adyen Magento plugin', $eventCode
        );

        self::$adyenLogger->addAdyenWarning($exceptionMessage);
        /*
         * InvalidDataException is used for consistency. Since Webhook Module
         * throws the same exception for unknown webhook event codes.
         */
        throw new InvalidDataException(__($exceptionMessage));
    }
}
