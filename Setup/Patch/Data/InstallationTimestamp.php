<?php
/**
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2025 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */
declare(strict_types=1);

namespace Adyen\Payment\Setup\Patch\Data;

use Adyen\Payment\Api\Data\AnalyticsEventTypeEnum;
use Adyen\Payment\Api\Data\ConfigurationEventType;
use Adyen\Payment\Helper\AnalyticsEventState;
use Adyen\Payment\Helper\CheckoutAnalytics;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\DataPatch;
use DateTime;
use DateTimeInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class InstallationTimestamp implements DataPatchInterface
{
    public function __construct(
        private readonly WriterInterface $configWriter,
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly DataPatch $dataPatchHelper,
        private readonly ManagerInterface  $eventManager
    ) {}

    public function apply()
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        $installationTimestamp = $this->dataPatchHelper->findConfig(
            $this->moduleDataSetup,
            sprintf(
                "%s/%s/%s",
                Config::XML_PAYMENT_PREFIX,
                Config::XML_ADYEN_ANALYTICS_PREFIX,
                Config::XML_INSTALLATION_TIME
            ),
            null
        );

        if (empty($installationTimestamp)) {
            $timestamp = new DateTime();

            $this->configWriter->save(
                sprintf(
                    "%s/%s/%s",
                    Config::XML_PAYMENT_PREFIX,
                    Config::XML_ADYEN_ANALYTICS_PREFIX,
                    Config::XML_INSTALLATION_TIME
                ),
                $timestamp->format(DateTimeInterface::ISO8601_EXPANDED)
            );

            $this->eventManager->dispatch(AnalyticsEventState::EVENT_NAME, ['data' => [
                'type' => AnalyticsEventTypeEnum::EXPECTED_START->value,
                'topic' => CheckoutAnalytics::TOPIC_PLUGIN_CONFIGURATION_TIME,
                'relationId' => ConfigurationEventType::PLUGIN_INSTALLATION->value
            ]]);
        }
    }

    public static function getDependencies()
    {
        return [];
    }

    public function getAliases()
    {
        return [];
    }
}
