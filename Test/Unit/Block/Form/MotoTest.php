<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2025 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Test\Block\Form;

use Adyen\Payment\Block\Form\Moto;
use Adyen\Payment\Helper\Config as AdyenConfig;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\Installments;
use Adyen\Payment\Logger\AdyenLogger;
use Magento\Backend\Model\Session\Quote;
use Magento\Checkout\Model\Session;
use Magento\Framework\View\Element\Template\Context;
use Magento\Payment\Model\Config as PaymentConfig;
use Magento\Quote\Model\Quote as QuoteModel;
use Magento\Sales\Api\Data\OrderAddressInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MotoTest extends TestCase
{
    protected ?Moto $motoFormBlock;
    protected Context|MockObject $contextMock;
    protected PaymentConfig|MockObject $paymentConfigMock;
    protected Data|MockObject $adyenHelperMock;
    protected Session|MockObject $checkoutSessionMock;
    protected Installments|MockObject $installmentsHelperMock;
    protected AdyenLogger|MockObject $adyenLoggerMock;
    protected AdyenConfig|MockObject $adyenConfigMock;
    protected Quote|MockObject $backendSessionMock;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->contextMock = $this->createMock(Context::class);
        $this->paymentConfigMock = $this->createMock(PaymentConfig::class);
        $this->adyenHelperMock = $this->createMock(Data::class);
        $this->checkoutSessionMock = $this->createMock(Session::class);
        $this->installmentsHelperMock = $this->createMock(Installments::class);
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);
        $this->adyenConfigMock = $this->createMock(AdyenConfig::class);
        $this->backendSessionMock = $this->createMock(Quote::class);

        $this->motoFormBlock = new Moto(
            $this->contextMock,
            $this->paymentConfigMock,
            $this->adyenHelperMock,
            $this->checkoutSessionMock,
            $this->installmentsHelperMock,
            $this->adyenLoggerMock,
            $this->adyenConfigMock,
            $this->backendSessionMock
        );
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $this->motoFormBlock = null;
    }

    /**
     * @return void
     */
    public function testGetCountryId()
    {
        $countryId = "NL";

        $billingAddressMock = $this->createMock(OrderAddressInterface::class);
        $billingAddressMock->method('getCountryId')->willReturn($countryId);

        $quoteModelMock = $this->createMock(QuoteModel::class);
        $quoteModelMock->method('getBillingAddress')->willReturn($billingAddressMock);

        $this->backendSessionMock->method('getQuote')->willReturn($quoteModelMock);

        $this->assertEquals(
            $countryId,
            $this->motoFormBlock->getCountryId()
        );
    }
}
