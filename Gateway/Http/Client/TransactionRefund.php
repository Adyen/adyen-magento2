<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2015 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Http\Client;

use Adyen\AdyenException;
use Adyen\Client;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\Idempotency;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;

/**
 * Class TransactionSale
 */
class TransactionRefund implements TransactionRefundInterface
{
    /**
     * @var Data
     */
    private $adyenHelper;

    /**
     * @var Idempotency
     */
    private $idempotencyHelper;

    /**
     * PaymentRequest constructor.
     *
     * @param Data $adyenHelper
     * @param Idempotency $idempotencyHelper
     */
    public function __construct(
        Data $adyenHelper,
        Idempotency $idempotencyHelper
    ) {
        $this->adyenHelper = $adyenHelper;
        $this->idempotencyHelper = $idempotencyHelper;
    }

    /**
     * @param TransferInterface $transferObject
     * @return array
     * @throws AdyenException
     * @throws NoSuchEntityException
     */
    public function placeRequest(TransferInterface $transferObject): array
    {
        $requests = $transferObject->getBody();
        $headers = $transferObject->getHeaders();

        foreach ($requests as $request) {
            // call lib
            $service = new \Adyen\Service\Modification(
                $this->adyenHelper->initializeAdyenClient($transferObject->getClientConfig()['storeId'])
            );

            $idempotencyKey = $this->idempotencyHelper->generateIdempotencyKey(
                $request,
                $headers['idempotencyExtraData'] ?? null
            );

            $requestOptions['idempotencyKey'] = $idempotencyKey;

            $this->adyenHelper
                ->logRequest($request, Client::API_PAYMENT_VERSION, '/pal/servlet/Payment/{version}/refund');
            try {
                $response = $service->refund($request, $requestOptions);

                // Add amount original reference and amount information to response
                $response[self::REFUND_AMOUNT] = $request['modificationAmount']['value'];
                $response[self::REFUND_CURRENCY] = $request['modificationAmount']['currency'];

                $response[self::ORIGINAL_REFERENCE] = $request['originalReference'];
            } catch (AdyenException $e) {
                $response = ['error' => $e->getMessage()];
            }
            $this->adyenHelper->logResponse($response);

            $responses[] = $response;
        }
        return $responses;
    }
}
