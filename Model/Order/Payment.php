<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

// phpcs:disable Generic.CodeAnalysis.UselessOverridingMethod.Found
namespace Adyen\Payment\Model\Order;

use Adyen\Payment\Api\Data\OrderPaymentInterface;
use DateTime;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;

class Payment extends AbstractModel implements OrderPaymentInterface
{
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
        $this->_init(\Adyen\Payment\Model\ResourceModel\Order\Payment::class);
    }

    public function getPspreference(): string
    {
        return $this->getData(self::PSPREFRENCE);
    }

    public function setPspreference(string $pspReference): OrderPaymentInterface
    {
        return $this->setData(self::PSPREFRENCE, $pspReference);
    }

    public function getMerchantReference(): string
    {
        return $this->getData(self::MERCHANT_REFERENCE);
    }

    public function setMerchantReference(string $merchantReference): OrderPaymentInterface
    {
        return $this->setData(self::MERCHANT_REFERENCE, $merchantReference);
    }

    public function getPaymentId(): int
    {
        return $this->getData(self::PAYMENT_ID);
    }

    public function setPaymentId(int $paymentId): OrderPaymentInterface
    {
        return $this->setData(self::PAYMENT_ID, $paymentId);
    }

    public function getPaymentMethod(): string
    {
        return $this->getData(self::PAYMENT_METHOD);
    }

    public function setPaymentMethod(string $paymentMethod): OrderPaymentInterface
    {
        return $this->setData(self::PAYMENT_METHOD, $paymentMethod);
    }

    public function getAmount(): int
    {
        return $this->getData(self::AMOUNT);
    }

    public function setAmount(int $amount): OrderPaymentInterface
    {
        return $this->setData(self::AMOUNT, $amount);
    }

    public function getTotalRefunded(): int
    {
        return $this->getData(self::TOTAL_REFUNDED);
    }

    public function setTotalRefunded(int $totalRefunded): OrderPaymentInterface
    {
        return $this->setData(self::TOTAL_REFUNDED, $totalRefunded);
    }

    public function getCreatedAt(): DateTime
    {
        return $this->getData(self::CREATED_AT);
    }

    public function setCreatedAt(DateTime $createdAt): OrderPaymentInterface
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }

    public function getUpdatedAt(): DateTime
    {
        return $this->getData(self::UPDATED_AT);
    }

    public function setUpdatedAt(DateTime $updatedAt): OrderPaymentInterface
    {
        return $this->setData(self::UPDATED_AT, $updatedAt);
    }

    public function setCaptureStatus(string $captureStatus): OrderPaymentInterface
    {
        return $this->setData(self::CAPTURE_STATUS, $captureStatus);
    }

    public function getCaptureStatus(): string
    {
        return $this->getData(self::CAPTURE_STATUS);
    }

    public function getTotalCaptured(): ?int
    {
        return $this->getData(self::TOTAL_CAPTURED);
    }

    public function setTotalCaptured(int $totalCaptured): OrderPaymentInterface
    {
        return $this->setData(self::TOTAL_CAPTURED, $totalCaptured);
    }
}
