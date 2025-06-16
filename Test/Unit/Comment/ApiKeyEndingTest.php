<?php

namespace Adyen\Payment\Test\Comment;

use Adyen\Payment\Model\Comment\ApiKeyEnding;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\Encryption\Encryptor;

class ApiKeyEndingTest extends AbstractAdyenTestCase
{
    /**
     * @var ApiKeyEnding
     */
    private $apiKeyEndingComment;

    public function setUp(): void
    {
        /** @var Encryptor $encryptor */
        $encryptor = $this->createMock(Encryptor::class);
        $map = [
            ['4321', '1234'],
            ['asdfasdfasdf', 'qwerqwerqwer']
        ];
        $encryptor->method('decrypt')
            ->will($this->returnValueMap($map));

        $this->apiKeyEndingComment = new ApiKeyEnding($encryptor);
    }

    public function testCommentReturnsJustTheEnding()
    {
        $this->assertEquals(
            'Your stored key ends with <strong>1234</strong>',
            $this->apiKeyEndingComment->getCommentText('4321')
        );
        $this->assertEquals(
            'Your stored key ends with <strong>qwer</strong>',
            $this->apiKeyEndingComment->getCommentText('asdfasdfasdf')
        );
    }
}
