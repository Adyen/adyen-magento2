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

namespace Adyen\Payment\Model\PaymentMethod;

use Adyen\Payment\Api\Data\AdyenPaymentMethodRepositoryInterface;
use Adyen\Payment\Model\PaymentMethod\AdyenPaymentMethod as AdyenPaymentMethodModel;
use Adyen\Payment\Model\ResourceModel\AdyenPaymentMethod;
use Adyen\Payment\Model\ResourceModel\AdyenPaymentMethod\CollectionFactory;
use Magento\Framework\Exception\NoSuchEntityException;


class AdyenPaymentMethodRepository implements AdyenPaymentMethodRepositoryInterface
{
    /** @var AdyenPaymentMethodFactory */
    private $adyenPaymentMethodFactory;

    /** @var AdyenPaymentMethod */
    private $adyenPaymentMethodResource;

    /** @var CollectionFactory */
    private $adyenPaymentMethodCollectionFactory;

    public function __construct(
        AdyenPaymentMethodFactory $adyenPaymentMethodFactory,
        AdyenPaymentMethod  $adyenPaymentMethodResource,
        CollectionFactory $adyenPaymentMethodCollectionFactory
    ) {
        $this->adyenPaymentMethodFactory = $adyenPaymentMethodFactory;
        $this->adyenPaymentMethodResource = $adyenPaymentMethodResource;
        $this->adyenPaymentMethodCollectionFactory = $adyenPaymentMethodCollectionFactory;
    }

    /**
     * @param int $id
     * @return AdyenPaymentMethodModel
     * @throws NoSuchEntityException
     */
    public function getById(int $id): AdyenPaymentMethodModel
    {
        /** @var AdyenPaymentMethodModel $adyenPaymentMethod */
        $adyenPaymentMethod = $this->adyenPaymentMethodFactory->create();
        $this->adyenPaymentMethodResource->load($adyenPaymentMethod, $id);
        if (!$adyenPaymentMethod->getEntityId()) {
            throw new NoSuchEntityException(__('Unable to find AdyenPaymentMethod with ID "%1"', $id));
        }

        return $adyenPaymentMethod;
    }

    /**
     * @throws NoSuchEntityException
     */
    public function getByPaymentMethodName(string $paymentMethod): AdyenPaymentMethodModel
    {
        /** @var AdyenPaymentMethodModel $adyenPaymentMethod */
        $adyenPaymentMethod = $this->adyenPaymentMethodFactory->create();
        $this->adyenPaymentMethodResource->load($adyenPaymentMethod, $paymentMethod, AdyenPaymentMethodModel::PAYMENT_METHOD);
        if (!$adyenPaymentMethod->getEntityId()) {
            throw new NoSuchEntityException(__('Unable to find AdyenPaymentMethod with payment method "%1"', $paymentMethod));
        }

        return $adyenPaymentMethod;
    }
}
