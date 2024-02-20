<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2024 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */
namespace Adyen\Payment\Test\Unit\Helper;

namespace Adyen\Payment\Test\Unit\Helper;

use Adyen\Payment\Helper\PaymentResponseHandler;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Helper\Vault;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\Quote;
use Adyen\Payment\Helper\Order as OrderHelper;
use Magento\Sales\Model\ResourceModel\Order;
use Magento\Sales\Model\OrderRepository;
use Magento\Sales\Model\Order\Status\HistoryFactory;
use Adyen\Payment\Helper\StateData;
use PHPUnit\Framework\TestCase;

class PaymentResponseHandlerTest extends TestCase
{
    private $adyenLoggerMock;
    private $vaultHelperMock;
    private $orderResourceModelMock;
    private $dataHelperMock;
    private $quoteHelperMock;
    private $orderHelperMock;
    private $orderRepositoryMock;
    private $orderHistoryFactoryMock;
    private $stateDataHelperMock;

    private $paymentResponseHandler;

    protected function setUp(): void
    {
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);
        $this->vaultHelperMock = $this->createMock(Vault::class);
        $this->orderResourceModelMock = $this->createMock(Order::class);
        $this->dataHelperMock = $this->createMock(Data::class);
        $this->quoteHelperMock = $this->createMock(Quote::class);
        $this->orderHelperMock = $this->createMock(OrderHelper::class);
        $this->orderRepositoryMock = $this->createMock(OrderRepository::class);
        $this->orderHistoryFactoryMock = $this->createMock(HistoryFactory::class);
        $this->stateDataHelperMock = $this->createMock(StateData::class);

        $this->paymentResponseHandler = new PaymentResponseHandler(
            $this->adyenLoggerMock,
            $this->vaultHelperMock,
            $this->orderResourceModelMock,
            $this->dataHelperMock,
            $this->quoteHelperMock,
            $this->orderHelperMock,
            $this->orderRepositoryMock,
            $this->orderHistoryFactoryMock,
            $this->stateDataHelperMock
        );
    }

    private static function dataSourceForFormatPaymentResponseFinalResultCodes(): array
    {
        return [
            ['resultCode' => PaymentResponseHandler::AUTHORISED],
            ['resultCode' => PaymentResponseHandler::REFUSED],
            ['resultCode' => PaymentResponseHandler::ERROR],
            ['resultCode' => PaymentResponseHandler::POS_SUCCESS]
        ];
    }

    /**
     * @param $resultCode
     * @return void
     * @dataProvider dataSourceForFormatPaymentResponseFinalResultCodes
     */
    public function testFormatPaymentResponseForFinalResultCodes($resultCode)
    {
        $expectedResult = [
            "isFinal" => true,
            "resultCode" => $resultCode
        ];

        // Execute method of the tested class
        $result = $this->paymentResponseHandler->formatPaymentResponse($resultCode);

        // Assert conditions
        $this->assertEquals($expectedResult, $result);
    }

    private static function dataSourceForFormatPaymentResponseActionRequredPayments(): array
    {
        return [
            ['resultCode' => PaymentResponseHandler::REDIRECT_SHOPPER, 'action' => ['type' => 'qrCode']],
            ['resultCode' => PaymentResponseHandler::IDENTIFY_SHOPPER, 'action' => ['type' => 'qrCode']],
            ['resultCode' => PaymentResponseHandler::CHALLENGE_SHOPPER, 'action' => ['type' => 'qrCode']],
            ['resultCode' => PaymentResponseHandler::PENDING, 'action' => ['type' => 'qrCode']],
        ];
    }

    /**
     * @param $resultCode
     * @param $action
     * @return void
     * @dataProvider dataSourceForFormatPaymentResponseActionRequredPayments
     */
    public function testFormatPaymentResponseForActionRequiredPayments($resultCode, $action)
    {
        $expectedResult = [
            "isFinal" => false,
            "resultCode" => $resultCode,
            "action" => $action
        ];

        // Execute method of the tested class
        $result = $this->paymentResponseHandler->formatPaymentResponse($resultCode, $action);

        // Assert conditions
        $this->assertEquals($expectedResult, $result);
    }

    /**
     * @return void
     */
    public function testFormatPaymentResponseForVoucherPayments()
    {
        $resultCode = PaymentResponseHandler::PRESENT_TO_SHOPPER;
        $action = ['type' => 'voucher'];

        $expectedResult = [
            "isFinal" => true,
            "resultCode" => $resultCode,
            "action" => $action
        ];

        // Execute method of the tested class
        $result = $this->paymentResponseHandler->formatPaymentResponse($resultCode, $action);

        // Assert conditions
        $this->assertEquals($expectedResult, $result);
    }

    /**
     * @return void
     */
    public function testFormatPaymentResponseForOfflinePayments()
    {
        $resultCode = PaymentResponseHandler::RECEIVED;
        $additionalData = ['action' => ['voucher']];

        $expectedResult = [
            "isFinal" => true,
            "resultCode" => $resultCode,
            "additionalData" => $additionalData
        ];

        // Execute method of the tested class
        $result = $this->paymentResponseHandler->formatPaymentResponse($resultCode, null, $additionalData);

        // Assert conditions
        $this->assertEquals($expectedResult, $result);
    }

    /**
     * @return void
     */
    public function testFormatPaymentResponseForUnknownResults()
    {
        $resultCode = 'UNRECOGNISED_RESULT_CODE';

        $expectedResult = [
            "isFinal" => true,
            "resultCode" => PaymentResponseHandler::ERROR
        ];

        // Execute method of the tested class
        $result = $this->paymentResponseHandler->formatPaymentResponse($resultCode);

        // Assert conditions
        $this->assertEquals($expectedResult, $result);
    }


}
