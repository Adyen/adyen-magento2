<?php declare(strict_types=1);

namespace Adyen\Payment\Test\Unit\Helper;

use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Model\AdyenAmountCurrency;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Adyen\Payment\Helper\OpenInvoice;
use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\Product;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Item;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Invoice;

class OpenInvoiceTest extends AbstractAdyenTestCase
{
    private $adyenHelperMock;
    private $cartRepositoryMock;
    private $chargedCurrencyMock;
    private $configHelperMock;
    private $imageHelperMock;
    private $orderMock;
    private $cartMock;
    private $itemMock;
    private $productMock;
    private $invoiceMock;
    private $orderItemMock;
    private $invoiceItemMock;
    private $creditmemoMock;
    private $creditmemoItemMock;
    private $shippingAddressMock;
    private $shippingAmountCurrencyMock;

    protected function setUp(): void
    {
        # Constructor argument mocks
        $this->adyenHelperMock = $this->createPartialMock(Data::class, []);
        $this->cartRepositoryMock = $this->createMock(CartRepositoryInterface::class);
        $this->chargedCurrencyMock = $this->createMock(ChargedCurrency::class);
        $this->configHelperMock = $this->createMock(Config::class);
        $this->imageHelperMock = $this->createMock(Image::class);

        # Other mock property definitions
        $this->orderMock = $this->createMock(Order::class);
        $this->itemMock = $this->createMock(Item::class);
        $this->productMock = $this->createMock(Product::class);
        $this->invoiceMock = $this->createMock(Invoice::class);
        $this->orderItemMock = $this->createMock(Order\Item::class);
        $this->invoiceItemMock = $this->createMock(Invoice\Item::class);
        $this->creditmemoMock = $this->createMock(Creditmemo::class);
        $this->creditmemoItemMock = $this->createMock(Creditmemo\Item::class);
        $this->cartMock = $this->createMock(Quote::class);
        $this->shippingAddressMock = $this->createMock(Address::class);
        $this->shippingAmountCurrencyMock = $this->createMock(AdyenAmountCurrency::class);
    }

    public function testGetOpenInvoiceDataFomOrder(): void
    {
        $openInvoice = new OpenInvoice(
            $this->adyenHelperMock,
            $this->cartRepositoryMock,
            $this->chargedCurrencyMock,
            $this->configHelperMock,
            $this->imageHelperMock
        );

        $itemAmountCurrencyMock = $this->createMock(AdyenAmountCurrency::class);
        $itemAmountCurrencyMock->method('getAmountWithDiscount')->willReturn(100.00);
        $itemAmountCurrencyMock->method('getAmountIncludingTaxWithDiscount')->willReturn(100.00);
        $itemAmountCurrencyMock->method('getTaxAmount')->willReturn(0.00);
        $itemAmountCurrencyMock->method('getCurrencyCode')->willReturn('EUR');

        $this->chargedCurrencyMock->method('getQuoteItemAmountCurrency')->willReturn($itemAmountCurrencyMock);

        $this->itemMock->method('getQty')->willReturn(1);
        $this->itemMock->method('getProduct')->willReturn($this->productMock);
        $this->itemMock->method('getName')->willReturn('Push It Messenger Bag');

        $this->cartMock->method('getShippingAddress')->willReturn($this->shippingAddressMock);
        $this->cartMock->method('getAllVisibleItems')->willReturn([$this->itemMock]);

        $this->cartRepositoryMock->method('get')->willReturn($this->cartMock);

        $this->productMock->method('getId')->willReturn('14');

        $this->productMock->method('getUrlModel')->willReturn(new class {
            public function getUrl()
            {
                return 'https://localhost.store/index.php/push-it-messenger-bag.html';
            }
        });

        $this->orderMock->method('getShippingDescription')->willReturn('Flat Rate - Fixed');

        $this->imageHelperMock->method('init')->willReturnSelf();
        $this->imageHelperMock->method('setImageFile')->willReturnSelf();
        $this->imageHelperMock->method('getUrl')->willReturn('https://localhost.store/media/catalog/product/cache/3d0891988c4d57b25ce48fde378871d2/w/b/wb04-blue-0.jpg');

        $this->shippingAmountCurrencyMock->method('getAmountWithDiscount')->willReturn(500);
        $this->shippingAmountCurrencyMock->method('getAmountIncludingTaxWithDiscount')->willReturn(550);
        $this->shippingAmountCurrencyMock->method('getTaxAmount')->willReturn(50);
        $this->shippingAmountCurrencyMock->method('getCalculatedTaxPercentage')->willReturn(10);

        $this->chargedCurrencyMock->method('getQuoteShippingAmountCurrency')->willReturn($this->shippingAmountCurrencyMock);

        $this->shippingAddressMock->method('__call')->willReturnMap([
            ['getShippingAmount', [], 500.0],
            ['getShippingTaxAmount', [], 0.0],
            ['getShippingDescription', [], 'Flat Rate - Fixed'],
            ['getShippingAmountCurrency', [], 'EUR'],
            ['getShippingAmountCurrency', [], 'EUR'],
        ]);

        $expectedResult = [
            'lineItems' => [
                [
                    'id' => '14',
                    'amountExcludingTax' => 10000,
                    'amountIncludingTax' => 10000,
                    'taxAmount' => 0,
                    'description' => 'Push It Messenger Bag',
                    'quantity' => 1,
                    'taxPercentage' => 0,
                    'productUrl' => 'https://localhost.store/index.php/push-it-messenger-bag.html',
                    'imageUrl' => '',
                ],
                [
                    'id' => 'shippingCost',
                    'amountExcludingTax' => 50000,
                    'amountIncludingTax' => 55000,
                    'taxAmount' => 5000,
                    'description' => 'Flat Rate - Fixed',
                    'quantity' => 1,
                    'taxPercentage' => 1000
                ]
            ]
        ];

        // Act: Call the method with the mocked parameters
        $result = $openInvoice->getOpenInvoiceDataForOrder($this->orderMock);

        // Assert: Verify that the output matches your expectations
        $this->assertEquals($expectedResult, $result);
    }

