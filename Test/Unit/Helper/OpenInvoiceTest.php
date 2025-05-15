<?php declare(strict_types=1);

namespace Adyen\Payment\Test\Unit\Helper;

use Adyen\AdyenException;
use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\AdyenAmountCurrency;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Adyen\Payment\Helper\OpenInvoice;
use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\Product;
use Magento\Framework\Exception\NotFoundException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Item;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment;

class OpenInvoiceTest extends AbstractAdyenTestCase
{
    private $adyenHelperMock;
    private $cartRepositoryMock;
    private $chargedCurrencyMock;
    private $configHelperMock;
    private $imageHelperMock;
    private $orderMock;
    private $orderPaymentMock;
    private $quoteMock;
    private $quotePaymentMock;
    private $cartMock;
    private $quoteItemMock;
    private $productMock;
    private $invoiceMock;
    private $orderItemMock;
    private $invoiceItemMock;
    private $creditmemoMock;
    private $creditmemoItemMock;
    private $shippingAddressMock;
    private $shippingAmountCurrencyMock;
    private $adyenLoggerMock;

    protected function setUp(): void
    {
        # Constructor argument mocks
        $this->adyenHelperMock = $this->createPartialMock(Data::class, []);
        $this->cartRepositoryMock = $this->createMock(CartRepositoryInterface::class);
        $this->chargedCurrencyMock = $this->createMock(ChargedCurrency::class);
        $this->configHelperMock = $this->createMock(Config::class);
        $this->imageHelperMock = $this->createMock(Image::class);

        # Other mock property definitions
        $this->orderPaymentMock = $this->createMock(Payment::class);
        $this->orderMock = $this->createMock(Order::class);
        $this->orderMock->method('getPayment')->willReturn($this->orderPaymentMock);
        $this->quotePaymentMock = $this->createMock(Quote\Payment::class);
        $this->quoteMock = $this->createMock(Quote::class);
        $this->quoteMock->method('getPayment')->willReturn($this->quotePaymentMock);
        $this->quoteItemMock = $this->createGeneratedMock(
            Item::class,
            ['getName', 'getProduct', 'getSku', 'getQuote', 'getQty'],
            ['getIsVirtual', 'getTaxPercent']
        );
        $this->productMock = $this->createMock(Product::class);
        $this->invoiceMock = $this->createMock(Invoice::class);
        $this->orderItemMock = $this->createGeneratedMock(
            Order\Item::class,
            ['getIsVirtual', 'getName', 'getProduct', 'getTaxPercent', 'getSku', 'getOrder'],
            ['getQty']
        );
        $this->invoiceItemMock = $this->createMock(Invoice\Item::class);
        $this->creditmemoMock = $this->createMock(Creditmemo::class);
        $this->creditmemoItemMock = $this->createMock(Creditmemo\Item::class);
        $this->cartMock = $this->createMock(Quote::class);
        $this->shippingAddressMock = $this->createMock(Address::class);
        $this->shippingAmountCurrencyMock = $this->createMock(AdyenAmountCurrency::class);
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);
    }

    /**
     * @dataProvider isVirtualDataProvider()
     *
     * @param bool $isVirtual
     * @return void
     */
    public function testGetOpenInvoiceDataForOrder(bool $isVirtual): void
    {
        $openInvoice = new OpenInvoice(
            $this->adyenHelperMock,
            $this->cartRepositoryMock,
            $this->chargedCurrencyMock,
            $this->configHelperMock,
            $this->imageHelperMock,
            $this->adyenLoggerMock
        );

        $itemAmountCurrencyMock = $this->createMock(AdyenAmountCurrency::class);
        $itemAmountCurrencyMock->method('getAmountIncludingTax')->willReturn(100.00);
        $itemAmountCurrencyMock->method('getTaxAmount')->willReturn(0.00);
        $itemAmountCurrencyMock->method('getCurrencyCode')->willReturn('EUR');

        $this->chargedCurrencyMock->method('getQuoteItemAmountCurrency')->willReturn($itemAmountCurrencyMock);

        $this->quoteItemMock->method('getQty')->willReturn(1);
        $this->quoteItemMock->method('getProduct')->willReturn($this->productMock);
        $this->quoteItemMock->method('getName')->willReturn('Push It Messenger Bag');
        $this->quoteItemMock->method('getSku')->willReturn('24-WB04');
        $this->quoteItemMock->method('getIsVirtual')->willReturn($isVirtual);
        $this->quoteItemMock->method('getQuote')->willReturn($this->quoteMock);

        $this->quotePaymentMock->method('getMethod')->willReturn(PaymentMethods::ADYEN_PAYPAL);

        $this->cartMock->method('getShippingAddress')->willReturn($this->shippingAddressMock);
        $this->cartMock->method('getAllVisibleItems')->willReturn([$this->quoteItemMock]);

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

        $this->shippingAmountCurrencyMock->method('getAmountIncludingTax')->willReturn(550);
        $this->shippingAmountCurrencyMock->method('getTaxAmount')->willReturn(50);

        $this->chargedCurrencyMock->method('getQuoteShippingAmountCurrency')->willReturn($this->shippingAmountCurrencyMock);

        $this->shippingAddressMock->method('__call')->willReturnMap([
            ['getShippingAmount', [], 500.0],
            ['getShippingTaxAmount', [], 50.0],
            ['getShippingDescription', [], 'Flat Rate - Fixed'],
            ['getShippingAmountCurrency', [], 'EUR'],
        ]);

        $expectedResult = [
            'lineItems' => [
                [
                    'id' => '14',
                    'amountIncludingTax' => 10000,
                    'amountExcludingTax' => 10000,
                    'taxAmount' => 0,
                    'taxPercentage' => 0,
                    'description' => 'Push It Messenger Bag',
                    'sku' => '24-WB04',
                    'quantity' => 1,
                    'productUrl' => 'https://localhost.store/index.php/push-it-messenger-bag.html',
                    'imageUrl' => '',
                    'itemCategory' => $isVirtual ?
                        OpenInvoice::ITEM_CATEGORY_DIGITAL_GOODS :
                        OpenInvoice::ITEM_CATEGORY_PHYSICAL_GOODS
                ],
                [
                    'id' => 'shippingCost',
                    'amountIncludingTax' => 55000,
                    'taxAmount' => 5000,
                    'description' => 'Flat Rate - Fixed',
                    'quantity' => 1
                ]
            ]
        ];

        $result = $openInvoice->getOpenInvoiceDataForOrder($this->orderMock);

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * @return void
     */
    public function testBuildItemCategoryException(): void
    {
        $openInvoice = new OpenInvoice(
            $this->adyenHelperMock,
            $this->cartRepositoryMock,
            $this->chargedCurrencyMock,
            $this->configHelperMock,
            $this->imageHelperMock,
            $this->adyenLoggerMock
        );

        $itemAmountCurrencyMock = $this->createMock(AdyenAmountCurrency::class);
        $this->chargedCurrencyMock->method('getQuoteItemAmountCurrency')->willReturn($itemAmountCurrencyMock);
        $this->quoteItemMock->method('getQuote')->willThrowException(new AdyenException());
        $this->cartRepositoryMock->method('get')->willReturn($this->cartMock);
        $this->cartMock->method('getAllVisibleItems')->willReturn([$this->quoteItemMock]);
        $this->cartMock->method('getShippingAddress')->willReturn($this->shippingAddressMock);

        $result = $openInvoice->getOpenInvoiceDataForOrder($this->orderMock);
        $this->assertArrayNotHasKey('itemCategory', $result);
    }

    /**
     * @dataProvider isVirtualDataProvider()
     *
     * @param bool $isVirtual
     * @return void
     */
    public function testGetOpenInvoiceDataForLastInvoice(bool $isVirtual): void
    {
        $itemAmountCurrencyMock = $this->createMock(AdyenAmountCurrency::class);
        $itemAmountCurrencyMock->method('getAmountIncludingTax')->willReturn(121.00);
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
        $this->orderItemMock->method('getSku')->willReturn('24-WB04');
        $this->orderItemMock->method('getIsVirtual')->willReturn($isVirtual);
        $this->orderItemMock->method('getOrder')->willReturn($this->orderMock);

        $this->invoiceItemMock->method('getOrderItem')->willReturn($this->orderItemMock);
        $this->invoiceItemMock->method('getQty')->willReturn(1);

        $this->invoiceMock->method('getItems')->willReturn([$this->invoiceItemMock]);
        $this->invoiceMock->method('getShippingAmount')->willReturn(100);
        $this->invoiceMock->method('getOrder')->willReturn($this->orderMock);

        $this->chargedCurrencyMock->method('getInvoiceShippingAmountCurrency')->willReturn($this->shippingAmountCurrencyMock);
        $this->chargedCurrencyMock->method('getInvoiceItemAmountCurrency')->willReturn($itemAmountCurrencyMock);

        $this->orderMock->method('getShippingDescription')->willReturn('Flat Rate - Fixed');

        $this->shippingAmountCurrencyMock->method('getAmountIncludingTax')->willReturn(550);
        $this->shippingAmountCurrencyMock->method('getTaxAmount')->willReturn(50);

        $openInvoice = new OpenInvoice(
            $this->adyenHelperMock,
            $this->cartRepositoryMock,
            $this->chargedCurrencyMock,
            $this->configHelperMock,
            $this->imageHelperMock,
            $this->adyenLoggerMock
        );

        $expectedResult = [
            'lineItems' => [
                [
                    'id' => '14',
                    'amountIncludingTax' => 12100,
                    'amountExcludingTax' => 10000,
                    'taxAmount' => 2100,
                    'taxPercentage' => 2100,
                    'description' => 'Push It Messenger Bag',
                    'sku' => '24-WB04',
                    'quantity' => 1,
                    'productUrl' => 'https://localhost.store/index.php/push-it-messenger-bag.html',
                    'imageUrl' => '',
                ],
                [
                    'id' => 'shippingCost',
                    'amountIncludingTax' => 55000,
                    'taxAmount' => 5000,
                    'description' => 'Flat Rate - Fixed',
                    'quantity' => 1,
                ]
            ]
        ];

        $result = $openInvoice->getOpenInvoiceDataForInvoice($this->invoiceMock);

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * @dataProvider isVirtualDataProvider()
     *
     * @param bool $isVirtual
     * @return void
     */
    public function testGetOpenInvoiceDataForCreditMemo(bool $isVirtual): void
    {
        $this->creditmemoMock->method('getItems')->willReturn([$this->creditmemoItemMock]);
        $this->creditmemoMock->method('getShippingAmount')->willReturn(50);
        $this->creditmemoMock->method('getOrder')->willReturn($this->orderMock);

        $this->creditmemoItemMock->method('getOrderItem')->willReturn($this->orderItemMock);
        $this->creditmemoItemMock->method('getQty')->willReturn(1);

        $this->orderItemMock->method('getName')->willReturn('Push It Messenger Bag');
        $this->orderItemMock->method('getProduct')->willReturn($this->productMock);
        $this->orderItemMock->method('getTaxPercent')->willReturn(0);
        $this->orderItemMock->method('getSku')->willReturn('24-WB04');
        $this->orderItemMock->method('getIsVirtual')->willReturn($isVirtual);
        $this->orderItemMock->method('getOrder')->willReturn($this->orderMock);

        $itemAmountCurrencyMock = $this->createMock(AdyenAmountCurrency::class);
        $itemAmountCurrencyMock->method('getAmountIncludingTax')->willReturn(45);
        $itemAmountCurrencyMock->method('getTaxAmount')->willReturn(0);
        $itemAmountCurrencyMock->method('getCurrencyCode')->willReturn('EUR');

        $this->chargedCurrencyMock->method('getCreditMemoItemAmountCurrency')->willReturn($itemAmountCurrencyMock);

        $this->shippingAmountCurrencyMock->method('getAmountIncludingTax')->willReturn(550);
        $this->shippingAmountCurrencyMock->method('getTaxAmount')->willReturn(50);

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
            $this->imageHelperMock,
            $this->adyenLoggerMock
        );

        $expectedResult = [
            'lineItems' => [
                [
                    'id' => '14',
                    'amountIncludingTax' => 4500,
                    'amountExcludingTax' => 4500,
                    'taxAmount' => 0,
                    'taxPercentage' => 0,
                    'description' => 'Push It Messenger Bag',
                    'sku' => '24-WB04',
                    'quantity' => 1,
                    'productUrl' => 'https://localhost.store/index.php/push-it-messenger-bag.html',
                    'imageUrl' => ''
                ],
                [
                    'id' => 'shippingCost',
                    'amountIncludingTax' => 55000,
                    'taxAmount' => 5000,
                    'description' => 'Flat Rate - Fixed',
                    'quantity' => 1
                ]
            ]
        ];

        $result = $openInvoice->getOpenInvoiceDataForCreditMemo($this->creditmemoMock);

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * @return array
     */
    private static function isVirtualDataProvider(): array
    {
        return [
            ['isVirtual' => true],
            ['isVirtual' => false]
        ];
    }
}
