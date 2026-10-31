<?php

if ( ! defined( 'ABSPATH' ) ) exit;

add_action('admin_menu', function() {
    add_menu_page(
        'HOAPLUGIN Calendar',
        'HOAPLUGIN Calendar',
        'manage_options',
        'hoa-cal-settings',
        'hoa_render_settings_tabs',
        'dashicons-calendar-alt'
    );

});

function hoa_render_settings_tabs() {
    $active_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'settings';
    ?>
    <div class="wrap">
        <h1>HOAPLUGIN Calendar Configuration</h1>
        <h2 class="nav-tab-wrapper">
            <a href="?page=hoa-cal-settings&tab=settings" class="nav-tab <?php echo $active_tab == 'settings' ? 'nav-tab-active' : ''; ?>">Settings</a>
            <a href="?page=hoa-cal-settings&tab=backgrounds" class="nav-tab <?php echo $active_tab == 'backgrounds' ? 'nav-tab-active' : ''; ?>">Monthly Backgrounds</a>
            <a href="?page=hoa-cal-settings&tab=locations" class="nav-tab <?php echo $active_tab == 'locations' ? 'nav-tab-active' : ''; ?>">Locations</a>
            <a href="?page=hoa-cal-settings&tab=categories" class="nav-tab <?php echo $active_tab == 'categories' ? 'nav-tab-active' : ''; ?>">Categories</a>
            <a href="?page=hoa-cal-settings&tab=audit" class="nav-tab <?php echo $active_tab == 'audit' ? 'nav-tab-active' : ''; ?>">Events</a>
            <?php 
            // Allow external plugins (like upsell, Pro, and test) to inject their own tabs here
            do_action('hoa_calendar_extra_tabs', $active_tab); 
            ?>
        </h2>

        <div style="margin-top: 20px;">
            <?php
            switch($active_tab) {
                case 'settings':   hoa_render_settings_manager(); break;
                case 'locations':  hoa_render_location_manager(); break;
                case 'backgrounds':hoa_render_bg_manager(); break;
                case 'categories': hoa_render_category_manager(); break;
                case 'audit':      hoa_render_event_audit(); break;
                default:
                    // If it's not a core tab, fire a hook so external plugins can render content
                    do_action('hoa_calendar_extra_tab_content', $active_tab);
                    break;
            }
            ?>
        </div>
    </div>
    <?php
}
add_action('admin_init', function() {
    register_setting('hoa_cal_settings_group', 'hoa_cal_past_months');
    register_setting('hoa_cal_settings_group', 'hoa_cal_future_months');
    register_setting('hoa_cal_settings_group', 'hoa_time_position');
    register_setting('hoa_cal_settings_group', 'hoa_time_format');
    register_setting('hoa_cal_settings_group', 'hoa_start_day');
    register_setting('hoa_cal_settings_group', 'hoaplugin_nuke_on_delete' );
});

// Ensure scripts and modal styles are loaded for the settings page
add_action('admin_enqueue_scripts', function($hook) {
    if ($hook !== 'toplevel_page_hoa-cal-settings') return;

    // Load the main plugin enqueuer
    if (function_exists('hoa_enqueue_calendar_scripts')) {
        hoa_enqueue_calendar_scripts();
    }

    // Force Admin-specific overrides for modals and scrolling
    wp_add_inline_style('hoa-cal-style', "
        .hoa-modal { z-index: 99999 !important; display: none; position: fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); }
        .hoa-modal.is-visible { display: flex !important; align-items: center; justify-content: center; }
        body.hoa-admin-scroll-fix { overflow: auto !important; height: auto !important; }
        #dz-preview svg {
            max-height: 100px; /* Large but contained */
            width: auto;
            display: block;
            margin: 10px auto;
            transition: fill 0.2s ease;
        }
    ");
});

// Inject variables into the <head> before external JS files load
add_action('admin_head', function() {
    $screen = get_current_screen();
    if ( ! $screen || $screen->id !== 'toplevel_page_hoa-cal-settings' ) return;

    $upload = wp_upload_dir();
    $json_url = $upload['baseurl'] . '/hoaplugin-calendar/calendar.json';
    ?>
    <script>
        window.config = window.config || {};
        window.config.isAdmin = true;
        window.config.isDashboard = true;
        window.config.userEmail = '<?php echo esc_js(wp_get_current_user()->user_email); ?>';
        window.config.jsonUrl = '<?php echo esc_js($json_url); ?>';
    </script>
    <?php
}, 1);

