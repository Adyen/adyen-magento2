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
 * Copyright (c) 2021 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Api\Data;

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
     * Gets the ID for the payment.
     *
     * @return int|null Entity ID.
     */
    public function getEntityId();

    /**
     * Sets entity ID.
     *
     * @param int $entityId
     * @return $this
     */
    public function setEntityId($entityId);

    /**
     * Gets the Pspreference for the payment.
     *
     * @return int|null Pspreference.
     */
    public function getPspreference();

    /**
     * Sets Pspreference.
     *
     * @param string $pspreference
     * @return $this
     */
    public function setPspreference($pspreference);

    /**
     * Gets the Merchantreference for the payment.
     *
     * @return int|null MerchantReference.
     */
    public function getMerchantReference();

    /**
     * Sets MerchantReference.
     *
     * @param string $merchantReference
     * @return $this
     */
    public function setMerchantReference($merchantReference);

    /**
     * Gets the PaymentId for the payment.
     *
     * @return int|null PaymentId.
     */
    public function getPaymentId();

    /**
     * Sets PaymentId.
     *
     * @param string $paymentId
     * @return $this
     */
    public function setPaymentId($paymentId);

    /**
     * Gets the Paymentmethod for the payment.
     *
     * @return int|null PaymentMethod.
     */
    public function getPaymentMethod();

    /**
     * Sets PaymentMethod.
     *
     * @param string $paymentMethod
     * @return $this
     */
    public function setPaymentMethod($paymentMethod);

    /**
     * Gets the Amount for the payment.
     *
     * @return int|null Amount.
     */
    public function getAmount();

    /**
     * Sets Amount.
     *
     * @param string $amount
     * @return $this
     */
    public function setAmount($amount);

    /**
     * Gets the TotalRefunded for the payment.
     *
     * @return int|null TotalRefunded.
     */
    public function getTotalRefunded();

    /**
     * Sets Total Refunded.
     *
     * @param string $totalRefunded
     * @return $this
     */
    public function setTotalRefunded($totalRefunded);

    /**
     * Gets the created-at timestamp for the payment.
     *
     * @return string|null Created-at timestamp.
     */
    public function getCreatedAt();

    /**
     * Sets the created-at timestamp for the payment.
     *
     * @param string $createdAt timestamp
     * @return $this
     */
    public function setCreatedAt($createdAt);

    /**
     * Gets the updated-at timestamp for the payment.
     *
     * @return string|null Updated-at timestamp.
     */
    public function getUpdatedAt();

    /**
     * Sets the updated-at timestamp for the payment.
     *
     * @param string $timestamp
     * @return $this
     */
    public function setUpdatedAt($timestamp);

    /**
     * Sets the captured field for the payment
     *
     * @param $captured
     */
    public function setCaptureStatus($captured);

    /**
     * Gets the captured field for the payment
     *
     * @return mixed
     */
    public function getCaptureStatus();

    /**
     * Gets the TotalCaptured for the payment.
     *
     * @return int|null
     */
    public function getTotalCaptured();

    /**
     * Sets Total Captured.
     *
     * @param $totalCaptured
     */
    public function setTotalCaptured($totalCaptured);
}
