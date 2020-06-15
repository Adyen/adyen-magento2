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

namespace Adyen\Payment\Api\Data;

interface InvoiceInterface
{
    /**
     * Constants for keys of data array. Identical to the name of the getter in snake case.
     */

    /*
     * Entity ID.
     */
    const ENTITY_ID = 'entity_id';
    /*
     * Pspreference of the capture.
     */
    const PSPREFERENCE = 'pspreference';
    /*
     * Original Pspreference of the payment.
     */
    const ORIGINAL_REFERENCE = 'original_reference';
    /*
     * Acquirer reference.
     */
    const ACQUIRER_REFERENCE = 'acquirer_reference';
    /*
     * Invoice ID.
     */
    const INVOICE_ID = 'invoice_id';

    /**
     * Gets the ID for the invoice.
     *
     * @return int|null Entity ID.
     */
    public function getEntityId();

    /**
     * Sets entity ID.
     *
     * @param int $entityId
     * @return $this
     */
    public function setEntityId($entityId);

    /**
     * Gets the Pspreference for the invoice(capture).
     *
     * @return int|null Pspreference.
     */
    public function getPspreference();

    /**
     * Sets Pspreference.
     *
     * @param string $pspreference
     * @return $this
     */
    public function setPspreference($pspreference);

    /**
     * @return mixed
     */
    public function getOriginalReference();

    /**
     * @param  $originalReference
     * @return mixed
     */
    public function setOriginalReference($originalReference);

    /**
     * Gets the AcquirerReference for the invoice.
     *
     * @return int|null Acquirerreference.
     */
    public function getAcquirerReference();

    /**
     * Sets AcquirerReference.
     *
     * @param string $acquirerReference
     * @return $this
     */
    public function setAcquirerReference($acquirerReference);

    /**
     * Gets the InvoiceID for the invoice.
     *
     * @return int|null Invoice ID.
     */
    public function getInvoiceId();

    /**
     * Sets InvoiceID.
     *
     * @param int $invoiceId
     * @return $this
     */
    public function setInvoiceId($invoiceId);
}
