<?php
namespace Adyen\Payment\Test\Unit\Setup\Patch\Data;

use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Adyen\Payment\Setup\Patch\Data\CreditCardsBecomeCards;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\MockObject\MockObject;

class CreditCardsBecomeCardsTest extends AbstractAdyenTestCase
{
    /** @var ObjectManager */
    protected $objectManager;

    /** @var CreditCardsBecomeCards */
    protected $patch;

    /** @var ModuleDataSetupInterface | MockObject */
    protected $moduleDataSetupMock;

    protected function setUp(): void
    {
        $this->objectManager = new ObjectManager($this);

        $this->moduleDataSetupMock = $this->getMockBuilder(ModuleDataSetupInterface::class)
            ->getMock();

        $this->patch = $this->objectManager->getObject(
            CreditCardsBecomeCards::class,
            [
                'moduleDataSetup' => $this->moduleDataSetupMock,
            ]
        );
    }

    /**
     * @dataProvider applyDataProvider
     */
    public function testApply($configData, $expectedUpdate)
    {
        $configTable = 'core_config_data';

        $selectMock = $this->getMockBuilder(Select::class)
            ->disableOriginalConstructor()
            ->getMock();
        $selectMock->expects($this->once())
            ->method('from')
            ->willReturn($selectMock);
        $selectMock->expects($this->once())
            ->method('where')
            ->willReturn($selectMock);

        $connectionMock = $this->getMockBuilder(AdapterInterface::class)
            ->getMock();
        $connectionMock->expects($this->once())
            ->method('select')
            ->willReturn($selectMock);
        $connectionMock->expects($this->once())
            ->method('fetchRow')
            ->willReturn($configData);
        $connectionMock->expects($expectedUpdate ? $this->once() : $this->never())
            ->method('update');

        $this->moduleDataSetupMock->method('getConnection')->willReturn($connectionMock);
        $this->patch->apply();
    }

    public function applyDataProvider()
    {
        return [
            'Config data found' => [
                'configData' => ['value' => 'Credit Card'],
                'expectedUpdate' => true,
            ],
            'Config data not found' => [
                'configData' => null,
                'expectedUpdate' => false,
            ],
        ];
    }

    public function testGetAliases()
    {
        $aliases = $this->patch->getAliases();

        $this->assertSame([], $aliases);
    }

    public function testGetDependencies()
    {
        $dependencies = $this->patch::getDependencies();

        $this->assertSame([], $dependencies);
    }

    public function getVersion()
    {
        $version = $this->patch::getVersion();

        $this->assertNotEmpty($version);
    }

}
