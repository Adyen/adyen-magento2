<?php declare(strict_types=1);

namespace Adyen\Payment\Test\Unit\Helper;

use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Adyen\Payment\Helper\OpenInvoice;

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
    private $paymentMock;
    private $invoiceCollectionMock;
    private $invoiceMock;
    private $orderItemMock;
    private $invoiceItemMock;
    private $amountCurrencyMock;
    private $creditmemoMock;
    private $creditmemoItemMock;

    protected function setUp(): void
    {
        $this->adyenHelperMock = $this->createMock(\Adyen\Payment\Helper\Data::class);

        $this->adyenHelperMock->method('formatAmount')
            ->will($this->returnCallback(function ($amount, $currency) {
                if ($amount === null) {
                    return 0;
                }
                if ($amount == 450 && $currency == 'EUR') {
                    return 4500;
                }
                if ($amount == 500.0 && $currency == 'EUR') {
                    return 500; // Mocked formattedPriceExcludingTax value
                }
                if ($amount == 50.0 && $currency == 'EUR') {
                    return 50; // Mocked formattedTaxAmount value
                }
                return (int)number_format($amount, 0, '', ''); // For any other calls, return this default value
            }));


        $this->cartRepositoryMock = $this->createMock(\Magento\Quote\Api\CartRepositoryInterface::class);
        $this->chargedCurrencyMock = $this->createMock(\Adyen\Payment\Helper\ChargedCurrency::class);
        $this->configHelperMock = $this->createMock(\Adyen\Payment\Helper\Config::class);
        $this->imageHelperMock = $this->createMock(\Magento\Catalog\Helper\Image::class);
        $this->orderMock = $this->createMock(\Magento\Sales\Model\Order::class);
        $this->cartMock = $this->createMock(\Magento\Quote\Model\Quote::class);
        $this->itemMock = $this->createMock(\Magento\Quote\Model\Quote\Item::class);
        $this->productMock = $this->createMock(\Magento\Catalog\Model\Product::class);
        $this->paymentMock = $this->createMock(\Magento\Sales\Model\Order\Payment::class);
        $this->invoiceCollectionMock = $this->createMock(\Magento\Sales\Model\ResourceModel\Order\Invoice\Collection::class);
        $this->invoiceMock = $this->createMock(\Magento\Sales\Model\Order\Invoice::class);
        $this->orderItemMock = $this->createMock(\Magento\Sales\Model\Order\Item::class);
        $this->invoiceItemMock = $this->createMock(\Magento\Sales\Model\Order\Invoice\Item::class);
        $this->creditmemoMock = $this->createMock(\Magento\Sales\Model\Order\Creditmemo::class);
        $this->creditmemoItemMock = $this->createMock(\Magento\Sales\Model\Order\Creditmemo\Item::class);

        $this->amountCurrencyMock = $this->createMock(\Adyen\Payment\Model\AdyenAmountCurrency::class);
        $this->amountCurrencyMock->method('getCurrencyCode')->willReturn('EUR');
        $this->chargedCurrencyMock->method('getOrderAmountCurrency')->willReturn($this->amountCurrencyMock);

        $itemAmountCurrencyMock = $this->createMock(\Adyen\Payment\Model\AdyenAmountCurrency::class);
        $itemAmountCurrencyMock->method('getAmount')->willReturn(4500);
        $itemAmountCurrencyMock->method('getAmountIncludingTax')->willReturn(4500);
        $itemAmountCurrencyMock->method('getDiscountAmount')->willReturn(0);
        $this->chargedCurrencyMock->method('getQuoteItemAmountCurrency')->willReturn($itemAmountCurrencyMock);

        $this->orderMock->method('getQuoteId')->willReturn('12345');

        $this->cartMock = $this->createMock(\Magento\Quote\Model\Quote::class);

        $shippingAddressMock = $this->createMock(\Magento\Quote\Model\Quote\Address::class);

        $shippingAddressMock->method('__call')->willReturnMap([
            ['getShippingAmount', [], 500.0],
            ['getShippingTaxAmount', [], 0.0],
            ['getShippingDescription', [], 'Flat Rate - Fixed'],
            ['getShippingAmountCurrency', [], 'EUR'],
            ['getShippingAmountCurrency', [], 'EUR'],
        ]);

        $shippingAmountCurrencyMock = $this->createMock(\Adyen\Payment\Model\AdyenAmountCurrency::class);
        $shippingAmountCurrencyMock->method('getAmount')->willReturn(500);
        $shippingAmountCurrencyMock->method('getAmountIncludingTax')->willReturn(500);
        $shippingAmountCurrencyMock->method('getTaxAmount')->willReturn(0);
        $this->chargedCurrencyMock->method('getQuoteShippingAmountCurrency')->willReturn($shippingAmountCurrencyMock);
        $this->chargedCurrencyMock->method('getInvoiceShippingAmountCurrency')->willReturn($shippingAmountCurrencyMock);

        $this->cartMock->method('getShippingAddress')->willReturn($shippingAddressMock);

        $this->cartRepositoryMock->method('get')->willReturn($this->cartMock);

    }

    public function testGetOpenInvoiceDataFomOrder(): void
    {
        // Arrange: Set up the object with the mocks
        $openInvoice = new OpenInvoice(
            $this->adyenHelperMock,
            $this->cartRepositoryMock,
            $this->chargedCurrencyMock,
            $this->configHelperMock,
            $this->imageHelperMock
        );

        // Stub methods to return expected values
        $this->cartMock->method('getAllVisibleItems')->willReturn([$this->itemMock]);
        $this->itemMock->method('getQty')->willReturn(1);
        $this->itemMock->method('getProduct')->willReturn($this->productMock);
        $this->itemMock->method('getName')->willReturn('Push It Messenger Bag');
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
                    'amountExcludingTax' => 500,
                    'amountIncludingTax' => 500,
                    'taxAmount' => 0,
                    'description' => 'Flat Rate - Fixed',
                    'quantity' => 1,
                    'taxPercentage' => 0
                ],
            ],
        ];

        // Act: Call the method with the mocked parameters
        $result = $openInvoice->getOpenInvoiceDataForOrder($this->orderMock);

        // Assert: Verify that the output matches your expectations
        $this->assertEquals($expectedResult, $result);
    }

    public function testGetOpenInvoiceDataForLastInvoice(): void
    {
        $this->paymentMock->method('getOrder')->willReturn($this->orderMock);
        $this->orderMock->method('getInvoiceCollection')->willReturn($this->invoiceCollectionMock);
        $this->orderMock->method('getShippingDescription')->willReturn('Flat Rate - Fixed');
        $this->invoiceCollectionMock->method('getLastItem')->willReturn($this->invoiceMock);
        $this->invoiceMock->method('getItems')->willReturn([$this->invoiceItemMock]);
        $this->invoiceItemMock->method('getOrderItem')->willReturn($this->orderItemMock);
        $this->invoiceItemMock->method('getQty')->willReturn(1);
        $this->invoiceMock->method('getShippingAmount')->willReturn(100);
        $this->orderItemMock->method('getProduct')->willReturn($this->productMock);
        $this->productMock->method('getId')->willReturn('14');
        $itemAmountCurrencyMock = $this->createMock(\Adyen\Payment\Model\AdyenAmountCurrency::class);
        $this->chargedCurrencyMock->method('getInvoiceItemAmountCurrency')->willReturn($itemAmountCurrencyMock);
        $itemAmountCurrencyMock->method('getAmount')->willReturn(4500);
        $itemAmountCurrencyMock->method('getAmountIncludingTax')->willReturn(4500);
        $itemAmountCurrencyMock->method('getDiscountAmount')->willReturn(0);
        $this->orderItemMock->method('getName')->willReturn('Push It Messenger Bag');
        $this->productMock->method('getUrlModel')->willReturn(new class {
            public function getUrl()
            {
                return 'https://localhost.store/index.php/push-it-messenger-bag.html';
            }
        });

        // Arrange: Set up the object with the mocks
        $openInvoice = new OpenInvoice($this->adyenHelperMock, $this->cartRepositoryMock, $this->chargedCurrencyMock, $this->configHelperMock, $this->imageHelperMock);

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
                    'amountExcludingTax' => 500,
                    'amountIncludingTax' => 500,
                    'taxAmount' => 0,
                    'description' => 'Flat Rate - Fixed',
                    'quantity' => 1,
                    'taxPercentage' => 0
                ],
            ]
        ];

        $result = $openInvoice->getOpenInvoiceDataForLastInvoice($this->paymentMock);
        $this->assertEquals($expectedResult, $result);
    }

    public function testGetOpenInvoiceDataForCreditMemo(): void
    {
        $this->paymentMock->method('getOrder')->willReturn($this->orderMock);
        $this->paymentMock->method('getCreditMemo')->willReturn($this->creditmemoMock);
        $this->creditmemoMock->method('getItems')->willReturn([$this->creditmemoItemMock]);
        $this->creditmemoItemMock->method('getOrderItem')->willReturn($this->orderItemMock);
        $this->creditmemoItemMock->method('getQty')->willReturn(1);
        $this->orderItemMock->method('getProduct')->willReturn($this->productMock);
        $itemAmountCurrencyMock = $this->createMock(\Adyen\Payment\Model\AdyenAmountCurrency::class);

        $itemAmountCurrencyMock->method('getAmount')->willReturn(4500);
        $itemAmountCurrencyMock->method('getAmountIncludingTax')->willReturn(4500);
        $itemAmountCurrencyMock->method('getDiscountAmount')->willReturn(10);
        $this->chargedCurrencyMock->method('getCreditMemoItemAmountCurrency')->willReturn($itemAmountCurrencyMock);
        $this->chargedCurrencyMock->method('getCreditMemoShippingAmountCurrency')->willReturn($itemAmountCurrencyMock);
        $shippingAddressMock = $this->createMock(\Magento\Quote\Model\Quote\Address::class);
        $shippingAddressMock->method('__call')->willReturnMap([['getShippingDiscountAmount', [], 10.0],]);
        $this->orderMock->method('getShippingAddress')->willReturn($shippingAddressMock);
        $this->orderMock->method('getShippingDescription')->willReturn('Flat Rate - Fixed');
        $this->productMock->method('getId')->willReturn('14');
        $this->orderItemMock->method('getName')->willReturn('Push It Messenger Bag');
        $this->productMock->method('getUrlModel')->willReturn(new class {
            public function getUrl()
            {
                return 'https://localhost.store/index.php/push-it-messenger-bag.html';
            }
        });
        // Arrange: Set up the object with the mocks
        $openInvoice = new OpenInvoice($this->adyenHelperMock, $this->cartRepositoryMock, $this->chargedCurrencyMock, $this->configHelperMock, $this->imageHelperMock);

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
                    'id' => 'Discount',
                    'amountExcludingTax' => -20,
                    'amountIncludingTax' => -20,
                    'taxAmount' => 0,
                    'description' =>  __('Discount'),
                    'quantity' => 1,
                    'taxPercentage' => 0
                ],
                [
                    'id' => 'shippingCost',
                    'amountExcludingTax' => 4500,
                    'amountIncludingTax' => 4500,
                    'taxAmount' => 0,
                    'description' => 'Flat Rate - Fixed',
                    'quantity' => 1,
                    'taxPercentage' => 0
                ],
            ]
        ];

        $result = $openInvoice->getOpenInvoiceDataForCreditMemo($this->paymentMock);
        $this->assertEquals($expectedResult, $result);
    }
}