function hoa_render_settings_manager() {
    $past_val    = get_option('hoa_cal_past_months', 1);
    $future_val  = get_option('hoa_cal_future_months', 12);
    $time_pos    = get_option('hoa_time_position', 'prepend');
    $time_format = get_option('hoa_time_format', '12hr');
    $start_day   = get_option('hoa_start_day', '0');
    ?>
    <div class="wrap">
        <form method="post" action="options.php">
            <?php
                settings_fields('hoa_cal_settings_group');
                do_settings_sections('hoa_cal_settings_group');
            ?>
            <div style="background:#fff; padding:20px; border:1px solid #ccc; margin-top:20px; border-radius:4px;">
                <h3 style="margin-top:0;">Baking & Data Configuration</h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">Past Months:</th>
                        <td><input type="number" name="hoa_cal_past_months" value="<?php echo esc_attr($past_val); ?>" style="width:70px;"></td>
                    </tr>
                    <tr>
                        <th scope="row">Future Months:</th>
                        <td><input type="number" name="hoa_cal_future_months" value="<?php echo esc_attr($future_val); ?>" style="width:70px;"></td>
                    </tr>
                </table>
            </div>
            <div style="background:#fff; padding:20px; border:1px solid #ccc; margin-top:20px; border-radius:4px;">
                <h3>Display Preferences</h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">Calendar Start Day:</th>
                        <td>
                            <select name="hoa_start_day">
                                <option value="0" <?php selected($start_day, '0'); ?>>Sunday</option>
                                <option value="1" <?php selected($start_day, '1'); ?>>Monday</option>
                            </select>
                            <p class="description">The day the weekly grid begins on.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Time Format:</th>
                        <td>
                            <select name="hoa_time_format">
                                <option value="12hr" <?php selected($time_format, '12hr'); ?>>12-Hour (e.g. 1:00 PM)</option>
                                <option value="24hr" <?php selected($time_format, '24hr'); ?>>24-Hour (e.g. 13:00)</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Time Placement:</th>
                        <td>
                            <select name="hoa_time_position">
                                <option value="prepend" <?php selected($time_pos, 'prepend'); ?>>Time First</option>
                                <option value="append" <?php selected($time_pos, 'append'); ?>>Title First</option>
                                <option value="hidden" <?php selected($time_pos, 'hidden'); ?>>Hide Time</option>
                            </select>
                        </td>
                    </tr>
                </table>
            </div>
            <div style="background:#fff; padding:20px; border:1px solid #ccc; margin-top:20px; border-radius:4px;">
                <h3>Display Preferences</h3>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Uninstall Behavior:</th>
                        <td>
                            <label>
                                <input type="checkbox" name="hoaplugin_nuke_on_delete" value="1" <?php checked( 1, get_option( 'hoaplugin_nuke_on_delete' ), true ); ?> />
                                <strong style="color: #d63638;">Erase all calendar data upon plugin deletion</strong>
                            </label>
                            <p class="description">
                                Leave this unchecked to safely keep your events in the database when updating or temporarily deactivating the plugin.<br>
                                <strong>Warning:</strong> If checked, all events, schedules, backgrounds and settings will be permanently destroyed when this plugin is deleted from the WordPress plugins page.
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            <?php submit_button('Save All Settings & Re-Bake'); ?>
        </form>
    </div>
    <?php
}


function hoa_render_bg_manager() {
    if (isset($_POST['hoa_upload_zip'])) {
        hoa_handle_zip_upload();
    }

    $upload_dir = wp_upload_dir();
    $bg_url_base = $upload_dir['baseurl'] . '/hoaplugin-calendar/backgrounds/';
    $bg_path_base = $upload_dir['basedir'] . '/hoaplugin-calendar/backgrounds/';
    $current_file = "cal-" . date('Y-m') . ".png";

    ?>
    <h3>Monthly Backgrounds (ZIP Upload)</h3>
    <div style="margin-bottom: 20px;">
        <p>Upload a ZIP file containing images named <strong>cal-YYYY-MM.png</strong> (e.g., <em>cal-2026-03.png</em>).</p>
    </div>
    <form method="post" enctype="multipart/form-data" style="background:#fff; padding:20px; border:1px solid #ccc; display:inline-block;">
        <input type="file" name="cal_zip" accept=".zip" required>
        <input type="submit" name="hoa_upload_zip" class="button-primary" value="Upload and Process ZIP">
    </form>

    <hr>
    <h4>Current Month Preview</h4>
    <?php
    $current_file = "cal-" . date('Y-m') . ".png";
    if (file_exists($bg_path_base . $current_file)) {
        echo '<img src="' . $bg_url_base . $current_file . '" style="max-width:400px; border:2px solid #333;">';
    } else {
        echo '<p style="color:red;">No background found for ' . date('F Y') . ' (Expected: ' . $current_file . ')</p>';
    }
    ?>
    <a href="<?php echo admin_url('admin-ajax.php?action=hoa_generate_fallback_bg&template_only=1'); ?>" class="button" target="_blank" download>
            <span class="dashicons dashicons-download" style="margin-top:4px;"></span> Download 11x17 Canva Grid Seed (SVG)
    </a>
    <?php
}


