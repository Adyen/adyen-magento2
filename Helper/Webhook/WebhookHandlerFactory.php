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
use Adyen\Payment\Helper\Order;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Model\ResourceModel\Order\Payment\CollectionFactory as OrderPaymentCollectionFactory;
use Adyen\Payment\Model\Order\PaymentFactory;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Sales\Model\Order\InvoiceFactory as MagentoInvoiceFactory;

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

    /** @var Data */
    private static $adyenDataHelper;

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
        Data $adyenDataHelper
    )
    {
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
        self::$adyenDataHelper = $adyenDataHelper;
    }

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
                    self::$orderHelper
                );
            case Notification::REFUND:
                return new RefundWebhookHandler(
                    self::$orderHelper,
                    self::$configHelper,
                    self::$adyenLogger,
                    self::$adyenOrderPaymentCollectionFactory,
                    self::$adyenDataHelper,
                    self::$adyenOrderPayment
                );
            case Notification::REFUND_FAILED:
                return new RefundFailedWebhookHandler(
                    self::$orderHelper
                );
        }
    }
}
