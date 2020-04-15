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
 * Copyright (c) 2020 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;


class Config extends AbstractHelper
{

    const XML_PAYMENT_PREFIX = "payment";
    const XML_ADYEN_ABSTRACT_PREFIX = "adyen_abstract";
    const XML_NOTIFICATIONS_CAN_CANCEL_FIELD = "notifications_can_cancel";

    /**
     * @var Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * Config constructor.
     * @param Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Retrieve flag for notifications_can_cancel
     *
     * @param int $storeId
     * @return bool
     */
    public function getNotificationsCanCancel($storeId = null)
    {
        return (bool)$this->getConfigData(
            self::XML_NOTIFICATIONS_CAN_CANCEL_FIELD,
            self::XML_ADYEN_ABSTRACT_PREFIX,
            $storeId,
            true
        );
    }

    /**
     * Retrieve information from payment configuration
     *
     * @param string $field
     * @param string $xmlPrefix
     * @param int $storeId
     * @param bool|false $flag
     * @return bool|mixed
     */
    private function getConfigData($field, $xmlPrefix, $storeId, $flag = false)
    {
        $path = implode("/", [self::XML_PAYMENT_PREFIX, $xmlPrefix, $field]);

        if (!$flag) {
            return $this->scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
        } else {
            return $this->scopeConfig->isSetFlag($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
        }
    }
}