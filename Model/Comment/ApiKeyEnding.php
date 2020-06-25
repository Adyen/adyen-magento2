<?php

namespace Adyen\Payment\Model\Comment;

use Magento\Config\Model\Config\CommentInterface;
use Magento\Framework\Encryption\EncryptorInterface;

class ApiKeyEnding implements CommentInterface
{
    /**
     * @var EncryptorInterface
     */
    private $encryptor;

    /**
     * ApiKeyEnding constructor.
     *
     * @param EncryptorInterface $encryptor Magento encryptor, used to decrypt the API key.
     */
    public function __construct(EncryptorInterface $encryptor)
    {
        $this->encryptor = $encryptor;
    }

    /**
     * Method magically called by Magento. This returns the last 4 digits in the merchant's API key.
     *
     * @param string $elementValue The value of the field with this commented. In this case, an encrypted API key.
     * @return string Some HTML markup to be displayed in the admin panel.
     */
    public function getCommentText($elementValue)
    {
        $apiKeyEnding = substr($this->encryptor->decrypt(trim($elementValue)), -4);
        if (!$apiKeyEnding) {
            return '';
        }
        return "Key stored ending in <strong>$apiKeyEnding</strong>";
    }
}
