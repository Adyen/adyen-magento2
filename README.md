# Adyen Payment plugin for Magento2
Use Adyen's plugin for Magento 2 to offer frictionless payments online, in-app, and in-store.

## Integration
The plugin integrates card component(Secured Fields) using Adyen Checkout for all card payments. Local/redirect payment methods are integrated with DirectoryLookup and HPP. For Point Of Sale (POS) payments we use Terminal API using Cloud-based communication. Boleto and SEPA are a direct API integration into Adyen.

### Support Vault and Instant Purchase ###
Inside Adyen toggle the following settings on inside the API and Responses section (settings -> API and Responses)
* Recurring details
* Card summary
* Expiry date
* Variant

## Requirements
This plugin supports Magento2 version 2.2.8 and higher.

## Contributing
We strongly encourage you to join us in contributing to this repository so everyone can benefit from:
* New features and functionality
* Resolved bug fixes and issues
* Any general improvements

Read our [**contribution guidelines**](CONTRIBUTING.md) to find out how.


## Installation
You can install our plugin through Composer:
```
composer require adyen/module-payment
bin/magento module:enable Adyen_Payment
bin/magento setup:upgrade
```
For more information see our [installation section](https://docs.adyen.com/developers/plugins/magento-2/set-up-the-plugin-in-magento?redirect#step1installtheplugin)

 ## Documentation
[Magento 2 documentation](https://docs.adyen.com/developers/plugins/magento-2)


## Setup Cron
Make sure that your Magento cron is running every minute. We are using a cronjob to process the notifications, our webhook service. The cronjob will be executed every minute. It only executes the notifications that have been received at least 2 minutes ago. This is to ensure that Magento has created the order, and all save after events are executed. A handy tool to get insight into your cronjobs is AOE scheduler. You can download this tool through Magento Connect or GitHub.
If you need to setup your cronjob in Magento <a href="http://devdocs.magento.com/guides/v2.0/config-guide/cli/config-cli-subcommands-cron.html" target="_blank">this is described here</a>

We have defined this:
```
<group id="adyen_payment">
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

## License
MIT license. For more information, see the LICENSE file.