    public function testGetOpenInvoiceDataForLastInvoice(): void
    {
        $itemAmountCurrencyMock = $this->createMock(AdyenAmountCurrency::class);
        $itemAmountCurrencyMock->method('getAmountWithDiscount')->willReturn(100.00);
        $itemAmountCurrencyMock->method('getAmountIncludingTaxWithDiscount')->willReturn(121.00);
        $itemAmountCurrencyMock->method('getTaxAmount')->willReturn(21.00);

        $this->productMock->method('getId')->willReturn('14');
        $this->productMock->method('getUrlModel')->willReturn(new class {
            public function getUrl()
            {
                return 'https://localhost.store/index.php/push-it-messenger-bag.html';
            }
        });

        $this->orderItemMock->method('getProduct')->willReturn($this->productMock);
        $this->orderItemMock->method('getName')->willReturn('Push It Messenger Bag');
        $this->orderItemMock->method('getTaxPercent')->willReturn(21);

        $this->invoiceItemMock->method('getOrderItem')->willReturn($this->orderItemMock);
        $this->invoiceItemMock->method('getQty')->willReturn(1);

        $this->invoiceMock->method('getItems')->willReturn([$this->invoiceItemMock]);
        $this->invoiceMock->method('getShippingAmount')->willReturn(100);
        $this->invoiceMock->method('getOrder')->willReturn($this->orderMock);

        $this->chargedCurrencyMock->method('getInvoiceShippingAmountCurrency')->willReturn($this->shippingAmountCurrencyMock);
        $this->chargedCurrencyMock->method('getInvoiceItemAmountCurrency')->willReturn($itemAmountCurrencyMock);

        $this->orderMock->method('getShippingDescription')->willReturn('Flat Rate - Fixed');

        $this->shippingAmountCurrencyMock->method('getAmountWithDiscount')->willReturn(500);
        $this->shippingAmountCurrencyMock->method('getAmountIncludingTaxWithDiscount')->willReturn(550);
        $this->shippingAmountCurrencyMock->method('getTaxAmount')->willReturn(50);
        $this->shippingAmountCurrencyMock->method('getCalculatedTaxPercentage')->willReturn(10);

        $openInvoice = new OpenInvoice(
            $this->adyenHelperMock,
            $this->cartRepositoryMock,
            $this->chargedCurrencyMock,
            $this->configHelperMock,
            $this->imageHelperMock
        );

        $expectedResult = [
            'lineItems' => [
                [
                    'id' => '14',
                    'amountExcludingTax' => 10000,
                    'amountIncludingTax' => 12100,
                    'taxAmount' => 2100,
                    'description' => 'Push It Messenger Bag',
                    'quantity' => 1,
                    'taxPercentage' => 2100,
                    'productUrl' => 'https://localhost.store/index.php/push-it-messenger-bag.html',
                    'imageUrl' => '',
                ],
                [
                    'id' => 'shippingCost',
                    'amountExcludingTax' => 50000,
                    'amountIncludingTax' => 55000,
                    'taxAmount' => 5000,
                    'description' => 'Flat Rate - Fixed',
                    'quantity' => 1,
                    'taxPercentage' => 1000
                ]
            ]
        ];

        $result = $openInvoice->getOpenInvoiceDataForInvoice($this->invoiceMock);

        $this->assertEquals($expectedResult, $result);
    }

