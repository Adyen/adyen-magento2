<?php

namespace Adyen\Payment\Model\Webhook;

enum WebhookAcceptorType: string
{
    case STANDARD = 'standard';
    case TOKEN = 'token';
}
