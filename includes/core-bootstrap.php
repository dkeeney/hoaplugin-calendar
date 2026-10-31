<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Inject a custom body class if the calendar shortcode is present on the page.
 * This allows our CSS to safely override theme headers and padding.
 */
add_filter( 'body_class', 'hoa_calendar_body_class' );
function hoa_calendar_body_class( $classes ) {
    // Only run this on actual pages/posts (not archives or the backend)
    if ( is_singular() ) {
        global $post;

        // Check for the master shortcode, plus the legacy aliases
        if ( has_shortcode( $post->post_content, 'hoaplugin_calendar' ) ||
             has_shortcode( $post->post_content, 'hoaplugin_monthly_calendar' ) ||
             has_shortcode( $post->post_content, 'hoaplugin_agenda_calendar' ) ) {

            $classes[] = 'hoa-calendar-active';
        }
    }
    return $classes;
}


register_activation_hook( HOAPLUGIN_PLUGIN_FILE, 'hoa_core_network_activation' );

function hoa_core_network_activation( $network_wide ) {
    // If it's a multisite and the PMC clicked "Network Activate"
    if ( is_multisite() && $network_wide ) {
        // Fetch all sub-sites in the network
        $site_ids = get_sites( array( 'fields' => 'ids' ) );
        foreach ( $site_ids as $site_id ) {
            switch_to_blog( $site_id );

            $repo = new HOAPLUGIN\Cal\Repository();
            $repo->create_table();

            restore_current_blog(); // Crucial: Always switch back!
        }
    } else {
        // Standard single-site activation
        $repo = new HOAPLUGIN\Cal\Repository();
        $repo->create_table();
    }
}

// Listen for NEW HOAs being added to the network in the future
add_action( 'wp_insert_site', function( $new_site ) {
    // If the plugin is already network-active, build tables for the new site
    if ( is_plugin_active_for_network( plugin_basename( HOAPLUGIN_PLUGIN_FILE ) ) ) {
        switch_to_blog( $new_site->blog_id );

        $repo = new HOAPLUGIN\Cal\Repository();
        $repo->create_table();

        restore_current_blog();
    }
});


// --- REPOSITORY & COMPILER HELPERS ---
function hoa_get_repo() {
    $repo = apply_filters( 'hoa_override_repo', null );
    return $repo ? $repo : new \HOAPLUGIN\Cal\Repository();
}

function hoa_get_compiler() {
    $compiler = apply_filters( 'hoa_override_compiler', null );
    return $compiler ? $compiler : new \HOAPLUGIN\Cal\Compiler();
}

// Modify your JSON serving function to allow path interception:
add_action('wp_ajax_hoa_get_calendar_json', 'hoa_serve_calendar_json');
add_action('wp_ajax_nopriv_hoa_get_calendar_json', 'hoa_serve_calendar_json');

function hoa_serve_calendar_json() {
    // 1. Allow companion plugin to intercept the path
    $default_path = HOAPLUGIN_DATA_DIR . '/calendar-events.json';
    $path = apply_filters( 'hoa_calendar_json_path', $default_path );

    // 2. Check if the file actually exists on the server
    if ( ! file_exists( $path ) ) {
        wp_send_json_error( 'Calendar data file not found on server.', 404 );
    }

    // 3. Set headers so the browser treats this as a JSON file
    header( 'Content-Type: application/json; charset=utf-8' );
    header( 'Access-Control-Allow-Origin: *' ); 

    // 4. Read the file and spit it out
    echo file_get_contents( $path );
    exit;
}
// ------------------------------





add_action('admin_enqueue_scripts', function($hook) {
    // Only load on our settings page to keep admin clean
    if ($hook !== 'toplevel_page_hoa-cal-settings') return;

    // Load the CSS
    wp_enqueue_style('hoa-cal-style', HOAPLUGIN_ROOT_URL . 'assets/css/calendar-style.css');
    wp_enqueue_style('hoa-cell-style', HOAPLUGIN_ROOT_URL . 'assets/css/day-cell-style.css');

    // Enqueue the same scripts used for the front-end
    hoa_enqueue_calendar_scripts();

    // Force a specific Admin fix for the scrollbar and modal layering
    wp_add_inline_style('hoa-cal-style', "
        /* Prevent the modal from being hidden behind the WP Admin Menu */
        .hoa-modal {
            z-index: 99999 !important;
        }
        /* Override any 'overflow: hidden' that might get stuck on the admin body */
        body.hoa-admin-scroll-fix {
            overflow: auto !important;
            height: auto !important;
        }
    ");
});


// Hide the admin bar for mobile phones and tablets
add_filter( 'show_admin_bar', 'hoaplugin_hide_admin_bar_on_mobile_calendar' );

function hoaplugin_hide_admin_bar_on_mobile_calendar( $show ) {
    // Only intercept on the frontend, AND only if it is a mobile device
    if ( ! is_admin() && wp_is_mobile() && is_singular() ) {
        global $post;

        // If the current page contains your calendar shortcode, hide the admin bar
        if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'hoaplugin_calendar' ) ) {
            return false;
        }
    }

    // Otherwise, respect the default WordPress behavior
    return $show;
}

