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

use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Helper\PaymentMethodsFilter;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Payment\Model\Method\Adapter;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Model\Order\Address;
use Magento\Framework\App\RequestInterface;

class PaymentMethodsFilterTest extends AbstractAdyenTestCase
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

    const PAYMENT_METHODS_SORTED = [
        'adyen_sepadirectdebit',
        'adyen_alma',
        'adyen_paypal',
        'adyen_clearpay',
        'adyen_klarna_account',
        'unknown',
        'undefined'
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

    public function testSortAndFilterPaymentMethods(): void
    {
        $quoteMock = $this->createConfiguredMock(CartInterface::class, [
            'getId' => 1,
            'getBillingAddress' => $this->createConfiguredMock(Address::class, [
                'getCountryId' => 1
            ])
        ]);

        $channel = 'iOS';

        $paymentMethodsHelperMock = $this->createConfiguredMock(PaymentMethods::class, [
            'getPaymentMethods' => self::PAYMENT_METHODS_RESPONSE
        ]);

        $RequestInterfaceMock = $this->getMockBuilder(RequestInterface::class)
            ->getMockForAbstractClass();

        $RequestInterfaceMock->method('getParam')->with('channel')->willReturn($channel);

        $paymentMethodsFilterHelper = $this->createPaymentMethodsFilterHelper(
            $paymentMethodsHelperMock,
            $RequestInterfaceMock
        );

        $sortedMagentoPaymentMethods =
            $paymentMethodsFilterHelper->sortAndFilterPaymentMethods($this->magentoPaymentMethods, $quoteMock)[0];

        $assertArray = [];
        foreach ($sortedMagentoPaymentMethods as $paymentMethod) {
            $assertArray[] = $paymentMethod->getCode();
        }

        $this->assertEquals(self::PAYMENT_METHODS_SORTED, $assertArray);
    }

    protected function createPaymentMethodsFilterHelper(
        $paymentMethodsHelperMock = null,
        $RequestInterfaceMock = null
    ): PaymentMethodsFilter {
        if (is_null($paymentMethodsHelperMock)) {
            $paymentMethodsHelperMock = $this->createMock(PaymentMethods::class);
        }

        if (is_null($RequestInterfaceMock)) {
            $RequestInterfaceMock = $this->createMock(RequestInterface::class);
        }

        return new PaymentMethodsFilter($paymentMethodsHelperMock, $RequestInterfaceMock);
    }
}
