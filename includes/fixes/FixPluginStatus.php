<?php

/*
 * 1) WP_Plugins_List_Table (wp-admin/includes/class-wp-plugins-list-table.php)
 *    has code in the constructor: "global $status". WordPress uses that $status
 *    to set up the "plugin_status" in the URL.
 *
 * 2) There is no way to fix global $status at the beginning, especially -
 *    before doing any action, like "Activate", "Deactivate" etc. There is no
 *    hook or filter to use. We can only fix the global status for custom tabs
 *    only in hidden input [name="plugin_status"] in bulk actions form (the last
 *    usage of the variable).
 *
 * 3) As for plugin actions, the part of the plugin action URLs -
 *    "plugin_status=..." - have no big sence. WordPress will use the global
 *    variable to make a redirect link after any action. But still, replace all
 *    "plugin_status=all" with "plugin_status=hidden" in plugin action links.
 *
 * 4) All other work will handle module FixRedirects.
 */

namespace HideMyPlugins;

/**
 * @requires WordPress 2.5.0 for filter "plugin_action_links" (wp-admin/includes/class-wp-plugins-list-table.php)
 * @requires WordPress 3.1.0 for filter "network_admin_plugin_action_links" (wp-admin/includes/class-wp-plugins-list-table.php)
 * @requires WordPress 3.5.0 for filter "views_{$this->screen->id}" (wp-admin/includes/class-wp-list-table.php)
 */
class FixPluginStatus
{
    const TAB_ALL    = 'all';
    const TAB_HIDDEN = 'hidden';

    /** @var PluginsScreen */
    protected $screen = null;

    public function __construct(PluginsScreen $screen)
    {
        $this->screen = $screen;

        // Fix value of "plugin_status" in action URLs
        add_filter('plugin_action_links', [$this, 'fixPluginActions']);
        add_filter('network_admin_plugin_action_links', [$this, 'fixPluginActions']);

        // Fix "plugin_status" in the form
        add_filter('views_plugins', [$this, 'fixFormStatus'], 20);
        add_filter('views_plugins-network', [$this, 'fixFormStatus'], 20);
    }

    /**
     * @param array $actions
     * @return array
     */
    public function fixPluginActions($actions)
    {
        // The fix required only on tab "Hidden"; don't filter actions on any
        // other tab
        if (!$this->screen->isOnTabHidden()) {
            // Nothing to fix in the future
            remove_filter('plugin_action_links', [$this, 'fixPluginActions']);
            remove_filter('network_admin_plugin_action_links', [$this, 'fixPluginActions']);

            return $actions;
        }

        $search  = 'plugin_status=' . self::TAB_ALL;
        $replace = 'plugin_status=' . self::TAB_HIDDEN;

        foreach ($actions as &$action) {
            $action = str_replace($search, $replace, $action);
        }

        unset($action);

        return $actions;
    }

    /**
     * @param array $filteredVar Plugins list table views.
     * @return array
     *
     * @global string $status
     */
    public function fixFormStatus($filteredVar)
    {
        global $status;

        if ($this->screen->isOnTabHidden()) {
            $status = $this->screen->getActiveTab();
        }

        return $filteredVar;
    }
}