add_action('wp_enqueue_scripts', function() {
    // Let the shortcode handle script loading directly on demand
    // to protect page speed everywhere else across the site hierarchy.
});



function hoa_enqueue_calendar_scripts() {
    //error_log("HOAPLUGIN CALENDAR: hoa_enqueue_calender_scripts() called");
    $ver = '1.1';
    $current_user = wp_get_current_user();
    $user_email = !empty($current_user->user_email) ? $current_user->user_email : '';


    // Load the View logic and editor
    wp_enqueue_script( 'hoa-cal-view', HOAPLUGIN_ROOT_URL . 'assets/js/calendar-view.js', $ver, true );

    // 1. ALWAYS register the script so WordPress knows it exists and what its dependencies are
    wp_register_script( 'hoa-cal-editor', HOAPLUGIN_ROOT_URL . 'assets/js/calendar-editor.js', array('hoa-cal-view'), $ver, true );


    $is_admin = current_user_can('manage_options');

    // Quick check: Is this user an owner of ANY event?
    $repo = hoa_get_repo();
    $is_delegate = false;
    $delegated_categories = [];
    if ( is_user_logged_in() && !$is_admin && !empty($user_email) ) {
        $is_delegate = $repo->is_user_delegate($user_email);
        $delegated_categories = $repo->get_user_delegated_categories($user_email);
    }


    if ( $is_admin || $is_delegate) {
        wp_enqueue_media();

        // Load the Editor logic (For this pass, load for everyone to test the split)
        wp_enqueue_script('hoa-cal-editor');
    }


    wp_enqueue_style('hoa-cal-style', HOAPLUGIN_ROOT_URL . 'assets/css/calendar-style.css');
    wp_enqueue_style('hoa-agenda-style', HOAPLUGIN_ROOT_URL . 'assets/css/agenda-style.css');
    wp_enqueue_style('hoa-cell-style', HOAPLUGIN_ROOT_URL . 'assets/css/day-cell-style.css');
    
    // Fetch data for the JS
    $locations = $repo->get_locations();
    $categories = $repo->get_categories();
    $upload_dir = wp_upload_dir();
    $bg_dir = apply_filters('hoa_background_url', HOAPLUGIN_DATA_URL . '/backgrounds/');

    // 3. Localize ONE TIME with all data
    wp_localize_script('hoa-cal-view', 'hoaplugin_data', array(
        'ajax_url'      => admin_url('admin-ajax.php'),
        'nonce'         => wp_create_nonce('hoa_cal_nonce'),
        'bg_base_url'   => $bg_dir,
        'past_limit'    => (int)get_option('hoa_cal_past_months', 1),
        'future_limit'  => (int)get_option('hoa_cal_future_months', 12),
        'locations'     => $locations,
        'categories'    => $categories,
        'time_position' => get_option('hoa_time_position', 'prepend'),
        'time_format'   => get_option('hoa_time_format', '12hr'),
        'start_day'     => get_option('hoa_start_day', '0'),
        'is_admin'      => $is_admin,
        'user_email'    => $user_email,
        'delegated_categories' => $delegated_categories,
        'version'       => time(),
        'is_pro'        => apply_filters('hoa_is_pro_active', false),
        'print_js_url'  => apply_filters('hoa_pro_print_js_url', ''),
        'print_data_url' => apply_filters('hoa_pro_print_data_url', '')
    ));

   do_action('hoa_enqueue_pro_scripts');
}