function hoa_render_location_manager() {
    global $wpdb;
    $table = $wpdb->prefix . 'hoaplugin_locations';
    $edit_loc = null;

    // 1. Handle "Edit" Mode Detection
    if (isset($_GET['edit_loc'])) {
        $edit_id = intval($_GET['edit_loc']);
        $edit_loc = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $edit_id));
    }

    $repo = hoa_get_repo();

    // 2. Handle Form Submission (Add or Update)
    if (isset($_POST['save_location'])) {
        check_admin_referer('hoa_location_action', 'hoa_loc_nonce');
        $loc_name = sanitize_text_field($_POST['loc_name']);

        if (!empty($loc_name)) {
            $data = ['name' => $loc_name];
            if (!empty($_POST['loc_id'])) {
                $data['id'] = $_POST['loc_id'];
                $repo->save_location($data);
                echo '<div class="updated"><p>Location updated.</p></div>';
            } else {
                $repo->save_location($data);
                echo '<div class="updated"><p>Location added.</p></div>';
            }

            $compiler = hoa_get_compiler();
            $compiler->bake();
            $edit_loc = null; // Clear edit mode
        }
    }

    // 3. Handle Deletion
    if (isset($_GET['delete_loc']) && isset($_GET['_wpnonce'])) {
        if (wp_verify_nonce($_GET['_wpnonce'], 'delete_loc_' . $_GET['delete_loc'])) {
            $repo->delete_location($_GET['delete_loc']);

            $compiler = hoa_get_compiler();
            $compiler->bake();
            echo '<div class="updated"><p>Location deleted.</p></div>';
        }
    }

    $results = $wpdb->get_results("SELECT * FROM $table ORDER BY name ASC");
    ?>
    <div class="card" style="max-width: 800px;">
        <h3>Room & Location Management</h3>
        <p class="description">Define the specific areas within the community (e.g., Lodge, Ballroom, Pool).</p>

        <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
            <thead>
                <tr>
                    <th>Location Name</th>
                    <th style="width: 150px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($results)) : ?>
                    <tr><td colspan="2">No locations defined.</td></tr>
                <?php else : ?>
                    <?php foreach ($results as $loc):
                        $delete_url = wp_nonce_url("?page=hoa-cal-settings&tab=locations&delete_loc=" . $loc->id, 'delete_loc_' . $loc->id);
                        $edit_url = "?page=hoa-cal-settings&tab=locations&edit_loc=" . $loc->id;
                    ?>
                        <tr>
                            <td><strong><?php echo esc_html($loc->name); ?></strong></td>
                            <td>
                                <a href="<?php echo $edit_url; ?>" class="button button-small">Edit</a>
                                <a href="<?php echo $delete_url; ?>"
                                   class="button button-small"
                                   style="color:#a00;"
                                   onclick="return confirm('Delete this location? Events using it will show TBD.');">Del</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div style="margin-top:30px; background:#f9f9f9; padding:20px; border:1px solid #ccc; border-radius: 4px;">
            <h4><?php echo $edit_loc ? 'Edit Location: ' . esc_html($edit_loc->name) : 'Add New Location'; ?></h4>
            <form method="post" action="?page=hoa-cal-settings&tab=locations">
                <?php wp_nonce_field('hoa_location_action', 'hoa_loc_nonce'); ?>
                <input type="hidden" name="loc_id" value="<?php echo $edit_loc ? $edit_loc->id : ''; ?>">

                <div style="margin-bottom: 15px;">
                    <label style="display:block; font-weight:bold; margin-bottom:5px;">Location Name:</label>
                    <input type="text" name="loc_name" value="<?php echo $edit_loc ? esc_attr($edit_loc->name) : ''; ?>"
                           placeholder="e.g. Lodge" class="regular-text" required>
                </div>

                <input type="submit" name="save_location" class="button-primary" value="<?php echo $edit_loc ? 'Update Location' : 'Add Location'; ?>">
                <?php if ($edit_loc) : ?>
                    <a href="?page=hoa-cal-settings&tab=locations" class="button">Cancel</a>
                <?php endif; ?>
            </form>
        </div>
    </div>
    <?php
}


