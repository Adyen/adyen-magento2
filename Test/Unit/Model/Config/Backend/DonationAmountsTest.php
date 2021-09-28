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

namespace Adyen\Payment\Tests\Helper;

use Adyen\Payment\Model\Config\Backend\DonationAmounts;

class DonationAmountsTest extends \PHPUnit\Framework\TestCase
{

    private $donationAmounts;

    private function getSimpleMock($originalClassName, $methods = [])
    {
        return $this->getMockBuilder($originalClassName)
            ->disableOriginalConstructor()
            ->setMethods($methods)
            ->getMock();
    }

    protected function setUp(): void
    {
        $context = $this->getSimpleMock(\Magento\Framework\Model\Context::class);
        $registry = $this->getSimpleMock(\Magento\Framework\Registry::class);
        $scopeConfigInterface = $this->getSimpleMock(\Magento\Framework\App\Config\ScopeConfigInterface::class);
        $typeListInterface = $this->getSimpleMock(\Magento\Framework\App\Cache\TypeListInterface::class);
        $abstractResource = $this->getSimpleMock(\Magento\Framework\Model\ResourceModel\AbstractResource::class);
        $abstractDb = $this->getSimpleMock(\Magento\Framework\Data\Collection\AbstractDb::class);
        $storeManagerInterface = $this->getSimpleMock(\Magento\Store\Model\StoreManagerInterface::class, ['getStore']);
        $storeInterface = $this->getSimpleMock(\Magento\Store\Api\Data\StoreInterface::class, ['getBaseCurrency']);
        $currency = $this->getSimpleMock(\Magento\Directory\Model\Currency::class, ['getRate']);
        $currency->method('getRate')->willReturn(1);
        $storeInterface->method('getBaseCurrency')->willReturn($currency);
        $storeManagerInterface->method('getStore')->willReturn($storeInterface);


        $this->donationAmounts = new DonationAmounts($context, $registry, $scopeConfigInterface, $typeListInterface,
            $abstractResource, $abstractDb, $storeManagerInterface);
    }

    /**
     * @dataProvider donationAmountsProvider
     */
    public function testValidateDonationAmounts($donationAmounts, $expectedResult)
    {
        $result = $this->donationAmounts->validateDonationAmounts(explode(',', $donationAmounts));
        $this->assertEquals($result, $expectedResult);
    }

    public static function donationAmountsProvider()
    {
        return array(
            array(
                "12,453,68,2",
                true
            ),
            array(
                "12,45,h,34",
                true
            ),
            array(
                "12.4,45.3,,34",
                false
            ),
            array(
                "12.4,45.3,34",
                true
            ),
            array(
                "",
                false
            )
        );
    }
}
