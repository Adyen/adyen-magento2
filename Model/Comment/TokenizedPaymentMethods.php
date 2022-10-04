<?php
/**
 *
 * Adyen Payment Module
 *
 * @author Adyen BV <support@adyen.com>
 * @copyright (c) 2022 Adyen B.V.
 * @license https://opensource.org/licenses/MIT MIT license
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 */

namespace Adyen\Payment\Model\Comment;

use Magento\Config\Model\Config\CommentInterface;

class TokenizedPaymentMethods implements CommentInterface
{
    /**
     *
     * @param string $elementValue The value of the field with this commented
     */
    public function getCommentText($elementValue): string
    {
        return 'Selected payment methods will automatically be tokenized on every transaction.
        At the moment, CardOnFile tokens can only be created using Wallet payment methods (Google Pay).';
    }
}
