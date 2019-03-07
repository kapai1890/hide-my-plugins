<?php

namespace HideMyPlugins;

class FixRedirects
{
    const TAB_HIDDEN = 'hidden';

    /** @var PluginsScreen */
    protected $screen = null;

    public function __construct(PluginsScreen $screen)
    {
        $this->screen = $screen;

        /** @requires WordPress 2.1.0 */
        add_filter('wp_redirect', [$this, 'fixRedirect']);
    }

    public function fixRedirect($location)
    {
        if (!$this->screen->isOnTabHidden()) {
            return $location;
        }

        // Check query args and script file
        $queryString = parse_url($location, PHP_URL_QUERY);

        $scriptFile = parse_url($location, PHP_URL_PATH);
        $scriptFile = !is_null($scriptFile) ? preg_replace('/[^\/]*\//', '', $scriptFile) : null;

        if (is_null($queryString) || is_null($scriptFile) || $scriptFile != 'plugins.php') {
            return $location;
        }

        // Check the action
        $queryArgs = wp_parse_args($queryString);
        $handleActions = ['activate', 'deactivate', 'activate-multi', 'deactivate-multi'];
        $actionsDone = array_intersect(array_keys($queryArgs), $handleActions);

        if (!empty($actionsDone)) {
            // Go back to the tab "Hidden"
            $queryArgs['plugin_status'] = self::TAB_HIDDEN;

            $location = add_query_arg($queryArgs, plugins_url());
        }

        return $location;
    }
}
