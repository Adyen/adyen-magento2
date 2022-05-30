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
use Adyen\Payment\Helper\Order;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Notification;
use Magento\Framework\Serialize\SerializerInterface;

class WebhookHandlerFactory
{
    /** @var WebhookService */
    private static $webhookService;

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

    public function __construct(
        WebhookService $webhookService,
        AdyenOrderPayment $adyenOrderPayment,
        Order $orderHelper,
        CaseManagement $caseManagementHelper,
        SerializerInterface $serializer,
        AdyenLogger $adyenLogger,
        ChargedCurrency $chargedCurrency,
        Config $configHelper
    )
    {
        self::$webhookService = $webhookService;
        self::$adyenOrderPayment = $adyenOrderPayment;
        self::$orderHelper = $orderHelper;
        self::$caseManagementHelper = $caseManagementHelper;
        self::$serializer = $serializer;
        self::$adyenLogger = $adyenLogger;
        self::$chargedCurrency = $chargedCurrency;
        self::$configHelper = $configHelper;
    }

    public static function create(string $eventCode): WebhookHandlerInterface
    {
        switch ($eventCode) {
            case Notification::AUTHORISATION:
                return new AuthorisationWebhookHandler(
                    self::$webhookService,
                    self::$adyenOrderPayment,
                    self::$orderHelper,
                    self::$caseManagementHelper,
                    self::$serializer,
                    self::$adyenLogger,
                    self::$chargedCurrency,
                    self::$configHelper
                );
            case Notification::CAPTURE:
                return new CaptureWebhookHandler();
        }
    }
}
