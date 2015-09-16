<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Adyen\Payment\Model;

use Adyen\Payment\Api\Data\NotificationInterface;
use Magento\Framework\Object\IdentityInterface;

class Notification extends \Magento\Framework\Model\AbstractModel
    implements NotificationInterface
{

    const AUTHORISATION = 'AUTHORISATION';
    const PENDING = 'PENDING';
    const AUTHORISED = 'AUTHORISED';
    const CANCELLED = 'CANCELLED';
    const REFUSED = 'REFUSED';
    const ERROR = 'ERROR';
    const REFUND = 'REFUND';
    const REFUND_FAILED = 'REFUND_FAILED';
    const CANCEL_OR_REFUND  = 'CANCEL_OR_REFUND';
    const CAPTURE = 'CAPTURE';
    const CAPTURE_FAILED = 'CAPTURE_FAILED';
    const CANCELLATION = 'CANCELLATION';
    const POSAPPROVED = 'POS_APPROVED';
    const HANDLED_EXTERNALLY  = 'HANDLED_EXTERNALLY';
    const MANUAL_REVIEW_ACCEPT = 'MANUAL_REVIEW_ACCEPT';
    const MANUAL_REVIEW_REJECT = 'MANUAL_REVIEW_REJECT ';
    const RECURRING_CONTRACT = "RECURRING_CONTRACT";
    const REPORT_AVAILABLE = "REPORT_AVAILABLE";
    const ORDER_CLOSED = "ORDER_CLOSED";

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Model\Resource\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }


    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('Adyen\Payment\Model\Resource\Notification');
    }

    /**
     * Gets the Pspreference for the notification.
     *
     * @return int|null Pspreference.
     */
    public function getPspreference()
    {
        return $this->getData(self::PSPREFRENCE);
    }

    /**
     * Sets Pspreference.
     *
     * @param string $pspreference
     * @return $this
     */
    public function setPspreference($pspreference)
    {
        return $this->setData(self::PSPREFRENCE, $pspreference);
    }

    /**
     * Gets the Merchantreference for the notification.
     *
     * @return int|null MerchantReference.
     */
    public function getMerchantReference()
    {
        return $this->getData(self::MERCHANT_REFERENCE);
    }

    /**
     * Sets MerchantReference.
     *
     * @param string $merchantReference
     * @return $this
     */
    public function setMerchantReference($merchantReference)
    {
        return $this->setData(self::MERCHANT_REFERENCE, $merchantReference);
    }

    /**
     * Gets the Eventcode for the notification.
     *
     * @return int|null Eventcode.
     */
    public function getEventCode()
    {
        return $this->getData(self::EVENT_CODE);
    }

    /**
     * Sets EventCode.
     *
     * @param string $eventCode
     * @return $this
     */
    public function setEventCode($eventCode)
    {
        return $this->setData(self::EVENT_CODE, $eventCode);
    }

    /**
     * Gets the success for the notification.
     *
     * @return int|null Success.
     */
    public function getSucess()
    {
        return $this->getData(self::SUCCESS);
    }

    /**
     * Sets Success.
     *
     * @param boolean $success
     * @return $this
     */
    public function setSuccess($success)
    {
        return $this->setData(self::SUCCESS, $success);
    }


    /**
     * Gets the Paymentmethod for the notification.
     *
     * @return int|null PaymentMethod.
     */
    public function getPaymentMethod()
    {
        return $this->getData(self::PAYMENT_METHOD);
    }

    /**
     * Sets PaymentMethod.
     *
     * @param string $paymentMethod
     * @return $this
     */
    public function setPaymentMethod($paymentMethod)
    {
        return $this->setData(self::PAYMENT_METHOD, $paymentMethod);
    }

    /**
     * Gets the AmountValue for the notification.
     *
     * @return int|null AmountValue.
     */
    public function getAmountValue()
    {
        return $this->getData(self::AMOUNT_VALUE);
    }

    /**
     * Sets AmountValue.
     *
     * @param string $amountValue
     * @return $this
     */
    public function setAmountValue($amountValue)
    {
        return $this->setData(self::AMOUNT_VALUE, $amountValue);
    }

    /**
     * Gets the AmountValue for the notification.
     *
     * @return int|null AmountValue.
     */
    public function getAmountCurency()
    {
        return $this->getData(self::AMOUNT_CURRENCY);
    }

    /**
     * Sets AmountCurrency.
     *
     * @param string $amountCurrency
     * @return $this
     */
    public function setAmountCurrency($amountCurrency)
    {
        return $this->setData(self::AMOUNT_CURRENCY, $amountCurrency);
    }

    /**
     * Gets the Reason for the notification.
     *
     * @return int|null Reason.
     */
    public function getReason()
    {
        return $this->getData(self::REASON);
    }

    /**
     * Sets Reason.
     *
     * @param string $reason
     * @return $this
     */
    public function setReason($reason)
    {
        return $this->setData(self::REASON, $reason);
    }

    /**
     * Gets the AdditionalData for the notification.
     *
     * @return int|null AdditionalData.
     */
    public function getAdditionalData()
    {
        return $this->getData(self::ADDITIONAL_DATA);
    }

    /**
     * Sets AdditionalData.
     *
     * @param string $additionalData
     * @return $this
     */
    public function setAddtionalData($additionalData)
    {
        return $this->setData(self::ADDITIONAL_DATA, $additionalData);
    }

    /**
     * Gets the Done for the notification.
     *
     * @return int|null Done.
     */
    public function getDone()
    {
        return $this->getData(self::DONE);
    }

    /**
     * Sets Done.
     *
     * @param string $done
     * @return $this
     */
    public function setDone($done)
    {
        return $this->setData(self::DONE, $done);
    }

    /**
     * Gets the created-at timestamp for the notification.
     *
     * @return string|null Created-at timestamp.
     */
    public function getCreatedAt()
    {
        return $this->getData(self::CREATED_AT);
    }

    /**
     * Sets the created-at timestamp for the notification.
     *
     * @param string $createdAt timestamp
     * @return $this
     */
    public function setCreatedAt($createdAt)
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }

    /**
     * Gets the updated-at timestamp for the notification.
     *
     * @return string|null Updated-at timestamp.
     */
    public function getUpdatedAt()
    {
        return $this->getData(self::UPDATED_AT);
    }

    /**
     * Sets the updated-at timestamp for the notification.
     *
     * @param string $timestamp
     * @return $this
     */
    public function setUpdatedAt($timestamp)
    {
        return $this->setData(self::UPDATED_AT, $timestamp);
    }


}