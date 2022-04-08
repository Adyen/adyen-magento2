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
 * Copyright (c) 2022 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Config\Backend;

use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\ManagementHelper;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;

class WebhookCredentials extends Value
{
    /**
     * @var ManagementHelper
     */
    private $managementApiHelper;
    /**
     * @var Config
     */
    private $configHelper;

    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        ManagementHelper $managementApiHelper,
        Config $configHelper,
        array $data = []
    ) {
        $this->managementApiHelper = $managementApiHelper;
        $this->configHelper = $configHelper;
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    public function beforeSave()
    {
        $username = $this->getValue();
        $password = $this->getFieldsetDataValue('notification_password');
        $url = $this->getFieldsetDataValue('webhook_url');
        $mode = (int) $this->getFieldsetDataValue('demo_mode') ? 'test' : 'live';
        $apiKey = $this->getFieldsetDataValue('api_key_' . $mode);
        if (empty($apiKey) || preg_match('/^\*+$/', $apiKey)) {
            $apiKey = $this->configHelper->getApiKey($mode);
        }
        $merchantAccount = $this->getFieldsetDataValue('merchant_account');

        // (re)configure webhook credentials if any changes have been made
        $originalPassword = $this->configHelper->getNotificationsPassword();
        if (preg_match('/^\*+$/', $password)) {
            // Password contains '******', set to the saved password for API request
            $password = $originalPassword;
        }
        $passwordChanged = $password !== $originalPassword;
        $urlChanged = $this->getFieldsetDataValue('webhook_url') !== $this->configHelper->getWebhookUrl();

        if ($this->hasDataChanges() || $passwordChanged || $urlChanged) {
            $this->managementApiHelper->setupWebhookCredentials($apiKey, $merchantAccount, $username, $password, $url, 'test' === $mode);
        }

        return parent::beforeSave();
    }
}
