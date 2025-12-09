<?php

namespace Adyen\Payment\Test\Gateway\Request;

use Adyen\Payment\Gateway\Request\BrowserInfoDataBuilder;
use Adyen\Payment\Helper\Requests;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;


class BrowserInfoDataBuilderTest extends AbstractAdyenTestCase
{
    private $adyenRequestsHelperMock;
    private $browserInfoDataBuilder;

    protected function setUp(): void
    {
        $this->adyenRequestsHelperMock = $this->createMock(Requests::class);

        $this->browserInfoDataBuilder = new BrowserInfoDataBuilder(
            $this->adyenRequestsHelperMock
        );
    }

    public function testBuild()
    {
        $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)';
        $httpAccept = 'text/html,application/xhtml+xml';

        $browserData = [
            'browserInfo' => [
                'userAgent' => $userAgent,
                'acceptHeader' => $httpAccept
            ]
        ];

        $this->adyenRequestsHelperMock->method('buildBrowserData')->willReturn($browserData);

        $buildSubject = []; // Adjust as necessary for your test
        $result = $this->browserInfoDataBuilder->build($buildSubject);

        $expectedResult = [
            'body' => $browserData
        ];

        $this->assertEquals($expectedResult, $result);
    }
}
