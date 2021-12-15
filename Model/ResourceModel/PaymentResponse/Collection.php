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

//    /**
//     * @param EntityFactoryInterface $entityFactory
//     * @param Logger $logger
//     * @param FetchStrategyInterface $fetchStrategy
//     * @param ManagerInterface $eventManager
//     * @param AdapterInterface|null $connection
//     * @param AbstractDb|null $resource
//     * @param SerializerInterface $serializer
//     */
//    public function __construct(
//        EntityFactoryInterface $entityFactory,
//        Logger $logger,
//        FetchStrategyInterface $fetchStrategy,
//        ManagerInterface $eventManager,
//        SerializerInterface $serializer,
//        AdapterInterface $connection = null,
//        AbstractDb $resource = null
//    ) {
//        parent::__construct($entityFactory, $logger, $fetchStrategy, $eventManager, $connection, $resource);
//        $this->serializer = $serializer;
//        $this->_init(
//            PaymentResponse::class,
//            ResourceModel::class
//        );
//    }

//    protected function _construct()
//    {
////        parent::_construct();
//        $this->_init(
//            PaymentResponse::class,
//            ResourceModel::class
//        );
//    }

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

    /**
     * Fetch payment response with specific payment id
     *
     * @param int $paymentId
     * @return array|null
     */
    public function getPaymentResponseByPaymentId(int $paymentId) {
        // TODO: Fix this method
        return $this->addFieldToFilter('payment_id', $paymentId)->getData(); // TODO: Make sure this returns PaymentResponse object
    }

    /**
     * @param string $incrementId
     * @param int $storeId
     * @return \Magento\Framework\DataObject
     */
    public function getPaymentResponseByIncrementAndStoreId(string $incrementId, int $storeId) {
        $this->addFieldToFilter(PaymentResponse::MERCHANT_REFERENCE, $incrementId);
        $this->addFieldToFilter(PaymentResponse::STORE_ID, $storeId);

        if (count($this->getData()) > 0) {
            // Not possible to use getFirstItem directly on the selection because the collection is already loaded.
            // Thus, first clone the collection, clear to unload, setPageSize(1) to not
            // load full collection (but should be max count of 1 anyway), now do getFirstItem
            // TODO: Try just doing clear on the current collection
            $collectionClone = clone $this;
            return $collectionClone->clear()->setPageSize(1)->getFirstItem();
        } else {
            return null;
        }
    }
}
