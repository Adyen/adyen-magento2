<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Test\Unit\Helper;

use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\ConnectedTerminals;
use Adyen\Payment\Helper\MagentoPaymentDetails;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Helper\PaymentMethodsFilter;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Checkout\Api\Data\PaymentDetailsExtensionInterface;
use Magento\Checkout\Model\PaymentDetails;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Payment\Model\Method\Adapter;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Model\Order\Address;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Data\Collection\AbstractDb;

class MagentoPaymentDetailsTest extends AbstractAdyenTestCase
{
    const PAYMENT_METHODS = [
        'adyen_alma',
        'adyen_paypal',
        'adyen_sepadirectdebit',
        'unknown',
        'adyen_clearpay',
        'adyen_ideal',
        'adyen_amazonpay',
        'undefined',
        'adyen_klarna_account'
    ];

    const CONNECTED_TERMINALS = [
        'uniqueTerminalIds' => [
            'ABC-123456XY',
            'DEF-678912TZ'
        ]
    ];

    const PAYMENT_METHODS_RESPONSE = <<<JSON
        {
          "paymentMethodsResponse": {
            "paymentMethods": [
              {
                "name": "SEPA Direct Debit",
                "type": "sepadirectdebit"
              },
              {
                "name": "Alma",
                "type": "alma"
              },
              {
                "configuration": {
                  "merchantId": "xxxxx",
                  "intent": "authorize"
                },
                "name": "PayPal",
                "type": "paypal"
              },
              {
                "name": "Clearpay",
                "type": "clearpay"
              },
              {
                "name": "Pay over time with Klarna.",
                "type": "klarna_account"
              }
            ]
          },
          "paymentMethodsExtraDetails": {
            "sepadirectdebit": {
              "icon": {
                "url": "https://localhost.store/static/version1695118335/frontend/Magento/luma/en_US/Adyen_Payment/images/logos/sepadirectdebit.svg",
                "width": 77,
                "height": 50
              },
              "isOpenInvoice": false,
              "configuration": {
                "amount": {
                  "value": 5900,
                  "currency": "EUR"
                },
                "currency": "EUR"
              }
            },
            "alma": {
              "icon": {
                "url": "https://localhost.store/static/version1695118335/frontend/Magento/luma/en_US/Adyen_Payment/images/logos/alma.svg",
                "width": 77,
                "height": 50
              },
              "isOpenInvoice": false,
              "configuration": {
                "amount": {
                  "value": 5900,
                  "currency": "EUR"
                },
                "currency": "EUR"
              }
            },
            "paypal": {
              "icon": {
                "url": "https://localhost.store/static/version1695118335/frontend/Magento/luma/en_US/Adyen_Payment/images/logos/paypal.svg",
                "width": 77,
                "height": 50
              },
              "isOpenInvoice": false,
              "configuration": {
                "amount": {
                  "value": 5900,
                  "currency": "EUR"
                },
                "currency": "EUR"
              }
            },
            "clearpay": {
              "icon": {
                "url": "https://localhost.store/static/version1695118335/frontend/Magento/luma/en_US/Adyen_Payment/images/logos/clearpay.svg",
                "width": 77,
                "height": 50
              },
              "isOpenInvoice": true,
              "configuration": {
                "amount": {
                  "value": 5900,
                  "currency": "EUR"
                },
                "currency": "EUR"
              }
            },
            "klarna_account": {
              "icon": {
                "url": "https://localhost.store/static/version1695118335/frontend/Magento/luma/en_US/Adyen_Payment/images/logos/klarna_account.svg",
                "width": 77,
                "height": 50
              },
              "isOpenInvoice": true,
              "configuration": {
                "amount": {
                  "value": 5900,
                  "currency": "EUR"
                },
                "currency": "EUR"
              }
            }
          }
        }
    JSON;

    private array $magentoPaymentMethods;

    public function __construct(?string $name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);