// =========================================================================
// THE UNIFIED CALENDAR SYSTEM SHORTCODE
// =========================================================================
function hoa_calendar_master_shortcode( $atts ) {
    // Provide an option parameter so admins can still force a single view if desired
    $args = shortcode_atts( array(
        'layout' => 'hybrid', // Options: 'hybrid' (both), 'month', 'agenda'
    ), $atts );

    // FORCE LOAD CALENDAR ASSETS STRICTLY WHEN THIS SHORTCODE IS PRESENT
    hoa_enqueue_calendar_scripts();

    // Determine the JSON path with cache-buster
    $json_url = admin_url('admin-ajax.php') . '?action=hoa_get_calendar_json';
    $json_url .= '&v=' . get_option('hoa_cal_version', time());

    // Get WP User Data
    $current_user = wp_get_current_user();
    $user_email   = !empty($current_user->user_email) ? $current_user->user_email : '';
    $is_admin     = current_user_can('manage_options') ? 'true' : 'false';

    ob_start();
    echo '<div class="hoa-unified-calendar-container">';

    // ----------------=========================================
    // MODULE A: THE MONTHLY GRID WORKSPACE
    // ----------------=========================================
    if ( $args['layout'] === 'hybrid' || $args['layout'] === 'month' ) {
        ?>
        <div id="hoa-monthly-wrapper">
            <div id="hoa-calendar-app"
                data-json-url="<?php echo esc_url($json_url); ?>"
                data-user-email="<?php echo esc_attr($user_email); ?>"
                data-is-admin="<?php echo $is_admin; ?>">

                <button type="button" id="prevMonth" class="nav-arrow prev">&#10094;</button>
                <button type="button" id="nextMonth" class="nav-arrow next">&#10095;</button>

                <div id="calendar-grid" class="calendar-grid"></div>
                
                <div class="hoa-detail-modal hoa-full-modal">
                    <div class="modal-backdrop"></div>
                    <div class="modal-window">
                        <button class="modal-close" onclick="window.hoaplugin_closeDetailModal()">&times;</button>
                        <div class="modal-content-area"> </div>
                    </div>
                </div>
                
                <div id="hoa-edit-modal" class="hoa-modal">
                    <div class="modal-content">
                        <span class="close-modal">&times;</span>
                        <div id="edit-form-container"></div>
                    </div>
                </div>
                <div id="hoa-reschedule-modal" class="hoa-modal">
                    <div class="modal-content" style="max-width: 400px;">
                        <span class="close-modal" onclick="window.hoaplugin_closeRescheduleModal()">&times;</span>
                        <div id="reschedule-form-container"></div>
                    </div>
                </div>
                <div id="hoa-day-modal" class="hoa-modal">
                    <div class="modal-content">
                        <span class="close-modal">&times;</span>
                        <div id="hoa-modal-content"></div>
                    </div>
                </div>
            </div>
            
            <div id="hoa-manage-modal" class="hoa-modal">
                <div class="modal-content" style="max-width: 450px;">
                    <span class="close-modal">&times;</span>
                    <div id="manage-form-container"></div>
                </div>
            </div>
            
            <div id="hoa-monthly-toolbar" class="calendar-footer-toolbar">
                <div class="toolbar-left">
                    <button type="button" id="jumpToday" class="hoa-mini-btn">Today</button>
                    <button type="button" id="toggleFullScreen" class="hoa-mini-btn">⛶ Fullscreen</button>
                </div>
                <div class="toolbar-right">
                    <?php do_action('hoaplugin_toolbar_export_area'); ?>

                    <label class="mini-label">
                        <input type="checkbox" id="toggle-magnifier" checked> Magnifier
                    </label>
                    <div class="view-toggle-container">
                        <span class="toggle-label">Monthly</span>
                        <label class="hoa-switch">
                            <input type="checkbox" id="viewToggle">
                            <span class="slider round"></span>
                        </label>
                        <span class="toggle-label">Agenda</span>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    // ----------------=========================================
    // MODULE B: THE AGENDA STREAM WORKSPACE
    // ----------------=========================================
    if ( $args['layout'] === 'hybrid' || $args['layout'] === 'agenda' ) {
        ?>
        <div id="hoa-agenda-wrapper">
             <div id="hoa-agenda-app"
                  class="agenda-mode-only"
                  data-json-url="<?php echo esc_url($json_url); ?>"
                  data-user-email="<?php echo esc_attr($user_email); ?>"
                  data-is-admin="<?php echo $is_admin; ?>">

                  <div class="nav-arrow prev" id="prevMonthAgenda">❮</div>
                  <div class="nav-arrow next" id="nextMonthAgenda">❯</div>
                  <div class="agenda-controls-wrapper">
                       <div id="agenda-sticky-header" class="agenda-only"></div>
                  </div>
                  <div id="agenda-view">
                       <div id="agenda-content-area"></div>
                  </div>

                  <div class="hoa-detail-modal hoa-full-modal">
                       <div class="modal-backdrop"></div>
                       <div class="modal-window">
                            <button class="modal-close" onclick="window.hoaplugin_closeDetailModal()">&times;</button>
                            <div class="modal-content-area"></div>
                       </div>
                  </div>
             </div>
             <div id="hoa-agenda-toolbar" class="calendar-footer-toolbar">
                  <div class="toolbar-left">
                       <button type="button" id="jumpToday" class="hoa-mini-btn">Today</button>
                  </div>
                  <div class="toolbar-right">
                       <div class="view-toggle-container">
                            <span class="toggle-label">Monthly</span>
                            <label class="hoa-switch">
                                 <input type="checkbox" id="viewToggle">
                                 <span class="slider round"></span>
                            </label>
                            <span class="toggle-label">Agenda</span>
                       </div>
                  </div>
             </div>
        </div>
        <?php
    }

    echo '</div>'; // Close Unified Envelope
    return ob_get_clean();
}

// 1. Register the  master entry point shortcode
add_shortcode( 'hoaplugin_calendar', 'hoa_calendar_master_shortcode' );

// 2. Map old shortcode endpoints as backwards-compatible aliases so your existing pages don't break!
add_shortcode( 'hoaplugin_monthly_calendar', function() { return hoa_calendar_master_shortcode(['layout' => 'month']); });
add_shortcode( 'hoaplugin_agenda_calendar', function() { return hoa_calendar_master_shortcode(['layout' => 'agenda']); });





// Use 'admin_init' to catch the redirect back from options.php
add_action('admin_init', function() {
    // Check if we just came back from saving our specific settings group
    if (isset($_GET['page']) && $_GET['page'] === 'hoa-cal-settings' && isset($_GET['settings-updated'])) {
        $compiler = hoa_get_compiler();
        $compiler->bake();
    }
});


// Listen for the "Get Details" call
// We wait until an edit screen is requested before getting the details.
add_action('wp_ajax_hoa_get_event_details', 'hoa_handle_get_event_details');

function hoa_handle_get_event_details() {
    $tz = get_option('timezone_string') ?: timezone_name_from_abbr('', get_option('gmt_offset') * 3600, false);
    if ($tz) date_default_timezone_set($tz);

    check_ajax_referer('hoa_cal_nonce', 'nonce');

    $event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
    if (!$event_id) wp_send_json_error('Invalid ID');

    $repo = hoa_get_repo();
    $event = $repo->get($event_id); // This uses your JOINed get() method

    if ($event) {
        // Map DB fields to JS-friendly keys if they differ
        $event->start_time = date('H:i', strtotime($event->start_datetime));
        $event->end_time   = date('H:i', strtotime($event->end_datetime));
        $event->base_date  = date('Y-m-d', strtotime($event->start_datetime));

        wp_send_json_success($event);
    } else {
        wp_send_json_error('Event not found');
    }
}

// We hook into 'save_post_hoaplugin_event' or a custom action
add_action('hoaplugin_event_updated', function($event_id) {
    $compiler = hoa_get_compiler();
    
    // 1. Get all active events for the next 12 months
    // 2. Compile them to the flat array
    // 3. Write to the JSON file in /wp-content/uploads/
    
    // For now, let's just trigger a log to prove it works on your Pi
    error_log("HOAPLUGIN Calendar: Event $event_id changed. Re-baking JSON...");
    $compiler->bake();
});

// Listen for the AJAX call from the JS "Save Changes" button
add_action('wp_ajax_hoa_save_calendar_event', 'hoa_handle_save_event');



function hoa_handle_save_event() {
    $tz = get_option('timezone_string') ?: timezone_name_from_abbr('', get_option('gmt_offset') * 3600, false);
    if ($tz) date_default_timezone_set($tz);

    $edit_mode  = sanitize_text_field($_POST['edit_mode'] ?? 'standard');
    $is_drag_drop = isset($_POST['is_drag_drop']) && $_POST['is_drag_drop'] === 'true';
    $master_id   = isset($_POST['event_id']) ? intval($_POST['event_id']) : null;
    $pivot_id   = isset($_POST['pivot_id']) ? intval($_POST['pivot_id']) : $master_id;
    $move_id   = isset($_POST['move_id']) ? intval($_POST['move_id']) : null;
    $repo = hoa_get_repo();
    $compiler = hoa_get_compiler();
    if ( $is_drag_drop && empty($GLOBALS['hoa_extended_buffer_enabled']) ) return;
    
    #error_log("HOAPLUGIN AJAX TRIGGERED: Mode=" . $edit_mode . 
    #          " ID=$master_id move_id=$move_id pivot_id=$pivot_id");

    
    // 1. Security & Permission Check
    check_ajax_referer('hoa_cal_nonce', 'nonce');
    #error_log("PHP DEBUG: Nonce Check Passed");


    // Quick check: Is this admin or user an owner of ANY event?
    $is_admin = current_user_can('manage_options');
    //  fetch the user's email so the Gatekeeper knows who they are!
    $current_user = wp_get_current_user();
    $user_email = !empty($current_user->user_email) ? strtolower($current_user->user_email) : '';
    $is_delegate = false;
    if ( is_user_logged_in() && !$is_admin ) {
        $is_delegate = $repo->is_user_delegate($user_email);
    }
    if (!$is_admin && !$is_delegate) {
        error_log("PHP DEBUG: Permission Denied for user");
        wp_send_json_error('You do not have permission to edit events.');
    }

    // ---  STRICT CATEGORY GATEKEEPER for delegates ---
    $category_id = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
    if (!$is_admin && $category_id) {
        $is_event_owner = false;
        if ($master_id) {
            $existing_event = $repo->get($master_id);
            if ($existing_event && strtolower($existing_event->owner_email) === strtolower($user_email)) {
                $is_event_owner = true;
            }
        }
        
        $is_cat_delegate = $repo->is_category_delegate($user_email, $category_id);
        
        // Block if they don't own the category AND they don't own the specific event
        if (!$is_cat_delegate && !$is_event_owner) {
            wp_send_json_error('Security Violation: You do not have permission to post to this category.');
        }
        
        // Edge case: An event owner is trying to move their event into a category they don't manage
        if ($is_event_owner && !$is_cat_delegate && $existing_event->category_id != $category_id) {
            wp_send_json_error('Security Violation: You cannot move this event into a category you do not manage.');
        }
    }


    // 2. Collect and Sanitize Data
    $title      = sanitize_text_field($_POST['title'] ?? '');
    $event_date = sanitize_text_field($_POST['date'] ?? '');  // date clicked on
    //
    // 1. Fetch the existing master record to see its original "Anchor Date"
    $existing_event = $master_id ? $repo->get($master_id) : null;


    // 2. Decide which date to use
    // If it's a Master event (no parent_id), keep its original date.
    // This prevents the "Dance Fitness" series from moving to today's date.
    $target_date = $event_date;
    // ONLY protect the master date if we are doing a standard 'single' edit.
    // If we are punching a hole (instance_cancel) or moving (instance_move),
    // we MUST use the new $event_date provided by the calendar cell.
    //if ($existing_event && empty($existing_event->parent_id) && $edit_mode === 'standard') {
    //    $target_date = date('Y-m-d', strtotime($existing_event->start_datetime));
    //}

    try {
        switch ($edit_mode) {
            case 'instance_cancel':
                error_log("PHP DEBUG: Entering instance_cancel for ID: " . $master_id);
                // "Punch a hole" in a repeating series
                $dna_start_time = substr($existing_event->start_datetime, 11, 8);
                $dna_end_time   = substr($existing_event->end_datetime, 11, 8);
                $data = [
                    'title'          => $existing_event->title,
                    'parent_id'      => $master_id,
                    'start_datetime' => "$target_date $dna_start_time",
                    'end_datetime'   => "$target_date $dna_end_time",
                    'status'         => 'cancelled',
                    'rrule'          => null // Children never repeat
                ];
                $repo->save($data);
                break;

            case 'master_cancel':
                // Kill the entire series or the one-shot
                $repo->cancel_series($master_id);
                break;

            case 'instance_restore':
               // $target_date is the date of the cell clicked.
               // We look for the first record >= that date that is 'cancelled'.
               $repo->restore_hole($master_id, $target_date);
               break;

            case 'series_end':
                // Stop the series before this date
                $until_date = date('Ymd\T235959', strtotime($event_date . ' -1 day'));
                $repo->end_series($pivot_id, $until_date);
                break;

            case 'master_delete':
                // Delete the master and all children.
                $repo->delete_series($master_id);
                break;

            case 'instance_move':
                $event_date   = sanitize_text_field($_POST['date']);
                $move_to_date = sanitize_text_field($_POST['move_to_date']);
                $new_start    = sanitize_text_field($_POST['move_to_start_time']);
                $scope        = sanitize_text_field($_POST['reschedule_scope'] ?? 'instance');

                error_log("HOAPLUGIN DEBUG: Entering move logic for ID $master_id, pivot_id: $pivot_id, move_id: $move_id");

                // Calculate end time based on the original duration
                $existing_pivot = $repo->get($pivot_id);   // pivot or master
                if (!$existing_pivot) {
                    error_log("HOAPLUGIN DEBUG: active pivot $pivot_id not found");
                    wp_send_json_error('Event not found');
                }
                $duration_seconds = strtotime($existing_pivot->end_datetime) - strtotime($existing_pivot->start_datetime);
                $new_end = date('H:i', strtotime($new_start) + $duration_seconds);

                $result = $repo->move_event_instance(
                    $master_id,
                    $pivot_id,   // id of pivot or master
                    $move_id,    // if moving from a move record or null
                    $event_date, // The date from the calendar cell
                    $move_to_date,
                    $new_start,
                    $new_end,
                    $scope
                );

                if (is_wp_error($result)) {
                    error_log("HOAPLUGIN DEBUG: Repo error: " . $result->get_error_message());
                    wp_send_json_error($result->get_error_message());
                }
                error_log("HOAPLUGIN DEBUG: Move call finished successfully");
                break;


            case 'series_resume':
                $repo->resume_series($pivot_id, $target_date);
                break;


            default:
                // GUARDRAIL: make sure there is a title.
                // If there is no title, assume we just want to do a bake.
                if (empty($title)) {
                    error_log("HOAPLUGIN DEBUG: title empty, just doing a bake.");
                    break;
                }

                error_log("HOAPLUGIN DEBUG: taking default case.");

                // Standard data payload used add/edit.
                $start_time = sanitize_text_field($_POST['start_time']); 
                $end_time   = sanitize_text_field($_POST['end_time']);
                $data = [
                    'title'          => $title,
                    'content'        => wp_kses_post($_POST['content'] ?? ''),
                    'setup_notes'    => wp_kses_post($_POST['setup_notes'] ?? ''),
                    'location_id'    => !empty($_POST['location_id']) ? intval($_POST['location_id']) : null,
                    'category_id'    => !empty($_POST['category_id']) ? intval($_POST['category_id']) : null,
                    'is_ticketed'    => isset($_POST['is_ticketed']) && $_POST['is_ticketed'] === 'true' ? 1 : 0,
                    'cost'           => sanitize_text_field($_POST['cost']),
                    'flyer_url'      => esc_url_raw($_POST['flyer_url'] ?? ''),
                    'visibility'     => isset($_POST['visibility']) ? sanitize_text_field($_POST['visibility']) : 'public',
                    'status'         => 'active',
                ];

                if (current_user_can('manage_options')) {
                    $data['owner_email'] = isset($_POST['owner_email']) ? sanitize_email($_POST['owner_email']) : null;
                }             

                if ($edit_mode == 'soft_save') {
                    // Just update the metadata on the Master record
                    $data['id'] = $master_id;
                    $repo->save($data);
                    // Note: We EXPLICITLY do not call hoa_maybe_pivot_series here.
                    break;
                }

                $new_rrule = !empty($_POST['rrule']) ? sanitize_text_field($_POST['rrule']) : null;
                $new_start_datetime = "$target_date $start_time:00";
                $new_end_datetime   = "$target_date $end_time:00";

                if (!$master_id) {
                    // this is an Add
                    error_log("HOAPLUGIN Doing a master add");
                    $data['rrule']          = $new_rrule;
                    $data['start_datetime'] = $new_start_datetime;
                    $data['end_datetime']   = $new_end_datetime;
                    $repo->save($data);

                } else {
                    // this is an update
                    error_log("HOAPLUGIN Doing an update on $pivot_id.");
                    $data['id'] = $master_id;
                    $repo->save($data);

                    //  Check for a Series Pivot
                    $data = [];
                    $data['title']          = $title;   // for debugging
                    $data['rrule']          = $new_rrule;
                    $data['start_datetime'] = $new_start_datetime;
                    $data['end_datetime']   = $new_end_datetime;
                    $repo->maybe_pivot_series($pivot_id, $data, $target_date);
                }
                break;
        }

        // 3. THE BAKE: Refresh the JSON file
        $compiler->bake();
        update_option('hoa_cal_version', time());
        wp_send_json_success(['message' => 'Success! Calendar baked.', 'mode' => $edit_mode]);

    } catch (\Exception $e) {
        error_log("HOAPLUGIN CRITICAL ERROR: in save logic. " . $e->getMessage());
        wp_send_json_error('Database error: ' . $e->getMessage());
    }

    wp_die();
}




// Allow both logged-in users and guests to export events
add_action('wp_ajax_hoa_export_event', 'hoa_ajax_export_event');
add_action('wp_ajax_nopriv_hoa_export_event', 'hoa_ajax_export_event');

function hoa_ajax_export_event() {
    $event_id = intval($_GET['event_id'] ?? 0);
    if (!$event_id) wp_die('Invalid Event ID');
    $site_domain = wp_parse_url(home_url(), PHP_URL_HOST);

    $repo = hoa_get_repo();
    $target_event = $repo->get($event_id);
    if (!$target_event) wp_die('Event not found');

    // If they clicked a child pivot, we want to export the whole series starting from the Master
    $family_root_id = !empty($target_event->parent_id) ? $target_event->parent_id : $target_event->id;
    
    // 1. Get the Master
    $master_event = $repo->get($family_root_id);
    
    // 2. Get all children (Pivots and single-instance moves)
    global $wpdb;
    $table_name = $repo->get_table_name();
    $family_members = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name WHERE parent_id = %d AND status = 'active' ORDER BY start_datetime ASC", 
        $family_root_id
    ));

    // 3. Get all "Holes" (Cancellations) for this family tree
    $exceptions = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name WHERE (id = %d OR parent_id = %d) AND status = 'cancelled'", 
        $family_root_id, $family_root_id
    ));

    $now = date('Ymd\THis');
    $ics = [];
    $ics[] = "BEGIN:VCALENDAR";
    $ics[] = "VERSION:2.0";
    $ics[] = "PRODID:-//HOAPLUGIN//Calendar Engine//EN";
    $ics[] = "CALSCALE:GREGORIAN";
    $ics[] = "X-WR-CALNAME:" . escape_ics_text($master_event->title); // Names the subscription

    // Helper function to generate a VEVENT block
    $build_vevent = function($ev) use ($now, $exceptions) {
        $dtstart = date('Ymd\THis', strtotime($ev->start_datetime));
        $dtend   = date('Ymd\THis', strtotime($ev->end_datetime));
        
        $block = [];
        $block[] = "BEGIN:VEVENT";
        // Link all family members to the same core UID so the calendar knows they are related
        $block[] = "UID:hoaplugin-family-{$ev->id}@{$site_domain}";
        $block[] = "DTSTAMP:{$now}";
        $block[] = "DTSTART:{$dtstart}";
        $block[] = "DTEND:{$dtend}";
        $block[] = "SUMMARY:" . escape_ics_text($ev->title);
        
        if (!empty($ev->location)) {
            $block[] = "LOCATION:" . escape_ics_text($ev->location);
        }
        if (!empty($ev->description)) {
            $block[] = "DESCRIPTION:" . escape_ics_text(wp_strip_all_tags($ev->description));
        }

        // Add the RRule
        if (!empty($ev->rrule)) {
            $clean_rule = trim(str_ireplace('RRULE:', '', $ev->rrule));
            $block[] = "RRULE:{$clean_rule}";
        }

        // Add EXDATEs (Holes) that belong to this specific era
        foreach ($exceptions as $ex) {
            $ex_target = !empty($ex->parent_id) ? $ex->parent_id : $ex->id;
            if ($ex_target == $ev->id) {
                // EXDATE format: YYYYMMDDTHHMMSS
                $ex_date = date('Ymd\THis', strtotime($ex->start_datetime));
                $block[] = "EXDATE:{$ex_date}";
            }
        }

        $block[] = "END:VEVENT";
        return $block;
    };

    // --- RENDER THE FAMILY TREE ---
    
    // Output the Master
    $ics = array_merge($ics, $build_vevent($master_event));

    // Output all Child Pivots
    if (!empty($family_members)) {
        foreach ($family_members as $child) {
            $ics = array_merge($ics, $build_vevent($child));
        }
    }

    $ics[] = "END:VCALENDAR";

    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="hoaplugin-events.ics"');
    
    // iCalendar spec requires CRLF line endings
    echo implode("\r\n", $ics);
    exit;
}

