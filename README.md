# Adyen Payment plugin for Adobe Commerce (Magento 2)
Use Adyen's plugin for Magento 2 to offer frictionless payments online, in-app, and in-store.

## Integration
The plugin integrates card component (Secured Fields) using Adyen Checkout for all card payments. For Point Of Sale (POS) payments we use Terminal API using Cloud-based communication.

### Support Vault and Instant Purchase ###
Inside Adyen toggle the following settings on inside the API and Responses section (Settings -> API and Responses):
* Recurring details
* Card bin
* Card summary
* Expiry date
* Variant

## Requirements
This plugin supports Magento 2 version 2.4.4 and higher.

## Releases

1. **Major** releases are done ONLY when absolutely required. We try to not to introduce breaking changes and do major releases as rare as possible. Current average is **yearly**.
2. A minor or a patch release is scheduled but not limited to **once every 2 weeks.**

**Note: This can be subject to change based on the load and dependancies of the Integration tools team.**

## Customizing Adobe Commerce Plugin
You can customize your shoppers' checkout experience and add custom functionality to the plugin to meet your business needs. For example, you can apply modifications to the checkout process, or customize the style of your checkout to match your brand. 

For customizations, developers should extend the plugin by following Adyen’s API and [Adyen Plugin Customisation Guide](https://docs.adyen.com/plugins/adobe-commerce/customize/). If you customize inside of the default Adyen plugin, Adyen may be unable to provide plugin support, and upgrading and troubleshooting your integration will require additional effort.

For more details, refer to:
* [Adyen API Explorer](https://docs.adyen.com/api-explorer/Checkout/latest/overview)
* [Adyen Adobe Commerce Plugin Customisation Guide](https://docs.adyen.com/plugins/adobe-commerce/customize/)
* [Adyen Webhooks](https://docs.adyen.com/api-explorer/Webhooks/1/overview)

## Support & Troubleshooting for Headfull Magento/ Adobe Commerce Plugin

We provide specialized plugin support for major versions of the plugin following Adyen Adobe Commerce Support policy for 2 years, along with permanent Adyen support. Contact our [support team here](https://support.adyen.com/hc/en-us/requests/new?ticket_form_id=360000705420).

When a major plugin version is no longer under Adyen Adobe Commerce plugin support, it will be treated as a custom merchant integration. It is recommended to upgrade your payments plugin every 1-2 years.

* [Migration and Upgrade Guide](https://docs.adyen.com/plugins/adobe-commerce/upgrade/)
* [Troubleshooting Guide](https://docs.adyen.com/plugins/adobe-commerce/troubleshooting/)
* [Adobe Comerce Plugin Support Schedule](https://docs.adyen.com/plugins/adobe-commerce/#support-levels) 

## Support & Troubleshooting for Headless Adobe Commerce Payments
Adyen Plugin Support can help you with questions relating to the core backend functionality of the Adobe Commerce Headless Payment integration, including [API request processing](https://docs.adyen.com/plugins/adobe-commerce/headless-integration/#checkout-flow), [authentication](https://docs.adyen.com/plugins/adobe-commerce/headless-integration/#requirements), and payment lifecycle management. Contact our [support team here](https://support.adyen.com/hc/en-us/requests/new?ticket_form_id=360000705420).

However, merchant-specific customizations, including frontend implementations, collection of shopper details, rendering of payment methods on custom front-end, middleware configurations e.g. placing the order, handling additional actions, checking payment status etc.; and third-party dependencies - fall outside the scope of Adyen support. 

We recommend leveraging Adyen’s debugging tools to troubleshoot custom Headless implementations:
* [Adyen Headless troubleshooting guide](https://docs.adyen.com/plugins/adobe-commerce/headless-integration/#troubleshooting)
* Troubleshoot Headless API validation: [GitPod FLOW for REST](https://www.postman.com/adyendev/adyen-flows/flow/669e40799441740032f40154), [GitPod Flow for GraphQL](https://www.postman.com/adyendev/adyen-flows/flow/66b665d5cafbb0003264bef9)
 
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
For more information see our [installation section](https://docs.adyen.com/developers/plugins/magento-2/set-up-the-plugin-in-magento?redirect#step1installtheplugin).

## Documentation
- [Adobe Commerce (Magento 2) Adyen Payments Documentation](https://docs.adyen.com/plugins/adobe-commerce)
- [Adyen Payments V9 Migration Guide](https://docs.adyen.com/plugins/adobe-commerce/migrate-to-a-new-version)

## Setup Cron
Make sure that your Magento cron is running every minute. We are using a cronjob to process the notifications (our webhook service) and to update Adyen servers' IP addresses. The cronjobs will be executed every minute.

```
<group id="adyen_payment">
    <job name="adyen_payment_process_notification" instance="Adyen\Payment\Model\Cron" method="processNotification">
        <schedule>*/1 * * * *</schedule>
    </job>
    <job name="adyen_payment_server_address_caching" instance="Adyen\Payment\Cron\ServerIpAddress" method="execute">
        <schedule>*/1 * * * *</schedule>
    </job>
</group>
```

The notification processing service queries the records that have been received at least 2 minutes ago. This is to ensure that Magento has created the order, and all save after events are executed. A handy tool to get insight into your cronjobs is AOE scheduler. You can download this tool through Magento Marketplace or GitHub.

If you need to setup your cronjob in Magento <a href="http://devdocs.magento.com/guides/v2.0/config-guide/cli/config-cli-subcommands-cron.html" target="_blank">this is described here</a>.

## Supported Payment Methods

See our [documentation](https://docs.adyen.com/plugins/adobe-commerce/supported-payment-methods/) for a full list of supported payment methods.

## Raising issues
If you have a feature request, or spotted a bug or a technical problem, create a GitHub issue. 

## API Library
This module is using the Adyen APIs Library for PHP for all (API) connections to Adyen.
<a href="https://github.com/Adyen/adyen-php-api-library" target="_blank">This library can be found here</a>

## License
MIT license. For more information, see the [LICENSE](LICENSE.txt) file.
