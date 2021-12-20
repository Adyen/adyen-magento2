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
 * Copyright (c) 2021 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\ResourceModel\PaymentResponse;

use Adyen\Payment\Model\ResourceModel\PaymentResponse as ResourceModel;
use Adyen\Payment\Model\PaymentResponse;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\DataObject;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Framework\Serialize\SerializerInterface;
use Psr\Log\LoggerInterface as Logger;

class Collection extends AbstractCollection
{

    /**
     * @var SerializerInterface
     */
    protected $serializer;

    public function _construct()
    {
        $this->_init(
            PaymentResponse::class,
            ResourceModel::class
        );
    }

    /**
     * Fetch the payment responses for the merchant references supplied
     *
     * @param array $merchantReferences []
     * @return array|null
     */
    public function getPaymentResponsesWithMerchantReferences($merchantReferences = [])
    {
        return $this->addFieldToFilter('merchant_reference', ["in" => [$merchantReferences]])->getData();
    }
}
