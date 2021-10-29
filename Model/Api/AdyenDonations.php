<?php
/**
 *                       ######
 *                       ######
 * ############    ####( ######  #####. ######  ############   ############
 * #############  #####( ######  #####. ######  #############  #############
 *        ######  #####( ######  #####. ######  #####  ######  #####  ######
 * ###### ######  #####( ######  #####. ######  #####  #####   #####  ######
 * ###### ######  #####( ######  #####. ######  #####          #####  ######
 * #############  #############  #############  #############  #####  ######
 *  ############   ############  #############   ############  #####  ######
 *                                      ######
 *                               #############
 *                               ############
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2021 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Api;

use Adyen\Payment\Api\AdyenDonationsInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NotFoundException;
use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\Command\CommandPoolInterface;

class AdyenDonations implements AdyenDonationsInterface
{
    /**
     * @var CommandPoolInterface
     */
    private $commandPool;

    public function __construct(CommandPoolInterface $commandPool)
    {
        $this->commandPool = $commandPool;
    }

    /**
     * @inheritDoc
     *
     * @throws CommandException
     * @throws NotFoundException
     */
    public function donate($payload)
    {
        $payload = json_decode($payload, true);

        // Validate JSON that has just been parsed if it was in a valid format
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new LocalizedException(__('Donations call failed because the request was not a valid JSON'));
        }

        /**
         * @todo PW-5424 The following structure should be sent from the frontend.
         * $payload = [
         *   'amount' => [
         *       'currency' => 'EUR',
         *       'value' => 1000
         *   ],
         *   'paymentMethod' => [
         *       "type"=> "scheme",
         *       "encryptedSecurityCode"=> "ENCRYPTED_CVC_FROM_CARD_COMPONENT"
         *   ],
         *   'donationToken' => 'h64j84he5ygdyf',
         *   'donationOriginalPspReference' => '991559660454807J',
         *   'returnUrl' => '',
         * ];
         */
        $donationsCaptureCommand = $this->commandPool->get('capture');

        return $donationsCaptureCommand->execute(['payment' => $payload]);
    }
}
