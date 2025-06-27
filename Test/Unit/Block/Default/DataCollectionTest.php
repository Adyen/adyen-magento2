<?php

namespace Adyen\Payment\Test\Unit\Block\Default;

use Adyen\Payment\Block\Default\DataCollection;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;

class DataCollectionTest extends AbstractAdyenTestCase
{
    protected ?DataCollection $dataCollection;
    protected Config|MockObject $configMock;
    protected Context|MockObject $contextMock;
    protected StoreManagerInterface $storeManagerMock;
    protected StoreInterface|MockObject $storeMock;
    protected array $dataMock = [];
    protected int $storeId = 1;

    /**
     * @return void
     * @throws Exception
     */
    public function setUp(): void
    {
        $this->configMock = $this->createMock(Config::class);
        $this->contextMock = $this->createMock(Context::class);

        $this->storeMock = $this->createMock(StoreInterface::class);
        $this->storeMock->method('getId')->willReturn($this->storeId);
        $this->storeManagerMock = $this->createMock(StoreManagerInterface::class);
        $this->storeManagerMock->method('getStore')->willReturn($this->storeMock);

        $this->contextMock->method('getStoreManager')->willReturn($this->storeManagerMock);

        $this->dataCollection = new DataCollection(
            $this->configMock,
            $this->contextMock,
            $this->dataMock
        );
    }

    /**
     * @return void
     */
    public function tearDown(): void
    {
        $this->dataCollection = null;
    }

    /**
     * @return array[]
     */
    private static function dataProviderGetEnvironment(): array
    {
        return [
            [
                'isDemoMode' => true,
                'expectedEnvironment' => 'test'
            ],
            [
                'isDemoMode' => false,
                'expectedEnvironment' => 'live'
            ]
        ];
    }

    /**
     * @dataProvider dataProviderGetEnvironment
     *
     * @param $isDemoMode
     * @param $expectedEnvironment
     * @return void
     * @throws NoSuchEntityException
     */
    public function testGetEnvironment($isDemoMode, $expectedEnvironment)
    {
        $this->configMock->method('isDemoMode')->with($this->storeId)->willReturn($isDemoMode);
        $this->assertEquals($expectedEnvironment, $this->dataCollection->getEnvironment());
    }

    /**
     * @return void
     * @throws NoSuchEntityException
     */
    public function testIsEnabled()
    {
        $isEnabled = true;

        $this->configMock->method('isOutsideCheckoutDataCollectionEnabled')
            ->with($this->storeId)
            ->willReturn($isEnabled);

        $this->assertEquals($isEnabled, $this->dataCollection->isEnabled());
    }
}
