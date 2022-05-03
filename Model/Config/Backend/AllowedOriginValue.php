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
 * Copyright (c) 2022 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Config\Backend;

use Adyen\Payment\Helper\ManagementHelper;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;

class AllowedOriginValue extends Value
{
    /**
     * @var ManagementHelper
     */
    private $managementApiHelper;

    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        ManagementHelper $managementApiHelper,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
        $this->managementApiHelper = $managementApiHelper;
    }

    public function beforeSave()
    {
        $demoMode = (int) $this->getFieldsetDataValue('demo_mode') ? 'test' : 'live';
        $apiKey = $this->getFieldsetDataValue('api_key_' . $demoMode);

        $configuredOrigins = $this->managementApiHelper->getAllowedOrigins($apiKey, $demoMode);
        $value = $this->getValue();
        if (!in_array($value, $configuredOrigins)) {
            $this->managementApiHelper->saveAllowedOrigin($apiKey, $demoMode, $value);
        }
        return parent::beforeSave();
    }
}
