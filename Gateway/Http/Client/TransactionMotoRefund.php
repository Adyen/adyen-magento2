<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2022 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Http\Client;

use Adyen\Client;
use Magento\Payment\Gateway\Http\ClientInterface;

/**
 * Class TransactionSale
 */
class TransactionMotoRefund implements TransactionRefundInterface
{
    /**
     * @var \Adyen\Payment\Helper\Data
     */
    private $adyenHelper;

    /**
     * PaymentRequest constructor.
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     */
    public function __construct(
        \Adyen\Payment\Helper\Data $adyenHelper
    ) {
        $this->adyenHelper = $adyenHelper;
    }

    /**
     * @param \Magento\Payment\Gateway\Http\TransferInterface $transferObject
     * @return null
     */
    public function placeRequest(\Magento\Payment\Gateway\Http\TransferInterface $transferObject)
    {
        $requests = $transferObject->getBody();
        $clientConfig = $transferObject->getClientConfig();

        $responses = [];

        foreach ($requests as $request) {
            // call lib

            $client = $this->adyenHelper->initializeAdyenClient(
                $clientConfig['storeId'],
                null,
                $request['merchantAccount']
            );

            $service = $this->adyenHelper->createAdyenCheckoutService($client);
            $this->adyenHelper
                ->logRequest($request, Client::API_CHECKOUT_VERSION, '/refunds');
            try {
                $response = $service->refunds($request);

                // Add amount original reference and amount information to response
                $response[self::REFUND_AMOUNT] = $request['amount']['value'];
                $response[self::REFUND_CURRENCY] = $request['amount']['currency'];
                $response[self::ORIGINAL_REFERENCE] = $request['paymentPspReference'];
            } catch (\Adyen\AdyenException $e) {
                $response = ['error' => $e->getMessage()];
            }
            $this->adyenHelper->logResponse($response);

            $responses[] = $response;
        }

        return $responses;
    }
}
