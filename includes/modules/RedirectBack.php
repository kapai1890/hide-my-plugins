<?php

namespace HideMyPlugins;

class RedirectBack
{
    const TAB_HIDDEN = 'hidden';

    public function __construct()
    {
        /** @requires WordPress 2.5.0 */
        add_action('admin_init', [$this, 'maybeRedirectBack']);
    }

    /**
     * WordPress automatically redirects to tab "All" after any action. We need
     * to go back to tab "Hidden" when doing any action from there.
     *
     * @see wp-admin/plugins.php
     *
     * @todo Fix "The page isn't working" (while doing all the redirects).
     */
    public function maybeRedirectBack()
    {
        $refererUrl = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';

        if (empty($refererUrl) || strpos($refererUrl, 'plugins.php') === false) {
            return;
        }

        // Still need to check, that "plugins.php" was referer script and not
        // just a part of the string, for example:
        //     wp-admin/edit.php?s=plugins.php

        $referer = parse_url($refererUrl);

        $file  = isset($referer['path']) ? preg_replace('/[^\/]*\//', '', $referer['path']) : '';
        $query = isset($referer['query']) ? $referer['query'] : '';

        $actionDone = array_intersect(array_keys($_GET), ['activate', 'deactivate', 'activate-multi', 'deactivate-multi']);
        $actionDone = reset($actionDone);

        if ($file == 'plugins.php'
            && strpos($query, 'plugin_status=hidden') !== false
            && $actionDone !== false
        ) {
            // We need to go back to the tab "Hidden"
            $redirectUrl = plugins_sendback_url(self::TAB_HIDDEN);

            // Add action data (to inform the user on new page, that the action
            // is done)
            $redirectUrl = add_query_arg($actionDone, sanitize_text_field($_GET[$actionDone]), $redirectUrl);

            $this->redirectTo($redirectUrl);
        }
    }

    protected function redirectTo($redirectUrl)
    {
        wp_safe_redirect($redirectUrl);
    }
}