function hoa_render_category_manager() {
    global $wpdb;
    $table = $wpdb->prefix . 'hoaplugin_categories';
    $edit_cat = null;

    // 1. Handle "Edit" Mode Detection
    if (isset($_GET['edit_cat'])) {
        $edit_id = intval($_GET['edit_cat']);
        $edit_cat = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $edit_id));
    }

    $repo = hoa_get_repo();

    // 2. Handle Form Submission (Add or Update)
    if (isset($_POST['save_cat'])) {
        check_admin_referer('hoa_category_action', 'hoa_cat_nonce');

        $data = [
            'name'            => sanitize_text_field($_POST['cat_name']),
            'color_hex'       => sanitize_hex_color($_POST['cat_color']),
            'svg_path'        => hoa_sanitize_svg($_POST['svg_path']),
            'delegate_emails' => sanitize_textarea_field($_POST['delegate_emails'] ?? '')
        ];

        if (!empty($_POST['cat_id'])) {
            $data['id'] = $_POST['cat_id'];
            $repo->save_category($data);
            echo '<div class="updated"><p>Category updated.</p></div>';
        } else {
            $repo->save_category($data);
            echo '<div class="updated"><p>Category added.</p></div>';
        }

        $compiler = hoa_get_compiler();
        $compiler->bake();
        $edit_cat = null; // Clear edit mode after save
    }

    // 3. Handle Deletion
    if (isset($_GET['delete_cat']) && isset($_GET['_wpnonce'])) {
        if (wp_verify_nonce($_GET['_wpnonce'], 'delete_cat_' . $_GET['delete_cat'])) {
            $repo->delete_category($_GET['delete_cat']);

            $compiler = hoa_get_compiler();
            $compiler->bake();
            echo '<div class="updated"><p>Category deleted.</p></div>';
        }
    }

    $results = $wpdb->get_results("SELECT * FROM $table ORDER BY name ASC");
    ?>
    <div class="card" style="max-width: 850px;">
        <h3>Event Categories</h3>
        <p class="description">Categories with an SVG path will render as corner icons on the grid.</p>

        <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
            <thead>
                <tr>
                    <th style="width: 25%;">Name</th>
                    <th style="width: 20%;">Color</th>
                    <th style="width: 35%;">Icon Preview</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($results)) : ?>
                    <tr><td colspan="4">No categories defined.</td></tr>
                <?php else : ?>
                    <?php foreach ($results as $cat):
                        $delete_url = wp_nonce_url("?page=hoa-cal-settings&tab=categories&delete_cat=" . $cat->id, 'delete_cat_' . $cat->id);
                        $edit_url = "?page=hoa-cal-settings&tab=categories&edit_cat=" . $cat->id;
                    ?>
                        <tr>
                            <td><strong><?php echo esc_html($cat->name); ?></strong></td>
                            <td>
                                <span style="background:<?php echo $cat->color_hex; ?>; padding: 4px 10px; border-radius: 4px; color: #fff; text-shadow: 1px 1px 1px #000; font-size: 11px;">
                                    <?php echo esc_html($cat->color_hex); ?>
                                </span>
                            </td>
                            
                            <td>
                                <?php if ($cat->svg_path): ?>
                                    <div style="display:flex; align-items:center; gap:10px;">
                                        <div style="height:24px; width:auto;">
                                            <?php
                                                $preview_style = sprintf(
                                                    'style="height:24px; width:auto; fill:%s; display:block;"',
                                                    esc_attr($cat->color_hex)
                                                );
                                                // The paths inside now HAVE to take this fill because they have none of their own
                                                echo str_replace('<svg', '<svg ' . $preview_style, $cat->svg_path);
                                            ?>
                                        </div>
                                        <span class="description" style="font-size: 10px; color: #888;">Stored via SVG Library</span>
                                    </div>
                                <?php else: ?>
                                    <span class="description" style="font-size: 11px;">Standard Bar</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?php echo $edit_url; ?>" class="button button-small">Edit</a>
                                <a href="<?php echo $delete_url; ?>" class="button button-small" style="color:#a00;" onclick="return confirm('Delete this category?');">Del</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div style="margin-top:30px; background:#f9f9f9; padding:20px; border:1px solid #ccc; border-radius: 4px;">
            <h4><?php echo $edit_cat ? 'Edit Category: ' . esc_html($edit_cat->name) : 'Add New Category'; ?></h4>
            <form method="post" action="?page=hoa-cal-settings&tab=categories">
                <?php wp_nonce_field('hoa_category_action', 'hoa_cat_nonce'); ?>
                <input type="hidden" name="cat_id" value="<?php echo $edit_cat ? $edit_cat->id : ''; ?>">

                <div style="margin-bottom: 15px;">
                    <label style="display:block; font-weight:bold;">Category Name:</label>
                    <input type="text" name="cat_name" value="<?php echo $edit_cat ? esc_attr($edit_cat->name) : ''; ?>" class="regular-text" required>
                </div>

                <div style="margin-bottom: 15px;">
                    <label style="display:block; font-weight:bold;">Display Color:</label>
                    <input type="color" name="cat_color" value="<?php echo $edit_cat ? esc_attr($edit_cat->color_hex) : '#3498db'; ?>" style="height:35px; width:60px; cursor:pointer;">
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display:block; font-weight:bold;">Category Delegates (Emails):</label>
                    <textarea name="delegate_emails" rows="2" class="large-text" placeholder="social_committe@hoaplugin.com, resident@email.com"><?php echo $edit_cat ? esc_textarea($edit_cat->delegate_emails) : ''; ?></textarea>
                    <p class="description">Comma-separated list of emails. These residents can create and manage events within this category.</p>
                </div>

                <div style="margin-bottom: 15px;">
                    <label style="display:block; font-weight:bold; margin-bottom:5px;">Category Icon (SVG):</label>
    
                    <div id="hoa-dropzone" style="border: 2px dashed #ccc; padding: 20px; text-align: center; background: #fff; cursor: pointer; border-radius: 4px; transition: border 0.2s;">
                        <p id="dz-instruction" style="margin:0;">Drag an SVG file here, or <strong>click to browse</strong></p>
                        <div id="dz-preview" style="margin-top: 10px; display: <?php echo $edit_cat && $edit_cat->svg_path ? 'block' : 'none'; ?>;">
                             <?php
                             if ($edit_cat && $edit_cat->svg_path) {
                                 // Inject the color immediately so it's not black while waiting for JS
                                 echo str_replace('<svg', '<svg style="fill:' . esc_attr($edit_cat->color_hex) . '; height:60px;"', $edit_cat->svg_path);
                             }
                             ?>
                        </div>
                    </div>

                    <input type="hidden" name="svg_path" id="svg_path_hidden" value="<?php echo $edit_cat ? esc_attr($edit_cat->svg_path) : ''; ?>">
                    <input type="file" id="svg_file_input" accept=".svg" style="display:none;">
    
                    <p class="description">
                        Upload an SVG icon. It will scale to 24px height on the calendar. 
                        <br><strong>Tip:</strong> If the icon looks "broken" or is missing parts, run it through 
                        <a href="https://jakearchibald.github.io/svgomg/" target="_blank" rel="noopener">SVGOMG</a> 
                        (using default settings) then try again.
                    </p>
                </div>

                <input type="submit" name="save_cat" class="button-primary" value="<?php echo $edit_cat ? 'Update Category' : 'Add Category'; ?>">
                <?php if ($edit_cat) : ?>
                    <a href="?page=hoa-cal-settings&tab=categories" class="button">Cancel</a>
                <?php endif; ?>
            </form>
        </div>
    </div>
    <script>
