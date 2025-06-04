<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen N.V. (https://www.adyen.com/)
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
use Magento\Sales\Model\Order\Payment\Repository as MagentoPaymentRepository;
use Magento\Sales\Api\Data\OrderPaymentInterface as MagentoPaymentInterface;
use Magento\Framework\Pricing\Helper\Data as PricingData;

class Payment extends AbstractModel implements OrderPaymentInterface
{
    protected ?MagentoPaymentInterface $magentoPayment = null;
    protected MagentoPaymentRepository $magentoPaymentRepository;
    private PricingData $pricingData;

    public function __construct(
        Context $context,
        Registry $registry,
        PricingData $pricingData,
        MagentoPaymentRepository $magentoPaymentRepository,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->magentoPaymentRepository = $magentoPaymentRepository;
        $this->pricingData = $pricingData;
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    protected function _construct()
    {
        $this->_init(\Adyen\Payment\Model\ResourceModel\Order\Payment::class);
    }

    public function getMagentoPayment(): ?MagentoPaymentInterface
    {
        if (!$this->magentoPayment && $this->getPaymentId()) {
            $this->magentoPayment = $this->magentoPaymentRepository->get($this->getPaymentId());
        }

        return $this->magentoPayment;
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

    public function getAmount(): float
    {
        return $this->getData(self::AMOUNT);
    }

    public function setAmount(float $amount): OrderPaymentInterface
    {
        return $this->setData(self::AMOUNT, $amount);
    }

    public function getTotalRefunded(): float
    {
        return $this->getData(self::TOTAL_REFUNDED);
    }

    public function setTotalRefunded(float $totalRefunded): OrderPaymentInterface
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

    public function getTotalCaptured(): ?float
    {
        return $this->getData(self::TOTAL_CAPTURED);
    }

    public function setTotalCaptured(float $totalCaptured): OrderPaymentInterface
    {
        return $this->setData(self::TOTAL_CAPTURED, $totalCaptured);
    }

    public function getFormattedAmountWithCurrency(): string
    {
        return $this->pricingData->currency(
            $this->getAmount(),
            $this->getMagentoPayment()->getOrder()->getOrderCurrencyCode(),
            false
        );
    }

    public function getFormattedTotalRefundedWithCurrency(): string
    {
        return $this->pricingData->currency(
            $this->getTotalRefunded(),
            $this->getMagentoPayment()->getOrder()->getOrderCurrencyCode(),
            false
        );
    }

    public function getFormattedTotalCapturedWithCurrency(): string
    {
        return $this->pricingData->currency(
            $this->getTotalCaptured(),
            $this->getMagentoPayment()->getOrder()->getOrderCurrencyCode(),
            false
        );
    }

    /**
     * Set sort order.
     *
     * @param int $sortOrder
     * @return $this
     */
    public function setSortOrder($sortOrder)
    {
        return $this->setData('order_sort', $sortOrder);
    }

    /**
     * Get sort order.
     *
     * @return int|null
     */
    public function getSortOrder()
    {
        return $this->getData('order_sort');
    }
}
