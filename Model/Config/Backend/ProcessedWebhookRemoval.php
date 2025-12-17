<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2025 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Config\Backend;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;

/**
 * Class ProcessedWebhookRemoval
 * @package Adyen\Payment\Model\Config\Backend
 */
class ProcessedWebhookRemoval extends Value
{
    /**
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param ManagerInterface $messageManager
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        private readonly ManagerInterface $messageManager,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * Shows a controller message notifying the user that the processed webhook removal has been enabled.
     *
     * @return ProcessedWebhookRemoval
     */
    public function afterSave(): ProcessedWebhookRemoval
    {
        if ($this->isValueChanged() && $this->getValue() === '1') {
            $numberOfDays = $this->getFieldsetDataValue('processed_webhook_removal_time');
            $message = __(
                'You enabled the automatic removal of Adyen\'s processed webhooks. Processed webhooks older than %1 days will be removed.',
                $numberOfDays
            );

            $this->messageManager->addWarningMessage($message);
        }

        return $this;
    }
}
