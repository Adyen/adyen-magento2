<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2022 Adyen NV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Tests\Helper;

use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Logger\AdyenLogger;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\View\Asset\Source;
use Magento\Framework\View\Design\Theme\ThemeProviderInterface;
use Magento\Framework\View\DesignInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use PHPUnit\Framework\TestCase;

class PaymentMethodsTest extends TestCase
{
    /**
     * @var PaymentMethods
     */
    private $paymentMethodsHelper;

    protected function setUp(): void
    {
        $quoteRepository = $this->getMockBuilder(CartRepositoryInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $config = $this->getMockBuilder(ScopeConfigInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $adyenHelper = $this->getMockBuilder(Data::class)
            ->disableOriginalConstructor()
            ->getMock();

        $localeResolver = $this->getMockBuilder(ResolverInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $adyenLogger = $this->getMockBuilder(AdyenLogger::class)
            ->disableOriginalConstructor()
            ->getMock();

        $assetRepo = $this->getMockBuilder(Repository::class)
            ->disableOriginalConstructor()
            ->getMock();

        $request = $this->getMockBuilder(RequestInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $assetSource = $this->getMockBuilder(Source::class)
            ->disableOriginalConstructor()
            ->getMock();

        $design = $this->getMockBuilder(DesignInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $themeProvider = $this->getMockBuilder(ThemeProviderInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $chargedCurrency = $this->getMockBuilder(ChargedCurrency::class)
        ->disableOriginalConstructor()
        ->getMock();

        $this->paymentMethodsHelper =  new \Adyen\Payment\Helper\PaymentMethods(
            $quoteRepository,
            $config,
            $adyenHelper,
            $localeResolver,
            $adyenLogger,
            $assetRepo,
            $request,
            $assetSource,
            $design,
            $themeProvider,
            $chargedCurrency
        );
    }

    /**
     * @return void
     */
    public function testIsAdyenPaymentTrue()
    {
        $paymentMethodCode = 'adyen_cc';

        $this->assertEquals(
            true,
            $this->paymentMethodsHelper->isAdyenPayment($paymentMethodCode)
        );
    }

    /**
     * @param $paymentMethodCode
     * @return void
     */
    public function testIsAdyenPaymentFalse()
    {
        $paymentMethodCode = 'different_payment_method';

        $this->assertEquals(
            false,
            $this->paymentMethodsHelper->isAdyenPayment($paymentMethodCode)
        );
    }
}
