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

define('HideMyPlugins\PLUGIN_DIR', plugin_dir_path(__FILE__)); // With trailing slash
define('HideMyPlugins\PLUGIN_URL', plugin_dir_url(__FILE__));

if (version_compare(PHP_VERSION, '5.4', '>=') && version_compare(get_bloginfo('version'), '4.7', '>=')) {
    require_once __DIR__ . '/includes/Plugin.php';

    // Create instance of the plugin
    HideMyPlugins\Plugin::getInstance();

} else {
    // Show error message
    add_action('admin_notices', function () {
        /* translators: %1$s: PHP version; %2$s: WordPress version */
        $message = sprintf(esc_html__('Hide My Plugins requires PHP version %1$s+ and WordPress version %2$s+, and cannot run in the current environment.', 'hide-my-plugins'), '5.4', '4.7');

        echo '<div class="error">', wpautop($message), '</div>';
    });
}
