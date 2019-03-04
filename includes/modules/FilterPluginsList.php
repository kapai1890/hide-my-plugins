<?php

namespace HideMyPlugins;

class FilterPluginsList
{
    /** @var PluginsScreen */
    protected $screen = null;

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

    public function __construct(PluginsScreen $screen)
    {
        $this->screen = $screen;

        if (is_wp_version('2.7.0')) {
            /** @requires WordPress 2.5.0 */
            add_filter('plugin_action_links', [$this, 'startOutputBuffering'], 10, 2);

            /** @requires WordPress 3.1.0 */
            add_filter('network_admin_plugin_action_links', [$this, 'startOutputBuffering'], 10, 2);
        }
    }

    /**
     * @param array $filteredVar Plugin actions.
     * @param string $pluginName
     * @return array
     */
    public function startOutputBuffering($filteredVar, $pluginName)
    {
        // Magic starts here
        ob_start();

        /**
         * The priority must be higher than priority (10) of
         * wp_plugin_update_row(), which adds the message "There is a new
         * version of %plugin% available". See add_action() in function
         * wp_plugin_update_rows() in wp-admin/includes/update.php.
         *
         * @requires WordPress 2.7.0
         */
        add_action("after_plugin_row_{$pluginName}", [$this, 'endOutputBuffering'], 20);

        return $filteredVar;
    }

    /**
     * @param string $pluginName
     */
    public function endOutputBuffering($pluginName)
    {
        $output = ob_get_clean();

        // Don't hide the plugin if the tab is neither "All", nor "Hidden"
        if (!$this->screen->isOnTabAllOrHidden()
            // Or is a proper plugin for current tab
            || is_hidden_plugin($pluginName) == $this->screen->isOnTabHidden()
        ) {
            // Show current plugin
            echo $output;
            $this->shownCount++;
        }

        $this->processedCount++;

        // Show no-items message
        if ($this->processedCount == $this->screen->getPluginsCount() && $this->shownCount == 0) {
            $this->noItems();
        }
    }

    protected function noItems()
    {
        echo '<tr class="no-items">';
            echo '<td class="colspanchange" colspan="3">';
                _e('You do not appear to have any plugins available at this time.');
            echo '</td>';
        echo '</tr>';
    }
}
