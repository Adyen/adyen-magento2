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
use Magento\Framework\Webapi\Rest\Request;
use Magento\Indexer\Test\Fixture\Indexer;
use Magento\Quote\Model\QuoteIdToMaskedQuoteIdInterface;
use Magento\Quote\Test\Fixture\AddProductToCart;
use Magento\Quote\Test\Fixture\GuestCart;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorage;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Quote\Model\GetQuoteByReservedOrderId;
use Magento\TestFramework\TestCase\WebapiAbstract;

class ChannelParameterTest extends WebapiAbstract
{
    protected GetQuoteByReservedOrderId $getQuoteByReservedOrderId;
    protected QuoteIdToMaskedQuoteIdInterface $quoteIdToMaskedQuoteId;
    protected DataFixtureStorage $fixtures;

    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->getQuoteByReservedOrderId = $objectManager->get(GetQuoteByReservedOrderId::class);
        $this->quoteIdToMaskedQuoteId = $objectManager->get(QuoteIdToMaskedQuoteIdInterface::class);
        $this->fixtures = Bootstrap::getObjectManager()->get(DataFixtureStorageManager::class)->getStorage();
    }

    #[
        DataFixture(Product::class, as: 'product1'),
        DataFixture(Indexer::class),
        DataFixture(GuestCart::class, ['reserved_order_id' => 'test_order_id'], as: 'guestCart1'),
        DataFixture(AddProductToCart::class, ['cart_id' => '$guestCart1.id$', 'product_id' => '$product1.id$', 'qty' => 5])
    ]
    public function testApplePayExcludedForAndroidChannel()
    {
        $cart = $this->fixtures->get('guestCart1');
        $cartId = $this->quoteIdToMaskedQuoteId->execute($cart->getId());

        $response = $this->setShippingInformationWithChannel($cartId);

        $this->assertIsArray($response['payment_methods']);

        foreach ($response['payment_methods'] as $paymentMethod) {
            $this->assertArrayHasKey(
                'code',
                $paymentMethod, 'Each payment method should have a code'
            );
            $this->assertNotEquals(
                'adyen_applepay',
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
