<?php

/**
 * @file plugins/generic/webhook/WebhookPlugin.php
 *
 * Copyright (c) 2023-2026 Thomas F. K. Jorna, Center of Trial and Error
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class WebhookPlugin
 *
 * @brief Sends a JSON payload to configured URLs when submissions change.
 */

namespace APP\plugins\generic\webhook;

use APP\core\Application;
use APP\facades\Repo;
use APP\submission\Submission;
use APP\template\TemplateManager;
use PKP\core\APIRouter;
use PKP\core\JSONMessage;
use PKP\decision\Decision;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use PKP\publication\PKPPublication;
use PKP\submission\GenreDAO;
use PKP\db\DAORegistry;
use PKP\userGroup\UserGroup;
use Throwable;

class WebhookPlugin extends GenericPlugin
{
    /**
     * Decision labels as the 3.3 version of this plugin sent them, so consumers keep working.
     */
    public const DECISION_LABELS = [
        Decision::ACCEPT => 'accept',
        Decision::PENDING_REVISIONS => 'requestRevisions',
        Decision::RESUBMIT => 'resubmit',
        Decision::DECLINE => 'decline',
        Decision::SEND_TO_PRODUCTION => 'sendToProduction',
        Decision::EXTERNAL_REVIEW => 'skipReview',
        Decision::INITIAL_DECLINE => 'initialDecline',
        Decision::NEW_EXTERNAL_ROUND => 'newRound',
        Decision::REVERT_DECLINE => 'revertDecline',
        Decision::REVERT_INITIAL_DECLINE => 'revertInitialDecline',
        Decision::SKIP_EXTERNAL_REVIEW => 'skipExternalReview',
        Decision::BACK_FROM_PRODUCTION => 'backFromProduction',
        Decision::BACK_FROM_COPYEDITING => 'backFromCopyediting',
        Decision::RECOMMEND_ACCEPT => 'recommendAccept',
        Decision::RECOMMEND_PENDING_REVISIONS => 'recommendRevisions',
        Decision::RECOMMEND_RESUBMIT => 'recommendResubmit',
        Decision::RECOMMEND_DECLINE => 'recommendDecline',
    ];

    public const STATUS_LABELS = [
        Submission::STATUS_QUEUED => ['label' => 'queued', 'text' => 'submissions.queued'],
        Submission::STATUS_PUBLISHED => ['label' => 'published', 'text' => 'submission.status.published'],
        Submission::STATUS_DECLINED => ['label' => 'declined', 'text' => 'submission.status.declined'],
        Submission::STATUS_SCHEDULED => ['label' => 'scheduled', 'text' => 'submission.status.scheduled'],
    ];

    public ?WebhookEventManager $webhookEventManager = null;

