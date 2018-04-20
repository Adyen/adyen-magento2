# Adyen_Magento2
Adyen Payment plugin for Magento2. This plugin supports Magento2 version 2.1 and higher. <br/>
For Magento2 version 2.0 support, use plugin version 1.4.6.
## Installation ##
```
bin/composer require adyen/module-payment
bin/magento module:enable Adyen_Payment
bin/magento setup:upgrade
```

## Manual ##
[Magento 2 documentation](https://docs.adyen.com/developers/plug-ins-and-partners/magento/magento-2)

## Setup Cron ##
Make sure that your magento cron is running every minute. We are using a cronjob to process the notifications. The cronjob will be executed every minute. It only executes the notifications that have been received at least 2 minutes ago. We have built in this 2 minutes so we are sure Magento has created the order and all save after events are executed. A handy tool to get insight into your cronjobs is AOE scheduler. You can download this tool through Magento Connect or GitHub

## Support ##
You can create issues on our Magento Repository or if you have some specific problems for your account you can contact magento@adyen.com as well.

## API Library ##
This module is using the Adyen APIs Library for PHP for all (API) connections to Adyen.
<a href="https://github.com/Adyen/adyen-php-api-library" target="_blank">This library can be found here</a>

## Setting up cronjob ##
The notifications of Adyen (this will give you the indication of the payment status) is processed by a cronjob. You need to setup your cronjob. <a href="http://devdocs.magento.com/guides/v2.0/config-guide/cli/config-cli-subcommands-cron.html" target="_blank">This is described here</a>

We have defined this:

```
<group id="index">
    <job name="adyen_payment_process_notification" instance="Adyen\Payment\Model\Cron" method="processNotification">
        <schedule>*/1 * * * *</schedule>
    </job>
</group>
```

## Vault ##
For enabling vault you need the following permissions:
rechargeSynchronousStoreDetails ask adyen support to enable this

Toggle one the following API responses. This can be done in the CA of Adyen inside API and Responses (settings -> API and Responses)
* Card summary
* Expiry date
* Variant