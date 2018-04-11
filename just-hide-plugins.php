<?php

/*
 * Plugin Name: Hide Plugins
 * Plugin URI: https://github.com/kapai1890/just-hide-plugins
 * Description: Allows to hide plugins from the plugins list.
 * Version: 18.15.1
 * Author: kapai1890
 * Author URI: https://github.com/kapai1890
 * License: MIT
 * Text Domain: just-hide-plugins
 * Domain Path: /languages
 */

declare(strict_types = 1);

namespace just;

if (!defined('ABSPATH')) {
    exit('Press Enter to proceed...');
}

/**
 * @requires PHP 7.0
 * @requires WordPress 3.5.0
 * @todo Add "Hide" and "Show" to bulk actions.
 */
final class HidePlugins
{
    /** @var \just\HidePlugins */
    private static $instance = null;

    /**
     * The plugin context. By default this can include "all", "active",
     * "inactive", "recently_activated", "upgrade", "mustuse", "dropins" and
     * "search".
     *
     * @var string
     */
    private $context = 'all';

    /**
     * Is another hide plugin found.
     *
     * @var bool
     */
    private $anotherHideFound = false;

    private function __construct()
    {
        if (isset($_GET['plugin_status'])) {
            $this->context = sanitize_text_field($_GET['plugin_status']);
        }

        register_activation_hook(__FILE__, [$this, 'onActivate']);

        $this->addActions();
    }

    private function addActions()
    {
        /**
         * Fires after WordPress has finished loading but before any headers are
         * sent.
         *
         * @requires WordPress 1.5
         *
         * @see https://developer.wordpress.org/reference/hooks/init/
         */
        add_action('init', [$this, 'loadTranslations']);

        /**
         * Fires as an admin screen or script is being initialized.
         *
         * @requires WordPress 2.5.0
         *
         * @see https://developer.wordpress.org/reference/hooks/admin_init/
         */
        add_action('admin_init', [$this, 'determineActivePlugins']);

        /**
         * Filters the full array of plugins to list in the Plugins list table.
         *
         * @requires WordPress 3.0.0
         *
         * @see https://developer.wordpress.org/reference/hooks/all_plugins/
         */
        add_filter('all_plugins', [$this, 'filterPluginsList']);

        /**
         * Filters the action links displayed for each plugin in the Plugins
         * list table.
         *
         * Filter "plugin_action_links_{$plugin}" fires after.
         *
         * @requires WordPress 2.5.0
         *
         * @see https://developer.wordpress.org/reference/hooks/plugin_action_links/
         */
        add_filter('plugin_action_links', [$this, 'filterPluginActions'], 10, 2);

        /**
         * Fires when an "action" request variable is sent.
         *
         * @requires WordPress 2.6.0
         *
         * @see https://developer.wordpress.org/reference/hooks/admin_action__requestaction/
         */
        add_action('admin_action_just_hide_plugin', [$this, 'onHidePlugin']);

        /**
         * Fires when an "action" request variable is sent.
         *
         * @requires WordPress 2.6.0
         *
         * @see https://developer.wordpress.org/reference/hooks/admin_action__requestaction/
         */
        add_action('admin_action_just_show_plugin', [$this, 'onShowPlugin']);

        /**
		 * Filters the list of available list table views.
		 *
		 * @requires WordPress 3.5.0
         *
         * @see https://developer.wordpress.org/reference/hooks/views_this-screen-id/
         * @see \WP_List_Table::views() in wp-admin/includes/class-wp-list-table.php
         */
        add_filter('views_plugins', [$this, 'addCategoryHidden']);
    }

    public function loadTranslations()
    {
        load_plugin_textdomain('just-hide-plugins', false, 'just-hide-plugins/languages');
    }

    public function determineActivePlugins()
    {
        $this->anotherHideFound |= is_plugin_active('hide-plugins/hide-plugins.php');
    }

    public function filterPluginsList(array $plugins): array
    {
        $hidden = $this->getHiddenPlugins();

        if ($this->isCategoryHidden()) {
            // Leave only hidden plugins and remove all others
            foreach (array_keys($plugins) as $plugin) {
                if (!in_array($plugin, $hidden)) {
                    // Can't just set $plugins = $hidden, $plugins contains
                    // information about all plugins
                    unset($plugins[$plugin]);
                }
            }

        } else {
            // Remove hidden plugins from the list
            foreach ($hidden as $plugin) {
                if (isset($plugins[$plugin])) {
                    unset($plugins[$plugin]);
                }
            }
        }

        return $plugins;
    }

