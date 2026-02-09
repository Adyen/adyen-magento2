<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2026 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */
namespace Adyen\Payment\Model\Method;

use Adyen\Payment\Logger\AdyenLogger;
use Magento\Framework\ObjectManagerInterface;
use UnexpectedValueException;

/**
 * Factory class for @see TxVariant
 */
class TxVariantFactory
{
    /**
     * Factory constructor
     *
     * @param AdyenLogger $adyenLogger
     * @param ObjectManagerInterface $objectManager
     * @param string $instanceName
     */
    public function __construct(
        protected AdyenLogger $adyenLogger,
        protected ObjectManagerInterface $objectManager,
        protected string $instanceName = '\\Adyen\\Payment\\Model\\Method\\TxVariant'
    ) { }

    /**
     * Create class instance with specified parameters
     *
     * @param array $data
     * @return TxVariant|null
     */
    public function create(array $data = []): ?TxVariant
    {
        try {
            return $this->objectManager->create($this->instanceName, $data);
        } catch (UnexpectedValueException $e) {
            $this->adyenLogger->error(sprintf(
                'Payment method instance could not be identified! The variant %s is not an Adyen wallet or alternative payment method. %s',
                $data['txVariant'],
                $e->getMessage()
            ));
            return null;
        }
    }
}