(function() {
    const dz = document.getElementById('hoa-dropzone');
    const fileInput = document.getElementById('svg_file_input');
    const hiddenInput = document.getElementById('svg_path_hidden');
    const preview = document.getElementById('dz-preview');
    const colorPicker = document.querySelector('input[name="cat_color"]');

    // 1. Unified Function to clean and style the SVG
    function processAndDisplaySVG(rawSvg) {
        // 1. Setup a temporary DOM parser to "sanitize" like PHP does
        const parser = new DOMParser();
        const doc = parser.parseFromString(rawSvg, 'image/svg+xml');
        const svgEl = doc.querySelector('svg');

        if (!svgEl) {
            alert("Invalid SVG structure.");
            return;
        }


        // Remove fixed dimensions
        svgEl.removeAttribute('width');
        svgEl.removeAttribute('height');

        // STRIP ALL FILLS: This makes the paths "naked"
        svgEl.querySelectorAll('*').forEach(el => {
            el.removeAttribute('fill');
            el.removeAttribute('style'); // Strip Canva's inline styles too
        });

        // Remove non-standard tags/attributes that PHP might strip
        const allowedTags = ['svg', 'path', 'circle', 'rect', 'g', 'polygon'];
        const allElements = svgEl.querySelectorAll('*');
    
        allElements.forEach(el => {
            if (!allowedTags.includes(el.tagName.toLowerCase())) {
                el.remove(); // Remove tags like <metadata>, <defs>, etc.
            }
        });

        // 4. Get the cleaned string
        const cleanedSvg = svgEl.outerHTML;

        // 5. Update Hidden Input and Preview
        hiddenInput.value = cleanedSvg;
        preview.innerHTML = cleanedSvg;
        preview.style.display = 'block';
    
        applyColor();
    }

    function applyColor() {
        const svg = preview.querySelector('svg');
        const selectedColor = colorPicker.value;

        if (svg) {
            // This is the magic line for 'currentColor'
            svg.style.color = selectedColor;

            // This is the backup for paths that might have missed the memo
            svg.style.fill = selectedColor;

            // Force all internal paths to inherit the color from the top-level SVG
            svg.querySelectorAll('path, circle, rect, g').forEach(el => {
                el.style.fill = 'currentColor';
            });
        }
    }

    // 2. Event Listeners
    dz.addEventListener('click', () => fileInput.click());
    fileInput.addEventListener('change', e => handleFile(e.target.files[0]));
    colorPicker.addEventListener('input', applyColor);

    function handleFile(file) {
        if (!file || file.type !== 'image/svg+xml') return;
        const reader = new FileReader();
        reader.onload = (e) => processAndDisplaySVG(e.target.result);
        reader.readAsText(file);
    }

    // 3. THE UNIFIER: If editing, process the existing SVG immediately
    if (hiddenInput.value.trim() !== "") {
        processAndDisplaySVG(hiddenInput.value);
    }

    // Drag and drop logic...
    dz.addEventListener('dragover', e => { e.preventDefault(); dz.style.borderColor = '#2271b1'; });
    dz.addEventListener('dragleave', () => dz.style.borderColor = '#ccc');
    dz.addEventListener('drop', e => {
        e.preventDefault();
        dz.style.borderColor = '#ccc';
        handleFile(e.dataTransfer.files[0]);
    });
})();

    </script>

    <?php
}

