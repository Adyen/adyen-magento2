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
 * Copyright (c) 2021 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */
// phpcs:disable Generic.CodeAnalysis.UselessOverridingMethod.Found
namespace Adyen\Payment\Model;

use Adyen\Payment\Api\Data\InvoiceInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;

class Invoice extends AbstractModel implements InvoiceInterface
{
    /**
     * Notification constructor.
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
        $this->_init(\Adyen\Payment\Model\ResourceModel\Invoice\Invoice::class);
    }

    /**
     * Gets the Pspreference for the invoice(capture).
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
     * Gets the Pspreference of the original Payment
     *
     * @deprecated
     * @return mixed
     */
    public function getOriginalReference()
    {
        return $this->getData(self::ORIGINAL_REFERENCE);
    }

    /**
     * Sets the OriginalReference
     *
     * @deprecated
     * @param $originalReference
     * @return $this
     */
    public function setOriginalReference($originalReference)
    {
        return $this->setData(self::ORIGINAL_REFERENCE, $originalReference);
    }

    /**
     * Gets the AcquirerReference for the invoice.
     *
     * @return int|null Acquirerreference.
     */
    public function getAcquirerReference()
    {
        return $this->getData(self::ACQUIRER_REFERENCE);
    }

    /**
     * Sets AcquirerReference.
     *
     * @param string $acquirerReference
     * @return $this
     */
    public function setAcquirerReference($acquirerReference)
    {
        return $this->setData(self::ACQUIRER_REFERENCE, $acquirerReference);
    }

    /**
     * Gets the InvoiceID for the invoice.
     *
     * @return int|null Invoice ID.
     */
    public function getInvoiceId()
    {
        return $this->getData(self::INVOICE_ID);
    }

    /**
     * Sets InvoiceID.
     *
     * @param int $invoiceId
     * @return $this
     */
    public function setInvoiceId($invoiceId)
    {
        return $this->setData(self::INVOICE_ID, $invoiceId);
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
     * @return Invoice
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
     * @return Invoice
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
     * @return Invoice
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
     * @return Invoice
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
     * @return Invoice
     */
    public function setUpdatedAt($updatedAt)
    {
        return $this->setData(self::UPDATED_AT, $updatedAt);
    }
}
