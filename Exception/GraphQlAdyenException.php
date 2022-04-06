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
namespace Adyen\Payment\Exception;

use GraphQL\Error\ClientAware;
use Magento\Framework\Exception\AggregateExceptionInterface;
use Magento\Framework\Exception\LocalizedException;

class GraphQlAdyenException extends AbstractAdyenException implements AggregateExceptionInterface, ClientAware
{
    const ADYEN_CATEGORY = 'adyen';

    /**
     * The array of errors that have been added via the addError() method
     *
     * @var LocalizedException[]
     */
    private $errors = [];

    /**
     * If code is in the safe error codes array, it can be displayed
     *
     * @return bool
     */
    public function isClientSafe(): bool
    {
        return in_array($this->getCode(), self::SAFE_ERROR_CODES);
    }

    /**
     * @return string
     */
    public function getCategory(): string
    {
        return self::ADYEN_CATEGORY;
    }

    /**
     * Add child error if used as aggregate exception
     *
     * @param LocalizedException $exception
     * @return $this
     */
    public function addError(LocalizedException $exception): self
    {
        $this->errors[] = $exception;
        return $this;
    }

    /**
     * Get child errors if used as aggregate exception
     *
     * @return LocalizedException[]
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
