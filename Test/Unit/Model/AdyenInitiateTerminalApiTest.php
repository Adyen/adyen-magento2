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
 * Copyright (c) 2019 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model;

class AdyenInitiateTerminalApiTest extends \PHPUnit\Framework\TestCase
{
    private $adyenInitiateTerminalApi;

    const MODULE_VERSION = '1.0.0';
    const MODULE_NAME = 'ModuleVersion';
    const PLATFORM_VERSION = '2.0.0';
    const PLATFORM_NAME = 'PlatformName';
    const CUSTOMER_ID = '1';
    const CUSTOMER_EMAIL = 'customer@example.com';
    const RECURRING_TYPE = 'ONECLICK,RECURRING';

    private function getSimpleMock($originalClassName)
    {
        return $this->getMockBuilder($originalClassName)
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function setUp()
    {
        $adyenHelper = $this->getSimpleMock(\Adyen\Payment\Helper\Data::class);
        $adyenLogger = $this->getSimpleMock(\Adyen\Payment\Logger\AdyenLogger::class);
        $checkoutSession = $this->getSimpleMock(\Magento\Checkout\Model\Session::class);
        $storeManager = $this->getSimpleMock(\Magento\Store\Model\StoreManagerInterface::class);
        $productMetadata = $this->getSimpleMock(\Magento\Framework\App\ProductMetadataInterface::class);
        $chargedCurrency = $this->getSimpleMock(\Adyen\Payment\Helper\ChargedCurrency::class);

        $store = $this->getSimpleMock(\Magento\Store\Api\Data\StoreInterface::class);
        $storeManager->method('getStore')
            ->will($this->returnValue($store));

        $adyenHelper->method('getModuleVersion')
            ->will($this->returnValue(self::MODULE_VERSION));

        // Create a map of arguments to return values.
        $map = [
            ['pos_timeout', null, ''],
            ['recurring_type', null, self::RECURRING_TYPE]
        ];

        // Configure the stub.
        $adyenHelper->method('getAdyenPosCloudConfigData')
            ->will($this->returnValueMap($map));

        $adyenHelper->method('getModuleName')
            ->will($this->returnValue(self::MODULE_NAME));

        $productMetadata->method('getVersion')
            ->will($this->returnValue(self::PLATFORM_VERSION));

        $productMetadata->method('getName')
            ->will($this->returnValue(self::PLATFORM_NAME));

        $this->adyenInitiateTerminalApi = new \Adyen\Payment\Model\AdyenInitiateTerminalApi(
            $adyenHelper,
            $adyenLogger,
            $checkoutSession,
            $storeManager,
            $productMetadata,
            $chargedCurrency
        );
    }

    /** @throws \Exception */
    public function testAddSaleToAcquirerData()
    {
        $quote = $this->getSimpleMock(\Magento\Quote\Model\Quote::class);
        $request = [];
        $result = $this->adyenInitiateTerminalApi->addSaleToAcquirerData($request, $quote);

        $appInfo = [
            'applicationInfo' => [
                'merchantApplication' => [
                    'version' => self::MODULE_VERSION,
                    'name' => self::MODULE_NAME
                ],
                'externalPlatform' => [
                    'version' => self::PLATFORM_VERSION,
                    'name' => self::PLATFORM_NAME
                ]
            ]
        ];

        $saleToAcquirerData = base64_encode(json_encode($appInfo));
        $resultArrayExpected = [
            'SaleToPOIRequest' => [
                'PaymentRequest' => [
                    'SaleData' => [
                        'SaleToAcquirerData' => $saleToAcquirerData
                    ]
                ]
            ]
        ];

        $this->assertEquals($resultArrayExpected, $result);
    }

    public function testAddSaleToAcquirerDataLoggedInCustomer()
    {
        $quoteMock = $this->getMockBuilder('Magento\Quote\Model\Quote')
            ->disableOriginalConstructor()
            ->setMethods(
                [
                    'getId',
                    'getCustomerEmail',
                    'getCustomerId'
                ]
            )
            ->getMock();

        $quoteMock->expects($this->any())
            ->method('getCustomerId')
            ->willReturn(self::CUSTOMER_ID);

        $quoteMock->expects($this->atLeastOnce())
            ->method('getCustomerEmail')
            ->willReturn(self::CUSTOMER_EMAIL);

        $request = [];
        $result = $this->adyenInitiateTerminalApi->addSaleToAcquirerData($request, $quoteMock);
        
        $appInfo = [
            'shopperEmail' => self::CUSTOMER_EMAIL,
            'shopperReference' => self::CUSTOMER_ID,
            'recurringContract' => self::RECURRING_TYPE,
            'applicationInfo' => [
                'merchantApplication' => [
                    'version' => self::MODULE_VERSION,
                    'name' => self::MODULE_NAME
                ],
                'externalPlatform' => [
                    'version' => self::PLATFORM_VERSION,
                    'name' => self::PLATFORM_NAME
                ]
            ]
        ];

        $saleToAcquirerData = base64_encode(json_encode($appInfo));
        $resultArrayExpected = [
            'SaleToPOIRequest' => [
                'PaymentRequest' => [
                    'SaleData' => [
                        'SaleToAcquirerData' => $saleToAcquirerData
                    ]
                ]
            ]
        ];

        $this->assertEquals($resultArrayExpected, $result);
    }
}
