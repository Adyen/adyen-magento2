<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2023 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */
// phpcs:disable Generic.CodeAnalysis.UselessOverridingMethod.Found
namespace Adyen\Payment\Model;

use Adyen\Payment\Api\Data\CreditmemoInterface;
use DateTime;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;

class Creditmemo extends AbstractModel implements CreditMemoInterface
{
    public function __construct(
        Context $context,
        Registry $registry,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
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
        $this->_init(ResourceModel\Creditmemo\Creditmemo::class);
    }

    public function getPspreference(): ?string
    {
        return $this->getData(self::PSPREFERENCE);
    }

    public function setPspreference(string $pspReference): Creditmemo
    {
        return $this->setData(self::PSPREFERENCE, $pspReference);
    }

    public function getOriginalReference(): string
    {
        return $this->getData(self::ORIGINAL_REFERENCE);
    }

    public function setOriginalReference(string $originalReference): Creditmemo
    {
        return $this->setData(self::ORIGINAL_REFERENCE, $originalReference);
    }

    public function getCreditmemoId(): ?int
    {
        return $this->getData(self::CREDITMEMO_ID);
    }

    public function setCreditmemoId(int $creditMemoId): Creditmemo
    {
        return $this->setData(self::CREDITMEMO_ID, $creditMemoId);
    }

    public function getAmount(): ?float
    {
        return $this->getData(self::AMOUNT);
    }

    public function setAmount(float $amount): Creditmemo
    {
        return $this->setData(self::AMOUNT, $amount);
    }

    public function getAdyenPaymentOrderId(): ?int
    {
        return $this->getData(self::ADYEN_ORDER_PAYMENT_ID);
    }

    public function setAdyenPaymentOrderId(int $id): Creditmemo
    {
        return $this->setData(self::ADYEN_ORDER_PAYMENT_ID, $id);
    }

    public function getStatus(): ?string
    {
        return $this->getData(self::STATUS);
    }

    public function setStatus(string $status): Creditmemo
    {
        return $this->setData(self::STATUS, $status);
    }

    public function getCreatedAt(): ?DateTime
    {
        return $this->getData(self::CREATED_AT);
    }

    public function setCreatedAt(DateTime $createdAt): Creditmemo
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }

    public function getUpdatedAt(): ?DateTime
    {
        return $this->getData(self::UPDATED_AT);
    }

    public function setUpdatedAt(DateTime $updatedAt): Creditmemo
    {
        return $this->setData(self::UPDATED_AT, $updatedAt);
    }
}
