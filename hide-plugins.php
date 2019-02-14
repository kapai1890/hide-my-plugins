<?php

/*
 * Plugin Name: Hide Plugins
 * Plugin URI: https://github.com/kapai1890/hide-plugins
 * Description: Hides plugins from the plugins list.
 * Version: 1.4.30
 * Author: Biliavskyi Yevhen
 * Author URI: https://github.com/kapai1890
 * License: MIT
 * Text Domain: hide-plugins
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit('Press Enter to proceed...');
}

class HidePlugins
{
    /**
     * Current active tab. By default this can include "all", "active",
     * "inactive", "recently_activated", "upgrade", "mustuse", "dropins",
     * "search" and "hidden".
     *
     * @var string
     */
    protected $activeTab = 'all';

    /**
     * Is on tab "Hidden".
     *
     * @var bool
     */
    protected $isTabHidden = false;

    public function __construct()
    {
        if (isset($_GET['plugin_status'])) {
            $this->activeTab   = sanitize_text_field($_GET['plugin_status']);
            $this->isTabHidden = $this->activeTab == 'hidden';
        }

        register_activation_hook(__FILE__, [$this, 'onActivate']);

        // No need to add actions on AJAX calls
        if (!defined('DOING_AJAX') || !DOING_AJAX) {
            $this->addActions();
        }
    }

    public function onActivate()
    {
        add_option('hidden_plugins', [], '', 'no');
    }

    protected function addActions()
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
        add_action('admin_init', [$this, 'maybeRedirectBack']);

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
        add_action('admin_action_hide_plugin', [$this, 'onHidePlugin']);

        /**
         * Fires when an "action" request variable is sent.
         *
         * @requires WordPress 2.6.0
         * @see https://developer.wordpress.org/reference/hooks/admin_action__requestaction/
         */
        add_action('admin_action_unhide_plugin', [$this, 'onUnhidePlugin']);

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
        load_plugin_textdomain('hide-plugins', false, 'hide-plugins/languages');
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
        $refererUrl = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';

        if (empty($refererUrl) || strpos($refererUrl, 'plugins.php') === false) {
            return;
        }

        // Still need to check, that "plugins.php" was referer script and not
        // just a part of the string, for example:
        //     admin.php?s=plugins.php

        $referer = parse_url($refererUrl);

        $file  = isset($referer['path']) ? preg_replace('/[^\/]*\//', '', $referer['path']) : '';
        $query = isset($referer['query']) ? $referer['query'] : '';

        $actionDone = array_intersect(array_keys($_GET), ['activate', 'deactivate', 'activate-multi', 'deactivate-multi']);

        if ($file == 'plugins.php'
            && strpos($query, 'plugin_status=hidden') !== false
            && !empty($actionDone)
        ) {
            // We need to go back to the tab "Hidden"
            $newQuery = [
                'plugin_status' => 'hidden',
                'paged'         => isset($_GET['paged']) ? absint($_GET['paged']) : 1,
                's'             => isset($_GET['s']) ? sanitize_text_field($_GET['s']) : ''
            ];

            foreach ($actionDone as $action) {
                $newQuery[$action] = sanitize_text_field($_GET[$action]);
            }

            $redirectUrl = add_query_arg($newQuery, admin_url('plugins.php'));
            wp_safe_redirect($redirectUrl);

            $this->selfDestruction(); // exit;
        }
    }

    /**
     * @param array $plugins
     * @return array
     */
    public function filterPluginsList($plugins)
    {
        $hiddenPlugins = $this->getHiddenPlugins();

        if ($this->isTabHidden) {
            // Leave only hidden plugins and remove all others
            foreach (array_keys($plugins) as $plugin) {
                if (!in_array($plugin, $hiddenPlugins)) {
                    // Can't just set $plugins = $hiddenPlugins, $plugins
                    // contains information about all plugins
                    unset($plugins[$plugin]);
                }
            }

        } else {
            // Remove hidden plugins from the list
            foreach ($hiddenPlugins as $plugin) {
                if (isset($plugins[$plugin])) {
                    unset($plugins[$plugin]);
                }
            }
        }

        return $plugins;
    }

    /**
     * <i>Hint. This method does not use context (tab name) of the filter
     * "plugin_action_links", because that context will be "all" on custom
     * tabs.</i>
     *
     * @global int $page
     * @global string $s
     *
     * @param array $actions
     * @param string $plugin
     * @return array
     */
    public function filterPluginActions($actions, $plugin)
    {
        global $page, $s; // Support current page number and search query

        if (!current_user_can('delete_plugins')) {
            return $actions;
        }

        // Do not try to hide must-use plugins
        if ($this->activeTab == 'mustuse') {
            return $actions;
        }

        $action      = $this->isTabHidden ? 'unhide_plugin' : 'hide_plugin';
        $nonceAction = $this->nonceKey($action, $plugin);
        $actionText  = $this->isTabHidden ? __('Unhide', 'hide-plugins') : __('Hide', 'hide-plugins');

        // Build action URL
        $actionUrl = add_query_arg([
            'plugin_status' => $this->activeTab,
            'paged'         => $page,
            's'             => $s,
            'action'        => $action,
            'plugin'        => $plugin
        ], 'plugins.php');

        $actionUrl = wp_nonce_url($actionUrl, $nonceAction, 'hide_plugins_nonce');

        $actionLink = '<a href="' . esc_url($actionUrl) . '" title="' . esc_attr($actionText) . '" class="edit">' . esc_html($actionText) . '</a>';

        $actions[$action] = $actionLink;

        return $actions;
    }

    /**
     * @param array $views
     * @return array
     */
    public function addHiddenPluginsTab($views)
    {
        $url   = add_query_arg('plugin_status', 'hidden', admin_url('plugins.php'));
        $atts  = $this->isTabHidden ? ' class="current" aria-current="page"' : '';
        $count = $this->getHiddenPluginsCount();

        // Build tab text
        $text = sprintf(__('Hidden %s', 'hide-plugins'), '<span class="count">(%s)</span>');
        $text = sprintf($text, number_format_i18n($count));

        // See "<a href..." in \WP_Plugins_List_Table::get_views() in
        // wp-admin/includes/class-wp-plugins-list-table.php
        $view = sprintf('<a href="%s"%s>%s</a>', esc_url($url), $atts, $text);

        $views['hidden'] = $view;

        return $views;
    }

    public function onHidePlugin()
    {
        $this->doAction('hide');
    }

    public function onUnhidePlugin()
    {
        $this->doAction('unhide');
    }

    /**
     * @param string $action "hide"|"unhide"
     */
    protected function doAction($action)
    {
        if (!isset($_GET['action']) || !isset($_GET['plugin'])) {
            return;
        }

        $pluginAction = sanitize_text_field($_GET['action']);
        $pluginName   = sanitize_text_field($_GET['plugin']);

        if (!$this->isValidInput($pluginAction, $pluginName) || !current_user_can('delete_plugins')) {
            return;
        }

        $hiddenPlugins = $this->getHiddenPlugins();

        // Hide or unhide the plugin
        switch ($action) {
            case 'hide':
                if (!in_array($pluginName, $hiddenPlugins)) {
                    $hiddenPlugins[] = $pluginName;
                }
                break;

            case 'unhide':
                $pluginIndex = array_search($pluginName, $hiddenPlugins);
                if ($pluginIndex !== false) {
                    unset($hiddenPlugins[$pluginIndex]);
                    $hiddenPlugins = array_values($hiddenPlugins); // Reset indexes after unset()
                }
                break;
        }

        $this->updateHiddenPlugins($hiddenPlugins);
    }

    /**
     * @param string $action
     * @param string $plugin
     * @return bool
     */
    protected function isValidInput($action, $plugin)
    {
        $nonce       = isset($_REQUEST['hide_plugins_nonce']) ? $_REQUEST['hide_plugins_nonce'] : '';
        $nonceAction = $this->nonceKey($action, $plugin);
        $verified    = wp_verify_nonce($nonce, $nonceAction);

        return $verified !== false;
    }

    /**
     * @param string $action
     * @param string $plugin
     * @return string
     */
    protected function nonceKey($action, $plugin)
    {
        return $action . '_' . $plugin;
    }

    /**
     * @return array
     */
    protected function getHiddenPlugins()
    {
        return get_option('hidden_plugins', []);
    }

    /**
     * @return int
     */
    protected function getHiddenPluginsCount()
    {
        $hiddenPlugins = $this->getHiddenPlugins();
        return count($hiddenPlugins);
    }

    /**
     * @param array $plugins
     */
    protected function updateHiddenPlugins($plugins)
    {
        update_option('hidden_plugins', $plugins);
    }

    private function selfDestruction()
    {
        exit;
    }
}

global $hidePlugins;
$hidePlugins = new HidePlugins();
