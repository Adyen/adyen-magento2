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

interface NotificationInterface
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
     * Pspreference.
     */
    const ORIGINAL_REFERENCE = 'original_reference';
    /*
     * Merchantreference
     */
    const MERCHANT_REFERENCE = 'merchant_reference';
    /*
     * Eventcode
     */
    const EVENT_CODE = 'event_code';
    /*
     * Success
     */
    const SUCCESS = 'success';
    /*
     * Paymentmethod
     */
    const PAYMENT_METHOD = 'payment_method';
    /*
     * Amount value
     */
    const AMOUNT_VALUE = 'amount_value';
    /*
     * Amount currency
     */
    const AMOUNT_CURRENCY = 'amount_currency';
    /*
     * Reason
     */
    const REASON = 'reason';
    /*
     * Live
     */
    const LIVE = 'live';
    /*
     * Done
     */
    const DONE = 'done';
    /*
     * Additional data
     */
    const ADDITIONAL_DATA = 'additional_data';
    /*
     * Processing
     */
    const PROCESSING = 'processing';
    /*
     * Error count
     */
    const ERROR_COUNT = 'error_count';
    /*
     * Error message
     */
    const ERROR_MESSAGE = 'error_message';
    /*
     * Created-at timestamp.
     */
    const CREATED_AT = 'created_at';
    /*
     * Updated-at timestamp.
     */
    const UPDATED_AT = 'updated_at';

    /**
     * Gets the ID for the notification.
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
     * Gets the Pspreference for the notification.
     *
     * @return string|null Pspreference.
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
     * Sets OriginalReference.
     *
     * @param string $originalReference
     * @return $this
     */
    public function setOriginalReference($originalReference);

    /**
     * Gets the OriginalReference for the notification.
     *
     * @return string|null OriginalReference.
     */
    public function getOriginalReference();

    /**
     * Gets the Merchantreference for the notification.
     *
     * @return string|null MerchantReference.
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
     * Gets the Eventcode for the notification.
     *
     * @return string|null Eventcode.
     */
    public function getEventCode();

    /**
     * Sets EventCode.
     *
     * @param string $eventCode
     * @return $this
     */
    public function setEventCode($eventCode);

    /**
     * Gets the success for the notification.
     *
     * @return int|null Success.
     */
    public function getSuccess();

    /**
     * Sets Success.
     *
     * @param boolean $success
     * @return $this
     */
    public function setSuccess($success);

    /**
     * Gets the Paymentmethod for the notification.
     *
     * @return string|null PaymentMethod.
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
     * Gets the AmountValue for the notification.
     *
     * @return int|null AmountValue.
     */
    public function getAmountValue();

    /**
     * Sets AmountValue.
     *
     * @param string $amountValue
     * @return $this
     */
    public function setAmountValue($amountValue);

    /**
     * Gets the AmountCurrency for the notification.
     *
     * @return string|null AmountCurrency.
     */
    public function getAmountCurrency();

    /**
     * Sets AmountCurrency.
     *
     * @param string $amountCurrency
     * @return $this
     */
    public function setAmountCurrency($amountCurrency);

    /**
     * Gets the Reason for the notification.
     *
     * @return int|null Reason.
     */
    public function getReason();

    /**
     * Sets Reason.
     *
     * @param string $reason
     * @return $this
     */
    public function setReason($reason);

    /**
     * Gets the AdditionalData for the notification.
     *
     * @return int|null AdditionalData.
     */
    public function getAdditionalData();

    /**
     * Sets AdditionalData.
     *
     * @param string $additionalData
     * @return $this
     */
    public function setAdditionalData($additionalData);

    /**
     * Gets the Done for the notification.
     *
     * @return int|null Done.
     */
    public function getDone();

    /**
     * Sets Done.
     *
     * @param string $done
     * @return $this
     */
    public function setDone($done);

    /**
     * Gets the created-at timestamp for the notification.
     *
     * @return string|null Created-at timestamp.
     */
    public function getCreatedAt();

    /**
     * Sets the created-at timestamp for the notification.
     *
     * @param string $createdAt timestamp
     * @return $this
     */
    public function setCreatedAt($createdAt);

    /**
     * Gets the updated-at timestamp for the notification.
     *
     * @return string|null Updated-at timestamp.
     */
    public function getUpdatedAt();

    /**
     * Sets the updated-at timestamp for the notification.
     *
     * @param string $timestamp
     * @return $this
     */
    public function setUpdatedAt($timestamp);
}
