<?php
/*
Plugin Name:	Force Plugin Activation/Deactivation (except if WP_DEBUG is on)
Plugin URI: 	http://danieldvork.in
Description:	Make sure the required plugins are always active.
Version:    	1.0
Author:     	Daniel Dvorkin
Author URI: 	http://danieldvork.in
*/

class Force_Plugin_Activation {

    /**
     * This is the option name where disabled "disable-allowed" plugins are stored.
     *
     * @var string
     */
    const DISABLED_PLUGINS_OPTION = 'force_activated_disabled_plugins';

    /**
     * These plugins will always be active (if WP_DEBUG is false)
     * and admins (or super) won't be able to deactivate them.
     *
     * Add elements as plugin path: directory/file.php
     */
    private $force_active = [

    ];

    /**
     * These plugins will be deactived and can't be activated (if WP_DEBUG is false)
     *
     * Add elements as plugin path: directory/file.php
     */
    private $force_deactive = [
        'query-monitor/query-monitor.php',
        'debug-bar/debug-bar.php',
        'debug-bar-action-hooks/debug-bar-action-hooks.php',
        'debug-bar-console/debug-bar-console.php',
        'debug-bar-cron/debug-bar-cron.php',
        'debug-bar-extender/debug-bar-extender.php',
        'rewrite-rules-inspector/rewrite-rules-inspector.php',
        'wp-log-in-browser/wp-log-in-browser.php',
        'wp-xhprof-profiler/xhprof-profiler.php',
    ];

    /**
     * These plugins will be shut down on the local config constant SILENT_MODE being true.
     * We sometimes want control separate from wp_debug to enable/disable some plugins for local dev.
     * This provides an easy switch for those.
     *
     * Add elements as plugin path: directory/file.php
     */
    private $force_silent = [];

    /**
     * These plugins will not show in the site plugins list.
     * They will only show in the network admin.
     *
     * Add elements as plugin path: directory/file.php
     */
    private $force_network_only = [];

    /**
     * These plugins are allowed to be disabled on a per-site basis.
     *
     * @var array
     */
    private $allow_site_disable = [
        'disable-comments/disable-comments.php',
    ];

    function __construct() {
        add_filter( 'option_active_plugins',               [ $this, 'force_plugins' ], 10, 1 );
        add_filter( 'site_option_active_sitewide_plugins', [ $this, 'force_plugins' ], 10, 1 );
        add_filter( 'plugin_action_links',                 [ $this, 'plugin_action_links' ], 99, 4 );
        add_filter( 'network_admin_plugin_action_links',   [ $this, 'plugin_action_links' ], 99, 4 );
        add_filter( 'all_plugins',                         [ $this, 'hide_from_blog' ], 99, 1 );
        add_action( 'deactivated_plugin',                  [ $this, 'deactivated_plugin' ], 99, 2 );
        add_action( 'activated_plugin',                    [ $this, 'activated_plugin' ], 99, 2 );
    }

    /**
     * Enforce the active/deactive plugin rules
     *
     * @param array $plugins
     *
     * @return array
     */
    function force_plugins( $plugins ) {

        /*
         * WordPress works in mysterious ways
         * active_plugins has the plugin paths as array key and a number as value
         * active_sitewide_plugins has the number as key and the plugin path as value
         * I'm standarizing so we can run the array operations below, then flipping back if needed.
         */
        if ( current_filter() == 'site_option_active_sitewide_plugins' ) {
            $plugins = array_flip( $plugins );
        }

        // Add our force-activated plugins
        $plugins = array_merge( (array) $plugins, $this->force_active );

        if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
            // Remove our force-deactivated plguins unless WP_DEBUG is on
            $plugins = array_diff( (array)$plugins, $this->force_deactive );
        }

        if ( defined( 'SILENT_MODE' ) && SILENT_MODE ) {
            // Remove our silent dev plugins if the flag is set
            $plugins = array_diff( (array)$plugins, $this->force_silent );
        }

        // Deduplicate
        $plugins = array_unique( $plugins );

        // Flip back if needed (see comment above)
        if ( current_filter() == 'site_option_active_sitewide_plugins' ) {

            // Filter out plugins that are allowed to be disabled at the site level.
            $plugins = array_filter( $plugins, function ( $plugin ) {
                return ! in_array( $plugin, $this->allow_site_disable );
            } );

            $plugins = array_flip( $plugins );
        }

