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
 * Copyright (c) 2021 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */
declare(strict_types=1);

namespace Adyen\Payment\Model\Resolver\DataProvider;

use Adyen\Payment\Model\Api\AdyenOrderPaymentStatus;
use Adyen\Payment\Model\Api\AdyenPaymentDetails;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;

class GetAdyenPaymentStatus
{
    /**
     * @var AdyenOrderPaymentStatus
     */
    protected $adyenOrderPaymentStatusModel;
    /**
     * @var AdyenPaymentDetails
     */
    protected $adyenPaymentDetails;
    /**
     * @var Json
     */
    protected $jsonSerializer;

    /**
     * GetAdyenPaymentStatus constructor.
     * @param AdyenOrderPaymentStatus $adyenOrderPaymentStatusModel
     * @param AdyenPaymentDetails $adyenPaymentDetails
     * @param Json $jsonSerializer
     */
    public function __construct(
        AdyenOrderPaymentStatus $adyenOrderPaymentStatusModel,
        AdyenPaymentDetails $adyenPaymentDetails,
        Json $jsonSerializer
    ) {
        $this->adyenOrderPaymentStatusModel = $adyenOrderPaymentStatusModel;
        $this->adyenPaymentDetails = $adyenPaymentDetails;
        $this->jsonSerializer = $jsonSerializer;
    }

    /**
     * @param string $orderId
     * @return array
     */
    public function getGetAdyenPaymentStatus(string $orderId): array
    {
        $adyenPaymentStatus = $this->jsonSerializer->unserialize($this->adyenOrderPaymentStatusModel->getOrderPaymentStatus($orderId));
        return $this->formatResponse($adyenPaymentStatus);
    }

    /**
     * @param string $payload
     * @return array
     * @throws LocalizedException
     */
    public function getGetAdyenPaymentDetails(string $payload): array
    {
        $adyenPaymentDetails = $this->jsonSerializer->unserialize($this->adyenPaymentDetails->initiate($payload));
        return $this->formatResponse($adyenPaymentDetails);
    }

    /**
     * @param array $response
     * @return array
     */
    public function formatResponse(array $response): array
    {
        if (isset($response['action'])) {
            $response['action'] = $this->jsonSerializer->serialize($response['action']);
        }
        if (isset($response['additionalData'])) {
            $response['additionalData'] = $this->jsonSerializer->serialize($response['additionalData']);
        }
        return $response;
    }
}
