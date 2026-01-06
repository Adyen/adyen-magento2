<?php

declare(strict_types=1);

namespace Adyen\Payment\Test\Unit\Block\Form;

use Adyen\Payment\Block\Form\PosCloud;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\ConnectedTerminals;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\PointOfSale;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Backend\Model\Session\Quote;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\View\Element\Template\Context;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;

#[CoversClass(PosCloud::class)]
class PosCloudTest extends AbstractAdyenTestCase
{
    private PosCloud $posCloudBlock;

    private MockObject $connectedTerminalsHelper;
    private MockObject $serializer;
    private MockObject $adyenHelper;
    private MockObject $backendSession;
    private MockObject $posHelper;
    private MockObject $configHelper;

    protected function setUp(): void
    {
        $context = $this->createMock(Context::class);
        $this->connectedTerminalsHelper = $this->createMock(ConnectedTerminals::class);
        $this->serializer = $this->createMock(SerializerInterface::class);
        $this->adyenHelper = $this->createMock(Data::class);
        $this->backendSession = $this->createMock(Quote::class);
        $this->posHelper = $this->createMock(PointOfSale::class);
        $this->configHelper = $this->createMock(Config::class);

        $this->posCloudBlock = new PosCloud(
            $context,
            $this->connectedTerminalsHelper,
            $this->serializer,
            $this->adyenHelper,
            $this->backendSession,
            $this->posHelper,
            $this->configHelper
        );
    }

    public static function connectedTerminalsDataProvider(): array
    {
        return [
            'with terminals' => [
                'apiResponse' => [
                    'uniqueTerminalIds' => ['terminal1', 'terminal2', 'terminal3']
                ],
                'expected' => ['terminal1', 'terminal2', 'terminal3']
            ],
            'empty uniqueTerminalIds' => [
                'apiResponse' => [
                    'uniqueTerminalIds' => []
                ],
                'expected' => []
            ],
            'missing uniqueTerminalIds key' => [
                'apiResponse' => [],
                'expected' => []
            ],
            'null response' => [
                'apiResponse' => null,
                'expected' => []
            ]
        ];
    }

    #[Test]
    #[DataProvider('connectedTerminalsDataProvider')]
    public function testGetConnectedTerminals(?array $apiResponse, array $expected): void
    {
        $this->connectedTerminalsHelper
            ->expects(self::once())
            ->method('getConnectedTerminalsApiResponse')
            ->willReturn($apiResponse);

        $this->assertSame($expected, $this->posCloudBlock->getConnectedTerminals());
    }
}
