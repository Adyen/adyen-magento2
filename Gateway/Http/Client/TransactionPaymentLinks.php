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
 * Copyright (c) 2021 Adyen NV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Http\Client;

use Adyen\AdyenException;
use Adyen\Payment\Helper\Data;
use Magento\Payment\Gateway\Http\ClientInterface;
use Adyen\Payment\Model\ApplicationInfo;
use Magento\Payment\Gateway\Http\TransferInterface;

class TransactionPaymentLinks implements ClientInterface
{

    /**
     * @var Data
     */
    private $adyenHelper;

    /**
     * @var ApplicationInfo
     */
    private $applicationInfo;

    /**
     * @param Data $adyenHelper
     * @param ApplicationInfo $applicationInfo
     */
    public function __construct(
        Data $adyenHelper,
        ApplicationInfo $applicationInfo
    ) {
        $this->adyenHelper = $adyenHelper;
        $this->applicationInfo = $applicationInfo;
    }

    /**
     * @param TransferInterface $transferObject
     * @return array|mixed|string
     * @throws AdyenException
     */
    public function placeRequest(TransferInterface $transferObject)
    {
        $request = $transferObject->getBody();

        // If the payment links call is already done return the request
        if (!empty($request['resultCode'])) {
            //Initiate has already a response
            return $request;
        }

        $client = $this->adyenHelper->initializeAdyenClient();
        $service = $this->adyenHelper->createAdyenCheckoutService($client);

        $requestOptions = [];

        $request = $this->applicationInfo->addMerchantApplicationIntoRequest($request);

        try {
            $response = $service->paymentLinks($request, $requestOptions);
        } catch (AdyenException $e) {
            $response['error'] = $e->getMessage();
        }

        return $response;
    }
}
