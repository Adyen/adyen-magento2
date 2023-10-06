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

        // example payload, won't work as the token is not valid
        // TODO figure out how to use data fixtures to not have to use the state data
        $payload = '{"riskData":{"clientData":"eyJ2ZXJzaW9uIjoiMS4wLjAiLCJkZXZpY2VGaW5nZXJwcmludCI6ImRmLXRpbWVkT3V0In0="},"paymentMethod":{"type":"scheme","holderName":"Rok PL","encryptedCardNumber":"eyJhbGciOiJSU0EtT0FFUCIsImVuYyI6IkEyNTZDQkMtSFM1MTIiLCJ2ZXJzaW9uIjoiMSJ9.gx2dpJmEHD6Q5P5cKDfsqkb-VshX0NrbJ6hsC3Hn6zCBmZEA0EENfZ8uEczstcdcKF9iz6k6zvZnsf3o0VpOIFcIYmLsunKkELBrAlCb_ZoJ84BSPOyOX-BmgRH3QtenhLY3KZlkYEcwtF0v3ecQZ3nbJi8HX_A3AQFlM2icIIiTjlBpE1vvQCyTTvWAabQT4K19kBRyb3k-aFPQi0pBB1u7sn8AMVesOYbN4OncPGQMDsvJi2dncrYvQmTpfaL1UeCuVSLtRGuU_Pn8A6UuL7fO7rgO_ihxFbwAhqcsgValZYNz66pgoFUPzmh44DjaJCVi0WN4m6v348olj7SQSw.Z71pFLlCTz4ex4BW_6v40g.fbresBvC0yhGAv4wJ4rMQ0a8oNne-0vO7oOIvYJdgMwnMPA5uK8RX01R-SSwfB0vt2eZ4-N_rATDHasCUpEeGBeThLUx7l4rqnhVdtIYHwmnVHKzb3yRkrz4GtZIJozBIwxeNHWJvvMjpn7h3osgNBSFfhJhiFgjBpjPp08fTbr1YjDpMj5_lQcKhTCdBR0JmYDddImPjudvF527jIVZnCjB6MfxvlIehrcJbceieN9T9YpHTbkUjYWkX-Xt0-PYne52WnNq06hF41XjYB-LZmZHQpkKev0A90vg1bvsPP27c_OnebDvcajr-QGUdpcS4pJxzXCGXxJZNyMr5K5cyL-APxLcCJlu-P7y5ASKve8Q70pFsRaiXkoClVefyHzflDb_gwIyCb3-cpkchlXiI8mNeucANPMVS4-X1JM3lTHqiqHFl6XhFK8UsP7yldj0H4V1FS4YHg5rd0tUQ3z5-McJYpi1bm63xKZrC6Kj3W72BJmfaFQLAhliShj7j1c5-W7Mi9upK-CNiTPfkKI0YSZYyBfkkEyxKdHKkpM_-oWP1KmAh0KZ2dj8aUS0Y3SIgOVbGFdwlavN_GD8znFkfQxmog5-3fp0MlfezTcyXGnmR7FI1tr9l6iMq9ikr-HiW6NtGbDwRhbksh2X0qdgytud404npCM_dytXYKjBy32CcIO3baKRP6XenVVQj3AGxWunRyFiOW3_rbZZqKfBww.88f5n33CbLWnapP9NrZ0RrJw6S1INRZCze4spk9JDRU","encryptedExpiryMonth":"eyJhbGciOiJSU0EtT0FFUCIsImVuYyI6IkEyNTZDQkMtSFM1MTIiLCJ2ZXJzaW9uIjoiMSJ9.ZZnrwVHDpIpUZFIE4GG0RQOXP7oOzu_nh3UuWhpNtMeFORm07GMKuuVm2Oh7-z4jUqTeNKHlk1pKjp60wvByH-iI0EqpBHDwdwd7xxoqIgwZNSAkTzdV80GbyfI8c-8DQFV6IsM2KW7hWxZws4jnAsjP1mUgvRhyxuU4kPehIDR-MTi9xvg4yMux1PtwU993rdwAvnTf53iTLUCosESOXdQ3MUkXBadpFk90moLl3fYTYFGOpynis3Qp6HUJ7i1DgJ5CWIGMLwOE8L1lHb5LBlnu3M4IWBr44Og8vMCwY8isXUg_hEjY0vRjsaJa6aM4DPt5jllkHwPn2OyjHRvlLA.l30CkLX3sfnQBAHS4wR5Rg.3HwnLRAdeEyi_006zs2t5mwtz2Fekcm-UDV7cvdJVOEKLZY9Mx-4SwRhd6Wv1j_3dMc11ffvCAuirmWpFLEWLg.nPJBdUaUdgtAg64byUb0ycQqlwzoE4KT7eUzL8UHw0w","encryptedExpiryYear":"eyJhbGciOiJSU0EtT0FFUCIsImVuYyI6IkEyNTZDQkMtSFM1MTIiLCJ2ZXJzaW9uIjoiMSJ9.EjP3yu9Lq7E1lCMUNk5GvefDNl16cbNfg87I0tSDxK8hxo4ytfeM8TOg7O3fZlVpyZ_ThsbGJeI91kxIZzilIFidqa2Nj9U22KlJiBD8rcuFfSnosUudo5leNeIO4tNhkXWlxCIzCtiLZ9F4JRu2TLXl58wfbycwQGplLz5d7AuDcGXRmKqJphXVLngaysLvYZ04DhZAZLyx9iSePWPJbXl3YifKihd3DuhIVD0b6dJfIgKb8KpgdTSiNn-2wAPvi0wMmTxSLMR4Ac8PQeapiPWVT2N2UVkkZcAueB1-wnkkAVfTcwSKlUYeaMkzr3Gs3GsK1RZdvwsZWmuzxpYVKg.SGOtaRHepFPliUk7JWwLsg.kzlOXMXV1OZ4Lsx4jzEuwvDIj3BK9KVBPLWsncL5Asr6o3AOtWv5L_vIsrZTKo-E-i8YdLWCOy-8_vp4WuQskg.RWjuXYpnpuW2uv_RuFjKNCjGLJUGPFkEKBcVFRPAKz4","encryptedSecurityCode":"eyJhbGciOiJSU0EtT0FFUCIsImVuYyI6IkEyNTZDQkMtSFM1MTIiLCJ2ZXJzaW9uIjoiMSJ9.VaEmNPkvmhGhl959sVJKJO_m902q_7BfpKDNGVkC1fCt3uK1upKvIx2hP0H8nvUsHFKLGpsQOQhMajCww_bDONy5oGusR4WuzVGYAOeEHC8Y5z4MJIjJB-CmJPSa7R1WhlpMbk2qcrmcW4-UQh6SHdK3jMxonzC99MuwSKreM3WOTETqVd1LE1wNDcPGp6y2xTZuo1xUxhyGpMTIEUSNf9CgDl4sBvIQNSFP-7sGZSzulYgnW0VwU0-LFbMavZBq2_RBx9sSLxaKruBLFOZ9IzwxtEmXFq7120kb5YdYxnfMjnZbM3wpsU-NSDJaKDxi9scW2tCUa9wmH0vEQ7gHsg.cbd05i2uxZz1Vw_y3_TCEg.kxqh_G5x0BCyDV6WH3VQDp0s7a61tt1VLPIY2EQJ8sjh5jTiDC7lYi_sjaUuWWKnmUTagzzAgK7gbf58TB13907g9GN1z01EhJnQTySu3DPXqaBLrVgQFMvT4aCLA47VTb4ekR6AMrG2HFEukA9KzV34FiNPf-o1HulW43u1byqTRucbM_9u37sSNozU-fNqNLAYstara7EA2M--i7oWgrJVvdVJ4R82f-jHP2htnQ_wPLjTxV0ad3MsPZNqiSojjrWcEjnhJMv5eC2i8pf2xT3R6GECfsut8gx7TFYrXYPG4Rmr5ZCy9KimftnFLavR9P7c2Mz6-hnG8N2gnPtIh3nWSV9l-AuJGptL_jPOgBxlLbFHAvRRpypcTr_VyYNtI_SFmRyXyWBqR-2IZ5wjyE_aOOakYIb0_C-6laWX1yrM3I6onk35e8t_85S8kmW39F26_LF67y-HqT4g-1-9r4eo-Nk1lq0ImRV-COebwi4.sdX1eVuNG115evCu9O3Fxpa7xzTq-bLYBxsT0h6Gij4","brand":"mc","checkoutAttemptId":"be6a4c83-e8c6-4742-a79f-99bf4a167d961696603720198BDB23865A4C4CFD11CD1BD9B10911C8B9AAFF83E6E99070A55B7671D665274DF"},"storePaymentMethod":true,"browserInfo":{"acceptHeader":"*/*","colorDepth":30,"language":"en-US","javaEnabled":false,"screenHeight":982,"screenWidth":1512,"userAgent":"Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/117.0.0.0 Safari/537.36","timeZoneOffset":-120},"origin":"https://192.168.58.20","clientStateDataIndicator":true,"amount":{"currency":"EUR","value":500},"returnUrl":"https://192.168.58.20/index.php/checkout/onepage/success/"}';
        $response = $this->_webApiCall($serviceInfo, ['payload' => $payload]);
        $this->assertNotEmpty($response);
    }
}
