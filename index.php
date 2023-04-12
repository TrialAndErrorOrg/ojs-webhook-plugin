<?php

/**
 * @defgroup plugins_generic_fixssl Web Feed Plugin
 */
 
/**
 * @file plugins/generic/fixSSL/index.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @ingroup plugins_generic_fixssl
 * @brief Wrapper for FixSSL plugin
 *
 */

require_once('FixSSLPlugin.inc.php');

return new FixSSLPlugin(); 
