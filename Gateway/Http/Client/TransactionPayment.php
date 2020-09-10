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
 * Copyright (c) 2015 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Http\Client;

use Magento\Payment\Gateway\Http\ClientInterface;
use Adyen\Payment\Model\ApplicationInfo;

class TransactionPayment implements ClientInterface
{

    /**
     * @var \Adyen\Payment\Helper\Data
     */
    private $adyenHelper;

    /**
     * @var ApplicationInfo
     */
    private $applicationInfo;

    /**
     * TransactionPayment constructor.
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     * @param ApplicationInfo $applicationInfo
     */
    public function __construct(
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Adyen\Payment\Model\ApplicationInfo $applicationInfo
    ) {
        $this->adyenHelper = $adyenHelper;
        $this->applicationInfo = $applicationInfo;
    }

    /**
     * @param \Magento\Payment\Gateway\Http\TransferInterface $transferObject
     * @return array|mixed|string
     * @throws \Adyen\AdyenException
     */
    public function placeRequest(\Magento\Payment\Gateway\Http\TransferInterface $transferObject)
    {
        $request = $transferObject->getBody();

        // If the payments call is already done return the request
        if (!empty($request['resultCode'])) {
            //Initiate has already a response
            return $request;
        }

        $client = $this->adyenHelper->initializeAdyenClient();
        $service = $this->adyenHelper->createAdyenCheckoutService($client);

        $requestOptions = [];

        $request = $this->applicationInfo->addMerchantApplicationIntoRequest($request);

        try {
            $response = $service->payments($request, $requestOptions);
        } catch (\Adyen\AdyenException $e) {
            $response['error'] = $e->getMessage();
        }

        return $response;
    }
}