function hoa_sanitize_svg($svg_str) {
    return wp_kses($svg_str, [
        'svg'  => [
            'xmlns'   => true,
            'viewbox' => true,
            'fill'    => true,
            'style'   => true,
        ],
        'g'    => ['fill' => true, 'transform' => true],
        'path' => [
            'd'    => true,
            'fill' => true,
            'transform' => true,
        ],
        'circle' => ['cx' => true, 'cy' => true, 'r' => true],
        'rect'   => ['x' => true, 'y' => true, 'width' => true, 'height' => true],
    ]);
}

function hoa_render_event_audit() {

    $repo = hoa_get_repo();
    $results = $repo->get_audit_log_events(200);
    $today = date('Y-m-d');
    ?>
    <style>
        /* 1. Make all individual rows less high by overriding WP defaults */
        .hoa-audit-table td {
            padding: 5px 10px !important;
            vertical-align: middle !important;
            line-height: 1.3 !important;
        }

        /* 2. Half-height styling for Holes and Pivots */
        tr.hoa-audit-child td {
            padding-top: 0px !important;
            padding-bottom: 0px !important;
            line-height: 1 !important;  /* Squashes WP default text spacing */
            height: 1px !important;     /* Hack: Forces cell to shrink-wrap text */
            border-top: none !important;
        }

        /* slightly shrink the DB ID size on children so it doesn't crowd */
        tr.hoa-audit-child td code {
            margin-top: 0px !important;
            margin-bottom: 0px !important;
            line-height: 1 !important;
        }
        /* 3. Push master/single row content to the floor to meet the children */
        tr.hoa-audit-master td {
            padding-bottom: 2px !important;    /* Strip out the bottom breathing room */
            vertical-align: bottom !important; /* Drop the text to the bottom edge */
        }
    </style>
    <div class="card" style="max-width: 100%; margin-top: 0; position: relative;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 15px;">
            <h3 style="margin:0;">Events</h3>
            <span title="Add New Event" style="color:#0056b3; cursor:pointer; font-size:2.2rem; font-weight:900; line-height:1; padding: 0 10px;" onclick="window.hoaplugin_openAddModal(null)">+</span>
        </div>
        <p class="description" style="margin-top: 0; margin-bottom: 15px;">A database view of the event declarations ordered by root date.</p>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 80px;">DB ID</th>
                    <th>Event Title</th>
                    <th style="width: 130px;">Start Date/Time</th>
                    <th style="width: 90px;">Status</th>
                    <th style="width: 300px;">Type / Recurrence</th>
                    <th style="width: 100px; text-align:center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                foreach ($results as $e) : 
                    $is_child = !empty($e->parent_id);
                    $is_hole = ($e->status === 'cancelled');
                    $row_class = $is_child ? 'hoa-audit-child' : 'hoa-audit-master';
                    $row_style = $is_child ? 'background: #fcfcfc;' : 'background: #f0f6fb; font-weight: 600;';
                    if ($is_hole) $row_style .= ' opacity: 0.6;';
                    $target_date = $repo->get_first_instance_date($e->start_datetime, $e->rrule);  // first instance
                    $target_time = date('H:i', strtotime($e->start_datetime));
                    $display_time = date('g:i A', strtotime($e->start_datetime));
                    
                    // --- THE ROUTING ENGINE ---
                    // Default to assuming this row is the Master
                    $js_master_id = $e->id;
                    $js_pivot_id  = $e->id;
                    $js_move_id   = 'null';

                    if ($is_child) {
                        $js_master_id = $e->parent_id; // Children always point up to the Master

                        if ($is_hole) {
                            $type_label = 'Exception (Hole)';
                        } elseif (!empty($e->rrule)) {
                            $type_label = 'Pivot';
                            $js_pivot_id = $e->id; // This row IS the Pivot
                        } else {
                            $type_label = 'Move';
                            // If the DB stored the pivot_id for this move, use it. Otherwise, fallback.
                            $js_pivot_id = !empty($e->pivot_id) ? $e->pivot_id : 'null'; 
                            $js_move_id = $e->id;  // This row IS the Move
                        }
                    } else {
                        $type_label = !empty($e->rrule) ? '<strong>Master Series</strong>' : 'Single';
                    }
                ?>
                    <tr class="<?php echo $row_class; ?>" style="<?php echo $row_style; ?>">
                        <td><code>#<?php echo $e->id; ?></code></td>
                        <td><span style="<?php echo $is_child ? 'margin-left: 20px;' : ''; ?>"><?php echo esc_html($e->title); ?></span></td>
                        <td><?php echo $target_date; ?> <span style="font-size: 10px; color: #666; font-weight: normal;"><?php echo $display_time; ?></span>
                        </td>
                        <td><span class="status-tag <?php echo $e->status; ?>" style="text-transform: uppercase; font-size: 8px; font-weight: bold; border: 1px solid; padding: 2px 4px; border-radius: 3px;"><?php echo esc_html($e->status); ?></span></td>
                        <td>
                            <div style="font-size: 11px;"><?php echo $type_label; ?></div>
                            <?php if (!empty($e->rrule)) : ?>
                                <div style="font-size: 10px; color: #2271b1; margin-top: 0px; margin-bottom: 0px; word-break: break-all; line-height: 1;">
                                    <code><?php echo esc_html(str_replace('RRULE:', '', (string)$e->rrule)); ?></code>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center; font-size: 1.2rem;">
                            <?php if (!$is_hole): ?>
                                <span title="Edit" 
                                     style="color:#f57c00; cursor:pointer; margin-right:12px;" 
                                     onclick="window.hoaplugin_handleEditClick(
                                         <?php echo $e->id; ?>, 
                                         '<?php echo $target_date; ?>', 
                                         '<?php echo $target_time; ?>',
                                         <?php echo $js_pivot_id; ?>, 
                                         <?php echo $js_move_id; ?>,
                                         true
                                     )">✎
                                </span>
                            <?php endif; ?>
                            <span title="Delete" style="color:#d32f2f; cursor:pointer;" onclick="window.hoaplugin_handleAuditDelete(<?php echo $e->id; ?>)">&times;</span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>


    <div id="hoa-edit-modal" class="hoa-modal">
        <div class="modal-content">
            <span class="close-modal" onclick="window.hoaplugin_closeAdminModals()">&times;</span>
            <div id="edit-form-container"></div>
        </div>
    </div>

    <div id="hoa-day-modal" class="hoa-modal">
        <div class="modal-content">
            <span class="close-modal" onclick="window.hoaplugin_closeAdminModals()">&times;</span>
            <div id="hoa-modal-content"></div>
        </div>
    </div>

    <script>
        window.hoaplugin_closeAdminModals = function() {
            document.querySelectorAll('.hoa-modal').forEach(m => m.classList.remove('is-visible'));
            document.body.style.removeProperty('overflow');
            document.documentElement.style.removeProperty('overflow');
            document.body.classList.add('hoa-admin-scroll-fix');
            document.body.classList.remove('modal-open');
        };

        // 2. Ensure Add Modal works even if logic.js hasn't attached listeners yet
        window.hoaplugin_openAddModal = function(dateStr) {
            if (typeof window.openEditModal === 'function') {
                window.openEditModal(dateStr, null, null, null, null);
            } else {
                console.error("Calendar logic not loaded yet.");
            }
        };

        window.hoaplugin_handleAuditDelete = async function(id) {
            if(confirm('Permanently delete record #' + id + '?')) {
                const params = new URLSearchParams({
                    action: 'hoa_save_calendar_event',
                    edit_mode: 'master_delete',
                    event_id: id,
                    nonce: '<?php echo wp_create_nonce('hoa_cal_nonce'); ?>'
                });
                const response = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: params
                });
                const result = await response.json();
                if(result.success) { location.reload(); } else { alert(result.data); }
            }
        };

        // 3. Auto-Refresh the static Audit Log table after a successful modal edit
        const originalFetch = window.fetch;
        window.fetch = async function(...args) {
            // Let the real network request happen normally
            const response = await originalFetch.apply(this, args);

            try {
                // Inspect the outgoing request
                if (args[1] && args[1].body) {
                    const body = args[1].body;
                    let isSaveAction = false;

                    // Check if it's our save event (handles FormData, URLSearchParams, and raw Strings)
                    if (body.get && typeof body.get === 'function') {
                        isSaveAction = body.get('action') === 'hoa_save_calendar_event';
                    } else if (typeof body === 'string') {
                        isSaveAction = body.includes('action=hoa_save_calendar_event');
                    }

                    // If it was a save, check if the server said it was successful
                    if (isSaveAction) {
                        // Clone the response so we don't break the main calendar JS reading it
                        const data = await response.clone().json();
                        if (data.success) {
                            // The database has committed the change. Wait 200ms to let
                            // the modal visually close, then reload the page!
                            setTimeout(() => {
                                window.location.reload();
                            }, 200);
                        }
                    }
                }
            } catch (e) {
                // Fail silently so we never accidentally break the calendar app
            }

            return response;
        };
    </script>
    <?php
}



