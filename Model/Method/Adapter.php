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
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2015 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Method;

use Magento\Payment\Model\Method;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface;
use Magento\Payment\Gateway\Command\CommandManagerInterface;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Magento\Payment\Gateway\Config\ValueHandlerPoolInterface;
use Magento\Payment\Gateway\Validator\ValidatorPoolInterface;

class Adapter extends Method\Adapter
{
    /**
     * @var \Adyen\Payment\Model\Api\PaymentRequest
     */
    protected $_paymentRequest;

    /**
     * Adapter constructor.
     *
     * @param \Adyen\Payment\Model\Api\PaymentRequest $paymentRequest
     * @param ManagerInterface $eventManager
     * @param ValueHandlerPoolInterface $valueHandlerPool
     * @param PaymentDataObjectFactory $paymentDataObjectFactory
     * @param string $code
     * @param string $formBlockType
     * @param string $infoBlockType
     * @param CommandPoolInterface|null $commandPool
     * @param ValidatorPoolInterface|null $validatorPool
     * @param CommandManagerInterface|null $commandExecutor
     */
    public function __construct(
        \Adyen\Payment\Model\Api\PaymentRequest $paymentRequest,
        ManagerInterface $eventManager,
        ValueHandlerPoolInterface $valueHandlerPool,
        PaymentDataObjectFactory $paymentDataObjectFactory,
        $code,
        $formBlockType,
        $infoBlockType,
        CommandPoolInterface $commandPool = null,
        ValidatorPoolInterface $validatorPool = null,
        \Magento\Payment\Gateway\Command\CommandManagerInterface $commandExecutor = null
    ) {
        parent::__construct(
            $eventManager,
            $valueHandlerPool,
            $paymentDataObjectFactory,
            $code,
            $formBlockType,
            $infoBlockType,
            $commandPool,
            $validatorPool,
            $commandExecutor
        );

        $this->_paymentRequest = $paymentRequest;
    }


    /**
     * @param \Adyen\Payment\Model\Billing\Agreement $agreement
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function updateBillingAgreementStatus(\Adyen\Payment\Model\Billing\Agreement $agreement)
    {
        $targetStatus = $agreement->getStatus();
        if ($targetStatus == \Magento\Paypal\Model\Billing\Agreement::STATUS_CANCELED) {
            try {
                $this->_paymentRequest->disableRecurringContract(
                    $agreement->getReferenceId(),
                    $agreement->getCustomerReference(),
                    $agreement->getStoreId()
                );
            } catch (\Exception $e) {
                throw new \Magento\Framework\Exception\LocalizedException(__('Failed to disable this contract'));
            }
        }
        return $this;
    }
}
