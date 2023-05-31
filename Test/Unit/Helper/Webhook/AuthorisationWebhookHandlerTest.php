<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen NV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Test\Unit\Helper;


use Adyen\Payment\Helper\AdyenOrderPayment;
use Adyen\Payment\Helper\CaseManagement;
use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Invoice;
use Adyen\Payment\Helper\Order as OrderHelper;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Helper\Webhook\AuthorisationWebhookHandler;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\AdyenAmountCurrency;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Adyen\Webhook\PaymentStates;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Api\Data\OrderPaymentInterface;

class AuthorisationWebhookHandlerTest extends AbstractAdyenTestCase
{
    public function testHandleWebhookStatePaid()
    {
        $storeId = 1;
        $mockAdyenOrderPayment = $this->createMock(AdyenOrderPayment::class);
        $orderAmountCurrency = new AdyenAmountCurrency(
            10.33,
            'EUR',
            null,
            null,
            10.33
        );
        $order = $this->createConfiguredMock(Order::class, [
            'getStoreId' => $storeId,
            'getPayment' => $this->createConfiguredMock(Order\Payment::class, [
                'setAmountAuthorized' => 10.33
            ])
        ]);
        $notification = $this->createConfiguredMock(Notification::class, [
            'getPaymentMethod' => 'visa'
        ]);
        $mockPaymentMethodsHelper = $this->createConfiguredMock(PaymentMethods::class, [
            'isAutoCapture' => true
        ]);
        $mockChargedCurrency = $this->createConfiguredMock(ChargedCurrency::class, [
            'getOrderAmountCurrency' => $orderAmountCurrency
        ]);

        // Create an instance of the class containing the handleWebhook method
        $handler = $this->createAuthorisationWebhookHandler(
            $mockAdyenOrderPayment,
            null,
            null,
            null,
            null,
            $mockChargedCurrency,
            null,
            null,
            $mockPaymentMethodsHelper
        );

        $transitionState = PaymentStates::STATE_PAID;
        $result = $handler->handleWebhook($order, $notification, $transitionState);

        // Assert that handleSuccessfulAuthorisation was called and handleFailedAuthorisation wasn't called
        $this->assertNotNull($result);
        $this->assertEquals($order->toArray(), $result->toArray());
    }

    public function testHandleWebhookStateFailed()
    {
        $storeId = 1;
        $mockAdyenOrderPayment = $this->createMock(AdyenOrderPayment::class);
        $orderAmountCurrency = new AdyenAmountCurrency(
            10.33,
            'EUR',
            null,
            null,
            10.33
        );
        $mockPayment = $this->createConfiguredMock(Order\Payment::class, [
            'getMethod' => 'adyen_cc'
        ]);
        $order = $this->createConfiguredMock(Order::class, [
            'getStoreId' => $storeId,
            'getPayment' => $mockPayment
        ]);
        $notification = $this->createConfiguredMock(Notification::class, [
            'getPaymentMethod' => 'visa'
        ]);
        $mockPaymentMethodsHelper = $this->createConfiguredMock(PaymentMethods::class, [
            'isAutoCapture' => true
        ]);
        $mockChargedCurrency = $this->createConfiguredMock(ChargedCurrency::class, [
            'getOrderAmountCurrency' => $orderAmountCurrency
        ]);

        $handler = $this->createAuthorisationWebhookHandler(
            $mockAdyenOrderPayment,
            null,
            null,
            null,
            null,
            $mockChargedCurrency,
            null,
            null,
            $mockPaymentMethodsHelper
        );

        $transitionState = PaymentStates::STATE_FAILED;
        $result = $handler->handleWebhook($order, $notification, $transitionState);

        // Assert that handleFailedAuthorisation was called and handleSuccessfulAuthorisation wasn't called
        $this->assertNotNull($result);
        $this->assertEquals($order->toArray(), $result->toArray());

    }

    public function testHandleFailedAuthorisation()
    {
        // Create the necessary mocks and objects

        // Condition 1: Previous Authorization and Captured Payments
        // Test when previous Adyen event code is "AUTHORISATION : TRUE"
        // Assert that the order is not canceled and the appropriate log entry is added

        // Test when there was a previous payment capture
        // Assert that the order is not canceled and the appropriate log entry is added

        // Condition 2: Check Order Status
        // Test when the order is already canceled
        // Assert that the order is not canceled and the appropriate log entry is added

        // Test when the order is on hold
        // Assert that the order is not canceled and the appropriate log entry is added

        // Condition 3: Check Payment Method
        // Test when the payment method is "PBL" and can be canceled
        // Assert that the order is canceled or held based on the failure counter

        // Test when the payment method is "PBL" and cannot be canceled
        // Assert that the order is not canceled and the appropriate log entry is added

        // Condition 4: Change Order State
        // Test when the order cannot be canceled and configuration allows canceling orders
        // Assert that the order state is changed from "PAYMENT_REVIEW" to "NEW"

        // Test when the order cannot be canceled and configuration does not allow canceling orders
        // Assert that the order state is not changed

        // Condition 5: Cancel or Hold the Order
        // Test when the order can be canceled or held
        // Assert that the order is canceled or held based on the configuration

        // Test when the order cannot be canceled or held
        // Assert that the order is returned without further processing
    }


    protected function createAuthorisationWebhookHandler(
        $mockAdyenOrderPayment = null,
        $mockOrderHelper = null,
        $mockCaseManagementHelper = null,
        $mockSerializer = null,
        $mockAdyenLogger = null,
        $mockChargedCurrency = null,
        $mockConfigHelper = null,
        $mockInvoiceHelper = null,
        $mockPaymentMethodsHelper = null
    ): AuthorisationWebhookHandler
    {
        if (is_null($mockAdyenOrderPayment)) {
            $mockAdyenOrderPayment = $this->createMock(AdyenOrderPayment::class);
        }

        if (is_null($mockOrderHelper)) {
            $mockOrderHelper = $this->createMock(OrderHelper::class);
        }

        if (is_null($mockCaseManagementHelper)) {
            $mockCaseManagementHelper = $this->createMock(CaseManagement::class);
        }

        if (is_null($mockSerializer)) {
            $mockSerializer = $this->createMock(SerializerInterface::class);
        }

        if (is_null($mockAdyenLogger)) {
            $mockAdyenLogger = $this->createMock(AdyenLogger::class);
        }

        if (is_null($mockChargedCurrency)) {
            $mockChargedCurrency = $this->createMock(ChargedCurrency::class);
        }

        if (is_null($mockConfigHelper)) {
            $mockConfigHelper = $this->createMock(Config::class);
        }

        if (is_null($mockInvoiceHelper)) {
            $mockInvoiceHelper = $this->createMock(Invoice::class);
        }

        if (is_null($mockPaymentMethodsHelper)) {
            $mockPaymentMethodsHelper = $this->createMock(PaymentMethods::class);
        }

        return new AuthorisationWebhookHandler(
            $mockAdyenOrderPayment,
            $mockOrderHelper,
            $mockCaseManagementHelper,
            $mockSerializer,
            $mockAdyenLogger,
            $mockChargedCurrency,
            $mockConfigHelper,
            $mockInvoiceHelper,
            $mockPaymentMethodsHelper
        );
    }
}
