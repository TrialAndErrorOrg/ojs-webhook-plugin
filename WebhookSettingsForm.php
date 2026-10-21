<?php

/**
 * @file plugins/generic/webhook/WebhookSettingsForm.php
 *
 * Copyright (c) 2023-2026 Thomas F. K. Jorna, Center of Trial and Error
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class WebhookSettingsForm
 *
 * @brief The webhook URLs of a journal and the events each one gets.
 */

namespace APP\plugins\generic\webhook;

use APP\core\Application;
use APP\notification\NotificationManager;
use APP\template\TemplateManager;
use PKP\form\Form;
use PKP\form\validation\FormValidatorCSRF;
use PKP\form\validation\FormValidatorPost;
use PKP\notification\Notification;

class WebhookSettingsForm extends Form
{
    public function __construct(public WebhookPlugin $plugin, public int $contextId)
    {
        parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));

        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));
    }

    /**
     * @copydoc Form::fetch()
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'pluginName' => $this->plugin->getName(),
            'apiUrl' => $request->getDispatcher()->url($request, Application::ROUTE_API, $request->getContext()->getPath(), 'webhook'),
        ]);

        return parent::fetch($request, $template, $display);
    }

    /**
     * @copydoc Form::initData()
     */
    public function initData()
    {
        $this->setData('webhooks', $this->plugin->getRegisteredWebhooks($this->contextId));
    }

    /**
     * @copydoc Form::readInputData()
     */
    public function readInputData()
    {
        $this->readUserVars(['webhooks']);
    }

    /**
     * @copydoc Form::validate()
     */
    public function validate($callHooks = true)
    {
        $isValid = true;

        foreach ($this->getData('webhooks') ?? [] as $index => $webhook) {
            if (empty($webhook['url']) || !filter_var($webhook['url'], FILTER_VALIDATE_URL)) {
                $this->addError("webhookUrl-{$index}-error", __('plugins.generic.webhook.validation.invalidUrl'));
                $isValid = false;
            }

            if (empty($webhook['events']) || !is_array($webhook['events'])) {
                $this->addError("webhookEvents-{$index}-error", __('plugins.generic.webhook.validation.noEventsSelected'));
                $isValid = false;
            }
        }

        return $isValid && parent::validate($callHooks);
    }

    /**
     * @copydoc Form::execute()
     */
    public function execute(...$functionArgs)
    {
        parent::execute(...$functionArgs);

        $webhooks = [];
        foreach ($this->getData('webhooks') ?? [] as $webhookData) {
            $webhooks[] = [
                'url' => $webhookData['url'],
                'events' => array_keys(array_filter($webhookData['events'])),
                'disabled' => empty($webhookData['disabled']),
            ];
        }
        $this->plugin->updateSetting($this->contextId, 'webhooks', $webhooks);

        $webhooksByEvent = [];
        foreach ($webhooks as $webhook) {
            if ($webhook['disabled']) {
                continue;
            }
            foreach ($webhook['events'] as $event) {
                $webhooksByEvent[$event][] = $webhook;
            }
        }
        $this->plugin->updateSetting($this->contextId, 'webhooksByEvent', $webhooksByEvent);

        $user = Application::get()->getRequest()->getUser();
        (new NotificationManager())->createTrivialNotification($user->getId(), Notification::NOTIFICATION_TYPE_SUCCESS);
    }
}
