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

/**
 * Class TransactionSale
 */
class TransactionPayment implements ClientInterface
{

    /**
     * @var \Adyen\Payment\Helper\Data
     */
    private $adyenHelper;


    /**
     * TransactionPayment constructor.
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     */
    public function __construct(
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->adyenHelper = $adyenHelper;
        $storeId = $storeManager->getStore()->getId();
        $this->_client = $this->adyenHelper->initializeAdyenClient($storeId);
    }

    /**
     * @param \Magento\Payment\Gateway\Http\TransferInterface $transferObject
     * @return mixed
     * @throws ClientException
     */
    public function placeRequest(\Magento\Payment\Gateway\Http\TransferInterface $transferObject)
    {
        $request = $transferObject->getBody();
        $client = $this->adyenHelper->initializeAdyenClient();

        $service = new \Adyen\Service\Checkout($client);

        try {
            $response = $service->payments($request);
        } catch (\Adyen\AdyenException $e) {
            $response['error'] = $e->getMessage();
        }

        return $response;
    }
}
