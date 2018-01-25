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
 * Copyright (c) 2015 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Tests\Helper;

use Adyen\Payment\Helper\Data;
//use PHPUnit\Framework\TestCase;

class DataTest extends \PHPUnit_Framework_TestCase
{
    private $dataHelper;

    private function getSimpleMock($originalClassName)
    {
        return $this->getMockBuilder($originalClassName)
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function setUp()
    {
        $context = $this->getSimpleMock('\Magento\Framework\App\Helper\Context');
        $encryptor = $this->getSimpleMock('\Magento\Framework\Encryption\EncryptorInterface');
        $dataStorage = $this->getSimpleMock('\Magento\Framework\Config\DataInterface');
        $country = $this->getSimpleMock('\Magento\Directory\Model\Config\Source\Country');
        $moduleList = $this->getSimpleMock('\Magento\Framework\Module\ModuleListInterface');
        $billingAgreementCollectionFactory = $this->getSimpleMock('\Adyen\Payment\Model\Resource\Billing\Agreement\CollectionFactory');
        $assetRepo = $this->getSimpleMock('\Magento\Framework\View\Asset\Repository');
        $assetSource = $this->getSimpleMock('\Magento\Framework\View\Asset\Source');
        $notificationFactory = $this->getSimpleMock('\Adyen\Payment\Model\Resource\Notification\CollectionFactory');

        $this->dataHelper = new Data($context, $encryptor, $dataStorage, $country, $moduleList,
            $billingAgreementCollectionFactory, $assetRepo, $assetSource, $notificationFactory);
    }

    public function testFormatAmount()
    {
        $this->assertEquals("1234", $this->dataHelper->formatAmount("12.34", "EUR"));
        $this->assertEquals("1200", $this->dataHelper->formatAmount("12.00", "USD"));
        $this->assertEquals("12", $this->dataHelper->formatAmount("12.00", "JPY"));
    }
}