function hoa_handle_zip_upload() {
    // 1. Security Check
    if ( ! current_user_can( 'manage_options' ) ) return;

    if ( empty( $_FILES['cal_zip']['tmp_name'] ) ) {
        echo '<div class="notice notice-error"><p>Please select a ZIP file to upload.</p></div>';
        return;
    }

    $file = $_FILES['cal_zip'];

    // 2. Validate it is actually a ZIP
    $file_type = wp_check_filetype( $file['name'] );
    if ( $file_type['ext'] !== 'zip' ) {
        echo '<div class="notice notice-error"><p>Invalid file type. Please upload a .zip file.</p></div>';
        return;
    }

    // 3. Initialize WP Filesystem
    require_once ABSPATH . 'wp-admin/includes/file.php';
    WP_Filesystem();
    
    // 4. Set target directory and ensure it exists
    $upload_dir = wp_upload_dir();
    $target_dir = $upload_dir['basedir'] . '/hoaplugin-calendar/backgrounds/';

    if ( ! wp_mkdir_p( $target_dir ) ) {
        echo '<div class="notice notice-error"><p>Failed to create the backgrounds directory in your uploads folder.</p></div>';
        return;
    }

    // 5. Extract the ZIP directly into the target folder
    $unzip_result = unzip_file( $file['tmp_name'], $target_dir );

    if ( is_wp_error( $unzip_result ) ) {
        echo '<div class="notice notice-error"><p>Failed to extract ZIP: ' . esc_html( $unzip_result->get_error_message() ) . '</p></div>';
    } else {
        hoa_purge_optimized_bg_copies( $target_dir );
        echo '<div class="notice notice-success is-dismissible"><p>Background images successfully uploaded and extracted!</p></div>';
    }
}


