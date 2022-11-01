<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2022 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */
// phpcs:disable Generic.CodeAnalysis.UselessOverridingMethod.Found
namespace Adyen\Payment\Model;

use Adyen\Payment\Api\Data\CreditmemoInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;

class Creditmemo extends AbstractModel implements CreditMemoInterface
{
    /**
     * Creditmemo constructor.
     *
     * @param Context $context
     * @param Registry $registry
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
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
        $this->_init(\Adyen\Payment\Model\ResourceModel\Creditmemo\Creditmemo::class);
    }

    /**
     * Gets the Pspreference for the creditmemo(capture).
     *
     * @return int|null Pspreference.
     */
    public function getPspreference(): ?int
    {
        return $this->getData(self::PSPREFERENCE);
    }

    /**
     * Sets Pspreference.
     *
     * @param string $pspreference
     * @return $this
     */
    public function setPspreference($pspreference): Creditmemo
    {
        return $this->setData(self::PSPREFERENCE, $pspreference);
    }

    /**
     * @return array|mixed|null
     */
    public function getOriginalReference(): mixed
    {
        return $this->getData(self::ORIGINAL_REFERENCE);
    }

    /**
     * @param $originalReference
     * @return $this
     */
    public function setOriginalReference($originalReference): Creditmemo
    {
        return $this->setData(self::ORIGINAL_REFERENCE, $originalReference);
    }

    /**
     * Gets the CreditmemoID for the invoice.
     *
     * @return int|null Creditmemo ID.
     */
    public function getCreditmemoId(): ?int
    {
        return $this->getData(self::CREDITMEMO_ID);
    }

    /**
     * Sets CreditmemoID.
     *
     * @param int $creditmemoId
     * @return $this
     */
    public function setCreditmemoId($creditmemoId): Creditmemo
    {
        return $this->setData(self::CREDITMEMO_ID, $creditmemoId);
    }

    /**
     * @return int|null
     */
    public function getAmount(): ?int
    {
        return $this->getData(self::AMOUNT);
    }

    /**
     * @param $amount
     * @return Creditmemo
     */
    public function setAmount($amount): Creditmemo
    {
        return $this->setData(self::AMOUNT, $amount);
    }

    /**
     * @return int|null
     */
    public function getAdyenPaymentOrderId(): ?int
    {
        return $this->getData(self::ADYEN_ORDER_PAYMENT_ID);
    }

    /**
     * @param $id
     * @return Creditmemo
     */
    public function setAdyenPaymentOrderId($id): Creditmemo
    {
        return $this->setData(self::ADYEN_ORDER_PAYMENT_ID, $id);
    }

    /**
     * @return string|null
     */
    public function getStatus(): ?string
    {
        return $this->getData(self::STATUS);
    }

    /**
     * @param $status
     * @return Creditmemo
     */
    public function setStatus($status): Creditmemo
    {
        return $this->setData(self::STATUS, $status);
    }

    /**
     * @return mixed
     */
    public function getCreatedAt(): mixed
    {
        return $this->getData(self::CREATED_AT);
    }

    /**
     * @param $createdAt
     * @return Creditmemo
     */
    public function setCreatedAt($createdAt): Creditmemo
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }

    /**
     * @return mixed
     */
    public function getUpdatedAt(): mixed
    {
        return $this->getData(self::UPDATED_AT);
    }

    /**
     * @param $updatedAt
     * @return Creditmemo
     */
    public function setUpdatedAt($updatedAt): Creditmemo
    {
        return $this->setData(self::UPDATED_AT, $updatedAt);
    }
}
