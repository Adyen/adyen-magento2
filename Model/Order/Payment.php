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
namespace Adyen\Payment\Model\Order;

use Adyen\Payment\Api\Data\OrderPaymentInterface;

class Payment extends \Magento\Framework\Model\AbstractModel implements OrderPaymentInterface
{
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
        $this->_init(\Adyen\Payment\Model\ResourceModel\Order\Payment::class);
    }

    /**
     * @return mixed
     */
    public function getPspreference()
    {
        return $this->getData(self::PSPREFRENCE);
    }

    /**
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
    public function getMerchantReference()
    {
        return $this->getData(self::MERCHANT_REFERENCE);
    }

    /**
     * @param string $merchantReference
     * @return $this
     */
    public function setMerchantReference($merchantReference)
    {
        return $this->setData(self::MERCHANT_REFERENCE, $merchantReference);
    }

    /**
     * @return mixed
     */
    public function getPaymentId()
    {
        return $this->getData(self::PAYMENT_ID);
    }

    /**
     * @param string $paymentId
     * @return $this
     */
    public function setPaymentId($paymentId)
    {
        return $this->setData(self::PAYMENT_ID, $paymentId);
    }

    /**
     * @return mixed
     */
    public function getPaymentMethod()
    {
        return $this->getData(self::PAYMENT_METHOD);
    }

    /**
     * @param string $paymentMethod
     * @return $this
     */
    public function setPaymentMethod($paymentMethod)
    {
        return $this->setData(self::PAYMENT_METHOD, $paymentMethod);
    }

    /**
     * @return mixed
     */
    public function getAmount()
    {
        return $this->getData(self::AMOUNT);
    }

    /**
     * @param string $amount
     * @return $this
     */
    public function setAmount($amount)
    {
        return $this->setData(self::AMOUNT, $amount);
    }

    /**
     * @return mixed
     */
    public function getTotalRefunded()
    {
        return $this->getData(self::TOTAL_REFUNDED);
    }

    /**
     * @param string $totalRefunded
     * @return $this
     */
    public function setTotalRefunded($totalRefunded)
    {
        return $this->setData(self::TOTAL_REFUNDED, $totalRefunded);
    }

    /**
     * @return mixed
     */
    public function getCreatedAt()
    {
        return $this->getData(self::CREATED_AT);
    }

    /**
     * @param string $createdAt
     * @return $this
     */
    public function setCreatedAt($createdAt)
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }

    /**
     * @return mixed
     */
    public function getUpdatedAt()
    {
        return $this->getData(self::UPDATED_AT);
    }

    /**
     * @param string $updatedAt
     * @return $this
     */
    public function setUpdatedAt($updatedAt)
    {
        return $this->setData(self::UPDATED_AT, $updatedAt);
    }
}
