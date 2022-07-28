<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2022 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Http\Client;

use Adyen\Service\Modification;
use Magento\Payment\Gateway\Http\ClientInterface;

/**
 * Class TransactionSale
 */
class TransactionMotoCancel implements ClientInterface
{
    /**
     * @var \Adyen\Payment\Helper\Data
     */
    private $adyenHelper;

    /**
     * PaymentRequest constructor.
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     */
    public function __construct(
        \Adyen\Payment\Helper\Data $adyenHelper
    ) {
        $this->adyenHelper = $adyenHelper;
    }

    /**
     * @param \Magento\Payment\Gateway\Http\TransferInterface $transferObject
     * @return null
     */
    public function placeRequest(\Magento\Payment\Gateway\Http\TransferInterface $transferObject)
    {
        $request = $transferObject->getBody();

        $client = $this->adyenHelper->initializeAdyenClient(null, null, $request['merchantAccount']);
        $service = new Modification($client);

        try {
            $response = $service->cancel($request);
        } catch (\Adyen\AdyenException $e) {
            $response['error'] = $e->getMessage();
        }
        return $response;
    }
}