    /**
     * @copydoc Plugin::register()
     *
     * @param null|mixed $mainContextId
     */
    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);
        if (!$success || Application::isUnderMaintenance()) {
            return $success;
        }

        $this->webhookEventManager = new WebhookEventManager($this);

        $defaultEvents = [
            ['publicationEdit', 'Publication::edit', $this->editPublication(...)],
            ['decision', 'Decision::add', $this->decision(...)],
            ['add', 'Submission::add', $this->addSubmission(...)],
            ['confirmReview', 'ReviewerAction::confirmReview', $this->confirmReview(...)],
            ['updateStatus', 'Submission::updateStatus', $this->updateStatus(...)],
        ];

        Hook::call('Plugin::Webhook::addEvent', [&$defaultEvents]);

        foreach ($defaultEvents as $event) {
            $this->webhookEventManager->addEvent($event[0], $event[1], $event[2] ?? null);
        }

        if ($this->getEnabled($mainContextId)) {
            Hook::add('APIHandler::endpoints::plugin', function (string $hookName, APIRouter $router): bool {
                $router->registerPluginApiControllers([new WebhookController($this)]);
                return Hook::CONTINUE;
            });
        }

        return $success;
    }

    /**
     * @copydoc Plugin::getDisplayName()
     */
    public function getDisplayName()
    {
        return __('plugins.generic.webhook.displayName');
    }

    /**
     * @copydoc Plugin::getDescription()
     */
    public function getDescription()
    {
        return __('plugins.generic.webhook.description');
    }

    /**
     * @copydoc Plugin::getContextSpecificPluginSettingsFile()
     */
    public function getContextSpecificPluginSettingsFile()
    {
        return $this->getPluginPath() . '/settings.xml';
    }

    public function decision(string $hookName, array $args): array
    {
        /** @var Decision $decision */
        [$decision] = $args;

        $type = Repo::decision()->getDecisionType($decision->getData('decision'));

        $decisionData = $decision->_data;
        $decisionData['decisionLabel'] = self::DECISION_LABELS[$decision->getData('decision')] ?? 'unknown';
        $decisionData['decisionText'] = $type?->getLabel();

        return [
            'submission' => Repo::submission()->get($decision->getData('submissionId')),
            'decision' => $decisionData,
            'result' => null,
            'recommendation' => Repo::decision()->isRecommendation($decision->getData('decision')),
        ];
    }

    public function addSubmission(string $hookName, array $args): array
    {
        [$submission] = $args;

        return ['submission' => $submission];
    }

    public function updateStatus(string $hookName, array $args): array
    {
        /** @var Submission $submission */
        [$newStatus, $status, $submission] = $args;

        $statusLabel = self::STATUS_LABELS[$status] ?? null;
        $newStatusLabel = self::STATUS_LABELS[$newStatus] ?? null;

        return [
            'status' => $status,
            'statusLabel' => $statusLabel['label'] ?? 'unknown',
            'statusText' => $statusLabel ? __($statusLabel['text']) : null,
            'newStatus' => $newStatus,
            'newStatusLabel' => $newStatusLabel['label'] ?? 'unknown',
            'newStatusText' => $newStatusLabel ? __($newStatusLabel['text']) : null,
            'submission' => $submission,
        ];
    }

    public function confirmReview(string $hookName, array $args): array
    {
        [$request, $submission, $mailable, $decline] = $args;

        $reviewer = $request->getUser();

        return [
            'submission' => $submission,
            'email' => $mailable,
            'decline' => $decline,
            'reviewer' => $reviewer ? ['id' => $reviewer->getId(), 'fullName' => $reviewer->getFullName()] : null,
        ];
    }

    public function editPublication(string $hookName, array $args): array
    {
        /** @var PKPPublication $newPublication */
        [$newPublication, $publication, $params] = $args;

        $submission = Repo::submission()->get($newPublication->getData('submissionId'));
        $contextId = $submission->getData('contextId');

        if ($newPublication->getData('authors') === null) {
            $newPublication->setData(
                'authors',
                Repo::author()->getCollector()->filterByPublicationIds([$newPublication->getId()])->getMany()
            );
        }
        if ($newPublication->getData('galleys') === null) {
            $newPublication->setData(
                'galleys',
                Repo::galley()->getCollector()->filterByPublicationIds([$newPublication->getId()])->getMany()
            );
        }

        /** @var GenreDAO $genreDao */
        $genreDao = DAORegistry::getDAO('GenreDAO');

        try {
            $data = Repo::publication()
                ->getSchemaMap(
                    $submission,
                    UserGroup::withContextIds($contextId)->get(),
                    $genreDao->getByContextId($contextId)->toAssociativeArray()
                )
                ->map($newPublication);
        } catch (Throwable $e) {
            error_log('Webhook could not map publication ' . $newPublication->getId() . ': ' . $e->getMessage());
            $data = $newPublication->_data;
        }

        return ['publication' => $data, 'props' => $params, 'submission' => $submission];
    }

    /**
     * Send a webhook to the given URLs
     *
     * @param string $event The event to fire
     * @param mixed $data The data to send
     * @param string[] $urls The URLs to send to
     * @param string[] $headers Extra headers to send
     *
     * @return array<string, string|false> The response body per URL
     */
    public function fireWebhook(string $event, mixed $data, array $urls, array $headers = []): array
    {
        $payload = json_encode(
            ['event' => $event, 'data' => $data],
            JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE
        );

        $defaultHeaders = [
            'Content-Type: application/json',
            'X-OJS-Webhook-Event: ' . $event,
        ];

        $results = [];
        foreach ($urls as $url) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => array_merge($defaultHeaders, $headers),
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 10,
            ]);
            $result = curl_exec($ch);
            if ($result === false) {
                error_log("Webhook {$event} to {$url} failed: " . curl_error($ch));
            }
            curl_close($ch);
            $results[$url] = $result;
        }

        return $results;
    }

    /**
     * @return array{url: string, events: string[], disabled: bool}[]
     */
    public function getRegisteredWebhooks(int $contextId): array
    {
        return $this->getSetting($contextId, 'webhooks') ?? [];
    }

    /**
     * @copydoc Plugin::getActions()
     */
    public function getActions($request, $actionArgs)
    {
        $actions = parent::getActions($request, $actionArgs);
        if (!$this->getEnabled()) {
            return $actions;
        }

        $router = $request->getRouter();

        return array_merge(
            [
                new LinkAction(
                    'settings',
                    new AjaxModal(
                        $router->url($request, null, null, 'manage', null, ['verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'generic']),
                        $this->getDisplayName()
                    ),
                    __('plugins.generic.webhook.linkactions.settings'),
                    null
                ),
            ],
            $actions
        );
    }

    /**
     * @copydoc Plugin::manage()
     */
    public function manage($args, $request)
    {
        if ($request->getUserVar('verb') !== 'settings') {
            return parent::manage($args, $request);
        }

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('webhookEventManager', $this->webhookEventManager);

        $form = new WebhookSettingsForm($this, $request->getContext()->getId());

        if ($request->getUserVar('save')) {
            $form->readInputData();
            if ($form->validate()) {
                $form->execute();
                return new JSONMessage(true);
            }
            $templateMgr->assign('formErrors', $form->getErrorsArray());
        } else {
            $form->initData();
        }

        return new JSONMessage(true, $form->fetch($request));
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\plugins\generic\webhook\WebhookPlugin', '\WebhookPlugin');
}
