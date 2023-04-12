<?php
class WebhookEventManager
{
    /**
     * @var FixSSLPlugin
     */
    private $plugin;
    private $webhooksByEvent;

    private $events = [];


    public function __construct(FixSSLPlugin $plugin)
    {
        $this->plugin = $plugin;
        $this->webhooksByEvent = $plugin->getSetting($plugin->getRequest()->getContext()->getId(), 'webhooksByEvent');
    }

    public function addEvent(string $eventName, string $hookName, ?callable $dataProvider = null)
    {
        $this->events[] = $eventName;

        HookRegistry::register($hookName, function ($hookName, $args) use ($eventName, $dataProvider) {
            error_log("Firing event $eventName for hook $hookName");
            if (!isset($this->webhooksByEvent[$eventName]) || $this->webhooksByEvent[$eventName]['disabled']) {
                return;
            }
            $data = $dataProvider ? $dataProvider($hookName, $args) : $args;
            $this->fireEvent($eventName, $data);
        });
    }


    public function getEvents()
    {
        return $this->events;
    }

    private function fireEvent(string $eventName, array $data)
    {
        error_log("Firing event $eventName");
        error_log(json_encode($this->webhooksByEvent));


        $webhookUrls = array_map(function ($webhook) {
            return $webhook['url'];
        }, $this->webhooksByEvent[$eventName]);
        error_log("Webhook urls: " . json_encode($webhookUrls));
        $this->plugin->fireWebhook($eventName, $data, $webhookUrls);

    }

}