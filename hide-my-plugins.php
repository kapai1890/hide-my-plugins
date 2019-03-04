<?php

/*
 * Plugin Name: Hide My Plugins
 * Plugin URI: https://github.com/kapai1890/hide-my-plugins
 * Description: Hides plugins from the plugins list.
 * Version: 2.0
 * Author: Biliavskyi Yevhen
 * Author URI: https://github.com/kapai1890
 * License: MIT
 * Text Domain: hide-my-plugins
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit('Press Enter to proceed...');
}

require_once __DIR__ . '/includes/Plugin.php';

global $hideMyPlugins;
$hideMyPlugins = new HideMyPlugins\Plugin();
