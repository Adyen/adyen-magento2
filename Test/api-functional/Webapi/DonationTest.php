<?php

namespace Adyen\Payment\Test\Webapi;

use Magento\Framework\Webapi\Rest\Request;
use Magento\TestFramework\TestCase\WebapiAbstract;

class DonationsTest extends WebapiAbstract
{
    public function testSuccessfulDonation()
    {
        $orderId = 10;
        $serviceInfo = [
            'rest' => [
                'resourcePath' => "/V1/adyen/orders/guest-carts/{$orderId}/donations",
                'httpMethod' => Request::HTTP_METHOD_POST
            ]
        ];

        $payload = '{"amount":{"currency":"EUR","value":100},"returnUrl":"https://local.store/index.php/checkout/onepage/success/"}';
        $response = $this->_webApiCall($serviceInfo, ['payload' => $payload]);
        $this->assertNotEmpty($response);
    }
}
