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

    /**
     * @return int|null Entity ID.
     */
    public function getEntityId();

    /**
     * @param int $entityId
     * @return $this
     */
    public function setEntityId($entityId);

    public function getPspreference(): string;

    public function setPspreference(string $pspReference): OrderPaymentInterface;

    public function getMerchantReference(): string;

    public function setMerchantReference(string $merchantReference): OrderPaymentInterface;

    public function getPaymentId(): int;

    public function setPaymentId(int $paymentId): OrderPaymentInterface;

    public function getPaymentMethod(): ?string;

    public function setPaymentMethod(string $paymentMethod): OrderPaymentInterface;

    public function getAmount(): int;

    public function setAmount(int $amount): OrderPaymentInterface;

    public function getTotalRefunded(): int;

    public function setTotalRefunded(int $totalRefunded): OrderPaymentInterface;

    public function getCreatedAt(): DateTime;

    public function setCreatedAt(DateTime $createdAt): OrderPaymentInterface;

    public function getUpdatedAt(): DateTime;

    public function setUpdatedAt(DateTime $updatedAt): OrderPaymentInterface;

    public function getCaptureStatus(): ?string;

    public function setCaptureStatus(string $captureStatus): OrderPaymentInterface;

    public function getTotalCaptured(): ?int;

    public function setTotalCaptured(int $totalCaptured): OrderPaymentInterface;
}
