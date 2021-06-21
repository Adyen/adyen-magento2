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
 * Copyright (c) 2020 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper;

use Adyen\AdyenException;
use Adyen\Payment\Logger\AdyenLogger;
use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

class ConnectedTerminals
{
    /**
     * @var Session
     */
    protected $session;

    /**
     * @var Data
     */
    protected $adyenHelper;

    /**
     * @var AdyenLogger
     */
    private $adyenLogger;

    public function __construct(
        Data $adyenHelper,
        Session $session,
        AdyenLogger $adyenLogger
    ) {
        $this->adyenHelper = $adyenHelper;
        $this->session = $session;
        $this->adyenLogger = $adyenLogger;
    }

    /**
     * @return array|mixed
     * @throws AdyenException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getConnectedTerminals()
    {
        $storeId = $this->session->getQuote()->getStoreId();

        // initialize the adyen client
        $client = $this->adyenHelper->initializeAdyenClient($storeId, $this->adyenHelper->getPosApiKey($storeId));

        // initialize service
        $service = $this->adyenHelper->createAdyenPosPaymentService($client);

        $requestParams = [
            "merchantAccount" => $this->adyenHelper->getAdyenMerchantAccount('adyen_pos_cloud', $storeId),
        ];

        // In case the POS store id is set, provide in the request
        if (!empty($this->adyenHelper->getPosStoreId($storeId))) {
            $requestParams['store'] = $this->adyenHelper->getPosStoreId($storeId);
        }

        try {
            $responseData = $service->getConnectedTerminals($requestParams);
        } catch (AdyenException $e) {
            $this->adyenLogger->error(
                "The getConnectedTerminals response is empty check your Adyen configuration in Magento."
            );
            // return empty result
            return [];
        }

        return $responseData;
    }
}
