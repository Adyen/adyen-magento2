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

class TransactionAuthorization implements ClientInterface
{

    /**
     * @var \Adyen\Client
     */
    protected $client;

    /**
     * @var ApplicationInfo
     */
    private $applicationInfo;

    /**
     * TransactionAuthorization constructor.
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     * @param ApplicationInfo $applicationInfo
     * @throws \Adyen\AdyenException
     */
    public function __construct(
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Adyen\Payment\Model\ApplicationInfo $applicationInfo
    ) {
        $this->applicationInfo = $applicationInfo;
        $this->client = $adyenHelper->initializeAdyenClient();
    }

    /**
     * @param \Magento\Payment\Gateway\Http\TransferInterface $transferObject
     * @return array|mixed
     * @throws \Adyen\AdyenException
     */
    public function placeRequest(\Magento\Payment\Gateway\Http\TransferInterface $transferObject)
    {
        $request = $transferObject->getBody();
        $requestOptions = [];

        // call lib
        $service = new \Adyen\Service\Payment($this->client);

        $request = $this->applicationInfo->addMerchantApplicationIntoRequest($request);

        try {
            $response = $service->authorise($request, $requestOptions);
        } catch (\Adyen\AdyenException $e) {
            $response['error'] = $e->getMessage();
        }

        return $response;
    }
}
