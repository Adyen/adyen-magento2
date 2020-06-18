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
 * Copyright (c) 2018 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */
// phpcs:disable Generic.CodeAnalysis.UselessOverridingMethod.Found
namespace Adyen\Payment\Model;

use Adyen\Payment\Api\Data\InvoiceInterface;

class Invoice extends \Magento\Framework\Model\AbstractModel implements InvoiceInterface
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
        $this->_init(\Adyen\Payment\Model\ResourceModel\Invoice::class);
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
     * @return mixed
     */
    public function getOriginalReference()
    {
        return $this->getData(self::ORIGINAL_REFERENCE);
    }

    /**
     * Sets the OriginalReference
     *
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
}
