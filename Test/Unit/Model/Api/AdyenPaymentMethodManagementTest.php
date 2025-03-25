<?php


namespace Adyen\Payment\Test\Unit\Model\Api;

use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Model\Api\AdyenPaymentMethodManagement;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use PHPUnit\Framework\MockObject\MockObject;

class AdyenPaymentMethodManagementTest extends AbstractAdyenTestCase
{
    /** @var PaymentMethods|MockObject */
    private $paymentMethodsHelperMock;

    /** @var AdyenPaymentMethodManagement */
    private $adyenPaymentMethodManagement;

    protected function setUp(): void
    {
        $this->paymentMethodsHelperMock = $this->createMock(PaymentMethods::class);

        $this->adyenPaymentMethodManagement = new AdyenPaymentMethodManagement(
            $this->paymentMethodsHelperMock
        );
    }

    public function testGetPaymentMethods(): void
    {
        $cartId = '12345';
        $shopperLocale = 'en_US';
        $country = 'US';
        $channel = 'web';

        // Define the expected result
        $expectedResult = '["payment_method_1", "payment_method_2"]';

        $this->paymentMethodsHelperMock->expects($this->once())
            ->method('getPaymentMethods')
            ->with($cartId, $country, $shopperLocale, $channel)
            ->willReturn($expectedResult);

        $result = $this->adyenPaymentMethodManagement->getPaymentMethods($cartId, $shopperLocale, $country, $channel);

        // Assert that the result matches the expected result
        $this->assertEquals($expectedResult, $result);
    }
}
