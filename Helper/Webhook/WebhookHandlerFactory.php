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
use Adyen\Payment\Helper\Order;
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

    public function __construct(
        WebhookService $webhookService,
        AdyenOrderPayment $adyenOrderPayment,
        Order $orderHelper,
        CaseManagement $caseManagementHelper,
        SerializerInterface $serializer
    )
    {
        self::$webhookService = $webhookService;
        self::$adyenOrderPayment = $adyenOrderPayment;
        self::$orderHelper = $orderHelper;
        self::$caseManagementHelper = $caseManagementHelper;
        self::$serializer = $serializer;
    }

    public static function create(string $eventCode): WebhookHandlerInterface
    {
        return new AuthorisationWebhookHandler(
            self::$webhookService,
            self::$adyenOrderPayment,
            self::$orderHelper,
            self::$caseManagementHelper,
            self::$serializer
        );
    }
}
