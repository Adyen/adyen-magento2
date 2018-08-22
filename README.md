# Adyen Payment plugin for Magento2
Use Adyen's plugin for Magento 2 to offer frictionless payments online, in-app, and in-store.

## Requirements
This plugin supports Magento2 version 2.1 and higher. <br/>
For Magento2 version 2.0 support, use plugin version 1.4.6.

## Collaboration
We commit all our new features directly into our GitHub repository.
But you can also request or suggest new features or code changes yourself!

## Installation
You can install our plugin through Composer:
```
composer require adyen/module-payment
bin/magento module:enable Adyen_Payment
bin/magento setup:upgrade
```
For more information see our [installation section](https://docs.adyen.com/developers/plug-ins-and-partners/magento-2/set-up-the-plugin-in-magento#step1installtheplugin)

 ## Documentation
[Magento 2 documentation](https://docs.adyen.com/developers/plug-ins-and-partners/magento-2)


## Setup Cron
Make sure that your Magento cron is running every minute. We are using a cronjob to process the notifications, our webhook service. The cronjob will be executed every minute. It only executes the notifications that have been received at least 2 minutes ago. This is to ensure that Magento has created the order, and all save after events are executed. A handy tool to get insight into your cronjobs is AOE scheduler. You can download this tool through Magento Connect or GitHub.
If you need to setup your cronjob in Magento <a href="http://devdocs.magento.com/guides/v2.0/config-guide/cli/config-cli-subcommands-cron.html" target="_blank">this is described here</a>

We have defined this:
```
<group id="index">
    <job name="adyen_payment_process_notification" instance="Adyen\Payment\Model\Cron" method="processNotification">
        <schedule>*/1 * * * *</schedule>
    </job>
</group>
```

## Support
You can create issues on our Magento Repository. In case of specific problems with your account, please contact  <a href="mailto:support@adyen.com">support@adyen.com</a>.

## API Library
This module is using the Adyen APIs Library for PHP for all (API) connections to Adyen.
<a href="https://github.com/Adyen/adyen-php-api-library" target="_blank">This library can be found here</a>

## Licence
MIT license. For more information, see the LICENSE file.
