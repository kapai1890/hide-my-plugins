<?php

namespace HideMyPlugins;

/**
 * @requires WordPress 3.5.0 for filter "views_{$this->screen->id}" (wp-admin/includes/class-wp-list-table.php)
 */
class TabHidden
{
    const TAB_HIDDEN = 'hidden';

    /** @var PluginsScreen */
    protected $screen = null;

    public function __construct(PluginsScreen $screen)
    {
        $this->screen = $screen;

        // Add tab "Hidden"
        add_filter('views_plugins', [$this, 'addTab']);
        add_filter('views_plugins-network', [$this, 'addTab']);
    }

    /**
     * @param array $views
     * @return array
     */
    public function addTab($views)
    {
        $hiddenPluginsCount = $this->screen->getHiddenPluginsCount();
        $isCurrentTab       = $this->screen->isOnTabHidden();

        // Don't add the tab when there are no hidden plugins. But add the tab
        // when displaying it
        if ($hiddenPluginsCount == 0 && !$isCurrentTab) {
            return $views;
        }

        $url  = add_query_arg('plugin_status', self::TAB_HIDDEN, plugins_url());
        $atts = $isCurrentTab ? ' class="current" aria-current="page"' : '';

        // Build tab text
        $text = sprintf(__('Hidden %s', 'hide-my-plugins'), '<span class="count">(%s)</span>');
        $text = sprintf($text, number_format_i18n($hiddenPluginsCount));

        // See "<a href..." in \WP_Plugins_List_Table::get_views() in
        // wp-admin/includes/class-wp-plugins-list-table.php
        $view = sprintf('<a href="%s"%s>%s</a>', esc_url($url), $atts, $text);

        $views[self::TAB_HIDDEN] = $view;

        return $views;
    }
}
