<?php

import('lib.pkp.classes.form.Form');

class WebhookSettingsForm extends Form
{
    /** @var int Context ID */
    var $contextId;

    /** @var WebhookPlugin */
    var $plugin;

    /**
     * Constructor
     * @param WebhookPlugin $plugin
     * @param int $contextId
     */
    public function __construct($plugin, $contextId)
    {
        $this->contextId = $contextId;
        $this->plugin = $plugin;

        parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));

        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));
    }

    /**
     * @copydoc Form::fetch()
     */
    function fetch(Request $request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('pluginName', $this->plugin->getName());

        $apiUrl = $request->getBaseUrl() . '/' . 'index.php' . '/' . $request->getContext()->getPath() . '/' . 'api' . '/' . 'v1' . '/' . 'webhook';
        $templateMgr->assign('apiUrl', $apiUrl);






        return parent::fetch($request, $template, $display);
    }

    public function initData()
    {
        $plugin = $this->plugin;
        $contextId = $this->contextId;

        $this->setData('webhooks', $plugin->getSetting($contextId, 'webhooks'));
    }

    public function readInputData()
    {
        $this->readUserVars(['webhooks']);
    }

    /**
     * Save the form
     * 
     * @copydoc Form::execute()
     */
    public function execute($request, ...$functionArgs)
    {

        parent::execute($request, ...$functionArgs);
        $plugin = $this->plugin;
        $contextId = $this->contextId;

        $webhooksData = $this->getData('webhooks');
        error_log(json_encode($webhooksData));
        $webhooks = [];

        foreach ($webhooksData as $webhookData) {
            $url = $webhookData['url'];
            $events = array_keys(array_filter($webhookData['events'])); // Get selected event keys
            $disabled = !$webhookData['disabled'];
            $webhooks[] = ['url' => $url, 'events' => $events, 'disabled' => $disabled];
        }

        $plugin->updateSetting($contextId, 'webhooks', $webhooks);

        $webhooksByEvent = [];
        foreach ($webhooks as $webhook) {
            foreach ($webhook['events'] as $event) {
                if ($webhook['disabled']) {
                    continue;
                }
                $webhooksByEvent[$event][] = $webhook;
            }
        }

        $plugin->updateSetting($contextId, 'webhooksByEvent', $webhooksByEvent);


        error_log("webhook data: " . json_encode($webhooks));

        $notificationManager = new NotificationManager();
        $notificationManager->createTrivialNotification($request->getUser()->getId(), NOTIFICATION_TYPE_SUCCESS);


    }

    /**
     * @copydoc Form::validate()
     */
    public function validate($callHooks = true)
    {
        $webhooks = $this->getData('webhooks');

        $isValid = true;

        foreach ($webhooks as $index => $webhook) {
            if (empty($webhook['url']) || !filter_var($webhook['url'], FILTER_VALIDATE_URL)) {
                $this->addError("webhookUrl-{$index}-error", __('plugins.generic.webhook.validation.invalidUrl'));
                $isValid = false;
            }

            if (empty($webhook['events']) || !is_array($webhook['events'])) {
                $this->addError("webhookEvents-{$index}-error", __('plugins.generic.webhook.validation.noEventsSelected'));
                $isValid = false;
            }
        }

        if (!$isValid) {
            return false;
        }

        return parent::validate($callHooks);
    }

}