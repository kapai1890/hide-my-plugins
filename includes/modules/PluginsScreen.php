<?php

namespace HideMyPlugins;

/**
 * @requires WordPress 3.0.0 for filter "all_plugins" (wp-admin/includes/class-wp-plugins-list-table.php)
 */
class PluginsScreen
{
    const TAB_ALL     = 'all';
    const TAB_MUSTUSE = 'mustuse';
    const TAB_HIDDEN  = 'hidden';

    /**
     * Current active tab. By default this can include "all", "active",
     * "inactive", "recently_activated", "upgrade", "mustuse", "dropins",
     * "search" and "hidden".
     *
     * @var string
     */
    protected $activeTab = self::TAB_ALL;

    /**
     * The total amount of plugins.
     *
     * @var int
     */
    protected $pluginsCount = 0;

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

    public function __construct()
    {
        if (isset($_GET['plugin_status'])) {
            $this->activeTab = sanitize_text_field($_GET['plugin_status']);
        }

        // Count all/visible/hidden plugins
        add_filter('all_plugins', [$this, 'countPlugins']);
    }

    /**
     * @param array $plugins
     * @return array
     */
    public function countPlugins($plugins)
    {
        $this->pluginsCount = count($plugins);

        // Count only real plugins. get_hidden_plugins() may have nonexistent
        // plugins, for example, removed plugins
        foreach (array_keys($plugins) as $pluginName) {
            if (is_hidden_plugin($pluginName)) {
                $this->hiddenCount++;
            } else {
                $this->visibleCount++;
            }
        }

        return $plugins;
    }

    /**
     * @return string
     */
    public function getActiveTab()
    {
        return $this->activeTab;
    }

    /**
     * @return bool
     */
    public function isOnTabAll()
    {
        return $this->activeTab == self::TAB_ALL;
    }

    /**
     * @return bool
     */
    public function isOnTabMustUse()
    {
        return $this->activeTab == self::TAB_MUSTUSE;
    }

    /**
     * @return bool
     */
    public function isOnTabHidden()
    {
        return $this->activeTab == self::TAB_HIDDEN;
    }

    /**
     * @return bool
     */
    public function isOnTabAllOrHidden()
    {
        return $this->isOnTabAll() || $this->isOnTabHidden();
    }

    /**
     * @return int
     */
    public function getPluginsCount()
    {
        return $this->pluginsCount;
    }

    /**
     * @return int
     */
    public function getVisiblePluginsCount()
    {
        return $this->visibleCount;
    }

    /**
     * @return int
     */
    public function getHiddenPluginsCount()
    {
        return $this->hiddenCount;
    }
}
