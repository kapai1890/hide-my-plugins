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
}
