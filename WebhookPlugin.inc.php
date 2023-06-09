<?php

/**
 * @file plugins/generic/webhook/WebhookPlugin.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class WebhookPlugin
 * @ingroup plugins_block_webhook
 *
 * @brief Fix SSL plugin class
 */


// define('SUBMISSION_EDITOR_DECISION_EXTERNAL_REVIEW', 8);

// // Submission and review stages decision actions.
// define('SUBMISSION_EDITOR_DECISION_ACCEPT', 1);
// define('SUBMISSION_EDITOR_DECISION_DECLINE', 4);

// // Review stage decisions actions.
// define('SUBMISSION_EDITOR_DECISION_PENDING_REVISIONS', 2);
// define('SUBMISSION_EDITOR_DECISION_RESUBMIT', 3);
// define('SUBMISSION_EDITOR_DECISION_NEW_ROUND', 16);

// // Editorial stage decision actions.
// define('SUBMISSION_EDITOR_DECISION_SEND_TO_PRODUCTION', 7);


import('lib.pkp.classes.plugins.GenericPlugin');


class WebhookPlugin extends GenericPlugin
{

	var $submissionMap = array('', 'accept', 'revisions', 'resubmit', 'decline', '', '', 'production', 'review', '', '', '', '', '', '', '', 'round');

	var $decisionMap = [
		//  SUBMISSION_EDITOR_DECISION_ACCEPT
		1 => 'accept',

		//	SUBMISSION_EDITOR_DECISION_PENDING_REVISIONS
		2 => 'requestRevisions',

		//	SUBMISSION_EDITOR_DECISION_RESUBMIT
		3 => 'resubmit',

		//	SUBMISSION_EDITOR_DECISION_DECLINE
		4 => 'decline',

		//	SUBMISSION_EDITOR_DECISION_SEND_TO_PRODUCTION
		7 => 'sendToProduction',

		//	SUBMISSION_EDITOR_DECISION_EXTERNAL_REVIEW
		8 => 'skipReview',

		// SUBMISSION_EDITOR_DECISION_INITIAL_DECLINE
		9 => 'initialDecline',

		//SUBMISSION_EDITOR_DECISION_NEW_ROUND
		16 => 'newRound',

		// SUBMISSION_EDITOR_DECISION_REVERT_DECLINE
		17 => 'revertDecline',
	];

	var $statusMap = [
		1 => [
			'label' => 'queued',
			'text' => 'submissions.queued'
		],
		3 => [
			'label' => 'published',
			'text' => 'submission.status.published'
		],
		4 => [
			'label' => 'declined',
			'text' => 'submission.status.declined'
		],
		5 => [
			'label' => 'scheduled',
			'text' => 'submission.status.scheduled'
		]
	];
	// var $urls = ['https://auto.trialanderror.org/webhook-test/cda048ab-cbb1-42d4-8fa7-3116f20bea48', 'https://auto.trialanderror.org/webhook/cda048ab-cbb1-42d4-8fa7-3116f20bea48', 'https://play.svix.com/in/e_OlxpPyfrm1bj6kOtaAWFX6v2w91/', 'https://typedwebhook.tools/webhook/0d27b246-0df7-49d5-9629-84582558c664'];


	/** @var WebhookEventManager */
	var $webhookEventManager;
	/**
	 * Get the display name of this plugin
	 * @return string
	 */
	public function getDisplayName()
	{
		return __('plugins.generic.webhook.displayName');
	}

	/**
	 * Get the description of this plugin
	 * @return string
	 */
	public function getDescription()
	{
		return __('plugins.generic.webhook.description');
	}


