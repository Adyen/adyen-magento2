<?php
declare(strict_types=1);

namespace Adyen\Payment\Test\Unit\Helper;

use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\PlatformInfo;
use Adyen\Payment\Helper\PointOfSale;
use Adyen\Payment\Helper\Vault;
use Adyen\Payment\Model\ApplicationInfo;
use Adyen\Payment\Model\Ui\AdyenPosCloudConfigProvider;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;

class PointOfSaleTest extends AbstractAdyenTestCase
{
    private $dataHelper;
    private $productMetadata;
    private $vaultHelper;
    private $platformInfo;
    private $pointOfSale;

    protected function setUp(): void
    {
        $this->dataHelper = $this->createMock(Data::class);
        $this->productMetadata = $this->createMock(ProductMetadataInterface::class);
        $this->vaultHelper = $this->createMock(Vault::class);
        $this->platformInfo = $this->createMock(PlatformInfo::class);

        $this->pointOfSale = new PointOfSale(
            $this->dataHelper,
            $this->productMetadata,
            $this->vaultHelper,
            $this->platformInfo
        );
    }

    public function testAddSaleToAcquirerDataWithCustomer()
    {
        $order = $this->createMock(Order::class);
        $order->method('getCustomerId')->willReturn('123');
        $order->method('getStoreId')->willReturn(1);
        $order->method('getCustomerEmail')->willReturn('test@example.com');

        $this->vaultHelper->method('getPaymentMethodRecurringActive')
            ->with(AdyenPosCloudConfigProvider::CODE, 1)
            ->willReturn(true);

        $this->vaultHelper->method('getPaymentMethodRecurringProcessingModel')
            ->with(AdyenPosCloudConfigProvider::CODE, 1)
            ->willReturn('CardOnFile');

        $this->dataHelper->method('padShopperReference')->with('123')->willReturn('ref_123');
        $this->platformInfo->method('getModuleVersion')->willReturn('1.0.0');
        $this->platformInfo->method('getModuleName')->willReturn('Adyen_Payment');
        $this->productMetadata->method('getVersion')->willReturn('2.4.6');
        $this->productMetadata->method('getName')->willReturn('Magento');

        $request = ['SaleToPOIRequest' => ['PaymentRequest' => ['SaleData' => []]]];

        $result = $this->pointOfSale->addSaleToAcquirerData($request, $order);

        $this->assertArrayHasKey('SaleToPOIRequest', $result);
        $this->assertArrayHasKey('SaleToAcquirerData', $result['SaleToPOIRequest']['PaymentRequest']['SaleData']);

        $decoded = json_decode(
            base64_decode($result['SaleToPOIRequest']['PaymentRequest']['SaleData']['SaleToAcquirerData']),
            true
        );

        $this->assertEquals('test@example.com', $decoded['shopperEmail']);
        $this->assertEquals('ref_123', $decoded['shopperReference']);
        $this->assertEquals('CardOnFile', $decoded['recurringProcessingModel']);
        $this->assertEquals('1.0.0', $decoded[ApplicationInfo::APPLICATION_INFO][ApplicationInfo::MERCHANT_APPLICATION][ApplicationInfo::VERSION]);
    }

    public function testGetFormattedInstallments()
    {
        $installments = [
            100 => [2, 4],  // If amount is >= 100, allow 2 and 4 installments
            200 => [5]      // If amount is >= 200, also allow 5
        ];
        $amount = 250.00;
        $currencyCode = 'EUR';
        $precision = 2;

        $result = $this->pointOfSale->getFormattedInstallments($installments, $amount, $currencyCode, $precision);

        $expected = [
            2 => '2 x 125.00 EUR',
            4 => '4 x 62.50 EUR',
            5 => '5 x 50.00 EUR',
        ];

        $this->assertEquals($expected, $result);
    }
}
