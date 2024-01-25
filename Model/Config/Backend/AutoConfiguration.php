<?php
/**
 * Adyen Payment Module
 *
 * Copyright (c) 2022 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Config\Backend;

use Adyen\Payment\Helper\BaseUrlHelper;
use Adyen\Payment\Helper\ManagementHelper;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;

class AutoConfiguration extends Value
{
    /**
     * @var ManagementHelper
     */
    private $managementApiHelper;
    /**
     * @var UrlInterface
     */
    private $url;
    /**
     * @var BaseUrlHelper
     */
    private $baseUrlHelper;

    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        ManagementHelper $managementApiHelper,
        UrlInterface $url,
        BaseUrlHelper $baseUrlHelper,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
        $this->managementApiHelper = $managementApiHelper;
        $this->url = $url;
        $this->baseUrlHelper = $baseUrlHelper;
    }

    public function beforeSave()
    {
        if ('auto' === $this->getValue()) {
            $demoMode = (int)$this->getFieldsetDataValue('demo_mode');
            $environment = $demoMode ? 'test' : 'live';

            $apiKey = $this->getFieldsetDataValue('api_key_' . $environment);
            $client = $this->managementApiHelper->getAdyenApiClient($apiKey, $demoMode);
            $configuredOrigins = $this->managementApiHelper->getAllowedOrigins($client);

            $domain = $this->baseUrlHelper->getDomainFromUrl($this->url->getBaseUrl());
            if (!in_array($domain, $configuredOrigins)) {
                $this->managementApiHelper->saveAllowedOrigin($client, $domain);
            }
        }
        return parent::beforeSave();
    }
}
