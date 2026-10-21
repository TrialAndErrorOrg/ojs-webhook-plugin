<?php

/**
 * @file plugins/generic/webhook/WebhookController.php
 *
 * Copyright (c) 2023-2026 Thomas F. K. Jorna, Center of Trial and Error
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class WebhookController
 *
 * @brief POST /api/v1/webhook: send a webhook by hand, for testing a URL from the settings form.
 */

namespace APP\plugins\generic\webhook;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\security\Role;

class WebhookController extends PKPBaseController
{
    public function __construct(private WebhookPlugin $plugin)
    {
    }

    /**
     * @copydoc PKPBaseController::getHandlerPath()
     */
    public function getHandlerPath(): string
    {
        return 'webhook';
    }

    /**
     * @copydoc PKPBaseController::getRouteGroupMiddleware()
     */
    public function getRouteGroupMiddleware(): array
    {
        return [
            'has.user',
            'has.context',
            self::roleAuthorizer([Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER]),
        ];
    }

    /**
     * @copydoc PKPBaseController::getGroupRoutes()
     */
    public function getGroupRoutes(): void
    {
        Route::post('', $this->send(...))->name('webhook.send');
    }

    /**
     * @copydoc PKPBaseController::authorize()
     */
    public function authorize(PKPRequest $request, array &$args, array $roleAssignments): bool
    {
        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));

        $rolePolicy = new PolicySet(PolicySet::COMBINING_PERMIT_OVERRIDES);
        foreach ($roleAssignments as $role => $operations) {
            $rolePolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, $role, $operations));
        }
        $this->addPolicy($rolePolicy);

        return parent::authorize($request, $args, $roleAssignments);
    }

    public function send(Request $illuminateRequest): JsonResponse
    {
        $event = $illuminateRequest->input('event');
        $data = $illuminateRequest->input('data');
        $urls = $illuminateRequest->input('urls');

        if (!$event || !$data) {
            return response()->json([
                'success' => false,
                'message' => 'The event and data parameters are required.',
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($urls && !is_array($urls)) {
            $urls = [$urls];
        }

        if (empty($urls)) {
            $contextId = $this->getRequest()->getContext()->getId();
            $webhooks = array_filter(
                $this->plugin->getRegisteredWebhooks($contextId),
                fn (array $webhook) => empty($webhook['disabled'])
            );
            $urls = array_column($webhooks, 'url');
        }

        if (empty($urls)) {
            return response()->json([
                'success' => false,
                'message' => 'No webhook URLs found.',
            ], Response::HTTP_BAD_REQUEST);
        }

        return response()->json([
            'success' => true,
            'message' => $this->plugin->fireWebhook($event, $data, $urls),
        ], Response::HTTP_OK);
    }
}
