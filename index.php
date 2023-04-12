<?php

/**
 * @defgroup plugins_generic_webhook Web Feed Plugin
 */

/**
 * @file plugins/generic/webhook/index.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @ingroup plugins_generic_webhook
 * @brief Wrapper for Webhook plugin
 *
 */

require_once('WebhookPlugin.inc.php');

return new WebhookPlugin();