        foreach (self::PAYMENT_METHODS as $paymentMethod) {
            $this->magentoPaymentMethods[] = $this->createConfiguredMock(Adapter::class, [
                'getCode' => $paymentMethod
            ]);
        }
    }

    public function testAddAdyenExtensionAttributes(): void
    {
        $quoteId = 1;
        $storeId = 1;
        $quoteMock = $this->createConfiguredMock(CartInterface::class, [
            'getId' => $quoteId,
            'getStoreId' => $storeId,
            'getBillingAddress' => $this->createConfiguredMock(Address::class, [
                'getCountryId' => 1
            ])
        ]);

        $cartRepositoryInterfaceMock = $this->createConfiguredMock(CartRepositoryInterface::class, [
            'get' => $quoteMock
        ]);

        $extensionAttributesMock = $this->createGeneratedMock(PaymentDetailsExtensionInterface::class, [
            'setAdyenPaymentMethodsResponse',
            'getAdyenPaymentMethodsResponse',
            'setAdyenConnectedTerminals',
            'getAdyenConnectedTerminals'
        ]);
        $extensionAttributesMock->method('getAdyenPaymentMethodsResponse')
            ->willReturn(self::PAYMENT_METHODS_RESPONSE);
        $extensionAttributesMock->method('getAdyenConnectedTerminals')
            ->willReturn(self::CONNECTED_TERMINALS);


        $paymentDetailsMock = $this->createConfiguredMock(PaymentDetails::class, [
            'getPaymentMethods' => $this->magentoPaymentMethods,
            'getExtensionAttributes' => $extensionAttributesMock
        ]);

        $paymentMethodsFilterMock = $this->createConfiguredMock(PaymentMethodsFilter::class, [
            'sortAndFilterPaymentMethods' => [
                $this->magentoPaymentMethods,
                self::PAYMENT_METHODS_RESPONSE
            ]
        ]);

        $connectedTerminalsMock = $this->createConfiguredMock(ConnectedTerminals::class, [
            'getConnectedTerminals' => self::CONNECTED_TERMINALS
        ]);

        $configHelperMock = $this->createConfiguredMock(Config::class, [
            'getIsPaymentMethodsActive' => true,
            'getAdyenPosCloudConfigData' => true
        ]);

        $paymentMethodsHelperMock = $this->createConfiguredMock(PaymentMethods::class, [
            'getApiResponse' => self::PAYMENT_METHODS_RESPONSE
        ]);

        $magentoPaymentDetails = $this->createMagentoPaymentDetailsHelper(
            $paymentMethodsFilterMock,
            $configHelperMock,
            $cartRepositoryInterfaceMock,
            $connectedTerminalsMock,
            $paymentMethodsHelperMock
        );

        $paymentDetails = $magentoPaymentDetails->addAdyenExtensionAttributes($paymentDetailsMock, $quoteId);
        $extensionAttributes = $paymentDetails->getExtensionAttributes();

        $adyenPaymentMethodsResponse = $extensionAttributes->getAdyenPaymentMethodsResponse();
        $adyenConnectedTerminals = $extensionAttributesMock->getAdyenConnectedTerminals();

        $this->assertEquals(self::CONNECTED_TERMINALS, $adyenConnectedTerminals);
        $this->assertEquals(self::PAYMENT_METHODS_RESPONSE, $adyenPaymentMethodsResponse);
    }

    public function testAddAdyenExtensionAttributesReturnsEarlyWhenBothDisabled(): void
    {
        $quoteId = 1;
        $storeId = 1;
        $quoteMock = $this->createConfiguredMock(CartInterface::class, [
            'getId' => $quoteId,
            'getStoreId' => $storeId
        ]);

        $cartRepositoryInterfaceMock = $this->createConfiguredMock(CartRepositoryInterface::class, [
            'get' => $quoteMock
        ]);

        $paymentDetailsMock = $this->createMock(PaymentDetails::class);

        $configHelperMock = $this->createConfiguredMock(Config::class, [
            'getIsPaymentMethodsActive' => false,
            'getAdyenPosCloudConfigData' => false
        ]);

        $paymentMethodsFilterMock = $this->createMock(PaymentMethodsFilter::class);
        $paymentMethodsFilterMock->expects($this->never())
            ->method('sortAndFilterPaymentMethods');

        $connectedTerminalsMock = $this->createMock(ConnectedTerminals::class);
        $connectedTerminalsMock->expects($this->never())
            ->method('getConnectedTerminals');

        $paymentMethodsHelperMock = $this->createMock(PaymentMethods::class);
        $paymentMethodsHelperMock->expects($this->never())
            ->method('getApiResponse');

        $magentoPaymentDetails = $this->createMagentoPaymentDetailsHelper(
            $paymentMethodsFilterMock,
            $configHelperMock,
            $cartRepositoryInterfaceMock,
            $connectedTerminalsMock,
            $paymentMethodsHelperMock
        );

        $result = $magentoPaymentDetails->addAdyenExtensionAttributes($paymentDetailsMock, $quoteId);

        $this->assertSame($paymentDetailsMock, $result);
    }

    private function createMagentoPaymentDetailsHelper(
        $paymentMethodsFilterMock = null,
        $configHelperMock = null,
        $cartRepositoryInterfaceMock = null,
        $connectedTerminalsMock = null,
        $paymentMethodsMock = null
    ): MagentoPaymentDetails {
        if (is_null($paymentMethodsFilterMock)) {
            $paymentMethodsFilterMock = $this->createMock(PaymentMethodsFilter::class);
        }

        if (is_null($configHelperMock)) {
            $configHelperMock = $this->createMock(Config::class);
        }

        if (is_null($cartRepositoryInterfaceMock)) {
            $cartRepositoryInterfaceMock = $this->createMock(CartRepositoryInterface::class);
        }

        if (is_null($connectedTerminalsMock)) {
            $connectedTerminalsMock = $this->createMock(ConnectedTerminals::class);
        }

        if (is_null($paymentMethodsMock)) {
            $paymentMethodsMock = $this->createMock(PaymentMethods::class);
        }

        return new MagentoPaymentDetails(
            $paymentMethodsFilterMock,
            $configHelperMock,
            $cartRepositoryInterfaceMock,
            $connectedTerminalsMock,
            $paymentMethodsMock
        );
    }
}
