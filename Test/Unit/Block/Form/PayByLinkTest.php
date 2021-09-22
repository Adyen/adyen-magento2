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
 * Copyright (c) 2021 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Tests\Block\Form;

use Adyen\Payment\Block\Form\PayByLink;
use Adyen\Payment\Model\Ui\AdyenPayByLinkConfigProvider;
use Magento\Framework\App\Config;
use Magento\Framework\View\Element\Template\Context;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManager;
use PHPUnit\Framework\TestCase;

class PayByLinkTest extends TestCase
{
    /**
     * @var PayByLink
     */
    private $payByLink;

    protected function setUp(): void
    {

        $scopeConfig = $this->getMockBuilder(Config::class)
            ->disableOriginalConstructor()
            ->setMethods(['getValue'])
            ->getMock();
        $scopeConfig->method('getValue')->willReturn(AdyenPayByLinkConfigProvider::MIN_EXPIRY_DAYS);

        $store = $this->getMockBuilder(Store::class)
            ->disableOriginalConstructor()
            ->setMethods(['getId'])
            ->getMock();
        $store->method('getId')->willReturn(1);

        $storeManager = $this->getMockBuilder(StoreManager::class)
            ->disableOriginalConstructor()
            ->setMethods(['getStore'])
            ->getMock();
        $storeManager->method('getStore')->willReturn($store);

        $context = $this->getMockBuilder(Context::class)
            ->disableOriginalConstructor()
            ->setMethods(['getScopeConfig', 'getStoreManager'])
            ->getMock();
        $context->method('getScopeConfig')->willReturn($scopeConfig);
        $context->method('getStoreManager')->willReturn($storeManager);

        $this->payByLink = new PayByLink($context);
    }

    public function testGetDefaultExpiryDate()
    {
        $tomorrow = new \DateTime('tomorrow');
        $this->assertEquals(
            $this->payByLink->getDefaultExpiryDate(),
            $tomorrow->format(AdyenPayByLinkConfigProvider::DATE_FORMAT)
        );
    }

    public function testGetMinExpiryTimestamp()
    {
        $date = new \DateTime('now', new \DateTimeZone('UTC'));
        $date->add(new \DateInterval('P' . AdyenPayByLinkConfigProvider::MIN_EXPIRY_DAYS . 'D'));
        $this->assertEquals(
            $this->payByLink->getMinExpiryTimestamp(),
            $date->getTimestamp() * 1000
        );
    }

    public function testGetMaxExpiryTimestamp()
    {
        $date = new \DateTime('now', new \DateTimeZone('UTC'));
        $date->add(new \DateInterval('P' . AdyenPayByLinkConfigProvider::MAX_EXPIRY_DAYS . 'D'));
        $this->assertEquals(
            $this->payByLink->getMaxExpiryTimestamp(),
            $date->getTimestamp() * 1000
        );
    }
}
