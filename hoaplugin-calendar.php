<?php
/**
 * Plugin Name: HOAplugin Calendar
 * Plugin URI:        https://hoaplugin.com
 * Description:       The complete website calendar talored for an HOA.
 * Version:           1.1.35
 * Author:            David Keeney
 * AI Tool:           Gemini Pro 2.5 and 3.1
 * Company:           HOAplugin.com
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * License: GPL-3.0+
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       hoaplugin-calendar
 *
 * Shortcodes:     
 *    [hoaplugin_calendar]
 * This is the master entry point for the plugin. When used without parameters, 
 * it defaults to the hybrid layout, loading both the monthly grid workspace 
 * and the agenda stream workspace into the container.
 *
 *     [hoaplugin_calendar layout="month"] 
 * Skips the agenda module and exclusively loads the monthly grid.
 *
 *     [hoaplugin_calendar layout="agenda"] 
 * Skips the monthly grid and shows only the agenda view.
 *     
 */

if ( ! defined( 'ABSPATH' ) ) exit;


// DEFENSIVE ABORT: Prevent Fatal Errors if Pro is running.
if ( function_exists( 'hoaplugin_pro_activation_routine' ) ) {
    add_action( 'admin_init', function() {
        if ( ! function_exists( 'deactivate_plugins' ) ) require_once ABSPATH . 'wp-admin/includes/plugin.php';
        deactivate_plugins( plugin_basename( __FILE__ ) );
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error is-dismissible"><p><strong>HOAplugin Calendar (Free)</strong> was deactivated because you are currently running the <strong>Pro</strong> version.</p></div>';
        });
    });
    return;
}

// ==========================================
// 1. SYSTEM PATH CONSTANTS
// ==========================================
// The Codebase (Where the PHP/CSS/JS files live)
define( 'HOAPLUGIN_ROOT_DIR', plugin_dir_path( __FILE__ ) );
define( 'HOAPLUGIN_ROOT_URL', plugin_dir_url( __FILE__ ) );
define( 'HOAPLUGIN_PLUGIN_FILE', __FILE__ );

// The Data Storage (Where the JSON cache and Canva backgrounds live)
$upload_env = wp_upload_dir();
define( 'HOAPLUGIN_DATA_DIR', $upload_env['basedir'] . '/hoaplugin-calendar' );
define( 'HOAPLUGIN_DATA_URL', $upload_env['baseurl'] . '/hoaplugin-calendar' );


use HOAPLUGIN\Cal\Repository;
use HOAPLUGIN\Cal\Compiler;

require_once __DIR__ . '/vendor/autoload.php';
if ( is_admin() ) {
    require_once __DIR__ . '/admin/settings-page.php';
    require_once __DIR__ . '/admin/upsell.php';
}
// 2. Load the shared Core logic
require_once __DIR__ . '/includes/core-bootstrap.php';

