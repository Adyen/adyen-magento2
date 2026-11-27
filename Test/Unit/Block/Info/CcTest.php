<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2026 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Test\Block\Info;

use Adyen\Payment\Block\Info\Cc;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Model\ResourceModel\Order\Payment\CollectionFactory;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\View\Element\Template\Context;
use Magento\Payment\Model\InfoInterface;
use PHPUnit\Framework\Attributes\DataProvider;

class CcTest extends AbstractAdyenTestCase
{
    private const CC_TYPES = [
        'VI' => ['name' => 'Visa', 'code_alt' => 'visa'],
        'MC' => ['name' => 'MasterCard', 'code_alt' => 'mc']
    ];

    public static function ccTypeDataProvider(): array
    {
        return [
            'card type code' => ['VI', 'Visa'],
            'adyen brand stored as code_alt' => ['mc', 'MasterCard'],
            'unknown brand is returned as is' => ['unknownbrand', 'unknownbrand'],
            'no card type' => [null, null]
        ];
    }

    #[DataProvider('ccTypeDataProvider')]
    public function testGetCcTypeName(?string $ccType, ?string $expectedName): void
    {
        $adyenHelperMock = $this->createMock(Data::class);
        $adyenHelperMock->method('getAdyenCcTypes')->willReturn(self::CC_TYPES);
        $adyenHelperMock->method('getCcTypesAltData')->willReturn([
            'visa' => ['name' => 'Visa', 'code_alt' => 'visa', 'code' => 'VI'],
            'mc' => ['name' => 'MasterCard', 'code_alt' => 'mc', 'code' => 'MC']
        ]);

        $infoMock = $this->createGeneratedMock(
            InfoInterface::class,
            [
                'encrypt',
                'decrypt',
                'setAdditionalInformation',
                'hasAdditionalInformation',
                'unsAdditionalInformation',
                'getAdditionalInformation',
                'getMethodInstance'
            ],
            ['getCcType']
        );
        $infoMock->method('getCcType')->willReturn($ccType);

        $block = new Cc(
            $adyenHelperMock,
            $this->createMock(Config::class),
            $this->createMock(CollectionFactory::class),
            $this->createMock(Context::class),
            ['info' => $infoMock]
        );

        $this->assertSame($expectedName, $block->getCcTypeName());
    }
}
