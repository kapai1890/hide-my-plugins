<?php

namespace HideMyPlugins;

/**
 * @return array
 */
function get_hidden_plugins()
{
    /** @required WordPress 3.0.0 */
    $userId = get_current_user_id();

    if ($userId > 0) {
        /**
         * @var string|array An array of hidden plugins or empty string, if
         * there are no such meta.
         */
        $hiddenPlugins = get_user_meta($userId, 'my_hidden_plugins', true);

        return is_array($hiddenPlugins) ? $hiddenPlugins : array();
    } else {
        return array();
    }
}

/**
 * @param string $plugins
 */
function set_hidden_plugins($plugins)
{
    /** @required WordPress 3.0.0 */
    $userId = get_current_user_id();

    if ($userId > 0) {
        update_user_meta($userId, 'my_hidden_plugins', $plugins);
    }
}

/**
 * @param string $plugin
 * @return bool
 */
function is_hidden_plugin($pluginName)
{
    $hiddenPlugins = get_hidden_plugins();
    return in_array($pluginName, $hiddenPlugins);
}

/**
 * @return bool
 */
function current_user_can_manage_plugins()
{
    if (!is_network_admin()) {
        return current_user_can('install_plugins');
    } else {
        return current_user_can('manage_network_plugins');
    }
}

/**
 * @return string
 */
function plugins_url()
{
    if (!is_network_admin()) {
        return admin_url('plugins.php');
    } else {
        return admin_url('network/plugins.php');
    }
}

/**
 * @return string
 */
function plugins_sendback_url($tab = null)
{
    if (is_null($tab)) {
        if (isset($_GET['plugin_status'])) {
            $tab = sanitize_text_field($_GET['plugin_status']);
        } else {
            $tab = '';
        }
    }

    $redirectUrl = plugins_url();

    if (!empty($tab)) {
        $redirectUrl = add_query_arg('plugin_status', $tab, $redirectUrl);
    }

    if (isset($_GET['paged'])) {
        $page = absint($_GET['paged']);

        if ($page > 1) {
            $redirectUrl = add_query_arg('paged', $page, $redirectUrl);
        }
    }

    if (isset($_GET['s'])) {
        $search = urlencode(wp_unslash($_GET['s'])); // Like in wp-admin/plugins.php

        if (!empty($search)) {
            $redirectUrl = add_query_arg('s', $search, $redirectUrl);
        }
    }

    return $redirectUrl;
}

/**
 * @param string $atLeast
 * @param bool $clean Optional. False by default.
 * @return bool
 *
 * @global string $wp_version
 */
function is_wp_version($atLeast, $clean = false)
{
    global $wp_version;

    $version = $clean ? preg_replace('/[^\d\.].*$/', '', $wp_version) : $wp_version;
    return version_compare($version, $atLeast, '>=');
}

function no_items()
{
    echo '<tr class="no-items">';
        echo '<td class="colspanchange" colspan="3">';
            _e('You do not appear to have any plugins available at this time.');
        echo '</td>';
    echo '</tr>';
}