	/**
	 * @copydoc Plugin::register()
	 */
	public function register($category, $path, $mainContextId = null)
	{
		if (!parent::register($category, $path, $mainContextId))
			return false;
		if ($this->getEnabled($mainContextId)) {
			$this->import('WebhookEventManager');
			$this->webhookEventManager = new WebhookEventManager($this);


			$defaultEvents = [
				['publicationEdit', 'Publication::validate', [$this, 'handleEditPublication']],
				['decision', 'EditorAction::recordDecision', [$this, 'editorDecision']],
				['add', 'Submission::add', [$this, 'addSubmission']],
				['confirmReview', 'ReviewerAction::confirmReview', [$this, 'confirmReview']],
				['updateStatus', 'Submission::updateStatus', [$this, 'updateStatus']],
			];

			HookRegistry::call('Plugin::Webhook::addEvent', [&$defaultEvents]);

			foreach ($defaultEvents as $event) {
				error_log(json_encode($event[0]));
				$this->webhookEventManager->addEvent($event[0], $event[1], $event[2] ?? null);
			}
			// add the api handler
			HookRegistry::register('Dispatcher::dispatch', function (string $hook, \PKPRequest $request): bool {
				$path = array_slice(explode('/', trim($request->getRequestPath(), '/')), -1);
				$router = $request->getRouter();
				if ($router instanceof \APIRouter && $path[0] == 'webhook') {
					error_log("Webhook handler");
					error_log(json_encode($path));
					$this->import('WebhookAPIHandler');
					$handler = new WebhookAPIHandler();
					$router->setHandler($handler);
					$handler->getApp()->run();
					exit;
				}
				return false;
			});

		}
		return true;
	}



	/**
	 * Get the name of the settings file to be installed on new context
	 * creation.
	 * @return string
	 */
	public function getContextSpecificPluginSettingsFile()
	{
		return $this->getPluginPath() . '/settings.xml';
	}


	public function editorDecision(string $hookName, $args)
	{
		list($submission, $editorDecision, $result, $recommendation) = $args;

		/** @var Submission $submission */
		$submission = $args[0];
		$editorDecision = $args[1];

		$decisionLabel = $this->decisionMap[$editorDecision['decision']] ?? 'unknown';

		$decisionText = __('editor.submission.decision.' . $decisionLabel);

		$editorDecision['decisionText'] = $decisionText;
		$editorDecision['decisionLabel'] = $decisionLabel;


		$payload = ["submission" => $submission, "decision" => $editorDecision, "result" => $result, "recommendation" => $recommendation];

		error_log(json_encode($payload));

		return $payload;
	}
	public function addSubmission(string $hookName, $args)
	{
		/** @var Submission $submission */
		$submission = $args[0];

		/** @var Request $request*/
		$request = $args[1];

		return ["submission" => $submission, "request" => $request];
	}

	public function updateStatus(string $hookname, $args)
	{
		/** @var number $status */
		$status = $args[0];
		/** @var Submission $submission */
		$submission = $args[1];

		$newStatus = $status;
		if ($status !== STATUS_DECLINED) {

			$newStatus = STATUS_QUEUED;
			$publication = $submission->getCurrentPublication();
			if ($publication && $publication->getData('status') === STATUS_PUBLISHED) {
				$newStatus = STATUS_PUBLISHED;
			}
			if ($publication && $publication->getData('status') === STATUS_SCHEDULED) {
				$newStatus = STATUS_SCHEDULED;
			}
		}
		$statusLabel = $this->statusMap[$status] ? $this->statusMap[$status]['label'] : 'unknown';
		$statusTextLabel = $statusLabel ? $this->statusMap[$status]['text'] : null;
		$statusText = $statusTextLabel ? __($statusTextLabel) : null;

		$newStatusLabel = $this->statusMap[$newStatus] ? $this->statusMap[$newStatus]['label'] : 'unknown';
		$newStatusTextLabel = $newStatusLabel ? $this->statusMap[$newStatus]['text'] : null;
		$newStatusText = $newStatusTextLabel ? __($newStatusTextLabel) : null;


		return [
			"status" => $status,
			"statusLabel" => $statusLabel,
			"statusText" => $statusText,
			"newStatus" => $newStatus,
			"newStatusLabel" => $newStatusLabel,
			"newStatusText" => $newStatusText,
			"submission" => $submission
		];
		// return false;
	}
	public function confirmReview(string $hookName, $args)
	{
		list($request, $submission, $email, $decline) = $args;
		return ["submission" => $submission, "request" => $request, "email" => $email, "decline" => $decline];
		// return false;
	}

	public function handleEditPublication(string $hookName, $args)
	{
		//	list($newPublication, $publication, $params) = $args;
		list($errors, $action, $props) = $args;


		if ($errors) {
			error_log(json_encode($errors));
		}

		$request = $this->getRequest();


		$publicationId = $props['id'];

		$publication = Services::get('publication')->get((int) $publicationId);

		error_log("Name of hook: " . $hookName);
		$userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /* @var $userGroupDao UserGroupDAO */
		$data = Services::get('publication')->getFullProperties(
			$publication,
			[
				'request' => $request,
				'userGroups' => $userGroupDao->getByContextId($publication->getData('contextId'))->toArray(),
			]
		);
		return ["publication" => $data, "props" => $props];
		// return false;
	}

