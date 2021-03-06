<?php

namespace HideMyPlugins;

/**
 * @requires WordPress 2.5 for filter "plugin_action_links" (wp-admin/includes/class-wp-plugins-list-table.php)
 * @requires WordPress 2.6 for action "admin_action_{$_REQUEST['action']}" (wp-admin/admin.php)
 * @requires WordPress 3.1 for filter "network_admin_plugin_action_links" (wp-admin/includes/class-wp-plugins-list-table.php)
 */
class PluginActions
{
    const ACTION_HIDE   = 'hide_my_plugin';
    const ACTION_UNHIDE = 'unhide_my_plugin';

    const NONCE_NAME = '_hmpnonce';

    /** @var PluginsScreen */
    protected $screen = null;

    public function __construct(PluginsScreen $screen)
    {
        $this->screen = $screen;

        // Add actions after the FixPluginStatus, so it will not filter our own
        // actions
        add_filter('plugin_action_links', [$this, 'addAction'], 20, 2);
        add_filter('network_admin_plugin_action_links', [$this, 'addAction'], 20, 2);

        // Do actions
        add_action('admin_action_hide_my_plugin', [$this, 'onHidePlugin']);
        add_action('admin_action_unhide_my_plugin', [$this, 'onUnhidePlugin']);
    }

    /**
     * <i>Hint. This method does not use context (tab name) of the filter
     * "plugin_action_links", because that context will be "all" on custom
     * tabs.</i>
     *
     * @param array $actions
     * @param string $pluginName
     * @return array
     */
    public function addAction($actions, $pluginName)
    {
        if (!current_user_can_manage_plugins()) {
            return $actions;
        }

        // Don't hide must-use plugins
        if ($this->screen->isOnTabMustUse()) {
            return $actions;
        }

        $isHidden   = is_hidden_plugin($pluginName);
        $actionText = $isHidden ? esc_html__('Unhide', 'hide-my-plugins') : esc_html__('Hide', 'hide-my-plugins');
        $actionId   = $isHidden ? self::ACTION_UNHIDE : self::ACTION_HIDE;

        // Build action URL
        $actionUrl = add_query_arg(
            [
                'action' => $actionId,
                'plugin' => $pluginName
            ],
            plugins_sendback_url()
        );

        // Add nonce
        $nonceAction = $this->nonceAction($actionId, $pluginName);
        $actionUrl = wp_nonce_url($actionUrl, $nonceAction, self::NONCE_NAME);

        $actionLink = '<a href="' . esc_url($actionUrl) . '" title="' . esc_attr($actionText) . '" class="edit">' . esc_html($actionText) . '</a>';
        $actions[$actionId] = $actionLink;

        return $actions;
    }

    public function onHidePlugin()
    {
        $this->doAction(self::ACTION_HIDE);
    }

    public function onUnhidePlugin()
    {
        $this->doAction(self::ACTION_UNHIDE);
    }

    /**
     * @param string $action
     */
    protected function doAction($action)
    {
        // $_GET['action'] -> $actionId
        if (!isset($_GET['action']) || !isset($_GET['plugin'])) {
            return;
        }

        $actionId   = sanitize_text_field($_GET['action']);
        $pluginName = sanitize_text_field($_GET['plugin']);

        if (!$this->isValidInput($actionId, $pluginName) || !current_user_can_manage_plugins()) {
            return;
        }

        $hiddenPlugins = get_hidden_plugins();

        // Hide or unhide the plugin
        switch ($action) {
            case self::ACTION_HIDE:
                if (!in_array($pluginName, $hiddenPlugins)) {
                    $hiddenPlugins[] = $pluginName;
                }
                break;

            case self::ACTION_UNHIDE:
                $pluginIndex = array_search($pluginName, $hiddenPlugins);

                if ($pluginIndex !== false) {
                    unset($hiddenPlugins[$pluginIndex]);

                    // Reset indexes after unset()
                    $hiddenPlugins = array_values($hiddenPlugins);
                }

                break;
        }

        set_hidden_plugins($hiddenPlugins);

        $this->resetQueryArgs();
    }

    /**
     * @param string $actionId
     * @param string $pluginName
     * @return bool
     */
    protected function isValidInput($actionId, $pluginName)
    {
        $nonce       = isset($_REQUEST[self::NONCE_NAME]) ? $_REQUEST[self::NONCE_NAME] : '';
        $nonceAction = $this->nonceAction($actionId, $pluginName);
        $verified    = wp_verify_nonce($nonce, $nonceAction);

        return $verified !== false;
    }

    /**
     * @param string $actionId
     * @param string $pluginName
     * @return string
     */
    protected function nonceAction($actionId, $pluginName)
    {
        return $actionId . '_' . $pluginName;
    }

    protected function resetQueryArgs()
    {
        wp_redirect(plugins_sendback_url());

        $this->selfDestruction();
    }

    protected function selfDestruction()
    {
        exit(0);
    }
}
