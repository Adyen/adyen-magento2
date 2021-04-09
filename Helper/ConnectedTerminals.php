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

class ConnectedTerminals
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $session;

    /**
     * @var \Adyen\Payment\Helper\Data
     */
    protected $adyenHelper;

    public function __construct(
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Magento\Checkout\Model\Session $session
    ) {
        $this->adyenHelper = $adyenHelper;
        $this->session = $session;
    }

    /**
     * @return array|mixed
     * @throws \Adyen\AdyenException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
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
        } catch (\Adyen\AdyenException $e) {
            $this->adyenLogger->error(
                "The getConnectedTerminals response is empty check your Adyen configuration in Magento."
            );
            // return empty result
            return [];
        }

        return $responseData;
    }
}
