<?php

namespace HideMyPlugins;

class Plugin
{
    /** @var Plugin */
    private static $instance = null;

    private function __construct()
    {
        if ($this->isPluginsPage() && !wp_doing_ajax()) {
            $this->load();
        }
    }

    public function load()
    {
        add_action('init', [$this, 'loadTranslations']);

        require_once __DIR__ . '/functions.php';
        require_once __DIR__ . '/modules/PluginsScreen.php';
        require_once __DIR__ . '/modules/TabHidden.php';
        require_once __DIR__ . '/fixes/FixRedirects.php';
        require_once __DIR__ . '/fixes/FixTotals.php';
        require_once __DIR__ . '/fixes/FixPluginStatus.php';
        require_once __DIR__ . '/actions/BulkActions.php';
        require_once __DIR__ . '/actions/PluginActions.php';
        require_once __DIR__ . '/filters/FilterPluginsList.php';

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
        $pluginDir = plugin_basename(PLUGIN_DIR); // "hide-my-plugins" or renamed name
        load_plugin_textdomain('hide-my-plugins', false, $pluginDir . '/languages');
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

    public static function getInstance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }
}
