<?php
/**
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2015 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Tests\Unit\Helper;

use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\Locale;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\ResourceModel\Billing\Agreement\CollectionFactory as BillingAgreementCollectionFactory;
use Adyen\Payment\Model\ResourceModel\Notification\CollectionFactory as NotificationCollectionFactory;
use Adyen\Payment\Tests\Unit\AbstractAdyenTestCase;
use Magento\Directory\Model\Config\Source\Country;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ProductMetadata;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Config\DataInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\View\Asset\Source;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Model\Order\Status\HistoryFactory;
use Magento\Store\Model\StoreManager;
use Magento\Tax\Model\Calculation;
use Magento\Tax\Model\Config;
use PHPUnit\Framework\TestCase;

class DataTest extends AbstractAdyenTestCase
{
    /**
     * @var Data
     */
    private $dataHelper;

    public function setUp(): void
    {
        $context = $this->createMock(Context::class);
        $encryptor = $this->createMock(EncryptorInterface::class);
        $dataStorage = $this->createMock(DataInterface::class);
        $country = $this->createMock(Country::class);
        $moduleList = $this->createMock(ModuleListInterface::class);
        $billingAgreementCollectionFactory = $this->createGeneratedMock(BillingAgreementCollectionFactory::class);
        $assetRepo = $this->createMock(Repository::class);
        $assetSource = $this->createMock(Source::class);
        $notificationFactory = $this->createGeneratedMock(NotificationCollectionFactory::class);
        $taxConfig = $this->createMock(Config::class);
        $taxCalculation = $this->createMock(Calculation::class);
        $productMetadata = $this->createMock(ProductMetadata::class);
        $adyenLogger = $this->createMock(AdyenLogger::class);
        $storeManager = $this->createMock(StoreManager::class);
        $cache = $this->createMock(CacheInterface::class);
        $localeResolver = $this->createMock(ResolverInterface::class);
        $config = $this->createMock(ScopeConfigInterface::class);
        $serializer = $this->createMock(SerializerInterface::class);
        $componentRegistrar = $this->createMock(ComponentRegistrarInterface::class);
        $localeHelper = $this->createMock(Locale::class);
        $orderManagement = $this->createMock(OrderManagementInterface::class);
        $orderStatusHistoryFactory = $this->createGeneratedMock(HistoryFactory::class);
        $adyenConfigHelper = $this->createGeneratedMock(\Adyen\Payment\Helper\Config::class);

        $this->dataHelper = new Data(
            $context,
            $encryptor,
            $dataStorage,
            $country,
            $moduleList,
            $billingAgreementCollectionFactory,
            $assetRepo,
            $assetSource,
            $notificationFactory,
            $taxConfig,
            $taxCalculation,
            $productMetadata,
            $adyenLogger,
            $storeManager,
            $cache,
            $localeResolver,
            $config,
            $serializer,
            $componentRegistrar,
            $localeHelper,
            $orderManagement,
            $orderStatusHistoryFactory,
            $adyenConfigHelper
        );
    }

    public function testFormatAmount()
    {
        $this->assertEquals('1234', $this->dataHelper->formatAmount('12.34', 'EUR'));
        $this->assertEquals('1200', $this->dataHelper->formatAmount('12.00', 'USD'));
        $this->assertEquals('12', $this->dataHelper->formatAmount('12.00', 'JPY'));
    }

    public function testisPaymentMethodOpenInvoiceMethod()
    {
        $this->assertEquals(true, $this->dataHelper->isPaymentMethodOpenInvoiceMethod('klarna'));
        $this->assertEquals(true, $this->dataHelper->isPaymentMethodOpenInvoiceMethod('klarna_account'));
        $this->assertEquals(true, $this->dataHelper->isPaymentMethodOpenInvoiceMethod('afterpay'));
        $this->assertEquals(true, $this->dataHelper->isPaymentMethodOpenInvoiceMethod('afterpay_default'));
        $this->assertEquals(true, $this->dataHelper->isPaymentMethodOpenInvoiceMethod('ratepay'));
        $this->assertEquals(false, $this->dataHelper->isPaymentMethodOpenInvoiceMethod('ideal'));
        $this->assertEquals(true, $this->dataHelper->isPaymentMethodOpenInvoiceMethod('test_klarna'));
    }

    /**
     * @param string $expectedResult
     * @param string $pspReference
     * @param string $checkoutEnvironment
     * @dataProvider checkoutEnvironmentsProvider
     *
     */
    public function testGetPspReferenceSearchUrl(string $expectedResult, string $pspReference, string $checkoutEnvironment)
    {
        $pspSearchUrl = $this->dataHelper->getPspReferenceSearchUrl($pspReference, $checkoutEnvironment);
        $this->assertEquals($expectedResult, $pspSearchUrl);
    }

    public function testGetPspReferenceWithNoAdditions()
    {
        $this->assertEquals(
            ['pspReference' => '852621234567890A', 'suffix' => ''],
            $this->dataHelper->parseTransactionId('852621234567890A')
        );
        $this->assertEquals(
            ['pspReference' => '852621234567890A', 'suffix' => '-refund'],
            $this->dataHelper->parseTransactionId('852621234567890A-refund')
        );
        $this->assertEquals(
            ['pspReference' => '852621234567890A', 'suffix' => '-capture'],
            $this->dataHelper->parseTransactionId('852621234567890A-capture')
        );
        $this->assertEquals(
            ['pspReference' => '852621234567890A', 'suffix' => '-capture-refund'],
            $this->dataHelper->parseTransactionId('852621234567890A-capture-refund')
        );
    }

    public static function checkoutEnvironmentsProvider(): array
    {
        return [
            [
                'https://ca-test.adyen.com/ca/ca/accounts/showTx.shtml?pspReference=7914073381342284',
                '7914073381342284',
                'false'
            ],
            [
                'https://ca-live.adyen.com/ca/ca/accounts/showTx.shtml?pspReference=883580976999434D',
                '883580976999434D',
                'true'
            ]
        ];
    }
}
