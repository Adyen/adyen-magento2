<?php
/**
 *                       ######
 *                       ######
 * ############    ####( ######  #####. ######  ############   ############
 * #############  #####( ######  #####. ######  #############  #############
 *        ######  #####( ######  #####. ######  #####  ######  #####  ######
 * ###### ######  #####( ######  #####. ######  #####  #####   #####  ######
 * ###### ######  #####( ######  #####. ######  #####          #####  ######
 * #############  #############  #############  #############  #####  ######
 *  ############   ############  #############   ############  #####  ######
 *                                      ######
 *                               #############
 *                               ############
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2021 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Tests\Helper;

use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Model\AdyenAmountCurrency;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\Data\CreditmemoItemInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo\Item;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ChargedCurrencyTest extends TestCase
{

    const AMOUNT_CURRENCY = [
        'base' =>
            [
                'amount' => 123.45,
                'currencyCode' => 'EUR',
                'discountAmount' => 67.89,
                'taxAmount' => 12.34,
                'amountDue' => 56.78
            ],
        'display' =>
            [
                'amount' => 654.32,
                'currencyCode' => 'USD',
                'discountAmount' => 98.76,
                'taxAmount' => 54.32,
                'amountDue' => 10.98
            ]
    ];

    /**
     * @var ChargedCurrency
     */
    private $chargedCurrencyHelper;

    /**
     * @var Order
     */
    private $order;

    /**
     * @var Config
     */
    private $configHelper;

    /**
     * @var Quote
     */
    private $quote;

    /**
     * @var Quote\Item
     */
    private $quoteItem;

    /**
     * @var Order\Invoice\Item
     */
    private $invoiceItem;

    /**
     * @var Order\Invoice
     */
    private $invoice;

    /**
     * @var CreditmemoItemInterface
     */
    private $creditMemoItem;

    protected function setUp(): void
    {
        $this->order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'getAdyenChargedCurrency',
                'getBaseGrandTotal',
                'getGlobalCurrencyCode',
                'getBaseDiscountAmount',
                'getBaseTaxAmount',
                'getBaseTotalDue',
                'getGrandTotal',
                'getOrderCurrencyCode',
                'getDiscountAmount',
                'getTaxAmount',
                'getTotalDue',
                'getChargedCurrency'
            ])
            ->getMock();
        $this->mockMethods($this->order,
            [
                'getBaseGrandTotal' => self::AMOUNT_CURRENCY['base']['amount'],
                'getGlobalCurrencyCode' => self::AMOUNT_CURRENCY['base']['currencyCode'],
                'getBaseDiscountAmount' => self::AMOUNT_CURRENCY['base']['discountAmount'],
                'getBaseTaxAmount' => self::AMOUNT_CURRENCY['base']['taxAmount'],
                'getBaseTotalDue' => self::AMOUNT_CURRENCY['base']['amountDue'],
                'getGrandTotal' => self::AMOUNT_CURRENCY['display']['amount'],
                'getOrderCurrencyCode' => self::AMOUNT_CURRENCY['display']['currencyCode'],
                'getDiscountAmount' => self::AMOUNT_CURRENCY['display']['discountAmount'],
                'getTaxAmount' => self::AMOUNT_CURRENCY['display']['taxAmount'],
                'getTotalDue' => self::AMOUNT_CURRENCY['display']['amountDue']
            ]
        );

        $shippingAddress = $this->getMockBuilder(Quote\Address::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'getBaseShippingAmount',
                'getBaseShippingDiscountAmount',
                'getBaseShippingTaxAmount',
                'getShippingAmount',
                'getShippingDiscountAmount',
                'getShippingTaxAmount'
            ])
            ->getMock();
        $this->mockMethods($shippingAddress,
            [
                'getBaseShippingAmount' => self::AMOUNT_CURRENCY['base']['amount'],
                'getBaseShippingDiscountAmount' => self::AMOUNT_CURRENCY['base']['discountAmount'],
                'getBaseShippingTaxAmount' => self::AMOUNT_CURRENCY['base']['taxAmount'],
                'getShippingAmount' => self::AMOUNT_CURRENCY['display']['amount'],
                'getShippingDiscountAmount' => self::AMOUNT_CURRENCY['display']['discountAmount'],
                'getShippingTaxAmount' => self::AMOUNT_CURRENCY['display']['taxAmount']
            ]
        );

        $this->quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'getBaseGrandTotal',
                'getBaseCurrencyCode',
                'getGrandTotal',
                'getQuoteCurrencyCode',
                'getStoreId',
                'getShippingAddress'
            ])
            ->getMock();
        $this->mockMethods($this->quote,
            [
                'getBaseGrandTotal' => self::AMOUNT_CURRENCY['base']['amount'],
                'getBaseCurrencyCode' => self::AMOUNT_CURRENCY['base']['currencyCode'],
                'getGrandTotal' => self::AMOUNT_CURRENCY['display']['amount'],
                'getQuoteCurrencyCode' => self::AMOUNT_CURRENCY['display']['currencyCode'],
                'getStoreId' => 'NA',
                'getShippingAddress' => $shippingAddress
            ]
        );

        $this->quoteItem = $this->getMockBuilder(Quote\Item::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'getBasePrice',
                'getBaseDiscountAmount',
                'getBaseTaxAmount',
                'getQuote',
                'getRowTotal',
                'getQty',
                'getDiscountAmount',
                'getTaxAmount'
            ])
            ->getMock();
        $this->mockMethods($this->quoteItem,
            [
                'getBasePrice' => self::AMOUNT_CURRENCY['base']['amount'],
                'getBaseDiscountAmount' => self::AMOUNT_CURRENCY['base']['discountAmount'],
                'getBaseTaxAmount' => self::AMOUNT_CURRENCY['base']['taxAmount'],
                'getRowTotal' => self::AMOUNT_CURRENCY['display']['amount'],
                'getQty' => 1,
                'getDiscountAmount' => self::AMOUNT_CURRENCY['display']['discountAmount'],
                'getTaxAmount' => self::AMOUNT_CURRENCY['display']['taxAmount'],
                'getQuote' => $this->quote
            ]
        );

        $this->invoice = $this->getMockBuilder(Order\Invoice::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'getShippingAmount',
                'getShippingTaxAmount',
                'getBaseShippingAmount',
                'getBaseShippingTaxAmount',
                'getBaseCurrencyCode',
                'getOrderCurrencyCode',
                'getOrder'
            ])
            ->getMock();
        $this->mockMethods($this->invoice,
            [
                'getOrder' => $this->order,
                'getBaseCurrencyCode' => self::AMOUNT_CURRENCY['base']['currencyCode'],
                'getOrderCurrencyCode' => self::AMOUNT_CURRENCY['display']['currencyCode'],
                'getBaseShippingAmount' => self::AMOUNT_CURRENCY['base']['amount'],
                'getBaseShippingTaxAmount' => self::AMOUNT_CURRENCY['base']['taxAmount'],
                'getShippingAmount' => self::AMOUNT_CURRENCY['display']['amount'],
                'getShippingTaxAmount' => self::AMOUNT_CURRENCY['display']['taxAmount']
            ]
        );

        $this->invoiceItem = $this->getMockBuilder(Order\Invoice\Item::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'getBasePrice',
                'getInvoice',
                'getBaseTaxAmount',
                'getPrice',
                'getTaxAmount'
            ])
            ->getMock();
        $this->mockMethods($this->invoiceItem,
            [
                'getBasePrice' => self::AMOUNT_CURRENCY['base']['amount'],
                'getInvoice' => $this->invoice,
                'getBaseTaxAmount' => self::AMOUNT_CURRENCY['base']['taxAmount'],
                'getPrice' => self::AMOUNT_CURRENCY['display']['amount'],
                'getTaxAmount' => self::AMOUNT_CURRENCY['display']['taxAmount']
            ]
        );


        $creditMemo = $this->getMockBuilder(Order\Creditmemo::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'getInvoice'
            ])
            ->getMock();
        $creditMemo->method('getInvoice')->willReturn($this->invoice);

        $this->creditMemoItem = $this->getMockBuilder(Item::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'getBasePrice',
                'getPrice',
                'getInvoice',
                'getCreditMemo',
                'getBaseTaxAmount',
                'getTaxAmount'
            ])
            ->getMock();
        $this->mockMethods($this->creditMemoItem,
            [
                'getBasePrice' => self::AMOUNT_CURRENCY['base']['amount'],
                'getInvoice' => $this->invoice,
                'getCreditMemo' => $creditMemo,
                'getBaseTaxAmount' => self::AMOUNT_CURRENCY['base']['taxAmount'],
                'getPrice' => self::AMOUNT_CURRENCY['display']['amount'],
                'getTaxAmount' => self::AMOUNT_CURRENCY['display']['taxAmount']
            ]
        );

        $this->configHelper = $this->getMockBuilder(Config::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * @dataProvider amountCurrencyProvider
     * @param $configValue
     * @param $orderPlacement
     * @param $getAdyenChargedCurrency
     * @param $expectedResult
     */
    public function testGetOrderAmountCurrency(
        $configValue,
        AdyenAmountCurrency $expectedResult,
        $orderPlacement,
        $getAdyenChargedCurrency
    ) {
        $this->configHelper->method('getChargedCurrency')->willReturn($configValue);
        $this->order->method('getAdyenChargedCurrency')->willReturn($getAdyenChargedCurrency);
        $this->chargedCurrencyHelper = new ChargedCurrency($this->configHelper);
        $result = $this->chargedCurrencyHelper->getOrderAmountCurrency($this->order, $orderPlacement);
        $this->assertEquals(
            [
                $expectedResult->getAmount(),
                $expectedResult->getCurrencyCode(),
                $expectedResult->getAmountDue()
            ],
            [
                $result->getAmount(),
                $result->getCurrencyCode(),
                $result->getAmountDue()
            ]
        );
    }

    /**
     * @dataProvider amountCurrencyProvider
     * @param $configValue
     * @param $expectedResult AdyenAmountCurrency
     * @param $orderPlacement
     */
    public function testGetQuoteAmountCurrency(
        $configValue,
        AdyenAmountCurrency $expectedResult,
        $orderPlacement
    ) {
        if ($orderPlacement) {
            $this->configHelper->method('getChargedCurrency')->willReturn($configValue);
            $this->chargedCurrencyHelper = new ChargedCurrency($this->configHelper);
            $result = $this->chargedCurrencyHelper->getQuoteAmountCurrency($this->quote);
            $this->assertEquals(
                [
                    $expectedResult->getAmount(),
                    $expectedResult->getCurrencyCode()
                ],
                [
                    $result->getAmount(),
                    $result->getCurrencyCode()
                ]
            );
        } else {
            // Quotes are not evaluated after being saved, only during order placement
            $this->expectNotToPerformAssertions();
        }
    }

    /**
     * @dataProvider amountCurrencyProvider
     * @param $configValue
     * @param $expectedResult AdyenAmountCurrency
     * @param $orderPlacement
     */
    public function testGetQuoteItemAmountCurrency(
        $configValue,
        AdyenAmountCurrency $expectedResult,
        $orderPlacement
    ) {
        if ($orderPlacement) {
            $this->configHelper->method('getChargedCurrency')->willReturn($configValue);
            $this->chargedCurrencyHelper = new ChargedCurrency($this->configHelper);
            $result = $this->chargedCurrencyHelper->getQuoteItemAmountCurrency($this->quoteItem);
            $this->assertEquals(
                [
                    $expectedResult->getAmount(),
                    $expectedResult->getCurrencyCode(),
                    $expectedResult->getDiscountAmount(),
                    $expectedResult->getTaxAmount()
                ],
                [
                    $result->getAmount(),
                    $result->getCurrencyCode(),
                    $result->getDiscountAmount(),
                    $result->getTaxAmount()
                ]
            );
        } else {
            // Quote items are not evaluated after being saved, only during order placement
            $this->expectNotToPerformAssertions();
        }
    }

    /**
     * @dataProvider amountCurrencyProvider
     * @param $configValue
     * @param $expectedResult
     * @param $orderPlacement
     * @param $getAdyenChargedCurrency
     */
    public function testGetInvoiceItemAmountCurrency(
        $configValue,
        AdyenAmountCurrency $expectedResult,
        $orderPlacement,
        $getAdyenChargedCurrency
    ) {
        $this->order->method('getAdyenChargedCurrency')->willReturn($orderPlacement ? $configValue : $getAdyenChargedCurrency);
        $this->chargedCurrencyHelper = new ChargedCurrency($this->configHelper);
        $result = $this->chargedCurrencyHelper->getInvoiceItemAmountCurrency($this->invoiceItem);
        $this->assertEquals(
            [
                $expectedResult->getAmount(),
                $expectedResult->getCurrencyCode(),
                $expectedResult->getTaxAmount()
            ],
            [
                $result->getAmount(),
                $result->getCurrencyCode(),
                $result->getTaxAmount()
            ]
        );

    }

    /**
     * @dataProvider amountCurrencyProvider
     * @param $configValue
     * @param $expectedResult
     * @param $orderPlacement
     * @param $getAdyenChargedCurrency
     */
    public function testGetCreditMemoItemAmountCurrency(
        $configValue,
        AdyenAmountCurrency $expectedResult,
        $orderPlacement,
        $getAdyenChargedCurrency
    ) {
        $this->order->method('getAdyenChargedCurrency')->willReturn($orderPlacement ? $configValue : $getAdyenChargedCurrency);
        $this->chargedCurrencyHelper = new ChargedCurrency($this->configHelper);
        $result = $this->chargedCurrencyHelper->getCreditMemoItemAmountCurrency($this->creditMemoItem);

        $this->assertEquals(
            [
                $expectedResult->getAmount(),
                $expectedResult->getCurrencyCode(),
                $expectedResult->getTaxAmount()
            ],
            [
                $result->getAmount(),
                $result->getCurrencyCode(),
                $result->getTaxAmount()
            ]
        );
    }

    /**
     * @dataProvider amountCurrencyProvider
     * @param $configValue
     * @param $expectedResult
     * @param $orderPlacement
     * @param $getAdyenChargedCurrency
     */
    public function testGetQuoteShippingAmountCurrency(
        $configValue,
        AdyenAmountCurrency $expectedResult,
        $orderPlacement,
        $getAdyenChargedCurrency
    ) {
        $this->configHelper->method('getChargedCurrency')->willReturn($orderPlacement ? $configValue : $getAdyenChargedCurrency);
        $this->chargedCurrencyHelper = new ChargedCurrency($this->configHelper);
        $result = $this->chargedCurrencyHelper->getQuoteShippingAmountCurrency($this->quote);
        $this->assertEquals(
            [
                $expectedResult->getAmount(),
                $expectedResult->getCurrencyCode(),
                $expectedResult->getDiscountAmount(),
                $expectedResult->getTaxAmount()
            ],
            [
                $result->getAmount(),
                $result->getCurrencyCode(),
                $result->getDiscountAmount(),
                $result->getTaxAmount()
            ]
        );

    }

    /**
     * @dataProvider amountCurrencyProvider
     * @param $configValue
     * @param $expectedResult
     * @param $orderPlacement
     * @param $getAdyenChargedCurrency
     */
    public function testGetInvoiceShippingAmountCurrency(
        $configValue,
        AdyenAmountCurrency $expectedResult,
        $orderPlacement,
        $getAdyenChargedCurrency
    ) {
        $this->order->method('getAdyenChargedCurrency')->willReturn($getAdyenChargedCurrency);
        $this->chargedCurrencyHelper = new ChargedCurrency($this->configHelper);
        $result = $this->chargedCurrencyHelper->getInvoiceShippingAmountCurrency($this->invoice);
        $this->assertEquals(
            [
                $expectedResult->getAmount(),
                $expectedResult->getCurrencyCode(),
                $expectedResult->getTaxAmount()
            ],
            [
                $result->getAmount(),
                $result->getCurrencyCode(),
                $result->getTaxAmount()
            ]
        );
    }

    private function mockMethods(MockObject $object, $methods): void
    {
        foreach ($methods as $method => $return) {
            $object->method($method)->willReturn($return);
        }
    }

    public function amountCurrencyProvider(): array
    {
        $adyenAmountCurrencyBase = new AdyenAmountCurrency(
            self::AMOUNT_CURRENCY['base']['amount'],
            self::AMOUNT_CURRENCY['base']['currencyCode'],
            self::AMOUNT_CURRENCY['base']['discountAmount'],
            self::AMOUNT_CURRENCY['base']['taxAmount'],
            self::AMOUNT_CURRENCY['base']['amountDue']
        );

        $adyenAmountCurrencyDisplay = new AdyenAmountCurrency(
            self::AMOUNT_CURRENCY['display']['amount'],
            self::AMOUNT_CURRENCY['display']['currencyCode'],
            self::AMOUNT_CURRENCY['display']['discountAmount'],
            self::AMOUNT_CURRENCY['display']['taxAmount'],
            self::AMOUNT_CURRENCY['display']['amountDue']
        );

        return array(
            // Config is base, during order placement
            array(
                'configValue' => 'base',
                'expectedResult' => $adyenAmountCurrencyBase,
                'orderPlacement' => true,
                'getAdyenChargedCurrency' => 'base'
            ),
            // Config is display, during order placement
            array(
                'configValue' => 'display',
                'expectedResult' => $adyenAmountCurrencyDisplay,
                'orderPlacement' => true,
                'getAdyenChargedCurrency' => 'display'
            ),
            // Config is base, after base order placement
            array(
                'configValue' => 'base',
                'expectedResult' => $adyenAmountCurrencyBase,
                'orderPlacement' => false,
                'getAdyenChargedCurrency' => 'base'
            ),
            // Config is base, after display order placement
            array(
                'configValue' => 'base',
                'expectedResult' => $adyenAmountCurrencyDisplay,
                'orderPlacement' => false,
                'getAdyenChargedCurrency' => 'display'
            ),
            // Config is display, after base order placement
            array(
                'configValue' => 'display',
                'expectedResult' => $adyenAmountCurrencyBase,
                'orderPlacement' => false,
                'getAdyenChargedCurrency' => 'base'
            ),
            // Config is display, after display order placement
            array(
                'configValue' => 'display',
                'expectedResult' => $adyenAmountCurrencyDisplay,
                'orderPlacement' => false,
                'getAdyenChargedCurrency' => 'display'
            )
        );
    }

}
