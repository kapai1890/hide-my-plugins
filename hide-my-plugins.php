<?php

/*
 * Plugin Name: Hide My Plugins
 * Plugin URI: https://github.com/kapai1890/hide-my-plugins
 * Description: Hides plugins from the plugins list.
 * Version: 1.4.32
 * Author: Biliavskyi Yevhen
 * Author URI: https://github.com/kapai1890
 * License: MIT
 * Text Domain: hide-my-plugins
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit('Press Enter to proceed...');
}

class HideMyPlugins
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
     * Is on tab "All".
     *
     * @var bool
     */
    protected $isTabAll = true;

    /**
     * Is on tab "Hidden".
     *
     * @var bool
     */
    protected $isTabHidden = false;

    /**
     * The total amount of plugins.
     *
     * @var int
     */
    protected $totalCount = 0;

    /**
     * Visible/not hidden plugins count.
     *
     * @var int
     */
    protected $visibleCount = 0;

    /**
     * Hidden plugins count.
     *
     * @var int
     */
    protected $hiddenCount = 0;

    /**
     * How many plugins tried to show (but some remained hidden).
     *
     * @var int
     */
    protected $processedCount = 0;

    /**
     * How many plugins shown in the current tab.
     *
     * @var int
     */
    protected $shownCount = 0;

    public function __construct()
    {
        if (isset($_GET['plugin_status'])) {
            $this->activeTab = sanitize_text_field($_GET['plugin_status']);

            $this->isTabAll    = $this->activeTab == 'all';
            $this->isTabHidden = $this->activeTab == 'hidden';
        }

        // No need to filter anything on AJAX calls
        if (!defined('DOING_AJAX') || !DOING_AJAX) {
            $this->addActions();
            $this->addNetworkActions();
        }
    }

    protected function addActions()
    {
        /** @requires WordPress 1.5 */
        add_action('init', [$this, 'loadTranslations']);

        /** @requires WordPress 2.5.0 */
        add_action('admin_init', [$this, 'maybeRedirectBack']);

        /** @requires WordPress 3.0.0 */
        add_filter('all_plugins', [$this, 'countPlugins']);

        /** @requires WordPress 3.5.0 */
        add_filter('bulk_actions-plugins', [$this, 'addBulkActions']);

        /** @requires WordPress 4.7.0 */
        add_filter('handle_bulk_actions-plugins', [$this, 'doBulkAction'], 10, 3);

        /** @requires WordPress 2.5.0 */
        add_filter('plugin_action_links', [$this, 'filterPluginActions'], 10, 2);
        add_filter('plugin_action_links', [$this, 'startOutputBuffering'], 10, 2);

        /** @requires WordPress 2.6.0 */
        add_action('admin_action_hide_my_plugin', [$this, 'onHidePlugin']);
        add_action('admin_action_unhide_my_plugin', [$this, 'onUnhidePlugin']);

        /** @requires WordPress 3.5.0 */
        add_filter('views_plugins', [$this, 'addHiddenPluginsTab']);
        add_filter('views_plugins', [$this, 'fixTabs']);
    }

    protected function addNetworkActions()
    {
        /** @requires WordPress 3.5.0 */
        add_filter('bulk_actions-plugins-network', [$this, 'addBulkActions']);

        /** @requires WordPress 4.7.0 */
        add_filter('handle_bulk_actions-plugins-network', [$this, 'doBulkAction'], 10, 3);

        /** @requires WordPress 3.1.0 */
        add_filter('network_admin_plugin_action_links', [$this, 'filterPluginActions'], 10, 2);
        add_filter('network_admin_plugin_action_links', [$this, 'startOutputBuffering'], 10, 2);

        /** @requires WordPress 3.5.0 */
        add_filter('views_plugins-network', [$this, 'addHiddenPluginsTab']);
        add_filter('views_plugins-network', [$this, 'fixTabs']);
    }

    public function loadTranslations()
    {
        load_plugin_textdomain('hide-my-plugins', false, 'hide-my-plugins/languages');
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
            $redirectUrl = $this->pluginsRedirectUrl();

            // Add action data
            foreach ($actionDone as $action) {
                $redirectUrl = add_query_arg($action, sanitize_text_field($_GET[$action]), $redirectUrl);
            }

            wp_safe_redirect($redirectUrl);

            $this->selfDestruction(); // exit;
        }
    }

    /**
     * @param array $plugins
     * @return array
     */
    public function countPlugins($plugins)
    {
        $this->totalCount = count($plugins);

        // Count only real plugins. $this->getHiddenPlugins() may have
        // nonexistent plugins, for example, removed plugins
        foreach (array_keys($plugins) as $plugin) {
            if ($this->isHiddenPlugin($plugin)) {
                $this->hiddenCount++;
            } else {
                $this->visibleCount++;
            }
        }

        return $plugins;
    }

    /**
     * @param array $actions
     * @return array
     */
    public function addBulkActions($actions)
    {
        if (!$this->isTabHidden) {
            $actions['hide-selected'] = __('Hide', 'hide-my-plugins');
        }

        if (!$this->isTabAll) {
            $actions['unhide-selected'] = __('Unhide', 'hide-my-plugins');
        }

        return $actions;
    }

    /**
     * @param string|false $redirectUrl The redirect URL
     * @param string $action "hide-selected"|"unhide-selected"
     * @param array $plugins The plugins to take the action on.
     * @return string|false
     */
    public function doBulkAction($redirectUrl, $action, $plugins)
    {
        if ($action != 'hide-selected' && $action != 'unhide-selected') {
            return $redirectUrl;
        }

        $hiddenPlugins = $this->getHiddenPlugins();

        switch ($action) {
            case 'hide-selected':
                foreach ($plugins as $pluginName) {
                    if (!in_array($pluginName, $hiddenPlugins)) {
                        $hiddenPlugins[] = $pluginName;
                    }
                }
                break;

            case 'unhide-selected':
                foreach ($plugins as $pluginName) {
                    $pluginIndex = array_search($pluginName, $hiddenPlugins);
                    if ($pluginIndex !== false) {
                        unset($hiddenPlugins[$pluginIndex]);
                    }
                }
                $hiddenPlugins = array_values($hiddenPlugins); // Reset indexes after unset()
                break;
        }

        $this->setHiddenPlugins($hiddenPlugins);

        $redirectUrl = $this->pluginsRedirectUrl();

        return $redirectUrl;
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
        if (!$this->userCanManagePlugins()) {
            return $actions;
        }

        // Do not try to hide must-use plugins
        if ($this->activeTab == 'mustuse') {
            return $actions;
        }

        $isHidden    = $this->isHiddenPlugin($plugin);
        $actionText  = $isHidden ? __('Unhide', 'hide-my-plugins') : __('Hide', 'hide-my-plugins');
        $action      = $isHidden ? 'unhide_my_plugin' : 'hide_my_plugin';
        $nonceAction = $this->nonceKey($action, $plugin);

        // Build action URL
        $actionUrl = add_query_arg(
            [
                'action' => $action,
                'plugin' => $plugin
            ],
            $this->pluginsRedirectUrl()
        );

        $actionUrl = wp_nonce_url($actionUrl, $nonceAction, 'hide_my_plugins_nonce');

        $actionLink = '<a href="' . esc_url($actionUrl) . '" title="' . esc_attr($actionText) . '" class="edit">' . esc_html($actionText) . '</a>';

        $actions[$action] = $actionLink;

        return $actions;
    }

    /**
     * @param array $filteredVar Plugin actions.
     * @return array
     */
    public function startOutputBuffering($filteredVar, $plugin)
    {
        // Magic starts here
        ob_start();

        /**
         * The priority must be higher than priority (10) of
         * wp_plugin_update_row(). See add_action() in function
         * wp_plugin_update_rows() in wp-admin/includes/update.php.
         *
         * @requires WordPress 2.7.0
         */
        add_action("after_plugin_row_{$plugin}", [$this, 'endOutputBuffering'], 20, 1);

        return $filteredVar;
    }

    /**
     * @param string $plugin
     */
    public function endOutputBuffering($plugin)
    {
        $output = ob_get_clean();

        // Change the plugins list only on tabs "All" and "Hidden", show other
        // tabs without changes
        if (!$this->isTabAll && !$this->isTabHidden
            // Or is a proper plugin for current tab
            || $this->isHiddenPlugin($plugin) == $this->isTabHidden
        ) {
            echo $output;
            $this->shownCount++;
        }

        $this->processedCount++;

        // Show no-items message
        if ($this->processedCount == $this->totalCount && $this->shownCount == 0) {
            echo '<tr class="no-items">';
                echo '<td class="colspanchange" colspan="3">';
                    _e('You do not appear to have any plugins available at this time.');
                echo '</td>';
            echo '</tr>';
        }
    }

    /**
     * @param array $views
     * @return array
     */
    public function addHiddenPluginsTab($views)
    {
        // Don't add the tab when there are no hidden plugins
        if ($this->hiddenCount == 0 && !$this->isTabHidden) {
            return $views;
        }

        $url  = add_query_arg('plugin_status', 'hidden', $this->pluginsUrl());
        $atts = $this->isTabHidden ? ' class="current" aria-current="page"' : '';

        // Build tab text
        $text = sprintf(__('Hidden %s', 'hide-my-plugins'), '<span class="count">(%s)</span>');
        $text = sprintf($text, number_format_i18n($this->hiddenCount));

        // See "<a href..." in \WP_Plugins_List_Table::get_views() in
        // wp-admin/includes/class-wp-plugins-list-table.php
        $view = sprintf('<a href="%s"%s>%s</a>', esc_url($url), $atts, $text);

        $views['hidden'] = $view;

        return $views;
    }

    /**
     * @param array $views
     * @return array
     */
    public function fixTabs($views)
    {
        if (isset($views['all'])) {
            // Fix totals: "All (15)" -> "All (%total% - %hidden%)"
            $views['all'] = preg_replace('/\(\d+\)/', "({$this->visibleCount})", $views['all']);

            // Remove class "current" from tab "All", when displaying "Hidden"
            if ($this->isTabHidden) {
                $views['all'] = preg_replace('/( class="current")|( aria-current="page")/', '', $views['all']);
            }
        }

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

        if (!$this->isValidInput($pluginAction, $pluginName) || !$this->userCanManagePlugins()) {
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

        $this->setHiddenPlugins($hiddenPlugins);
    }

    /**
     * @param string $action
     * @param string $plugin
     * @return bool
     */
    protected function isValidInput($action, $plugin)
    {
        $nonce       = isset($_REQUEST['hide_my_plugins_nonce']) ? $_REQUEST['hide_my_plugins_nonce'] : '';
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
     * @return string
     */
    protected function pluginsUrl()
    {
        if (!is_network_admin()) {
            return admin_url('plugins.php');
        } else {
            return admin_url('network/plugins.php');
        }
    }

    protected function pluginsRedirectUrl()
    {
        $redirectUrl = $this->pluginsUrl();

        if (!$this->isTabAll) {
            $redirectUrl = add_query_arg('plugin_status', $this->activeTab, $redirectUrl);
        }

        if (isset($_GET['paged'])) {
            $redirectUrl = add_query_arg('paged', absint($_GET['paged']), $redirectUrl);
        }

        if (isset($_GET['s'])) {
            $search = urlencode(wp_unslash($_GET['s'])); // Like in wp-admin/plugins.php
            $redirectUrl = add_query_arg('s', $search, $redirectUrl);
        }

        return $redirectUrl;
    }

    /**
     * @return bool
     */
    protected function userCanManagePlugins()
    {
        if (!is_network_admin()) {
            return current_user_can('install_plugins');
        } else {
            return current_user_can('manage_network_plugins');
        }
    }

    /**
     * @return array
     */
    protected function getHiddenPlugins()
    {
        if (!is_network_admin()) {
            return get_option('my_hidden_plugins', []);
        } else {
            return get_site_option('my_hidden_plugins', []);
        }
    }

    /**
     * @param array $plugins
     */
    protected function setHiddenPlugins($plugins)
    {
        if (!is_network_admin()) {
            update_option('my_hidden_plugins', $plugins, false);
        } else {
            update_site_option('my_hidden_plugins', $plugins);
        }
    }

    protected function isHiddenPlugin($plugin)
    {
        $hiddenPlugins = $this->getHiddenPlugins();
        return in_array($plugin, $hiddenPlugins);
    }

    protected function selfDestruction()
    {
        exit;
    }
}

global $hideMyPlugins;
$hideMyPlugins = new HideMyPlugins();
