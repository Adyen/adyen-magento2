<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Test\Unit\Helper;

use Adyen\Payment\Helper\DataPatch;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\DB\Select;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;

class DataPatchTest extends AbstractAdyenTestCase
{
    const TEST_CONFIG_PATH = 'payment/adyen_mock_payment_method/sort_order';
    const CONFIG_VALUE = '10';

    public function testFindConfig()
    {
        $connectionMock = $this->createConfiguredMock(AdapterInterface::class, [
            'fetchAll' => [
                [
                    'config_id' => 1,
                    'scope' => 'default',
                    'scope_id' => 0,
                    'path' => self::TEST_CONFIG_PATH,
                    'value' => self::CONFIG_VALUE,
                    'updated_at' => '2023-10-11 11:05:11'
                ]
            ],
            'select' => $this->createConfiguredMock(Select::class, [
                'from' => $this->createMock(Select::class)
            ])
        ]);

        $setupMock = $this->createConfiguredMock(ModuleDataSetupInterface::class, [
            'getConnection' => $connectionMock
        ]);

        $dataPatchHelper = new DataPatch();

        $config = $dataPatchHelper->findConfig($setupMock, self::TEST_CONFIG_PATH, self::CONFIG_VALUE);

        $this->assertEquals(self::CONFIG_VALUE, $config['value']);
    }
}
