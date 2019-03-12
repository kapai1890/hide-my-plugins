<?php

namespace HideMyPlugins;

class FixTotals
{
    /** @var PluginsScreen */
    protected $screen = null;

    public function __construct(PluginsScreen $screen)
    {
        $this->screen = $screen;

        /** @requires WordPress 3.5.0 */
        add_filter('views_plugins', [$this, 'fixAllCount']);
        add_filter('views_plugins-network', [$this, 'fixAllCount']);

        /** @requires WordPress 2.8.0 */
        add_action('admin_enqueue_scripts', [$this, 'fixDisplayingNumber']);
    }

    /**
     * @param array $views
     * @return array
     */
    public function fixAllCount($views)
    {
        if (isset($views['all'])) {
            $count = $this->screen->getVisiblePluginsCount();

            // Fix totals: "All (15)" -> "All (%total% - %hidden%)"
            $views['all'] = preg_replace('/\(\d+\)/', "({$count})", $views['all']);

            // Remove class "current" from tab "All", when displaying "Hidden"
            if ($this->screen->isOnTabHidden()) {
                $views['all'] = preg_replace('/( class="current")|( aria-current="page")/', '', $views['all']);
            }
        }

        return $views;
    }

    public function fixDisplayingNumber()
    {
        if ($this->screen->isOnTabAllOrHidden() && $this->screen->getHiddenPluginsCount() > 0) {
            wp_enqueue_script('hide-my-plugins-admin', PLUGIN_URL . 'assets/admin.js', array('jquery'), '2.0', true);

            $pluginsCount = $this->screen->isOnTabAll() ? $this->screen->getVisiblePluginsCount() : $this->screen->getHiddenPluginsCount();
            $fixedText = sprintf(_n('%s item', '%s items', $pluginsCount), number_format_i18n($pluginsCount));

            wp_localize_script('hide-my-plugins-admin', 'HideMyPlugins', array('totalsFixedText' => $fixedText));
        }
    }
}