    public function testGetOpenInvoiceDataForCreditMemo(): void
    {
        $this->creditmemoMock->method('getItems')->willReturn([$this->creditmemoItemMock]);
        $this->creditmemoMock->method('getShippingAmount')->willReturn(50);
        $this->creditmemoMock->method('getOrder')->willReturn($this->orderMock);

        $this->creditmemoItemMock->method('getOrderItem')->willReturn($this->orderItemMock);
        $this->creditmemoItemMock->method('getQty')->willReturn(1);

        $this->orderItemMock->method('getName')->willReturn('Push It Messenger Bag');
        $this->orderItemMock->method('getProduct')->willReturn($this->productMock);
        $this->orderItemMock->method('getTaxPercent')->willReturn(0);

        $itemAmountCurrencyMock = $this->createMock(AdyenAmountCurrency::class);
        $itemAmountCurrencyMock->method('getAmountWithDiscount')->willReturn(45);
        $itemAmountCurrencyMock->method('getAmountIncludingTaxWithDiscount')->willReturn(45);
        $itemAmountCurrencyMock->method('getTaxAmount')->willReturn(0);
        $itemAmountCurrencyMock->method('getCurrencyCode')->willReturn('EUR');

        $this->chargedCurrencyMock->method('getCreditMemoItemAmountCurrency')->willReturn($itemAmountCurrencyMock);

        $this->shippingAmountCurrencyMock->method('getAmountWithDiscount')->willReturn(500);
        $this->shippingAmountCurrencyMock->method('getAmountIncludingTaxWithDiscount')->willReturn(550);
        $this->shippingAmountCurrencyMock->method('getTaxAmount')->willReturn(50);
        $this->shippingAmountCurrencyMock->method('getCalculatedTaxPercentage')->willReturn(10);

        $this->chargedCurrencyMock->method('getCreditMemoShippingAmountCurrency')->willReturn($this->shippingAmountCurrencyMock);

        $this->orderMock->method('getShippingDescription')->willReturn('Flat Rate - Fixed');

        $this->productMock->method('getId')->willReturn('14');
        $this->productMock->method('getUrlModel')->willReturn(new class {
            public function getUrl()
            {
                return 'https://localhost.store/index.php/push-it-messenger-bag.html';
            }
        });

        $openInvoice = new OpenInvoice(
            $this->adyenHelperMock,
            $this->cartRepositoryMock,
            $this->chargedCurrencyMock,
            $this->configHelperMock,
            $this->imageHelperMock
        );

        $expectedResult = [
            'lineItems' => [
                [
                    'id' => '14',
                    'amountExcludingTax' => 4500,
                    'amountIncludingTax' => 4500,
                    'taxAmount' => 0,
                    'description' => 'Push It Messenger Bag',
                    'quantity' => 1,
                    'taxPercentage' => 0,
                    'productUrl' => 'https://localhost.store/index.php/push-it-messenger-bag.html',
                    'imageUrl' => ''
                ],
                [
                    'id' => 'shippingCost',
                    'amountExcludingTax' => 50000,
                    'amountIncludingTax' => 55000,
                    'taxAmount' => 5000,
                    'description' => 'Flat Rate - Fixed',
                    'quantity' => 1,
                    'taxPercentage' => 1000
                ]
            ]
        ];

        $result = $openInvoice->getOpenInvoiceDataForCreditMemo($this->creditmemoMock);

        $this->assertEquals($expectedResult, $result);
    }
}
