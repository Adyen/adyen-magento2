<?php
declare(strict_types=1);

namespace Adyen\Payment\Exception;

use Magento\Framework\Exception\LocalizedException;

class AuthenticationException extends LocalizedException
{
    /**
     * Constructor with a default message and optional parameters.
     *
     * @param string $message
     * @param \Exception|null $cause
     */
    public function __construct(string $message = 'Unauthorized webhook request.', \Exception $cause = null)
    {
        parent::__construct(__($message), $cause);
    }
}
