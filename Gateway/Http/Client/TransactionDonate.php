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
 * Adyen Payment Module
 *
 * Copyright (c) 2021 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Http\Client;

use Adyen\AdyenException;
use Adyen\Client;
use Adyen\Payment\Helper\Data;
use Adyen\Service\Checkout;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;

class TransactionDonate implements ClientInterface
{
    /**
     * @var Client
     */
    private $client;

    /**
     * @throws AdyenException
     */
    public function __construct(Data $adyenHelper)
    {
        $this->client = $adyenHelper->initializeAdyenClient();
    }

    /**
     * @inheritDoc
     * @throws AdyenException
     */
    public function placeRequest(TransferInterface $transferObject)
    {
        $request = $transferObject->getBody();
        $service = new Checkout($this->client);

        try {
            $response = $service->donations($request);
        } catch (AdyenException $e) {
            $response = ['error' => $e->getMessage()];
        }

        return $response;
    }
}
