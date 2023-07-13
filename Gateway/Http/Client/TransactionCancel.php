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
use Adyen\Service\Modification;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;

/**
 * Class TransactionSale
 */
class TransactionCancel implements ClientInterface
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
        $request = $transferObject->getBody();
        $headers = $transferObject->getHeaders();
        $clientConfig = $transferObject->getClientConfig();

        //Check if it is a MOTO Transaction
        if(isset($clientConfig['isMotoTransaction']) && $clientConfig['isMotoTransaction'] === true) {
            $client = $this->adyenHelper->initializeAdyenClient(
                $clientConfig['storeId'],
                null,
                $request['merchantAccount']
            );
            $service = new Modification($client);
        } else {
            $service = new Modification(
                $this->adyenHelper->initializeAdyenClient($transferObject->getClientConfig()['storeId'])
            );
        }

        $idempotencyKey = $this->idempotencyHelper->generateIdempotencyKey(
            $request,
            $headers['idempotencyExtraData'] ?? null
        );

        $requestOptions['idempotencyKey'] = $idempotencyKey;

        $this->adyenHelper
            ->logRequest($request, Client::API_PAYMENT_VERSION, '/pal/servlet/Payment/{version}/cancel');
        try {
            $response = $service->cancel($request, $requestOptions);
        } catch (AdyenException $e) {
            $response['error'] = $e->getMessage();
        }
        $this->adyenHelper->logResponse($response);

        return $response;
    }
}
