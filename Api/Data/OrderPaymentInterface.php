<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2021 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Api\Data;

use DateTime;

interface OrderPaymentInterface
{
    /**
     * Constants for keys of data array. Identical to the name of the getter in snake case.
     */
    const ENTITY_ID = 'entity_id';
    const PSPREFRENCE = 'pspreference';
    const MERCHANT_REFERENCE = 'merchant_reference';
    const PAYMENT_ID = 'payment_id';
    const PAYMENT_METHOD = 'payment_method';
    const AMOUNT = 'amount';
    const TOTAL_REFUNDED = 'total_refunded';
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';
    const CAPTURE_STATUS = 'capture_status';
    const TOTAL_CAPTURED = 'total_captured';


    // Either manual capture is not being used OR payment method does not support manual capture
    const CAPTURE_STATUS_AUTO_CAPTURE = 'Auto Captured';

    // Payment has been manually captured
    const CAPTURE_STATUS_MANUAL_CAPTURE = 'Manually Captured';

    // Payment has been partially manually captured
    const CAPTURE_STATUS_PARTIAL_CAPTURE = 'Partially Captured';

    // Payment has not been captured yet
    const CAPTURE_STATUS_NO_CAPTURE = 'Not captured';

    public function getEntityId();

    public function setEntityId($entityId);

    /**
     * @return string
     */
    public function getPspreference(): string;

    /**
     * @param string $pspReference
     * @return OrderPaymentInterface
     */
    public function setPspreference(string $pspReference): OrderPaymentInterface;

    /**
     * @return string
     */
    public function getMerchantReference(): string;

    /**
     * @param string $merchantReference
     * @return OrderPaymentInterface
     */
    public function setMerchantReference(string $merchantReference): OrderPaymentInterface;

    /**
     * @return int
     */
    public function getPaymentId(): int;

    /**
     * @param int $paymentId
     * @return OrderPaymentInterface
     */
    public function setPaymentId(int $paymentId): OrderPaymentInterface;

    /**
     * @return string|null
     */
    public function getPaymentMethod(): ?string;

    /**
     * @param string $paymentMethod
     * @return OrderPaymentInterface
     */
    public function setPaymentMethod(string $paymentMethod): OrderPaymentInterface;

    /**
     * @return float
     */
    public function getAmount(): float;

    /**
     * @param float $amount
     * @return OrderPaymentInterface
     */
    public function setAmount(float $amount): OrderPaymentInterface;

    /**
     * @return float
     */
    public function getTotalRefunded(): float;

    /**
     * @param float $totalRefunded
     * @return OrderPaymentInterface
     */
    public function setTotalRefunded(float $totalRefunded): OrderPaymentInterface;

    /**
     * @return DateTime
     */
    public function getCreatedAt(): DateTime;

    /**
     * @param DateTime $createdAt
     * @return OrderPaymentInterface
     */
    public function setCreatedAt(DateTime $createdAt): OrderPaymentInterface;

    /**
     * @return DateTime
     */
    public function getUpdatedAt(): DateTime;

    /**
     * @param DateTime $updatedAt
     * @return OrderPaymentInterface
     */
    public function setUpdatedAt(DateTime $updatedAt): OrderPaymentInterface;

    /**
     * @return string|null
     */
    public function getCaptureStatus(): ?string;

    /**
     * @param string $captureStatus
     * @return OrderPaymentInterface
     */
    public function setCaptureStatus(string $captureStatus): OrderPaymentInterface;

    /**
     * @return float|null
     */
    public function getTotalCaptured(): ?float;

    /**
     * @param float $totalCaptured
     * @return OrderPaymentInterface
     */
    public function setTotalCaptured(float $totalCaptured): OrderPaymentInterface;
}
