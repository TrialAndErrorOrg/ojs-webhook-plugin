<?php

import('lib.pkp.classes.handler.APIHandler');
import('lib.pkp.classes.security.authorization.ContextAccessPolicy');
import('lib.pkp.classes.security.authorization.RoleBasedHandlerOperationPolicy');
import('lib.pkp.classes.security.authorization.PolicySet');
use Slim\Http\Request;
use Slim\Http\Response;

class WebhookAPIHandler extends APIHandler
{
    /**
     * @var FixSSLPlugin
     */
    protected $plugin;


    public function __construct()
    {
        $this->plugin = PluginRegistry::getPlugin('generic', 'fixsslplugin');

        $this->_handlerPath = 'webhook';
        $this->_endpoints = array(
            'POST' => array(
                array(
                    'pattern' => $this->getEndpointPattern(),
                    'handler' => array($this, 'handleWebhook'),
                    'roles' => [ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER],
                ),
            ),
        );

        parent::__construct();
    }

    /**
     * @copydoc APIHandler::authorize
     */
    function authorize(\Request $request, &$args, $roleAssignments)
    {
        error_log("HHohashtasht");
        import('lib.pkp.classes.security.authorization.PolicySet');
        $rolePolicy = new PolicySet(COMBINING_PERMIT_OVERRIDES);

        import('lib.pkp.classes.security.authorization.RoleBasedHandlerOperationPolicy');
        foreach ($roleAssignments as $role => $operations) {
            $rolePolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, $role, $operations));
        }
        $this->addPolicy($rolePolicy);


        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * @param $slimRequest Request Slim request object
     * @param $response Response object
     * @param array $args arguments
     */
    public function handleWebhook(Request $slimRequest, Response $response, $args)
    {
        // Check if the user has the required permissions

        $event = $slimRequest->getParam('event');
        $data = $slimRequest->getParam('data');
        $urls = $slimRequest->getParam('urls');

        error_log($event . json_encode($data) . json_encode($urls));

        if (!$event || !$data) {
            return $response->withJson(
                [
                    'success' => false,
                    'message' => 'The event and data parameters are required.',
                ],
                400
            );
        }

        if ($urls && !is_array($urls)) {
            $urls = [$urls];
        }

        if (!$urls || empty($urls)) {
            error_log("shouldnt be here");
            $registeredWebhooks = $this->plugin->getRegisteredWebhooks();
            // filter out the urls which have the passed event disabled

            $urls = array_filter(
                $registeredWebhooks,
                function ($webhook) use ($event) {
                    return !$webhook['disabled'];
                }
            );
        }


        if (empty($urls)) {
            return $response->withJson(
                [
                    'success' => false,
                    'message' => 'No webhook URLs found.',
                ],
                400
            );
        }

        // Call the fireWebhook method from the plugin
        $result = $this->plugin->fireWebhook($event, $data, $urls);

        // Return the result as a JSON response
        return $response->withJSON(['success' => true, 'message' => $result], 200);
    }
}