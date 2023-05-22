    <script>
        $(function() {ldelim}
        // Attach the form handler.
        $('#webhookSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
        {rdelim});
        {literal}
            $(document).ready(function() {
                $('#webhooksList').on('click', '.removeButton', function() {
                    $(this).closest('.webhook').remove();
                });
                // Create a unique key for each webhook
                let webhookKey = 0;

                const errors = {/literal}{$formErrors|json_encode}{literal};

                // Load existing webhooks
                let existingWebhooks = {/literal}{$webhooks|json_encode}{literal};
                existingWebhooks?.forEach((webhook) => {
                    if (webhook.disabled === '1') {
                        webhook.disabled = false;
                    }

                    if (webhook.disabled === undefined) {
                        webhook.disabled = true;
                    }

                    if (typeof webhook.events === 'object' && webhook.events !== null && !Array.isArray(
                            webhook.events)) {
                        webhook.events = Object.keys(webhook.events);
                    }
                    addWebhook(webhook);
                });

                // Add event listener for the "Add Webhook" button
                document.getElementById('addWebhook').addEventListener('click', function() {
                    addWebhook();
                });



                function addWebhook(webhookData = {}) {
                    let newWebhook = document.querySelector('.webhook.template').cloneNode(true);
                    newWebhook.classList.remove('template');
                    newWebhook.style.display = '';

                    let textInput = newWebhook.querySelector('input[type="text"]')
                    textInput.name = `webhooks[${webhookKey}][url]`;
                    textInput.id = `webhookurl-${webhookKey}`;

                    // Set webhook data if provided
                    if (webhookData.url) {
                        textInput.value = webhookData.url;
                    }

                    let eventCheckboxes = newWebhook.querySelectorAll('input[name="webhookEvents"]');
                    eventCheckboxes?.forEach((checkbox) => {
                        let eventTypeKey = checkbox.value;
                        checkbox.name = `webhooks[${webhookKey}][events][${eventTypeKey}]`;
                        checkbox.id = `webhookEvents-new-${eventTypeKey}`;
                        if (Array.isArray(webhookData.events) && webhookData.events.includes(
                                eventTypeKey)) {
                            checkbox.checked = true;
                        }
                    });

                    let disabledBox = newWebhook.querySelector('input[name="webhookDisabled"]');
                    disabledBox.name = `webhooks[${webhookKey}][disabled]`;
                    disabledBox.id = `webhookDisabled-${webhookKey}`;
                    disabledBox.checked = !webhookData.disabled;

                    let inputFormError = newWebhook.querySelector('.webhook-url-error');
                    inputFormError.id = `webhookUrl-${webhookKey}-error`;
                    if (errors?. [inputFormError.id]) {
                        inputFormError.textContent = errors[inputFormError.id];
                        inputFormError.style.display = 'block';
                    }

                    let eventsFormError = newWebhook.querySelector('.webhook-events-error');
                    eventsFormError.id = `webhookEvents-${webhookKey}-error`;
                    if (errors?. [eventsFormError.id]) {
                        eventsFormError.textContent = errors[eventsFormError.id];
                        eventsFormError.style.display = 'block';
                    }


                    webhookKey++;

                    document.getElementById('webhooksList').appendChild(newWebhook);
                }


                document.querySelectorAll('.testWebhook')?.forEach((button) => {
                    button.addEventListener('click', function() {
                        let webhookUrlInput = button.closest('.webhook').querySelector(
                            'input[type="text"]');
                        let webhookUrl = webhookUrlInput.value;

                        if (!webhookUrl || !isValidUrl(webhookUrl)) {
                            alert('Please enter a valid webhook URL.');
                            return;
                        }

                        // Perform the webhook test here (e.g., make an HTTP request to the webhook URL)
                        testWebhook(webhookUrl);
                    });
                });
            });

            // Helper function to validate URLs
            function isValidUrl(url) {
                try {
                    new URL(url);
                    return true;
                } catch (_) {
                    return false;
                }
            }

            // Helper function to test a webhook
            async function testWebhook(webhookUrl) {
                const  url = '{/literal}{$apiUrl}{literal}'
                const form = document.querySelector('#webhookSettingsForm');
                const csrfInput = form.querySelector('input[name="csrfToken"]');
                const csrfToken = csrfInput.value;

                try {
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Csrf-Token': csrfToken
                        },
                        body: JSON.stringify({
                            urls: [webhookUrl],
                            event: 'test',
                            "data": {
                                "test": "test successful"
                            }
                        })

                    });

                    if (response.ok) {
                        const result = await response.json();
                        if (result.success) {
                            alert(`Webhook test sent successfully to: ${webhookUrl}
                        Response: ${JSON.stringify(result.message)}`);
                        } else {
                            alert(`Webhook test failed for: ${webhookUrl}. 
                        Error: ${JSON.stringify(result.message)}.`);
                        }
                    } else {
                        alert(`Error sending webhook test: ${response.statusText}`);
                    }
                } catch (error) {
                    alert(`Error sending webhook test: ${error}`);
                }
            }
        {/literal}
    </script>

    <style>
        .template {
            display: none;
        }

        :root {
            --slate100: #f1f5f9;
        }

        .options-row {
            display: flex;
            margin-top: 2rem;
            gap: 0.5rem;
            margin-block: 0;
            padding-inline: 0;
            align-items: flex-end;
            justify-content: center;
        }

        .options-row li::marker {
            color: transparent;
        }

        .options-row li label {
            display: flex;
            padding: .5rem;
            flex-wrap: wrap;
            flex-direction: column-reverse;
            font-size: .8rem !important;
            width: 8rem;
            line-height: 1.2;
            align-items: center;
            justify-content: space-between;
            text-align: center;
        }

        .options-row li label:hover {
            background-color: var(--slate100);
        }

        .options-row li label input {
            width: 1.5rem;
            height: 1.5rem;
            margin: 1rem;
        }

        .existing-webhook {
            border: 1px solid var(--slate100);
            border-radius: .5rem;
            margin-top: 1rem;
            margin-bottom: 1rem;
            padding: 1rem;
        }

        .webhook-input-remove {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
        }

        #addWebhook::before {
            display: inline-block;
            font: normal normal normal 14px/1 FontAwesome;
            font-size: inherit;
            text-rendering: auto;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            transform: translate(0, 0);
            content: "\f067";
            margin-right: 0.5em;
        }

        .removeButton::before {
            display: inline-block;
            font: normal normal normal 18px/1 FontAwesome;
            font-size: 1.5em;
            text-rendering: auto;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            transform: translate(0, 0);
            content: "\f1f8";
        }

        .testWebhook::before {
            display: inline-block;
            font: normal normal normal 18px/1 FontAwesome;
            font-size: 1.5em;
            text-rendering: auto;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            transform: translate(0, 0);
            content: "\f04b";
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 3.5rem;
            height: 2rem;
        }

        .switch input[type="checkbox"] {
            display: none;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 100rem !important;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 1.5rem;
            width: 1.5rem;
            left: .25rem;
            bottom: .25rem;
            background-color: white;
            transition: .2s ease-in-out;
            border-radius: 100%;
        }

        input[type="checkbox"]:checked+.slider {
            background-color: #2196F3;
        }

        input[type="checkbox"]:checked+.slider:before {
            transform: translateX(1.5rem);
        }

        .webhook-buttons {
            display: flex;
            align-items: flex-end;
            justify-content: center;
            gap: 1rem;
        }

        .webhook-input {
            display: flex;
            align-items: flex-end;
            justify-content: center;
            width: 100%;
            gap: 1rem;
        }
    </style>
    <div class="webhook template">
        <label for="webhookUrl">{translate key="plugins.generic.webhook.settings.webhookUrl"}</label>

        <div class="webhook-url-error" id="webhookUrl-error" style="display: none; color: red;"></div>
        <div class="webhook-input">
            <input type="text" id="webhookUrl" name="webhookUrl">
            <div class="webhook-buttons">
                {fbvElement type="button" class="testWebhook" id="testWebhook"}
                <label class="switch">
                    <input type="checkbox" name="webhookDisabled" id="webhookDisabled" value=1
                        {if !isset($webhookData.disabled) || !$webhookData.disabled}checked{/if}>
                    <span class="slider"></span>
                </label>
                {fbvElement type="button" class="removeButton pkp_button_offset" label="plugins.generic.webhook.settings.removeWebhook" id="removeWebhook"}
            </div>
        </div>
        <div class="webhook-events-error" id="webhookEvents-error" style="display: none; color: red;">
        </div>
        <ul class="options-row">
            {assign var="eventTypes" value=$webhookEventManager->getEvents()}
            {foreach from=$eventTypes item=eventType key=eventTypeKey}
                {fbvElement type="checkbox" value=$eventType class="webhookEvent" label="plugins.generic.webhook.event.{$eventType}"
                id=$eventType name="webhookEvents"}
            {/foreach}
        </ul>
    </div>
    <form class="pkp_form" id="webhookSettingsForm" method="post"
        action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}">
        {csrf}

        {include file="controllers/notification/inPlaceNotification.tpl" notificationId="webhookNotification"}

        {fbvFormSection title="plugins.generic.webhook.settings.title"}
        {fbvFormArea id="webhooksList"}
        {* this is where the things are added *}
        {/fbvFormArea}
        {/fbvFormSection}

        {fbvElement type="button" id="addWebhook" class="submitFormButton" label="plugins.generic.webhook.settings.addWebhook"}

        {fbvFormButtons}
</form>