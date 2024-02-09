<?php
/**
 * Copyright © 2023 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 */

namespace Adyen\Payment\Test\Unit\Helper;

use Adyen\Payment\Helper\Idempotency;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;

class IdempotencyTest extends AbstractAdyenTestCase
{
    public function testGenerateIdempotencyKey()
    {
        $idempotency = new Idempotency();

        $request = [
            'amount' => [
                'currency' => 'EUR',
                'value' => '100.00',
            ],
            'reference' => 'test-reference',
        ];

        $idempotencyExtraData = [
            'totalRefunded' => '10.00',
        ];

        $expectedKey = 'c415b19b07ada77f036f10757d948dfae986a615155bad3c0db08e1b0a3f2e3e';

        $key = $idempotency->generateIdempotencyKey($request, $idempotencyExtraData);

        $this->assertEquals($expectedKey, $key);
    }
}