	/**
	 * @param $event string The event to fire
	 * @param $data array The data to send
	 * @param $urls array The URLs to send to
	 * @param $headers array The headers to send
	 * 
	 * @return array The results of the webhook
	 */
	public function fireWebhook($event, $data, $urls = null, $headers = [])
	{
		if (!$urls) {
			$urls = $this->getSetting($this->getRequest()->getContext()->getId(), 'webhooks');
		}
		$results = [];

		$payload = $this->makeWebhookPayload($event, $data);

		$defaultHeaders = [
			'Content-type: application/json',
			'X-OJS-Webhook-Event: ' . $event
		];
		error_log("IN fireWebhook" . json_encode($urls));

		foreach ($urls as $url) {
			$ch = curl_init();
			$optArray = [
				CURLOPT_URL => $url,
				CURLOPT_RETURNTRANSFER => TRUE,
				CURLOPT_POST => TRUE,
				CURLOPT_POSTFIELDS => $payload,
				CURLOPT_HTTPHEADER => array_merge($defaultHeaders, $headers)
			];
			curl_setopt_array($ch, $optArray);
			$result = curl_exec($ch);
			curl_close($ch);
			$results[$url] = $result;
		}
		return $results;
	}


	public function makeWebhookPayload($event, $data)
	{
		return json_encode(["event" => $event, "data" => $data]);
	}

	/**
	 * Add feed links to page <head> on select/all pages.
	 */
	public function fixBaseUrl($hookName, $args)
	{
		// Only page requests will be handled
		$baseUrl = &$args[0];
		$baseUrl = Config::getVar('general', 'base_url');

		return true;
	}

	/**
	 * @copydoc Plugin::getActions()
	 */
	public function getActions($request, $actionArgs)
	{
		// Get the existing actions
		$actions = parent::getActions($request, $actionArgs);
		if (!$this->getEnabled()) {
			return $actions;
		}
		$router = $request->getRouter();
		import('lib.pkp.classes.linkAction.request.AjaxModal');

		$pluginActions = ['settings'];

		return array_merge(
			array_map(
				fn($action) =>
				new LinkAction(
					$action,
					new AjaxModal(
						$router->url($request, null, null, 'manage', null, array('verb' => $action, 'plugin' => $this->getName(), 'category' => 'generic')),
						$this->getDisplayName()
					),
					__("plugins.generic.webhook.linkactions.$action"),
					null
				),
				$pluginActions
			)
		);
	}

	/**
	 * @copydoc Plugin::manage()
	 */
	public function manage($args, $request)
	{
		switch ($request->getUserVar('verb')) {
			case 'settings':
				AppLocale::requireComponents(LOCALE_COMPONENT_APP_COMMON, LOCALE_COMPONENT_PKP_MANAGER);
				$templateMgr = TemplateManager::getManager($request);

				$templateMgr->assign('webhookEventManager', $this->webhookEventManager);
				// $templateMgr->registerPlugin('function', 'plugin_url', array($this, 'smartyPluginUrl'));

				$this->import('WebhookSettingsForm');
				$form = new WebhookSettingsForm($this, $request->getContext()->getId());

				if ($request->getUserVar('save')) {
					$form->readInputData();

					if ($form->validate()) {
						$form->execute($request);
						return new JSONMessage(true);
					} else {
						$templateMgr->assign('formErrors', $form->getErrorsArray());
					}
				} else {
					$form->initData();
				}
				return new JSONMessage(true, $form->fetch($request));
		}
		return parent::manage($args, $request);
	}


	/**
	 * @return array{url: string, events: array<string, string>[] }[]
	 */
	public function getRegisteredWebhooks(): array
	{
		$webhooks = $this->getSetting($this->getRequest()->getContext()->getId(), 'webhooks');
		return $webhooks;
	}

	public function getWebhookUrls(string $event)
	{
		// Retrieve the registered webhooks
		$webhooks = $this->getSetting($this->getRequest()->getContext()->getId(), 'webhooks');

		// Filter the webhooks that have the specified event enabled
		$filteredWebhooks = array_filter($webhooks, function ($webhook) use ($event) {
			return in_array($event, $webhook['events']);
		});

		// Extract the URLs from the filtered webhooks
		$urls = array_map(function ($webhook) {
			return $webhook['url'];
		}, $filteredWebhooks);

		return $urls;
	}

}