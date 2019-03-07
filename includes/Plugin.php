<?php

namespace HideMyPlugins;

/**
 * @requires WordPress 3.5.0+ (to show the tab "Hidden" and apply all fixes)
 * @recommended WordPress 4.7.0+ (bulk actions)
 */
class Plugin
{
    public function __construct()
    {
        if ($this->isPluginsPage() && !$this->isAjax()) {
            $this->load();
        }
    }

    public function load()
    {
        /** @requires WordPress 1.5 */
        add_action('init', [$this, 'loadTranslations']);

        require_once __DIR__ . '/functions.php';
        require_once __DIR__ . '/modules/PluginsScreen.php';
        require_once __DIR__ . '/modules/FixRedirects.php';
        require_once __DIR__ . '/modules/FixTotals.php';
        require_once __DIR__ . '/modules/FixPluginStatus.php';
        require_once __DIR__ . '/modules/TabHidden.php';
        require_once __DIR__ . '/modules/BulkActions.php';
        require_once __DIR__ . '/modules/PluginActions.php';
        require_once __DIR__ . '/modules/FilterPluginsList.php';

        $screen = new PluginsScreen();

        new FixRedirects($screen);
        new FixTotals($screen);
        new FixPluginStatus($screen);

        new TabHidden($screen);
        new BulkActions($screen);
        new PluginActions($screen);

        if ($screen->isOnTabAllOrHidden()) {
            new FilterPluginsList($screen);
        }
    }

    public function loadTranslations()
    {
        load_plugin_textdomain('hide-my-plugins', false, 'hide-my-plugins/languages');
    }

    protected function isAjax()
    {
        return defined('DOING_AJAX') && DOING_AJAX;
    }

    protected function isPluginsPage()
    {
        if (!is_admin()) {
            return false;
        }

        // get_current_screen() is undefined on some pages. Also it can return
        // null if call to early

        $script = $_SERVER['SCRIPT_NAME'];

        return $script == '/wp-admin/plugins.php' || $script == '/wp-admin/network/plugins.php';
    }
}
