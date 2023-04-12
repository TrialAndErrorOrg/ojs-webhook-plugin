
Webhook Plugin
==============

The Webhook Plugin is an easy-to-use, flexible, and powerful solution for OJS/OMP that allows you to send notifications to external services when various events occur in your application. This plugin allows you to configure multiple webhooks per event and customize the data sent to each webhook. Additionally, the plugin provides an extensible interface for developers to add custom events and data providers.

Table of Contents
-----------------

*   [Installation](#installation)
*   [Configuration](#configuration)
*   [Usage](#usage)
    *   [Registering Your Own Webhooks](#registering-your-own-webhooks)
    *   [Adding Custom Events](#adding-custom-events)
    *   [Adding Custom Data Providers](#adding-custom-data-providers)
*   [Returned Data](#returned-data)

Installation
------------

1.  Download the latest release of the Webhook Plugin from the GitHub repository.
    
2.  Extract the downloaded file and rename the extracted folder to `webhook`.
    
3.  Upload the `webhook` folder to the `plugins/generic` directory of your OJS/OMP installation.
    
4.  Login to the OJS/OMP admin dashboard and navigate to **Settings > Website > Plugins**.
    
5.  Find the Webhook Plugin in the "Generic Plugins" section and click the "Enable" checkbox.
    
6.  Click the blue "Settings" button to configure the plugin.
    


Configuration
-------------

In the plugin settings, you can configure webhooks by adding URLs and selecting the events that trigger each webhook. For each webhook, you can:

*   Set the webhook URL.
*   Choose the events that trigger the webhook.
*   Send a test notification to the webhook URL.
*   Optionally disable the webhook without removing it.

Usage
-----

### Registering Your Own Webhooks

To register your own webhook, follow these steps:

1.  In the Webhook Plugin settings, click the "Add Webhook" button.
    
2.  Enter the webhook URL where the notifications will be sent.
    
3.  Check the events you want to trigger the webhook.
    
4.  Save your changes.
    
### Adding custom events

To register your own events, you should use the `Plugin::Webhook::addEvent` hook. 

This way, you get access to the default webhook events. By modifying this array, you can alter the available options. Make sure to inform your users that you are doing this.


```php
$defaultEvents = [
    ['publicationEdit', 'Publication::validate', [$this, 'handleEditPublication']],
    ['decision', 'EditorAction::recordDecision', [$this, 'editorDecision']],
    ['add', 'Submission::add', [$this, 'addSubmission']],
    ['confirmReview', 'ReviewerAction::confirmReview', [$this, 'confirmReview']],
    ['updateStatus', 'Submission::updateStatus', [$this, 'updateStatus']],
];
```

To use this hook in your own plugin, register it like this:

```php
HookRegistry::register('Plugin::Webhook::addEvent', [$this, 'handleAddWebhookEvents']);
```

You can then define a method `handleAddWebhookEvents` which gets access to the default webhook events. By modifying this array, you can alter the available options. Make sure to inform your users that you are doing this.

### Webhook API Endpoint

There is an API endpoint you can use to send webhooks from the frontend, although it is only accessible to administrators at the moment. The endpoint is located at `/api/v1/webhook` and accepts the following parameters:

*   `event`: (string) The name of the event.
*   `data`: (any) The data to include in the webhook payload.
*   `urls?`: (string\[\]) Optional array of URLs to send the webhook to. If not specified, the payload will be sent to the already defined URLs.

To use this API endpoint, make a POST request with the appropriate parameters.


Returned Data
-------------

The data returned by the webhook depends on the event that triggered it. 

In general,  the payload looks like
```json
{
    "event": "event_name",
    "data": {
       // relevant data 
    }
}
```

For e.g. publications, the data looks like
```json
```

The following events are currently supported:

*   `publicationEdit`: Triggered when a publication is edited. The data returned includes the publication ID, the submission ID, and the user ID of the editor who made the change.
*   `decision`: Triggered when a decision is made on a submission. The data returned includes the submission ID, the user ID of the editor who made the decision, and the decision.
*   `add`: Triggered when a submission is added. The data returned includes the submission ID, the user ID of the editor who added the submission, and the submission title.
*   `confirmReview`: Triggered when a reviewer confirms a review. The data returned includes the submission ID, the user ID of the reviewer who confirmed the review, and the review ID.
*   `updateStatus`: Triggered when a submission's status is updated. The data returned includes the submission ID, the user ID of the editor who updated the status, and the new status.
