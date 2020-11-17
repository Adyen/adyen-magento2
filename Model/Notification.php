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
// phpcs:disable Generic.CodeAnalysis.UselessOverridingMethod.Found
namespace Adyen\Payment\Model;

use Adyen\Payment\Api\Data\NotificationInterface;

class Notification extends \Magento\Framework\Model\AbstractModel implements NotificationInterface
{
    const AUTHORISATION = 'AUTHORISATION';
    const PENDING = 'PENDING';
    const AUTHORISED = 'AUTHORISED';
    const RECEIVED = 'RECEIVED';
    const CANCELLED = 'CANCELLED';
    const REFUSED = 'REFUSED';
    const ERROR = 'ERROR';
    const REFUND = 'REFUND';
    const REFUND_FAILED = 'REFUND_FAILED';
    const CANCEL_OR_REFUND = 'CANCEL_OR_REFUND';
    const CAPTURE = 'CAPTURE';
    const CAPTURE_FAILED = 'CAPTURE_FAILED';
    const CANCELLATION = 'CANCELLATION';
    const POSAPPROVED = 'POS_APPROVED';
    const HANDLED_EXTERNALLY = 'HANDLED_EXTERNALLY';
    const MANUAL_REVIEW_ACCEPT = 'MANUAL_REVIEW_ACCEPT';
    const MANUAL_REVIEW_REJECT = 'MANUAL_REVIEW_REJECT';
    const RECURRING_CONTRACT = "RECURRING_CONTRACT";
    const REPORT_AVAILABLE = "REPORT_AVAILABLE";
    const ORDER_CLOSED = "ORDER_CLOSED";
    const OFFER_CLOSED = "OFFER_CLOSED";
    const MAX_ERROR_COUNT = 5;

    /**
     * Notification constructor.
     *
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
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
        $this->_init(\Adyen\Payment\Model\ResourceModel\Notification::class);
    }

    /**
     * Check if the Adyen Notification is already stored in the system
     *
     * @param $pspReference
     * @param $eventCode
     * @param $success
     * @param $originalReference
     * @param null $done
     * @return bool
     */
    public function isDuplicate($pspReference, $eventCode, $success, $originalReference, $done = null)
    {
        $result = $this->getResource()->getNotification($pspReference, $eventCode, $success, $originalReference, $done);
        return (empty($result)) ? false : true;
    }

    /**
     * @return mixed
     */
    public function getEntityId()
    {
        return $this->getData(self::ENTITY_ID);
    }

    /**
     * @param int $entityId
     * @return $this
     */
    public function setEntityId($entityId)
    {
        return $this->setData(self::ENTITY_ID, $entityId);
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
     * @return mixed
     */
    public function getOriginalReference()
    {
        return $this->getData(self::ORIGINAL_REFERENCE);
    }

    /**
     * @param string $originalReference
     * @return $this
     */
    public function setOriginalReference($originalReference)
    {
        return $this->setData(self::ORIGINAL_REFERENCE, $originalReference);
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
    public function getSuccess()
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
     * @return string|null AmountValue.
     */
    public function getAmountCurrency()
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
     * @return string|null Reason.
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
     * Gets the Reason for the notification.
     *
     * @return int|null Reason.
     */
    public function getLive()
    {
        return $this->getData(self::LIVE);
    }

    /**
     * @param $live
     * @return $this
     */
    public function setLive($live)
    {
        return $this->setData(self::LIVE, $live);
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
    public function setAdditionalData($additionalData)
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
     * Gets the Processing flag for the notification.
     *
     * @return bool Processing.
     */
    public function getProcessing()
    {
        return $this->getData(self::PROCESSING);
    }

    /**
     * Sets Processing flag.
     *
     * @param bool $processing
     * @return $this
     */
    public function setProcessing($processing)
    {
        return $this->setData(self::PROCESSING, $processing);
    }

    /**
     * Gets the Error Count for the notification.
     *
     * @return bool|null ErrorCount.
     */
    public function getErrorCount()
    {
        return $this->getData(self::ERROR_COUNT);
    }

    /**
     * Sets Error Count.
     *
     * @param bool $errorCount
     * @return $this
     */
    public function setErrorCount($errorCount)
    {
        return $this->setData(self::ERROR_COUNT, $errorCount);
    }

    /**
     * Gets the Error Message for the notification.
     *
     * @return string|null ErrorMessage
     */
    public function getErrorMessage()
    {
        return $this->getData(self::ERROR_MESSAGE);
    }

    /**
     * Sets Error Message.
     *
     * @param string $errorMessage
     * @return $this
     */
    public function setErrorMessage($errorMessage)
    {
        return $this->setData(self::ERROR_MESSAGE, $errorMessage);
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