        if ( current_filter() === 'option_active_plugins' ) {
            $disabled_plugins = get_option( self::DISABLED_PLUGINS_OPTION, [] );

            // Filter out site-level plugins that are manually activated/deactivated.
            $plugins = array_filter( $plugins, function ( $plugin ) use ( $disabled_plugins ) {
                return ! in_array( $plugin, $disabled_plugins );
            } );
        }

        return $plugins;
    }

    /**
     * Removes the activate/deactivate links from the plugins list
     * if they are in the force active or force deactive lists.
     *
     * @param array $actions
     * @param string $plugin_file
     * @param array $plugin_data
     * @param string $context
     *
     * @return array
     */
    function plugin_action_links( $actions, $plugin_file, $plugin_data, $context ) {

        // Exclude plugins that are site-level only.
        if (
            current_filter() === 'network_admin_plugin_action_links'
            && in_array( $plugin_file, $this->allow_site_disable )
        ) {
            unset( $actions['deactivate'] );
            unset( $actions['activate'] );
        }

        if (
            in_array( $plugin_file, $this->force_active )
            && ! in_array( $plugin_file, $this->allow_site_disable )
        ) {
            unset( $actions['deactivate'] );
        }

        if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
            if ( in_array( $plugin_file, $this->force_deactive ) ) {
                unset( $actions['activate'] );
            }
        }

        return $actions;
    }

    /**
     * Removes plugins from the blog plugins list
     * if they are in the $force_network_only list
     *
     * Only on multisite.
     *
     * @param array $plugins
     *
     * @return array mixed
     */
    function hide_from_blog( $plugins ) {

        if ( ! is_multisite() ) {
            return $plugins;
        }

        $screen = get_current_screen();
        if ( $screen->in_admin( 'network' ) ) {
            return $plugins;
        }

        foreach ( (array) $this->force_network_only as $slug ) {
            if ( isset( $plugins[ $slug ] ) ) {
                unset( $plugins[ $slug ] );
            }
        }

        return $plugins;
    }

    /**
     * Adds disabled plugin to the disabled list if it is a disable-allowed, site-level plugin.
     *
     * @param string $plugin
     * @param bool $network_activation
     */
    public function deactivated_plugin( string $plugin, bool $network_activation ) {
        if ( ! $this->is_site_level_and_disable_allowed( $plugin, $network_activation ) ) {
            return;
        }

        // Add plugin to disabled list.
        $disabled_plugins = get_option( self::DISABLED_PLUGINS_OPTION, array() );
        update_option( self::DISABLED_PLUGINS_OPTION,
            array_merge( $disabled_plugins, [ $plugin ] )
        );
    }

    /**
     * Removes disabled plugin from the disabled list if it is a disable-allowed, site-level plugin.
     *
     * @param string $plugin
     * @param bool $network_activation
     */
    public function activated_plugin( string $plugin, bool $network_activation ) {
        if ( ! $this->is_site_level_and_disable_allowed( $plugin, $network_activation ) ) {
            return;
        }

        // Remove plugin from disabled list.
        $disabled_plugins = get_option( self::DISABLED_PLUGINS_OPTION, array() );
        $plugin_index     = array_search( $plugin, $disabled_plugins );

        if ( false === $plugin_index ) {
            // Do nothing if plugin is not in the list.
            return;
        }

        // Remove plugin from the disabled list.
        unset( $disabled_plugins[ $plugin_index ] );
        update_option( self::DISABLED_PLUGINS_OPTION, $disabled_plugins );
    }

    /**
     * Determines if plugin is a disable-allowed, site-level plugin.
     *
     * @param string $plugin
     * @param bool $network_activation
     * @return bool
     */
    private function is_site_level_and_disable_allowed( string $plugin, bool $network_activation ) : bool {
        return (
            // This is only for site-level deactivation.
            ! $network_activation
            // Only handle deactivations that are in the disable-allowed, force-activated plugins.
            && in_array( $plugin, $this->allow_site_disable )
        );
    }
}

new Force_Plugin_Activation();
