<?php

/*
 * Plugin Name: Hide Plugins
 * Plugin URI: https://github.com/kapai1890/just-hide-plugins
 * Description: Allows to hide plugins from the plugins list.
 * Version: 1.3.10
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
     * Is on tab "Hidden".
     *
     * @var bool
     */
    private $inHidden = false;

    /**
     * Is another hide plugin found.
     *
     * @var bool
     */
    private $anotherHiderExists = false;

    private function __construct()
    {
        if (isset($_GET['plugin_status'])) {
            $this->context = sanitize_text_field($_GET['plugin_status']);
            $this->inHidden = ($this->context == 'hidden');
        }

        register_activation_hook(__FILE__, [$this, 'onActivate']);

        // No need to add actions on AJAX calls
        if (!defined('DOING_AJAX') || !DOING_AJAX) {
            $this->addActions();
        }
    }

    public function onActivate()
    {
        add_option('just_hidden_plugins', [], '', 'no');
    }

    private function addActions()
    {
        /**
         * Fires after WordPress has finished loading but before any headers are
         * sent.
         *
         * @requires WordPress 1.5
         * @see https://developer.wordpress.org/reference/hooks/init/
         */
        add_action('init', [$this, 'loadTranslations']);

        /**
         * Fires as an admin screen or script is being initialized.
         *
         * @requires WordPress 2.5.0
         * @see https://developer.wordpress.org/reference/hooks/admin_init/
         */
        add_action('admin_init', [$this, 'maybeRedirectBack'], 10);

        /**
         * Fires as an admin screen or script is being initialized.
         *
         * @requires WordPress 2.5.0
         * @see https://developer.wordpress.org/reference/hooks/admin_init/
         */
        add_action('admin_init', [$this, 'determineActivePlugins'], 20);

        /**
         * Filters the full array of plugins to list in the Plugins list table.
         *
         * @requires WordPress 3.0.0
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
         * @see https://developer.wordpress.org/reference/hooks/plugin_action_links/
         */
        add_filter('plugin_action_links', [$this, 'filterPluginActions'], 10, 2);

        /**
         * Fires when an "action" request variable is sent.
         *
         * @requires WordPress 2.6.0
         * @see https://developer.wordpress.org/reference/hooks/admin_action__requestaction/
         */
        add_action('admin_action_just_hide_plugin', [$this, 'onHidePlugin']);

        /**
         * Fires when an "action" request variable is sent.
         *
         * @requires WordPress 2.6.0
         * @see https://developer.wordpress.org/reference/hooks/admin_action__requestaction/
         */
        add_action('admin_action_just_show_plugin', [$this, 'onShowPlugin']);

        /**
         * Filters the list of available list table views.
         *
         * @requires WordPress 3.5.0
         * @see https://developer.wordpress.org/reference/hooks/views_this-screen-id/
         * @see \WP_List_Table::views() in wp-admin/includes/class-wp-list-table.php
         */
        add_filter('views_plugins', [$this, 'addHiddenPluginsTab']);
    }

    public function loadTranslations()
    {
        load_plugin_textdomain('just-hide-plugins', false, 'just-hide-plugins/languages');
    }

    /**
     * WordPress automatically redirects to "All" page of plugins.php after any
     * action. We need to go back to "Hidden" tab when doing any action from
     * there.
     *
     * @see wp-admin/plugins.php
     *
     * @todo Fix "The page isn't working" (while doing all the redirects).
     */
    public function maybeRedirectBack()
    {
        $refererUrl = $_SERVER['HTTP_REFERER'] ?? '';

        if (empty($refererUrl) || strpos($refererUrl, 'plugins.php') === false) {
            return;
        }

        // Still need to check, that "plugins.php" was referer script and not
        // just a part of the string, for example:
        //     admin.php?s=plugins.php

        $referer = parse_url($refererUrl);

        $file  = ( isset($referer['path']) ) ? preg_replace('/[^\/]*\//', '', $referer['path']) : '';
        $query = $referer['query'] ?? '';

        $actionDone = array_intersect(array_keys($_GET), ['activate', 'deactivate', 'activate-multi', 'deactivate-multi']);

        if ($file == 'plugins.php'
            && strpos($query, 'plugin_status=hidden') !== false
            && !empty($actionDone)
        ) {
            // We need to go back to the tab "Hidden"
            $newQuery = ['plugin_status' => 'hidden'];
            $newQuery['paged'] = $_GET['paged'] ?? 1;
            $newQuery['s'] = $_GET['s'] ?? '';

            foreach ($actionDone as $action) {
                $newQuery[$action] = $_GET[$action];
            }

            $redirectUrl = add_query_arg($newQuery, admin_url('plugins.php'));

            wp_safe_redirect($redirectUrl);
            $this->stop(); // exit;
        }
    }

    public function determineActivePlugins()
    {
        $this->anotherHiderExists |= is_plugin_active('hide-plugins/hide-plugins.php');
    }

    public function filterPluginsList(array $plugins): array
    {
        $hidden = $this->getHiddenPlugins();

        if ($this->inHidden) {
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
     * tabs.</i>
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

        // Do not try to hide must-use plugins
        if ($this->context == 'mustuse') {
            return $actions;
        }

        $action      = $this->inHidden ? 'just_show_plugin' : 'just_hide_plugin';
        $nonceAction = $this->nonceKey($action, $plugin);

        // Build action text
        $actionText = '';
        if ($this->inHidden) {
            $actionText = ( !$this->isAnotherHiderExists() ) ? __('Show', 'just-hide-plugins') : __('Just Show', 'just-hide-plugins');
        } else {
            $actionText = ( !$this->isAnotherHiderExists() ) ? __('Hide', 'just-hide-plugins') : __('Just Hide', 'just-hide-plugins');
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

    public function addHiddenPluginsTab(array $views): array
    {
        $class = 'hidden';
        $count = $this->getHiddenPluginsCount();
        $url   = add_query_arg('plugin_status', $class, admin_url('plugins.php'));
        $atts  = $this->inHidden ? ' class="current" aria-current="page"' : '';

        // Build tab text
        $text = '';
        if (!$this->isAnotherHiderExists()) {
            $text = sprintf(_n('Hidden %s', 'Hidden %s', $count, 'just-hide-plugins'), '<span class="count">(%s)</span>');
        } else {
            $text = sprintf(_n('Just Hidden %s', 'Just Hidden %s', $count, 'just-hide-plugins'), '<span class="count">(%s)</span>');
        }
        $text = sprintf($text, number_format_i18n($count));

        // See "<a href..." in \WP_Plugins_List_Table::get_views() in wp-admin/includes/class-wp-plugins-list-table.php
        $view = sprintf('<a href="%s"%s>%s</a>', esc_url($url), $atts, $text);

        $views[$class] = $view;

        return $views;
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

    private function isAnotherHiderExists()
    {
        if (!$this->anotherHiderExists) {
            $this->anotherHiderExists = apply_filters( 'just_hide_plugins_another_hider_exists', $this->anotherHiderExists);
        }

        return $this->anotherHiderExists;
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

    private function stop()
    {
        exit;
    }

    public static function create()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
    }
}

HidePlugins::create();
