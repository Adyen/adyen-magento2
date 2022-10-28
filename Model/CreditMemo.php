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

use Adyen\Payment\Api\Data\CreditMemoInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;

class CreditMemo extends AbstractModel implements CreditMemoInterface
{
    /**
     * CreditMemo constructor.
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
        $this->_init(\Adyen\Payment\Model\ResourceModel\CreditMemo\CreditMemo::class);
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
    public function setPspreference($pspreference): CreditMemo
    {
        return $this->setData(self::PSPREFERENCE, $pspreference);
    }

    /**
     * @return array|mixed|null
     */
    public function getOriginalReference()
    {
        return $this->getData(self::ORIGINAL_REFERENCE);
    }

    /**
     * @param $originalReference
     * @return $this
     */
    public function setOriginalReference($originalReference): CreditMemo
    {
        return $this->setData(self::ORIGINAL_REFERENCE, $originalReference);
    }

    /**
     * Gets the InvoiceID for the invoice.
     *
     * @return int|null CreditMemoId.
     */
    public function getCreditMemoId(): ?int
    {
        return $this->getData(self::CREDITMEMO_ID);
    }

    /**
     * Sets InvoiceID.
     *
     * @param int $CreditMemoId
     * @return $this
     */
    public function setCreditMemoId($creditMemoId): CreditMemo
    {
        return $this->setData(self::INVOICE_ID, $invoiceId);
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
     * @return Invoice
     */
    public function setAmount($amount): CreditMemo
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
     * @return CreditMemo
     */
    public function setAdyenPaymentOrderId($id): CreditMemo
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
     * @return CreditMemo
     */
    public function setStatus($status): CreditMemo
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
     * @return CreditMemo
     */
    public function setCreatedAt($createdAt): CreditMemo
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
     * @return CreditMemo
     */
    public function setUpdatedAt($updatedAt): CreditMemo
    {
        return $this->setData(self::UPDATED_AT, $updatedAt);
    }
}
