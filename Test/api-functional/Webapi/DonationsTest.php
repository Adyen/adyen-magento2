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

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Quote\Model\QuoteIdToMaskedQuoteIdInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Quote\Model\GetQuoteByReservedOrderId;
use Magento\TestFramework\TestCase\WebapiAbstract;

class DonationsTest extends WebapiAbstract
{
    protected GetQuoteByReservedOrderId $getQuoteByReservedOrderId;
    protected QuoteIdToMaskedQuoteIdInterface $quoteIdToMaskedQuoteId;

    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->getQuoteByReservedOrderId = $objectManager->get(GetQuoteByReservedOrderId::class);
        $this->quoteIdToMaskedQuoteId = $objectManager->get(QuoteIdToMaskedQuoteIdInterface::class);
    }

    /**
     * @return void
     * @throws NoSuchEntityException
     * @magentoDataFixture Magento/Checkout/_files/quote_with_address_and_shipping_method_saved.php
     */
    public function testSuccessfulDonation()
    {
        $cart = $this->getQuoteByReservedOrderId->execute('test_order_1');
        $cartId = $this->quoteIdToMaskedQuoteId->execute($cart->getId());

        $this->placeOrder($cartId);

        $serviceInfoDonationCampaigns = [
            'rest' => [
                'resourcePath' => "/V1/adyen/orders/guest-carts/{$cartId}/donation-campaigns",
                'httpMethod' => Request::HTTP_METHOD_POST
            ]
        ];

        $this->_webApiCall($serviceInfoDonationCampaigns);

        $serviceInfoDonations = [
            'rest' => [
                'resourcePath' => "/V1/adyen/orders/guest-carts/{$cartId}/donations",
                'httpMethod' => Request::HTTP_METHOD_POST
            ]
        ];

        $payload = [
            'amount' => [
                'currency' => 'EUR',
                'value' => 500
            ],
            'returnUrl' => 'https://local.store/index.php/checkout/onepage/success/'
        ];

        $response = $this->_webApiCall($serviceInfoDonations, ['payload' => json_encode($payload)]);
        $this->assertEmpty($response);
    }

    /**
     * @param string $cartId
     * @return void
     */
    protected function placeOrder(string $cartId): void
    {
        $placeOrderServiceInfo = [
            'rest' => [
                'resourcePath' => "/V1/guest-carts/{$cartId}/payment-information",
                'httpMethod' => Request::HTTP_METHOD_POST
            ]
        ];

        $paymentInformationPayload = [
            'email' => 'customer@example.com',
            'paymentMethod' => [
                'method' => 'adyen_cc',
                'additional_data' => [
                    'stateData' => json_encode($this->getValidCardStateData())
                ]
            ]
        ];

        $this->_webApiCall($placeOrderServiceInfo, $paymentInformationPayload);
    }

    /**
     * @return array
     */
    protected function getValidCardStateData(): array
    {
        return [
            'paymentMethod' => [
                'type' => 'scheme',
                'brand' => 'visa',
                'encryptedCardNumber' => 'test_4111111111111111',
                'encryptedExpiryMonth' => 'test_03',
                'encryptedExpiryYear' => 'test_2030',
                'encryptedSecurityCode' => 'test_737'
            ]
        ];
    }
}
