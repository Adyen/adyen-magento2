<?php

namespace Adyen\Payment\Test\Comment;

use Adyen\Payment\Model\Comment\ApiKeyEnding;
use Magento\Framework\Encryption\Encryptor;

class ApiKeyEndingTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var ApiKeyEnding
     */
    private $apiKeyEndingComment;

    public function setUp()
    {
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
        $this->assertEquals('API key ending with: <strong>1234</strong>', $this->apiKeyEndingComment->getCommentText('4321'));
        $this->assertEquals('API key ending with: <strong>qwer</strong>', $this->apiKeyEndingComment->getCommentText('asdfasdfasdf'));
    }
}
