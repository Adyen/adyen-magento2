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

namespace Adyen\Payment\Model;

use Magento\Framework\GraphQl\Exception\GraphQlInputException;

class GraphqlInputArgumentValidator
{
    /**
     * Validates GraphQl input arguments
     *
     * Multidimensional arrays can be validated with fields separated with a dot.
     *
     * @param array|null $args
     * @param array $requiredFields
     * @return void
     * @throws GraphQlInputException
     */
    public function execute(?array $args, array $requiredFields): void
    {
        $missingFields = [];

        foreach ($requiredFields as $field) {
            $keys = explode('.', $field);
            $value = $args;

            foreach ($keys as $key) {
                $value = $value[$key] ?? null;
            }

            if (empty($value)) {
                $missingFields[] = $field;
            }
        }

        if (!empty($missingFields)) {
            throw new GraphQlInputException(
                __('Required parameters "%1" are missing', implode(', ', $missingFields))
            );
        }
    }
}