function escape_ics_text($string) {
    $string = str_replace(array('\\', ',', ';', "\n"), array('\\\\', '\,', '\;', '\\n'), $string);
    return $string;
}


// --- THE BACKGROUND SVG GENERATOR (TEMPLATE & FALLBACK) ---
add_action('wp_ajax_hoa_generate_fallback_bg', 'hoa_serve_svg_background');
add_action('wp_ajax_nopriv_hoa_generate_fallback_bg', 'hoa_serve_svg_background');


function hoa_serve_svg_background() {
    $year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
    $month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
    $is_template = isset($_GET['template_only']) && $_GET['template_only'] === '1';

    $month_name = date('F', mktime(0, 0, 0, $month, 10));
    $start_day = get_option('hoa_start_day', '0'); // 0=Sun, 1=Mon

    // Title case
    $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    if ($start_day === '1') {
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    }

    // Exact 1700x1100 Math
    $width = 1700;
    $height = 1100;
    $header_h = 154; // 14% of 1100
    $grid_h = $height - $header_h; // 946

    $col_w = $width / 7; // 242.857px
    $row_h = $grid_h / 5; // 189.2px

    header('Content-Type: image/svg+xml; charset=utf-8');

    if ($is_template) {
        header('Content-Disposition: attachment; filename="canva-grid-seed-1700x1100.svg"');
    }

    echo '<?xml version="1.0" encoding="UTF-8" standalone="no"?>';
    ?>
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 <?php echo $width; ?> <?php echo $height; ?>" width="5100" height="3300">
        <defs>
            <style>
                @import url('https://fonts.googleapis.com/css2?family=Calistoga&amp;display=swap');

                .bg { fill: #ffffff; }
                .header { fill: <?php echo $is_template ? '#ffffff' : '#ffffff'; ?>; }
                .grid-line { stroke: <?php echo $is_template ? '#444444' : '#444444'; ?>; stroke-width: 2; }

                /* heavy 3px perimeter */
                .perimeter { stroke: <?php echo $is_template ? '#000000' : '#000000'; ?>; stroke-width: 3; fill: none; }
                .title-text {
                    font-family: 'Calistoga', serif;
                    font-size: 92px;
                    font-weight: bold;
                    fill: <?php echo $is_template ? '#333333' : '#333333'; ?>;
                    stroke: <?php echo $is_template ? '#333333' : '#333333'; ?>;
                    stroke-width: 3px;
                }
                .day-text {
                    font-family: 'Calistoga', serif;
                    font-size: 43px;
                    font-weight: bold;
                    fill: <?php echo $is_template ? '#333333' : '#333333'; ?>;
                    stroke: <?php echo $is_template ? '#333333' : '#333333'; ?>;
                    stroke-width: 1.5px;
                    letter-spacing: 2px;
                }
            </style>
        </defs>

        <rect class="bg" x="0" y="0" width="<?php echo $width; ?>" height="<?php echo $height; ?>" />

        <rect class="header" x="0" y="0" width="<?php echo $width; ?>" height="<?php echo $header_h; ?>" />

        <?php if (!$is_template): ?>
            <text x="<?php echo $width / 2; ?>" y="86" class="title-text" text-anchor="middle"><?php echo esc_html("$month_name $year"); ?></text>
        <?php else: ?>
            <text x="<?php echo $width / 2; ?>" y="86" class="title-text" text-anchor="middle"><?php echo esc_html("$month_name $year"); ?></text>
        <?php endif; ?>

        <?php for ($i = 0; $i < 7; $i++):
            $x_center = ($i * $col_w) + ($col_w / 2);
            $y_text = $header_h - 15; // Just above the grid lines
        ?>
            <text x="<?php echo $x_center; ?>" y="<?php echo $y_text; ?>" class="day-text" text-anchor="middle"><?php echo $days[$i]; ?></text>
        <?php endfor; ?>

        <g class="grid-line">
            <line x1="0" y1="<?php echo $header_h; ?>" x2="<?php echo $width; ?>" y2="<?php echo $header_h; ?>" stroke="<?php echo $is_template ? '#222222' : '#222222'; ?>" stroke-width="3" />

            <?php for ($i = 1; $i < 7; $i++):
                $x = $i * $col_w;
            ?>
                <line x1="<?php echo $x; ?>" y1="<?php echo $header_h; ?>" x2="<?php echo $x; ?>" y2="<?php echo $height; ?>" />
            <?php endfor; ?>

            <?php for ($i = 1; $i < 5; $i++):
                $y = $header_h + ($i * $row_h);
            ?>
                <line x1="0" y1="<?php echo $y; ?>" x2="<?php echo $width; ?>" y2="<?php echo $y; ?>" />
            <?php endfor; ?>
        </g>

        <rect x="1.5" y="1.5" width="<?php echo $width - 3; ?>" height="<?php echo $height - 3; ?>" class="perimeter" />
    </svg>
    <?php
    exit;
}


/**
 * Add "User Manual" and "Upgrade to Pro" links to the plugin meta row.
 */
add_filter( 'plugin_row_meta', 'hoa_calendar_add_custom_meta_links', 10, 2 );

function hoa_calendar_add_custom_meta_links( $plugin_meta, $plugin_file ) {
    // Ensure this matches your exact folder and main file name
    if ( 'hoaplugin-calendar/hoaplugin-calendar.php' === $plugin_file ) {

        // 1. The User Manual Link
        $docs_url = 'https://hoaplugin.com/documentation/';
        $docs_link = '<a href="' . esc_url( $docs_url ) . '" target="_blank" style="font-weight: bold; color: #2271b1;">📖 User Manual</a>';
        $plugin_meta[] = $docs_link;

        // 2. The Pro Upsell Link
        // Check if the Pro version is physically installed on the server
        $pro_plugin_path = WP_PLUGIN_DIR . '/hoaplugin-calendar-pro/hoaplugin-calendar-pro.php';
        if ( ! file_exists( $pro_plugin_path ) ) {
           $pro_url = 'https://hoaplugin.com/';
           $pro_link = '<a href="' . esc_url( $pro_url ) . '" target="_blank" style="font-weight: bold; color: #d63638;">⭐ Upgrade to Pro</a>';
           $plugin_meta[] = $pro_link;
        }
    }

    return $plugin_meta;
}



