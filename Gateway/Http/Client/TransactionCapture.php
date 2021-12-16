<?php
/**
 *                       ######
 *                       ######
 * ############    ####( ######  #####. ######  ############   ############
 * #############  #####( ######  #####. ######  #############  #############
 *        ######  #####( ######  #####. ######  #####  ######  #####  ######
 * ###### ######  #####( ######  #####. ######  #####  #####   #####  ######
 * ###### ######  #####( ######  #####. ######  #####          #####  ######
 * #############  #############  #############  #############  #####  ######
 *  ############   ############  #############   ############  #####  ######
 *                                      ######
 *                               #############
 *                               ############
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2021 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Http\Client;

use Adyen\AdyenException;
use Adyen\Payment\Api\Data\OrderPaymentInterface;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\Requests;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Service\Modification;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;

/**
 * Class TransactionSale
 */
class TransactionCapture implements ClientInterface
{
    const MULTIPLE_AUTHORIZATIONS = 'multiple_authorizations';
    const FORMATTED_CAPTURE_AMOUNT = 'formatted_capture_amount';
    const CAPTURE_AMOUNT = 'capture_amount';
    const ORIGINAL_REFERENCE = 'original_reference';
    const CAPTURE_RECEIVED = '[capture-received]';

    /**
     * @var Data
     */
    private $adyenHelper;

    /**
     * @var AdyenLogger
     */
    private $adyenLogger;

    /**
     * PaymentRequest constructor.
     * @param Data $adyenHelper
     * @param AdyenLogger $adyenLogger
     */
    public function __construct(
        Data $adyenHelper,
        AdyenLogger $adyenLogger
    ) {
        $this->adyenHelper = $adyenHelper;
        $this->adyenLogger = $adyenLogger;
    }

    /**
     * @param TransferInterface $transferObject
     * @return null
     * @throws AdyenException
     */
    public function placeRequest(TransferInterface $transferObject)
    {
        $request = $transferObject->getBody();
        // call lib
        $service = new Modification(
            $this->adyenHelper->initializeAdyenClient($transferObject->getClientConfig()['storeId'])
        );

        if (array_key_exists(self::MULTIPLE_AUTHORIZATIONS, $request)) {
            return $this->placeMultipleCaptureRequests($service, $request);
        }

        try {
            $response = $service->capture($request);
            $response = $this->copyParamsToResponse($response, $request);
        } catch (AdyenException $e) {
            $response['error'] = $e->getMessage();
        }

        return $response;
    }

    /**
     * @param Modification $service
     * @param $requestContainer
     * @return array
     */
    private function placeMultipleCaptureRequests(Modification $service, $requestContainer)
    {
        $response = [];
        foreach ($requestContainer[self::MULTIPLE_AUTHORIZATIONS] as $request) {
            try {
                // Copy merchant account from parent array to every request array
                $request[Requests::MERCHANT_ACCOUNT] = $requestContainer[Requests::MERCHANT_ACCOUNT];
                $singleResponse = $service->capture($request);
                $singleResponse[self::FORMATTED_CAPTURE_AMOUNT] = $request['modificationAmount']['currency'] . ' ' .
                $this->adyenHelper->originalAmount(
                    $request['modificationAmount']['value'],
                    $request['modificationAmount']['currency']
                );
                $singleResponse = $this->copyParamsToResponse($singleResponse, $request);
                $response[self::MULTIPLE_AUTHORIZATIONS][] = $singleResponse;
            } catch (AdyenException $e) {
                $message = sprintf(
                    'Exception occurred when attempting to capture multiple authorizations.
                    Authorization with pspReference %s: %s',
                    $request[OrderPaymentInterface::PSPREFRENCE],
                    $e->getMessage()
                );

                $this->adyenLogger->error($message);
                $response[self::MULTIPLE_AUTHORIZATIONS]['error'] = $message;
            }
        }

        return $response;
    }

    /**
     * Copy data from the request to the response. This data will be used later when handling the response
     *
     * @param array $response
     * @param array $request
     * @return array
     */
    private function copyParamsToResponse(array $response, array $request): array
    {
        $response[self::CAPTURE_AMOUNT] = $request['modificationAmount']['value'];
        $response[self::ORIGINAL_REFERENCE] = $request['originalReference'];

        return $response;
    }
}
