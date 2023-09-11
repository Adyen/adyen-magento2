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

        protected function setUp(): void
        {
            $this->adyenHelperMock = $this->createMock(\Adyen\Payment\Helper\Data::class);
            $this->cartRepositoryMock = $this->createMock(\Magento\Quote\Api\CartRepositoryInterface::class);
            $this->chargedCurrencyMock = $this->createMock(\Adyen\Payment\Helper\ChargedCurrency::class);
            $this->configHelperMock = $this->createMock(\Adyen\Payment\Helper\Config::class);
            $this->imageHelperMock = $this->createMock(\Magento\Catalog\Helper\Image::class);
            $this->orderMock = $this->createMock(\Magento\Sales\Model\Order::class);
            $this->cartMock = $this->createMock(\Magento\Quote\Model\Quote::class);
            $this->itemMock = $this->createMock(\Magento\Quote\Model\Quote\Item::class);
            $this->productMock = $this->createMock(\Magento\Catalog\Model\Product::class);

            $amountCurrencyMock = $this->createMock(\Adyen\Payment\Model\AdyenAmountCurrency::class);
            $amountCurrencyMock->method('getCurrencyCode')->willReturn('EUR');
            $this->chargedCurrencyMock->method('getOrderAmountCurrency')->willReturn($amountCurrencyMock);

            $itemAmountCurrencyMock = $this->createMock(\Adyen\Payment\Model\AdyenAmountCurrency::class);
            $itemAmountCurrencyMock->method('getDiscountAmount')->willReturn(0);
            $this->chargedCurrencyMock->method('getQuoteItemAmountCurrency')->willReturn($itemAmountCurrencyMock);

            $this->orderMock->method('getQuoteId')->willReturn('12345');

            $this->cartMock = $this->createMock(\Magento\Quote\Model\Quote::class);

//            $shippingAddressMock = $this->createMock(\Magento\Quote\Model\Quote\Address::class);

            $shippingAddressMock = $this->createMock(\Magento\Quote\Model\Quote\Address::class);

            $shippingAddressMock->method('__call')->willReturnMap([
                ['getShippingAmount', [], 500.0],
                ['getShippingTaxAmount', [], 0.0],
                ['getShippingDescription', [], 'Flat Rate - Fixed'],
                ['getShippingAmountCurrency', [], 'EUR'],
                // add other method maps here if needed
            ]);


//            $shippingAddressMock->method('getShippingAmount')->willReturn(500);
//            $shippingAddressMock->method('getShippingTaxAmount')->willReturn(0);
//            $shippingAddressMock->method('getShippingDescription')->willReturn('Flat Rate - Fixed');
//            $shippingAddressMock->method('getShippingAmountCurrency')->willReturn('USD');

            $shippingAmountCurrencyMock = $this->createMock(\Adyen\Payment\Model\AdyenAmountCurrency::class);
            $shippingAmountCurrencyMock->method('getAmount')->willReturn(500.0);
            $shippingAmountCurrencyMock->method('getAmountIncludingTax')->willReturn(550.0);
            $shippingAmountCurrencyMock->method('getTaxAmount')->willReturn(50.0);

            $this->chargedCurrencyMock->method('getQuoteShippingAmountCurrency')->willReturn($shippingAmountCurrencyMock);

            $this->cartMock->method('getShippingAddress')->willReturn($shippingAddressMock);

            $this->cartRepositoryMock->method('get')->willReturn($this->cartMock);


            // Stub methods on mocks as necessary...
        }

    public function testGetOpenInvoiceData()
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

            $this->productMock->method('getId')->willReturn('14');
            $this->productMock->method('getUrlModel')->willReturn(new class {
                public function getUrl() {
                    return 'https://192.168.58.20/index.php/push-it-messenger-bag.html';
                }
            });
            $this->imageHelperMock->method('init')->willReturnSelf();
            $this->imageHelperMock->method('setImageFile')->willReturnSelf();
            $this->imageHelperMock->method('getUrl')->willReturn('https://192.168.58.20/media/catalog/product/cache/3d0891988c4d57b25ce48fde378871d2/w/b/wb04-blue-0.jpg');




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
                        'productUrl' => 'https://192.168.58.20/index.php/push-it-messenger-bag.html',
                        'imageUrl' => 'https://192.168.58.20/media/catalog/product/cache/3d0891988c4d57b25ce48fde378871d2/w/b/wb04-blue-0.jpg'
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
            $result = $openInvoice->getOpenInvoiceData($this->orderMock);

            // Assert: Verify that the output matches your expectations
            $this->assertEquals($expectedResult, $result);
        }
    }