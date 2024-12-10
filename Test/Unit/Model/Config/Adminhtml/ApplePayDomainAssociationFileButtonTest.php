<?php

namespace Adyen\Payment\Test\Unit\Model\Config\Adminhtml;

use Adyen\Payment\Model\Config\Adminhtml\ApplePayDomainAssociationFileButton;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Helper\Data;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\View\LayoutInterface;
use PHPUnit\Framework\MockObject\MockObject;

class ApplePayDomainAssociationFileButtonTest extends AbstractAdyenTestCase
{
    protected ?ApplePayDomainAssociationFileButton $applePayDomainAssociationFileButton;
    protected MockObject|Context $contextMock;
    protected MockObject|Data $backendHelperMock;
    protected MockObject|LayoutInterface $layoutMock;
    protected MockObject|ManagerInterface $managerMock;
    protected MockObject|ScopeConfigInterface $scopeConfigMock;

    /**
     * @return void
     */
    public function setUp(): void
    {
        $objectManagerMock = $this->createMock(ObjectManagerInterface::class);
        ObjectManager::setInstance($objectManagerMock);

        $this->managerMock = $this->createMock(ManagerInterface::class);
        $this->scopeConfigMock = $this->createMock(ScopeConfigInterface::class);

        $this->contextMock = $this->createMock(Context::class);
        $this->contextMock->method('getScopeConfig')->willReturn($this->scopeConfigMock);
        $this->contextMock->method('getEventManager')->willReturn($this->managerMock);

        $this->backendHelperMock = $this->createMock(Data::class);
        $this->layoutMock = $this->createMock(LayoutInterface::class);

        $this->applePayDomainAssociationFileButton = new ApplePayDomainAssociationFileButton(
            $this->contextMock,
            $this->backendHelperMock,
            []
        );
    }

    /**
     * @return void
     */
    public function tearDown(): void
    {
        $this->applePayDomainAssociationFileButton = null;
    }

    /**
     * Asserts default HTML template value
     *
     * @return void
     */
    public function testGetElementHtml()
    {
        $expected = '<tr id="row_"><td class="label"><label for=""><span></span></label></td><td class="value"></td><td class=""></td></tr>';

        $result = $this->applePayDomainAssociationFileButton
            ->render($this->createMock(AbstractElement::class));

        $this->assertEquals($expected, $result);
    }

    /**
     * Asserts return type of the button's backend model
     *
     * @return void
     */
    public function testPrepareLayout()
    {
        $result = $this->applePayDomainAssociationFileButton->setLayout($this->layoutMock);
        $this->assertInstanceOf(ApplePayDomainAssociationFileButton::class, $result);
    }

    /**
     * Asserts file download controller URL
     *
     * @return void
     */
    public function testGetActionUrl()
    {
        $expected = 'https://www.magento.demo/adyen/configuration/DownloadApplePayDomainAssociationFile';
        $this->backendHelperMock->expects($this->once())
            ->method('getUrl')
            ->with('adyen/configuration/DownloadApplePayDomainAssociationFile', [])
            ->willReturn($expected);

        $url = $this->applePayDomainAssociationFileButton->getActionUrl();

        $this->assertEquals($expected, $url);
    }
}
