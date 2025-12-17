<?php

namespace Adyen\Payment\Helper\Util;

use RuntimeException;

/**
 * Class Uuid
 *
 * Utility class for generating UUID version 4.
 *
 * @package Adyen\Payment\Helper\Util
 */
class Uuid
{
    /**
     * Generate a UUID v4 (random-based).
     *
     * A UUID v4 is a universally unique identifier that is generated using
     * random numbers. It follows the RFC 4122 standard.
     *
     * @return string A randomly generated UUID v4.
     * @throws RuntimeException If secure random generation fails.
     */
    public static function generateV4(): string
    {
        try {
            $random = random_bytes(16);
        } catch (\Exception $e) {
            throw new RuntimeException('Failed to generate a secure UUID: ' . $e->getMessage(), 0, $e);
        }

        // Set the version to 4 (0100)
        $random[6] = chr((ord($random[6]) & 0x0f) | 0x40);

        // Set the variant to RFC 4122 (10xx)
        $random[8] = chr((ord($random[8]) & 0x3f) | 0x80);

        // Convert binary to hexadecimal and format as UUID
        return sprintf(
            '%08s-%04s-%04s-%04s-%12s',
            bin2hex(substr($random, 0, 4)),
            bin2hex(substr($random, 4, 2)),
            bin2hex(substr($random, 6, 2)),
            bin2hex(substr($random, 8, 2)),
            bin2hex(substr($random, 10, 6))
        );
    }
}
