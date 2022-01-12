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
 * Copyright (c) 2022 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */


namespace Adyen\Payment\Model;

use Adyen\Payment\Api\Data\CreditmemoInterface;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Rule\Model\AbstractModel;

class Creditmemo extends AbstractModel implements CreditmemoInterface
{
    public function __construct(\Magento\Framework\Model\Context $context, \Magento\Framework\Registry $registry, \Magento\Framework\Data\FormFactory $formFactory, \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate, \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null, \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null, array $data = [], ExtensionAttributesFactory $extensionFactory = null, AttributeValueFactory $customAttributeFactory = null, \Magento\Framework\Serialize\Serializer\Json $serializer = null)
    {
        parent::__construct($context, $registry, $formFactory, $localeDate, $resource, $resourceCollection, $data, $extensionFactory, $customAttributeFactory, $serializer);
    }

    protected function _construct()
    {
        $this->_init(\Adyen\Payment\Model\ResourceModel\Creditmemo\Creditmemo::class);
    }


    /**
     * Gets the Pspreference for the creditmemo(capture).
     *
     * @return int|null Pspreference.
     */
    public function getPspreference()
    {
        return $this->getData(self::PSPREFERENCE);
    }

    /**
     * Sets Pspreference.
     *
     * @param string $pspreference
     * @return $this
     */
    public function setPspreference($pspreference)
    {
        return $this->setData(self::PSPREFERENCE, $pspreference);
    }

    /**
     * Gets the CreditmemoID for the creditmemo.
     *
     * @return int|null Creditmemo ID.
     */
    public function getCreditmemoId()
    {
        return $this->getData(self::INVOICE_ID);
    }

    /**
     * Sets CreditmemoID.
     *
     * @param int $creditmemoId
     * @return $this
     */
    public function setCreditmemoId($creditmemoId)
    {
        return $this->setData(self::INVOICE_ID, $creditmemoId);
    }

    /**
     * @return int|null
     */
    public function getAmount()
    {
        return $this->getData(self::AMOUNT);
    }

    /**
     * @param $amount
     * @return Creditmemo
     */
    public function setAmount($amount)
    {
        return $this->setData(self::AMOUNT, $amount);
    }

    /**
     * @return int|null
     */
    public function getAdyenPaymentOrderId()
    {
        return $this->getData(self::ADYEN_ORDER_PAYMENT_ID);
    }

    /**
     * @param $id
     * @return Creditmemo
     */
    public function setAdyenPaymentOrderId($id)
    {
        return $this->setData(self::ADYEN_ORDER_PAYMENT_ID, $id);
    }

    /**
     * @return string|null
     */
    public function getStatus()
    {
        return $this->getData(self::STATUS);
    }

    /**
     * @param $status
     * @return Creditmemo
     */
    public function setStatus($status)
    {
        return $this->setData(self::STATUS, $status);
    }

    /**
     * @return mixed
     */
    public function getCreatedAt()
    {
        return $this->getData(self::CREATED_AT);
    }

    /**
     * @param $createdAt
     * @return Creditmemo
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
     * @param $updatedAt
     * @return Creditmemo
     */
    public function setUpdatedAt($updatedAt)
    {
        return $this->setData(self::UPDATED_AT, $updatedAt);
    }
}