    /**
     * <i>Hint. This method does not use context from filter
     * "plugin_action_links", because that context will be "all" on custom
     * category pages.</i>
     *
     * @global int $page
     * @global string $s
     */
    public function filterPluginActions(array $actions, string $plugin): array
    {
        global $page, $s; // Support current page number and search query

        if (!current_user_can('delete_plugins')) {
            return $actions;
        }

        $action      = $this->isCategoryHidden() ? 'just_show_plugin' : 'just_hide_plugin';
        $nonceAction = $this->nonceKey($action, $plugin);

        // Build action text
        $actionText = '';
        if ($this->isCategoryHidden()) {
            $actionText = __('Show', 'just-hide-plugins');
        } else if (!$this->anotherHideFound) {
            $actionText = __('Hide', 'just-hide-plugins');
        } else {
            $actionText = __('Just Hide', 'just-hide-plugins');
        }

        // Build action URL
        $actionUrl = add_query_arg([
            'plugin_status' => $this->context,
            'paged'         => $page,
            's'             => $s,
            'action'        => $action,
            'plugin'        => $plugin
        ], 'plugins.php');
        $actionUrl = wp_nonce_url($actionUrl, $nonceAction, 'just_nonce');

        $actionLink = '<a href="' . esc_url($actionUrl) . '" title="' . esc_attr($actionText) . '" class="edit">' . esc_html($actionText) . '</a>';

        $actions[$action] = $actionLink;

        return $actions;
    }

    public function addCategoryHidden(array $views): array
    {
        $class = 'hidden';
        $count = $this->getHiddenPluginsCount();
        $url   = add_query_arg('plugin_status', $class, 'plugins.php');
        $atts  = $this->isCategoryHidden() ? ' class="current" aria-current="page"' : '';

        // Build category text
        $text = '';
        if (!$this->anotherHideFound) {
            $text = _n('Hidden <span class="count">(%s)</span>', 'Hidden <span class="count">(%s)</span>', $count, 'just-hide-plugins');
        } else {
            $text = _n('Just Hidden <span class="count">(%s)</span>', 'Just Hidden <span class="count">(%s)</span>', $count, 'just-hide-plugins');
        }
        $text = sprintf($text, number_format_i18n($count));

        // See "<a href..." in \WP_Plugins_List_Table::get_views() in wp-admin/includes/class-wp-plugins-list-table.php
        $view = sprintf('<a href="%s"%s>%s</a>', esc_url($url), $atts, $text);

        $views[$class] = $view;

        return $views;
    }

    private function isCategoryHidden(): bool
    {
        return ($this->context == 'hidden');
    }

    public function onActivate()
    {
        add_option('just_hidden_plugins', [], '', 'no');
    }

    public function onHidePlugin()
    {
        $this->doAction('hide');
    }

    public function onShowPlugin()
    {
        $this->doAction('show');
    }

    /**
     * @param string $action "hide"|"show"
     */
    private function doAction(string $action)
    {
        if (!isset($_GET['action']) || !isset($_GET['plugin'])) {
            return;
        }

        $adminAction = sanitize_text_field($_GET['action']);
        $plugin = sanitize_text_field($_GET['plugin']);

        if (!$this->isValidInput($adminAction, $plugin) || !current_user_can('delete_plugins')) {
            return;
        }

        $hiddenPlugins = $this->getHiddenPlugins();

        // Hide or show the plugin
        switch ($action) {
            case 'hide':
                if (!in_array($plugin, $hiddenPlugins)) {
                    $hiddenPlugins[] = $plugin;
                }
                break;

            case 'show':
                $pluginIndex = array_search($plugin, $hiddenPlugins);
                if ($pluginIndex !== false) {
                    unset($hiddenPlugins[$pluginIndex]);
                    $hiddenPlugins = array_values($hiddenPlugins); // Reset indexes after unset()
                }
                break;
        }

        $this->updateHiddenPlugins($hiddenPlugins);
    }

    private function isValidInput(string $action, string $plugin): bool
    {
        $nonceAction  = $this->nonceKey($action, $plugin);
        $nonce        = isset($_REQUEST['just_nonce']) ? $_REQUEST['just_nonce'] : '';
        $verification = wp_verify_nonce($nonce, $nonceAction);

        return ($verification !== false);
    }

    private function nonceKey(string $action, string $plugin): string
    {
        return $action . '_' . $plugin;
    }

    private function getHiddenPlugins(): array
    {
        return get_option('just_hidden_plugins', []);
    }

    private function getHiddenPluginsCount(): int
    {
        $hiddenPlugins = $this->getHiddenPlugins();
        return count($hiddenPlugins);
    }

    private function updateHiddenPlugins(array $plugins)
    {
        update_option('just_hidden_plugins', $plugins);
    }

    /**
     * Cloning of the object.
     */
    public function __clone() {
        // Cloning instances of the class is forbidden
        $this->terminate(__FUNCTION__, __('Do not clone the \just\HidePlugins class.', 'just-hide-plugins'), '18.14.1');
    }

    /**
     * Unserializing of the class.
     */
    public function __wakeup() {
        // Unserializing instances of the class is forbidden
        $this->terminate(__FUNCTION__, __('Do not clone the \just\HidePlugins class.', 'just-hide-plugins'), '18.14.1');
    }

    private function terminate($function, $message, $version)
    {
        if (function_exists('_doing_it_wrong')) {
            _doing_it_wrong($function, $message, $version);
        } else {
            die($message);
        }
    }

    public static function create()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
    }
}

HidePlugins::create();
