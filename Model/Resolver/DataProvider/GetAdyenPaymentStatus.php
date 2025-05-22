<?php
/**
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

use Adyen\Payment\Exception\AdyenPaymentDetailsException;
use Adyen\Payment\Model\Api\AdyenOrderPaymentStatus;
use Adyen\Payment\Model\Api\AdyenPaymentsDetails;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Api\Data\OrderInterface;

class GetAdyenPaymentStatus
{
    /**
     * @var AdyenOrderPaymentStatus
     */
    protected $adyenOrderPaymentStatusModel;
    /**
     * @var AdyenPaymentsDetails
     */
    protected $adyenPaymentDetails;
    /**
     * @var Json
     */
    protected $jsonSerializer;

    /**
     * GetAdyenPaymentStatus constructor.
     * @param AdyenOrderPaymentStatus $adyenOrderPaymentStatusModel
     * @param AdyenPaymentsDetails $adyenPaymentDetails
     * @param Json $jsonSerializer
     */
    public function __construct(
        AdyenOrderPaymentStatus $adyenOrderPaymentStatusModel,
        AdyenPaymentsDetails    $adyenPaymentDetails,
        Json                    $jsonSerializer
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
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @param \Magento\Quote\Api\Data\CartInterface $cart
     * @return array
     * @throws \Adyen\Payment\Exception\AdyenPaymentDetailsException
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getGetAdyenPaymentDetails(string $payload, OrderInterface $order, CartInterface $cart): array
    {
        if ($order->getQuoteId() !== $cart->getId()) {
            throw new LocalizedException(__('Your QuoteId and CartId do not match'));
        }
        $adyenPaymentDetails = $this->jsonSerializer->unserialize(
            $this->adyenPaymentDetails->initiate(
                $payload,
                (string) $order->getEntityId()
            )
        );
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