/**
 * Image optimizer plugins keep WebP/AVIF copies of our backgrounds and serve
 * them to browsers in place of the PNGs. Those copies are not rebuilt when a
 * ZIP overwrites the PNGs, so residents keep seeing the old artwork.
 * Delete them; the optimizer regenerates fresh copies if it is configured to.
 */
function hoa_purge_optimized_bg_copies( $target_dir ) {
    $target_dir = trailingslashit( wp_normalize_path( $target_dir ) );
    $content_dir = trailingslashit( wp_normalize_path( WP_CONTENT_DIR ) );

    // Copies stored next to the originals (LiteSpeed Cache, EWWW, ShortPixel: cal-2026-03.png.webp)
    $dirs = array( $target_dir );

    // Copies stored in a mirrored tree (Converter for Media, WebP Express)
    if ( strpos( $target_dir, $content_dir ) === 0 ) {
        $rel = substr( $target_dir, strlen( $content_dir ) ); // e.g. uploads/hoaplugin-calendar/backgrounds/
        $dirs[] = $content_dir . 'uploads-webpc/' . $rel;
        $dirs[] = $content_dir . 'webp-express/webp-images/' . $rel;
        $dirs[] = $content_dir . 'webp-express/webp-images/doc-root/wp-content/' . $rel;
    }

    // GLOB_BRACE is unavailable on some hosts, so glob each extension separately.
    foreach ( $dirs as $dir ) {
        foreach ( array( '*.webp', '*.avif' ) as $pattern ) {
            foreach ( glob( $dir . $pattern ) ?: array() as $file ) {
                wp_delete_file( $file );
            }
        }
    }
}


