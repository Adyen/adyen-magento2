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
use Adyen\Payment\Helper\Invoice;
use Adyen\Payment\Helper\Order;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Helper\Vault;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Api\PaymentRequest;
use Adyen\Payment\Model\Billing\AgreementFactory;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Model\ResourceModel\Billing\Agreement;
use Adyen\Payment\Model\ResourceModel\Billing\Agreement\CollectionFactory as AgreementCollectionFactory;
use Adyen\Payment\Model\ResourceModel\Order\Payment\CollectionFactory as OrderPaymentCollectionFactory;
use Adyen\Payment\Model\Order\PaymentFactory;
use Adyen\Payment\Model\ResourceModel\Order\Payment as OrderPaymentResourceModel;
use Adyen\Webhook\Exception\InvalidDataException;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Sales\Model\Order\InvoiceFactory as MagentoInvoiceFactory;
use Magento\Vault\Api\Data\PaymentTokenFactoryInterface;
use Magento\Vault\Api\PaymentTokenManagementInterface;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;

class WebhookHandlerFactory
{
    /** @var AdyenOrderPayment */
    private static $adyenOrderPayment;

    /** @var Order */
    private static $orderHelper;

    /** @var CaseManagement */
    private static $caseManagementHelper;

    /** @var SerializerInterface */
    private static $serializer;

    /** @var AdyenLogger $adyenLogger */
    private static $adyenLogger;

    /** @var ChargedCurrency */
    private static $chargedCurrency;

    /** @var Config */
    private static $configHelper;

    /** @var Invoice */
    private static $invoiceHelper;

    /** @var PaymentFactory */
    private static $adyenOrderPaymentFactory;

    /** @var MagentoInvoiceFactory */
    private static $magentoInvoiceFactory;

    /** @var PaymentMethods */
    private static $paymentMethodsHelper;

    /** @var OrderPaymentCollectionFactory */
    private static $adyenOrderPaymentCollectionFactory;

    /** @var Vault */
    private static $vaultHelper;

    /** @var PaymentRequest */
    private static $paymentRequest;

    /** @var AgreementCollectionFactory */
    private static $agreementCollectionFactory;

    /** @var AgreementFactory */
    private static $agreementFactory;

    /** @var Agreement */
    private static $billingAgreementResourceModel;

    /** @var PaymentTokenManagementInterface */
    private static $tokenManagement;

    /** @var PaymentTokenFactoryInterface */
    private static $paymentTokenFactory;

    /** @var EncryptorInterface */
    private static $encryptor;

    /** @var PaymentTokenRepositoryInterface */
    private static $paymentTokenRepository;

    /** @var OrderPaymentResourceModel */
    private static $orderPaymentResourceModel;

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
        Vault $vaultHelper,
        PaymentRequest $paymentRequest,
        AgreementCollectionFactory $agreementCollectionFactory,
        AgreementFactory $agreementFactory,
        Agreement $billingAgreementResourceModel,
        PaymentTokenManagementInterface $paymentTokenManagement,
        PaymentTokenFactoryInterface $paymentTokenFactory,
        EncryptorInterface $encryptor,
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
        self::$vaultHelper = $vaultHelper;
        self::$paymentRequest = $paymentRequest;
        self::$agreementCollectionFactory = $agreementCollectionFactory;
        self::$agreementFactory = $agreementFactory;
        self::$billingAgreementResourceModel = $billingAgreementResourceModel;
        self::$tokenManagement = $paymentTokenManagement;
        self::$paymentTokenFactory = $paymentTokenFactory;
        self::$encryptor = $encryptor;
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
                    self::$vaultHelper,
                    self::$adyenLogger,
                    self::$paymentRequest,
                    self::$agreementCollectionFactory,
                    self::$agreementFactory,
                    self::$billingAgreementResourceModel,
                    self::$configHelper,
                    self::$tokenManagement,
                    self::$paymentTokenFactory,
                    self::$encryptor,
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
