<?php

/**
 * @file plugins/generic/webhook/WebhookEventManager.php
 *
 * Copyright (c) 2023-2026 Thomas F. K. Jorna, Center of Trial and Error
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class WebhookEventManager
 *
 * @brief Ties webhook events to OJS hooks and sends them to the URLs subscribed to each event.
 */

namespace APP\plugins\generic\webhook;

use APP\core\Application;
use PKP\plugins\Hook;
use PKP\submission\PKPSubmission;

class WebhookEventManager
{
    /** @var string[] */
    private array $events = [];

    public function __construct(private WebhookPlugin $plugin)
    {
    }

    public function addEvent(string $eventName, string $hookName, ?callable $dataProvider = null): void
    {
        $this->events[] = $eventName;

        Hook::add($hookName, function (string $hookName, array $args) use ($eventName, $dataProvider): bool {
            $data = $dataProvider ? $dataProvider($hookName, $args) : $args;
            $this->fireEvent($eventName, $data);
            return Hook::CONTINUE;
        });
    }

    /**
     * @return string[]
     */
    public function getEvents(): array
    {
        return $this->events;
    }

    private function fireEvent(string $eventName, array $data): void
    {
        $contextId = $this->getContextId($data);
        if ($contextId === null || !$this->plugin->getEnabled($contextId)) {
            return;
        }

        $webhooks = array_filter(
            $this->plugin->getSetting($contextId, 'webhooksByEvent')[$eventName] ?? [],
            fn (array $webhook) => empty($webhook['disabled'])
        );
        if (empty($webhooks)) {
            return;
        }

        $this->plugin->fireWebhook($eventName, $data, array_column($webhooks, 'url'));
    }

    /**
     * The journal an event belongs to: the submission's, or else the request's
     */
    private function getContextId(array $data): ?int
    {
        $submission = $data['submission'] ?? null;
        if ($submission instanceof PKPSubmission) {
            return $submission->getData('contextId');
        }

        return Application::get()->getRequest()->getContext()?->getId();
    }
}
