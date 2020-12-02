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

namespace Adyen\Payment\Api\Data;

interface OrderPaymentInterface
{
    /**
     * Constants for keys of data array. Identical to the name of the getter in snake case.
     */

    /*
     * Entity ID.
     */
    const ENTITY_ID = 'entity_id';
    /*
     * Pspreference.
     */
    const PSPREFRENCE = 'pspreference';
    /*
     * Merchantreference
     */
    const MERCHANT_REFERENCE = 'merchant_reference';
    /*
    * payment_id
    */
    const PAYMENT_ID = 'payment_id';
    /*
     * Paymentmethod
     */
    const PAYMENT_METHOD = 'payment_method';
    /*
     * Amount
     */
    const AMOUNT = 'amount';
    /*
     * Amount
     */
    const TOTAL_REFUNDED = 'total_refunded';
    /*
     * Created-at timestamp.
     */
    const CREATED_AT = 'created_at';
    /*
     * Updated-at timestamp.
     */
    const UPDATED_AT = 'updated_at';

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
}
