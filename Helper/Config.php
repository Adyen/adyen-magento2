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

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;

class Config
{
    const XML_PAYMENT_PREFIX = "payment";
    const XML_ADYEN_ABSTRACT_PREFIX = "adyen_abstract";
    const XML_NOTIFICATIONS_CAN_CANCEL_FIELD = "notifications_can_cancel";
    const XML_NOTIFICATIONS_HMAC_CHECK = "notifications_hmac_check";
    const XML_NOTIFICATIONS_IP_CHECK = "notifications_ip_check";
    const XML_NOTIFICATIONS_HMAC_KEY_LIVE = "notification_hmac_key_live";
    const XML_NOTIFICATIONS_HMAC_KEY_TEST = "notification_hmac_key_test";
    const XML_CHARGED_CURRENCY = "charged_currency";

    /**
     * @var Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var EncryptorInterface
     */
    private $encryptor;

    /**
     * @var \Adyen\Payment\Helper\Data
     */
    private $adyenHelper;

    /**
     * Config constructor.
     *
     * @param Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param EncryptorInterface $encryptor
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        EncryptorInterface $encryptor,
        \Adyen\Payment\Helper\Data $adyenHelper
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->encryptor = $encryptor;
        $this->adyenHelper = $adyenHelper;
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
     * Retrieve flag for notifications_hmac_check
     *
     * @param int $storeId
     * @return bool
     */
    public function getNotificationsHmacCheck($storeId = null)
    {
        return (bool)$this->getConfigData(
            self::XML_NOTIFICATIONS_HMAC_CHECK,
            self::XML_ADYEN_ABSTRACT_PREFIX,
            $storeId,
            true
        );
    }

    /**
     * Retrieve flag for notifications_ip_check
     *
     * @param int $storeId
     * @return bool
     */
    public function getNotificationsIpCheck($storeId = null)
    {
        return (bool)$this->getConfigData(
            self::XML_NOTIFICATIONS_IP_CHECK,
            self::XML_ADYEN_ABSTRACT_PREFIX,
            $storeId,
            true
        );
    }

    /**
     * Retrieve key for notifications_hmac_key
     *
     * @param int $storeId
     * @return string
     */
    public function getNotificationsHmacKey($storeId = null)
    {
        if ($this->adyenHelper->isDemoMode($storeId)) {
            $key = $this->getConfigData(
                self::XML_NOTIFICATIONS_HMAC_KEY_TEST,
                self::XML_ADYEN_ABSTRACT_PREFIX,
                $storeId,
                false
            );
        } else {
            $key = $this->getConfigData(
                self::XML_NOTIFICATIONS_HMAC_KEY_LIVE,
                self::XML_ADYEN_ABSTRACT_PREFIX,
                $storeId,
                false
            );
        }
        return $this->encryptor->decrypt(trim($key));
    }

    /**
     * Check if alternative payment methods vault is enabled
     *
     * @param null|int|string $storeId
     * @return mixed
     */
    public function isStoreAlternativePaymentMethodEnabled($storeId = null)
    {
        return $this->adyenHelper->getAdyenHppVaultConfigDataFlag('active', $storeId);
    }

    /**
     * Retrive charged currency selection (base or display)
     *
     * @param null|int|string $storeId
     * @return mixed
     */
    public function getChargedCurrency($storeId = null)
    {
        return $this->adyenHelper->getAdyenAbstractConfigData(self::XML_CHARGED_CURRENCY, $storeId);
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
