<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2026 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */
declare(strict_types=1);

namespace Adyen\Payment\Test\Unit\Model;

use Adyen\Payment\Model\GraphqlInputArgumentValidator;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;

class GraphqlInputArgumentValidatorTest extends AbstractAdyenTestCase
{
    private GraphqlInputArgumentValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new GraphqlInputArgumentValidator();
    }

    /**
     * @return void
     * @throws GraphQlInputException
     */
    public function testExecuteWithAllRequiredFieldsPresent()
    {
        $args = [
            'name' => 'John',
            'email' => 'john@example.com'
        ];
        $requiredFields = ['name', 'email'];

        $this->validator->execute($args, $requiredFields);

        // No exception means the test passes
        $this->assertTrue(true);
    }

    /**
     * @return void
     * @throws GraphQlInputException
     */
    public function testExecuteWithNestedRequiredFieldsPresent()
    {
        $args = [
            'payment' => [
                'method' => 'adyen_cc',
                'details' => [
                    'brand' => 'visa'
                ]
            ]
        ];
        $requiredFields = ['payment.method', 'payment.details.brand'];

        $this->validator->execute($args, $requiredFields);

        $this->assertTrue(true);
    }

    /**
     * @return void
     */
    public function testExecuteThrowsExceptionForMissingTopLevelField()
    {
        $args = [
            'name' => 'John'
        ];
        $requiredFields = ['name', 'email'];

        $this->expectException(GraphQlInputException::class);
        $this->expectExceptionMessage('email');

        $this->validator->execute($args, $requiredFields);
    }

    /**
     * @return void
     */
    public function testExecuteThrowsExceptionForMissingNestedField()
    {
        $args = [
            'payment' => [
                'method' => 'adyen_cc'
            ]
        ];
        $requiredFields = ['payment.details.brand'];

        $this->expectException(GraphQlInputException::class);
        $this->expectExceptionMessage('payment.details.brand');

        $this->validator->execute($args, $requiredFields);
    }

    /**
     * @return void
     */
    public function testExecuteThrowsExceptionWithMultipleMissingFields()
    {
        $args = [
            'name' => 'John'
        ];
        $requiredFields = ['email', 'phone'];

        $this->expectException(GraphQlInputException::class);
        $this->expectExceptionMessage('email, phone');

        $this->validator->execute($args, $requiredFields);
    }

    /**
     * @return void
     */
    public function testExecuteThrowsExceptionForNullArgs()
    {
        $requiredFields = ['name'];

        $this->expectException(GraphQlInputException::class);
        $this->expectExceptionMessage('name');

        $this->validator->execute(null, $requiredFields);
    }

    /**
     * @return void
     */
    public function testExecuteThrowsExceptionForEmptyArgs()
    {
        $requiredFields = ['name'];

        $this->expectException(GraphQlInputException::class);
        $this->expectExceptionMessage('name');

        $this->validator->execute([], $requiredFields);
    }

    /**
     * @return void
     * @throws GraphQlInputException
     */
    public function testExecuteWithNoRequiredFields()
    {
        $args = ['name' => 'John'];

        $this->validator->execute($args, []);

        $this->assertTrue(true);
    }

    /**
     * @return void
     */
    public function testExecuteThrowsExceptionForEmptyStringValue()
    {
        $args = [
            'name' => ''
        ];
        $requiredFields = ['name'];

        $this->expectException(GraphQlInputException::class);
        $this->expectExceptionMessage('name');

        $this->validator->execute($args, $requiredFields);
    }
}
