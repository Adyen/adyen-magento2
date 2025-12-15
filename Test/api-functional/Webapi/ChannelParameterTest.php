<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2025 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Test\Webapi;

use Magento\Catalog\Test\Fixture\Product;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Quote\Model\QuoteIdToMaskedQuoteIdInterface;
use Magento\Quote\Test\Fixture\AddProductToCart;
use Magento\Quote\Test\Fixture\GuestCart;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Quote\Model\GetQuoteByReservedOrderId;
use Magento\TestFramework\TestCase\WebapiAbstract;

class ChannelParameterTest extends WebapiAbstract
{
    protected GetQuoteByReservedOrderId $getQuoteByReservedOrderId;
    protected QuoteIdToMaskedQuoteIdInterface $quoteIdToMaskedQuoteId;

    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->getQuoteByReservedOrderId = $objectManager->get(GetQuoteByReservedOrderId::class);
        $this->quoteIdToMaskedQuoteId = $objectManager->get(QuoteIdToMaskedQuoteIdInterface::class);
    }

    #[
        DataFixture(Product::class, [], 'product1'),
        DataFixture(GuestCart::class, ['reserved_order_id' => 'test_order_id'], 'guestCart1'),
        DataFixture(AddProductToCart::class, ['cartId' => '$guestCart1.id$', 'product_id' => '$product1.id$'])
    ]
    public function testApplePayExcludedForAndroidChannel()
    {
        $cart = $this->getQuoteByReservedOrderId->execute('test_order_id');
        $cartId = $this->quoteIdToMaskedQuoteId->execute($cart->getId());

        $this->setShippingInformationWithChannel($cartId);

        // Verify that Apple Pay is not present for the Android channel
        $serviceInfo = [
            'rest' => [
                'resourcePath' => "/V1/guest-carts/{$cartId}/payment-methods",
                'httpMethod' => Request::HTTP_METHOD_GET
            ]
        ];

        $response = $this->_webApiCall($serviceInfo);
        $this->assertIsArray($response, 'Response should be an array');

        foreach ($response as $paymentMethod) {
            $this->assertArrayHasKey(
                'code',
                $paymentMethod, 'Each payment method should have a code'
            );
            $this->assertNotEquals(
                'adyen_apple_pay',
                $paymentMethod['code'],
                'Apple Pay should not be present for Android channel'
            );
        }
    }

    /**
     * @param $maskedQuoteId
     * @return array
     */
    protected function setShippingInformationWithChannel($maskedQuoteId): array
    {
        $serviceInfo = [
            'rest' => [
                'resourcePath' => "/guest-carts/{$maskedQuoteId}/shipping-information?channel=Android",
                'httpMethod' => Request::HTTP_METHOD_POST
            ]
        ];

        $payload = [
            'addressInformation' => [
                'shipping_address' => [
                    'firstname' => 'Veronica',
                    'lastname' => 'Costello',
                    'company' => 'Adyen',
                    'street' => ['Simon Carmiggeltstraat', 'Main Street'],
                    'city' => 'Amsterdam',
                    'region' => 'Amsterdam',
                    'postcode' => '1011 DK',
                    'country_id' => 'NL',
                    'telephone' => '123456789'
                ],
                'shipping_carrier_code' => 'flatrate',
                'shipping_method_code' => 'flatrate'
            ]
        ];

        return $this->_webApiCall($serviceInfo, $payload, null, 'V1');
    }
}
