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
            $service = new \Adyen\Service\Modification($client);
            $this->adyenHelper
                ->logRequest($request, Client::API_PAYMENT_VERSION, '/pal/servlet/Payment/{version}/refund');
            try {
                $response = $service->refund($request);

                // Add amount original reference and amount information to response
                $response[self::REFUND_AMOUNT] = $request['modificationAmount']['value'];
                $response[self::REFUND_CURRENCY] = $request['modificationAmount']['currency'];
                $response[self::ORIGINAL_REFERENCE] = $request['originalReference'];
            } catch (\Adyen\AdyenException $e) {
                $response = ['error' => $e->getMessage()];
            }
            $this->adyenHelper->logResponse($response);

            $responses[] = $response;
        }

        return $responses;
    }
}
