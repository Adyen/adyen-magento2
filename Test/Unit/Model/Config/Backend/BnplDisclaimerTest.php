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

declare(strict_types=1);

namespace Adyen\Payment\Test\Unit\Model\Config\Backend;

use Adyen\Payment\Model\Config\Backend\BnplDisclaimer;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(BnplDisclaimer::class)]
#[AllowMockObjectsWithoutExpectations]
class BnplDisclaimerTest extends AbstractAdyenTestCase
{
    private BnplDisclaimer $model;

    protected function setUp(): void
    {
        parent::setUp();

        $this->model = new BnplDisclaimer(
            $this->createConfiguredMock(Context::class, [
                'getEventDispatcher' => $this->createMock(ManagerInterface::class),
            ]),
            $this->createMock(Registry::class),
            $this->createMock(ScopeConfigInterface::class),
            $this->createMock(TypeListInterface::class)
        );
    }

    #[DataProvider('validValueProvider')]
    public function testBeforeSaveAcceptsValidValue(?string $value, string $expected): void
    {
        $this->model->setValue($value);

        $this->assertSame($this->model, $this->model->beforeSave());
        $this->assertSame($expected, $this->model->getValue());
    }

    public static function validValueProvider(): array
    {
        return [
            'null' => [null, ''],
            'empty' => ['', ''],
            'whitespace is trimmed' => ["  Pay later with Klarna.\n ", 'Pay later with Klarna.'],
            'plain multiline text' => ["Pay in 30 days.\nCredit is subject to approval.", "Pay in 30 days.\nCredit is subject to approval."],
            'plain punctuation' => ['Fees: 0% (no interest) - a < b, 2 * 3!', 'Fees: 0% (no interest) - a < b, 2 * 3!'],
            'single link' => [
                'Read the [terms](https://www.example.com/terms).',
                'Read the [terms](https://www.example.com/terms).'
            ],
            'multiple links' => [
                "See [terms](https://example.com/terms?lang=en#credit)\nand [privacy policy](https://example.com/privacy).",
                "See [terms](https://example.com/terms?lang=en#credit)\nand [privacy policy](https://example.com/privacy)."
            ],
            'html is stripped' => [
                '<p>Pay later.</p> <a href="https://evil.example">Terms</a><script>alert(1)</script>',
                'Pay later. Termsalert(1)'
            ],
            'html around markdown link is stripped' => [
                '<b>[Terms](https://example.com/terms)</b><img src=x onerror=alert(1)>',
                '[Terms](https://example.com/terms)'
            ],
        ];
    }

    #[DataProvider('invalidValueProvider')]
    public function testBeforeSaveRejectsInvalidValue(string $value): void
    {
        $this->model->setValue($value);

        $this->expectException(LocalizedException::class);

        $this->model->beforeSave();
    }

    public static function invalidValueProvider(): array
    {
        return [
            'bold' => ['Pay **later**'],
            'bold underscore' => ['Pay __later__'],
            'italic' => ['Pay *later*'],
            'italic underscore' => ['Pay _later_'],
            'strikethrough' => ['Pay ~~later~~'],
            'formatting inside link text' => ['[**Terms**](https://example.com)'],
            'heading' => ['# Disclaimer'],
            'blockquote' => ['> Disclaimer'],
            'unordered list dash' => ["Terms:\n- item"],
            'unordered list asterisk' => ["Terms:\n* item"],
            'ordered list' => ["Terms:\n1. item"],
            'inline code' => ['Use `code`'],
            'fenced code' => ["```\ncode\n```"],
            'image' => ['![logo](https://example.com/logo.png)'],
            'reference link' => ['[terms][1]'],
            'link definition' => ['[1]: https://example.com'],
            'horizontal rule' => ["Text\n---"],
            'setext heading' => ["Title\n==="],
            'table' => ["| a | b |\n|---|---|"],
            'http link' => ['[terms](http://example.com)'],
            'javascript link' => ['[terms](javascript:alert(1))'],
            'javascript link without parentheses' => ['[terms](javascript:alert)'],
            'data link' => ['[terms](data:text/html;base64,PHNjcmlwdD4=)'],
            'relative link' => ['[terms](/terms)'],
            'protocol relative link' => ['[terms](//example.com)'],
            'link with title' => ['[terms](https://example.com "Terms")'],
        ];
    }
}
