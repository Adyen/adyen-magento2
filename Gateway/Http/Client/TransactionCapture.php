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
use Adyen\Payment\Gateway\Request\CaptureDataBuilder;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\Requests;
use Adyen\Service\Modification;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;

/**
 * Class TransactionSale
 */
class TransactionCapture implements ClientInterface
{
    /**
     * @var Data
     */
    private $adyenHelper;

    /**
     * PaymentRequest constructor.
     * @param Data $adyenHelper
     */
    public function __construct(
        Data $adyenHelper
    ) {
        $this->adyenHelper = $adyenHelper;
    }

    /**
     * @param TransferInterface $transferObject
     * @return null
     */
    public function placeRequest(TransferInterface $transferObject)
    {
        $request = $transferObject->getBody();
        // call lib
        $service = new Modification(
            $this->adyenHelper->initializeAdyenClient($transferObject->getClientConfig()['storeId'])
        );

        if (array_key_exists(CaptureDataBuilder::MULTIPLE_AUTHORIZATIONS, $request)) {
            return $this->placeMultipleCaptureRequests($service, $request);
        }

        try {
            $response = $service->capture($request);
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
        foreach ($requestContainer[CaptureDataBuilder::MULTIPLE_AUTHORIZATIONS] as $request) {
            try {
                // Copy merchant account from parent array to every request array
                $request[Requests::MERCHANT_ACCOUNT] = $requestContainer[Requests::MERCHANT_ACCOUNT];
                $response[CaptureDataBuilder::MULTIPLE_AUTHORIZATIONS][] = $service->capture($request);
            } catch (AdyenException $e) {
                $response[CaptureDataBuilder::MULTIPLE_AUTHORIZATIONS]['error'] = sprintf(
                    'Exception occurred when attempting to capture multiple authorizations.
                    Authorization with pspReference %s: %s',
                    $request[OrderPaymentInterface::PSPREFRENCE],
                    $e->getMessage()
                );
            }
        }

        return reset($response[CaptureDataBuilder::MULTIPLE_AUTHORIZATIONS]);
    }
}
