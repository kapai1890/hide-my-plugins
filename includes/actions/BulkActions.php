<?php

namespace HideMyPlugins;

/**
 * @requires WordPress 3.5.0 for filter "bulk_actions-{$this->screen->id}" (wp-admin/includes/class-wp-list-table.php)
 * @requires WordPress 4.7.0 for filter "handle_bulk_actions-{$screen->id}" (wp-admin/plugins.php)
 */
class BulkActions
{
    const ACTION_HIDE   = 'hide-selected';
    const ACTION_UNHIDE = 'unhide-selected';

    /** @var PluginsScreen */
    protected $screen = null;

    public function __construct(PluginsScreen $screen)
    {
        $this->screen = $screen;

        // Add bulk actions
        add_filter('bulk_actions-plugins', [$this, 'addActions']);
        add_filter('bulk_actions-plugins-network', [$this, 'addActions']);

        // Handle bulk actions
        add_filter('handle_bulk_actions-plugins', [$this, 'doAction'], 10, 3);
        add_filter('handle_bulk_actions-plugins-network', [$this, 'doAction'], 10, 3);
    }

    /**
     * @param array $actions
     * @return array
     */
    public function addActions($actions)
    {
        if (!$this->screen->isOnTabHidden()) {
            $actions[self::ACTION_HIDE] = esc_html__('Hide', 'hide-my-plugins');
        }

        if (!$this->screen->isOnTabAll()) {
            $actions[self::ACTION_UNHIDE] = esc_html__('Unhide', 'hide-my-plugins');
        }

        return $actions;
    }

    /**
     * @param string|false $sendback The redirect URL. False at the beginning.
     * @param string $action "hide-selected"|"unhide-selected"
     * @param array $plugins The plugins to take the action on.
     * @return string|false
     */
    public function doAction($sendback, $action, $plugins)
    {
        if ($action != self::ACTION_HIDE && $action != self::ACTION_UNHIDE) {
            return $sendback;
        }

        $hiddenPlugins = get_hidden_plugins();

        switch ($action) {
            case self::ACTION_HIDE:
                foreach ($plugins as $pluginName) {
                    // Don't add duplicates
                    if (!in_array($pluginName, $hiddenPlugins)) {
                        $hiddenPlugins[] = $pluginName;
                    }
                }
                break;

            case self::ACTION_UNHIDE:
                foreach ($plugins as $pluginName) {
                    $pluginIndex = array_search($pluginName, $hiddenPlugins);
                    if ($pluginIndex !== false) {
                        unset($hiddenPlugins[$pluginIndex]);
                    }
                }

                // Reset indexes after unset()
                $hiddenPlugins = array_values($hiddenPlugins);

                break;
        }

        set_hidden_plugins($hiddenPlugins);

        $sendback = plugins_sendback_url();

        return $sendback;
    }
}
