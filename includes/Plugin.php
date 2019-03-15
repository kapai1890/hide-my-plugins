<?php

namespace HideMyPlugins;

/**
 * Main required WordPress versions: 3.5.0 (to show the tab "Hidden" and apply
 * all fixes) and 4.7.0 (better translations support, handle custom bulk
 * actions, new functions).
 *
 * @requires WordPress 4.7.0
 */
class Plugin
{
    public function __construct()
    {
        if ($this->isPluginsPage() && !wp_doing_ajax() && $this->isWpVersion('4.7.0')) {
            $this->load();
        }
    }

    public function load()
    {
        /** @requires WordPress 1.5 */
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
        $domain = $slug = 'hide-my-plugins';

        load_plugin_textdomain($domain, false, "{$slug}/languages");

        // Load user translations
        $locale = apply_filters('plugin_locale', get_user_locale(), $domain);
        $mofile = WP_LANG_DIR . "/{$slug}/{$slug}-{$locale}.mo";

        load_textdomain($domain, $mofile);
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

    /**
     * @param string $atLeast
     * @return bool
     *
     * @global string $wp_version
     */
    protected function isWpVersion($atLeast)
    {
        global $wp_version;
        return version_compare($wp_version, $atLeast, '>=');
    }
}
