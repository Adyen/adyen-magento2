# Adyen_Magento2
Adyen Payment plugin for Magento2

## Installation ##
```
bin/composer require adyen/module-payment
bin/magento module:enable Adyen_Payment
bin/magento setup:upgrade
```

## Manual ##
<a href="https://docs.adyen.com/developers/magento#magento2integration" target="_blank">https://docs.adyen.com/developers/magento#magento2integration</a>

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
