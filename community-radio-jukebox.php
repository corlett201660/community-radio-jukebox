<?php
/**
 * Plugin Name: Community Radio Jukebox
 * Plugin URI:  https://github.com/corlett201660/community-radio-jukebox
 * Description: Interactive Jukebox with Auto DJ Flush Prediction, WooCommerce Artist Tipping, Marquee Patches, DJ Drops, Visual Schedules, Monthly Logging, AI Explicit Profiling, and Gemini 2.5 Pro.
 * Version:     4.60.2
 * Author:      Local Jukebox Architecture
 * License:     GPL v2 or later
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ==========================================
// 1. ASSET MANAGER
// ==========================================
define( 'CRJB_VERSION', '4.60.2' );
define( 'CRJB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

class CRJB_Asset_Manager {
    public function __construct() {
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );
        add_filter( 'script_loader_tag', [ $this, 'add_module_type_attribute' ], 10, 3 );
    }
    
    public function enqueue_admin_assets() { 
        wp_enqueue_media(); 
        wp_enqueue_style('crjb-select2-css', CRJB_PLUGIN_URL . 'assets/css/select2.min.css', [], '4.1.0');
        wp_enqueue_script('crjb-select2-js', CRJB_PLUGIN_URL . 'assets/js/select2.min.js', ['jquery'], '4.1.0', true);
        
        // Centralized admin scripts
        wp_enqueue_script('crjb-admin-js', CRJB_PLUGIN_URL . 'assets/js/jukebox-admin.js', ['jquery', 'crjb-select2-js'], CRJB_VERSION, true);
        wp_localize_script('crjb-admin-js', 'crjbAdminData', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'geminiNonce' => wp_create_nonce('crjb_gemini_scan_action'),
            'folderNonce' => wp_create_nonce('crjb_folder_upload_nonce')
        ]);
    }

    public function enqueue_frontend_assets() {
        global $post;
        
        if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'community_radio_jukebox' ) ) {
            wp_enqueue_style( 'crjb-frontend-app-style', CRJB_PLUGIN_URL . 'assets/css/jukebox-app.css', [], CRJB_VERSION );
            wp_enqueue_style( 'crjb-bootstrap', CRJB_PLUGIN_URL . 'assets/css/bootstrap.min.css', [], '5.3.0' );
            wp_enqueue_script( 'crjb-bootstrap-js', CRJB_PLUGIN_URL . 'assets/js/bootstrap.bundle.min.js', [], '5.3.0', true );
        }
    }

    public function add_module_type_attribute( $tag, $handle, $src ) {
        if ( in_array( $handle, ['crjb-admin-app', 'crjb-frontend-app'], true ) ) {
            return str_replace( '<script ', '<script type="module" ', $tag );
        }
        return $tag;
    }
}
new CRJB_Asset_Manager();

// ==========================================
// 2. CORE SETUP, CPTS, & TAXONOMIES
// ==========================================
add_action( 'init', 'crjb_register_cpts_and_taxonomies' );
function crjb_register_cpts_and_taxonomies() {
    register_post_type( 'crjb_song', [
        'labels' => [ 'name' => 'Jukebox Songs', 'singular_name' => 'Song', 'add_new_item' => 'Add New Song', 'all_items' => 'All Songs' ],
        'public' => true, 'menu_icon' => 'dashicons-format-audio', 'supports' => [ 'title', 'thumbnail' ], 
    ]);
    
    register_post_type( 'crjb_schedule', [
        'labels' => [ 'name' => 'Jukebox Schedules', 'singular_name' => 'Schedule', 'add_new_item' => 'Add New Schedule', 'all_items' => 'All Schedules' ],
        'public' => false, 'show_ui' => true, 'show_in_menu' => 'edit.php?post_type=crjb_song', 'supports' => [ 'title' ],
    ]);
	
	register_taxonomy( 'crjb_artist', 'crjb_song', [ 'labels' => [ 'name' => 'Artists' ], 'hierarchical' => false, 'show_ui' => true, 'show_admin_column' => true ]);
    register_taxonomy( 'crjb_playlist', 'crjb_song', [ 'labels' => [ 'name' => 'Playlists' ], 'hierarchical' => true, 'show_ui' => true, 'show_admin_column' => true ]);
    register_taxonomy( 'crjb_submitter', 'crjb_song', [ 'labels' => [ 'name' => 'Submitters' ], 'hierarchical' => false, 'show_ui' => true ]);
    register_taxonomy( 'crjb_genre', 'crjb_song', [ 'labels' => [ 'name' => 'Genres', 'singular_name' => 'Genre' ], 'hierarchical' => true, 'show_ui' => true, 'show_admin_column' => true ]);
}

// ==========================================
// 3. ADMIN SETTINGS, GEMINI SCANNER, EXPORT
// ==========================================
add_action('admin_menu', 'crjb_add_admin_menu');
function crjb_add_admin_menu() {
    add_submenu_page('edit.php?post_type=crjb_song', 'Jukebox Settings', 'Settings', 'manage_options', 'crjb_settings', 'crjb_settings_page');
    add_submenu_page('edit.php?post_type=crjb_song', 'Import & Scan', 'Import & Scan', 'manage_options', 'crjb_import_scan', 'crjb_import_scan_page');
    add_submenu_page('edit.php?post_type=crjb_song', 'Jukebox Tutorial', 'Tutorial & Setup', 'manage_options', 'crjb_tutorial', 'crjb_tutorial_page');
}

add_action('admin_init', 'crjb_register_settings');
function crjb_register_settings() {
    register_setting('crjb_settings_group', 'crjb_enable_submissions', ['type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean']);
    register_setting('crjb_settings_group', 'crjb_allow_explicit', ['type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean']);
    register_setting('crjb_settings_group', 'crjb_exclude_licensed', ['type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean']);
    register_setting('crjb_settings_group', 'crjb_strict_event_mode', ['type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean']);
    register_setting('crjb_settings_group', 'crjb_submission_url', ['type' => 'string', 'sanitize_callback' => 'esc_url_raw']);
    register_setting('crjb_settings_group', 'crjb_wipe_on_uninstall', ['type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean']);
}

function crjb_settings_page() {
    ?>
    <div class="wrap">
        <h1>Jukebox Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields('crjb_settings_group'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">Google Gemini AI Setup</th>
                    <td>
                        <p class="description" style="margin-top: 0; color: #0073aa; font-weight: 600;">API Keys are now securely managed centrally by WordPress.</p>
                        <p class="description">To enable AI Audio Scanning (Explicit Flags, Genres & Lyrics), please ensure the Google AI provider is installed and your key is configured under <strong>Settings &gt; Connectors</strong>.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Allow Explicit Content</th>
                    <td>
                        <input type="checkbox" name="crjb_allow_explicit" value="1" <?php checked(1, get_option('crjb_allow_explicit', 1), true); ?> />
                        <label>If unchecked, all songs marked as "Explicit" are instantly hidden from the catalog and skipped by the Auto DJ.</label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Exclude Licensed Music</th>
                    <td>
                        <input type="checkbox" name="crjb_exclude_licensed" value="1" <?php checked(1, get_option('crjb_exclude_licensed'), true); ?> />
                        <label>If checked, all standard tracks will be hidden and skipped. Only tracks marked as <strong>Royalty Free</strong> or with a <strong>License Override</strong> will play.</label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Strict Event Only Mode</th>
                    <td>
                        <input type="checkbox" name="crjb_strict_event_mode" value="1" <?php checked(1, get_option('crjb_strict_event_mode'), true); ?> />
                        <label>If checked, the Global Station will completely lock all song requests when no scheduled event is active.</label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Enable MP3 Submissions</th>
                    <td>
                        <input type="checkbox" name="crjb_enable_submissions" value="1" <?php checked(1, get_option('crjb_enable_submissions'), true); ?> />
                        <label>Show public upload link in the Jukebox header.</label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Submission URL</th>
                    <td>
                        <input type="url" name="crjb_submission_url" value="<?php echo esc_attr(get_option('crjb_submission_url')); ?>" class="regular-text" placeholder="https://dropbox.com/... or Google Drive link" />
                    </td>
                </tr>
                <tr>
                    <th scope="row" style="color: #d63638;">Wipe Data on Uninstall</th>
                    <td>
                        <input type="checkbox" name="crjb_wipe_on_uninstall" value="1" <?php checked(1, get_option('crjb_wipe_on_uninstall'), true); ?> />
                        <label>If checked, ALL plugin data (songs, schedules, AI data, and logs) will be permanently deleted from the database when the plugin is deleted.</label>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>

        <hr style="margin: 30px 0;">
        <h2>Monthly Broadcast Log Export</h2>
        <table class="form-table">
            <tr>
                <th scope="row">Download CSV by Month</th>
                <td>
                    <?php
                    $available_months = get_option('crjb_broadcast_log_months', []);
                    $legacy_log = get_option('crjb_broadcast_log', []);
                    
                    if (empty($available_months) && empty($legacy_log)) {
                        echo '<p>No broadcast history available yet.</p>';
                    } else {
                        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:flex; gap:10px; align-items:center;">';
                        echo '<input type="hidden" name="action" value="crjb_export_log">';
                        wp_nonce_field('crjb_export_action');
                        echo '<select name="log_month" style="padding: 4px 8px;">';
                        foreach ($available_months as $m) {
                            $label = gmdate('F Y', strtotime(str_replace('_', '-', $m) . '-01'));
                            echo '<option value="' . esc_attr($m) . '">' . esc_html($label) . '</option>';
                        }
                        if (!empty($legacy_log)) {
                            echo '<option value="legacy">Legacy Log</option>';
                        }
                        echo '</select>';
                        echo '<button type="submit" class="button button-secondary">Export Log</button>';
                        echo '</form>';
                    }
                    ?>
                    <p class="description" style="margin-top:10px;">Select a month to download a detailed CSV of all completed tracks played across <strong>all active stations</strong>.</p>
                </td>
            </tr>
        </table>
    </div>
    <?php
}

function crjb_import_scan_page() {
    ?>
    <div class="wrap">
        <h1>Import & Scan Tools</h1>
        <p class="description">Use these tools to populate your catalog and automate metadata generation.</p>

        <hr style="margin: 30px 0;">
        <h2>Gemini AI Bulk Catalog Scanner</h2>
        <table class="form-table">
            <tr>
                <th scope="row">Auto Tag Missing Genres, Explicit Flags & Lyrics</th>
                <td>
                    <div style="display:flex; gap:10px; align-items:center;">
                        <button type="button" id="crjb_bulk_scan_btn" class="button button-primary">Scan Incomplete Library</button>
                        <button type="button" id="crjb_clear_ai_data_btn" class="button button-secondary" style="color: #d63638; border-color: #d63638;">Wipe All AI Data</button>
                    </div>
                    <span id="crjb_bulk_status" style="display:block; margin-top:10px; font-weight:bold;"></span>
                    <p class="description"><strong>Scan Incomplete Library:</strong> Processes up to 10 songs missing standard layout vectors via the WP AI Client to prevent server timeouts.<br>
                    <strong>Wipe All AI Data:</strong> Instantly deletes all AI generated Genres and Lyrics from every track in your catalog so you can start a fresh rescan.</p>
                </td>
            </tr>
        </table>

        <hr style="margin: 30px 0;">
        <h2>Media Library Import</h2>
        <table class="form-table">
            <tr>
                <th scope="row">Import Unlinked MP3s</th>
                <td>
                    <button type="button" id="crjb_import_mp3s_btn" class="button button-primary">Scan & Import MP3s</button>
                    <span id="crjb_import_status" style="display:block; margin-top:10px; font-weight:bold;"></span>
                    <p class="description">Scans the WordPress Media Library for MP3s that haven't been added to the Jukebox yet. Creates a new Draft Song entry for each, using the filename as the track title.</p>
                </td>
            </tr>
        </table>

        <hr style="margin: 30px 0;">
        <h2>Bulk Folder Import</h2>
        <table class="form-table">
            <tr>
                <th scope="row">Import Artist Folders</th>
                <td>
                    <input type="file" id="crjb_folder_upload" webkitdirectory directory multiple accept="audio/mpeg" class="button" />
                    <p class="description">Select a folder. The folder name will be used as the Artist, and the MP3 filename (minus the extension) will be the Song Title. Tracks will be imported as Drafts.</p>
                    
                    <div id="crjb_upload_progress_container" style="display:none; margin-top: 15px; max-width: 400px;">
                        <div style="background: #e0e0e0; border-radius: 4px; overflow: hidden; width: 100%; height: 20px;">
                            <div id="crjb_upload_progress_bar" style="background: #0073aa; width: 0%; height: 100%; transition: width 0.3s;"></div>
                        </div>
                        <p id="crjb_upload_status" style="margin-top: 8px; font-weight: 600;"></p>
                    </div>
                </td>
            </tr>
        </table>

        <hr style="margin: 30px 0;">
        <h2>Storage Management</h2>
        <table class="form-table">
            <tr>
                <th scope="row">Orphaned Audio Cleanup</th>
                <td>
                    <button type="button" id="crjb_cleanup_audio_btn" class="button button-secondary" style="color: #d63638; border-color: #d63638;">Delete Unused MP3s</button>
                    <span id="crjb_cleanup_status" style="display:block; margin-top:10px; font-weight:bold;"></span>
                    <p class="description"><strong>Warning:</strong> This permanently deletes any MP3 file in your WordPress Media Library that is <strong>not</strong> actively attached to a Jukebox Song (as a main track, intro, or outro). Ensure you aren't using these MP3s on other pages of your site!</p>
                </td>
            </tr>
        </table>
    </div>
    <?php
}

// ------------------------------------------
// GEMINI API HANDLERS
// ------------------------------------------

// 1. Force the WordPress HTTP API Arguments
add_filter( 'http_request_args', 'crjb_force_gemini_http_args', 99, 2 );
function crjb_force_gemini_http_args( $args, $url ) {
    if ( strpos( $url, 'generativelanguage.googleapis.com' ) !== false ) {
        $args['timeout'] = 150;
    }
    return $args;
}

// 2. Force the underlying cURL Transport Handle (Bypasses WP restrictions)
add_action( 'http_api_curl', 'crjb_force_curl_timeout', 99, 3 );
function crjb_force_curl_timeout( $handle, $args, $url ) {
    if ( strpos( $url, 'generativelanguage.googleapis.com' ) !== false ) {
        curl_setopt( $handle, CURLOPT_TIMEOUT, 150 );
        curl_setopt( $handle, CURLOPT_CONNECTTIMEOUT, 150 );
    }
}

add_action('wp_ajax_crjb_import_media_library', 'crjb_import_media_library_handler');
function crjb_import_media_library_handler() {
    // 1. Verify Security
    if (!isset($_POST['security']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['security'])), 'crjb_gemini_scan_action')) wp_send_json_error('Security check failed.');
    if (!current_user_can('edit_posts')) wp_send_json_error('Unauthorized.');

    // 2. Identify already imported MP3s to prevent duplicates
    global $wpdb;
    $existing_ids_col = $wpdb->get_col("SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'crjb_audio_attachment_id' AND meta_value != ''");
    $existing_ids = array_map('intval', $existing_ids_col);

    // 3. Query Media Library for unlinked MP3s
    $args = [
        'post_type'      => 'attachment',
        'post_mime_type' => 'audio/mpeg',
        'post_status'    => 'inherit',
        'posts_per_page' => -1,
        'fields'         => 'ids'
    ];

    if (!empty($existing_ids)) {
        $args['post__not_in'] = $existing_ids;
    }

    $unlinked_mp3s = get_posts($args);

    if (empty($unlinked_mp3s)) {
        wp_send_json_success(['msg' => 'No unlinked MP3s found. Your catalog is up to date.']);
    }

    // 4. Loop, extract metadata, and create posts
    $imported = 0;
    foreach ($unlinked_mp3s as $attachment_id) {
        $file_path = get_attached_file($attachment_id);
        if (!$file_path) continue;

        // Extract filename and clean it up for the title (removes dashes/underscores)
        $filename = pathinfo($file_path, PATHINFO_FILENAME);
        $clean_title = ucwords(str_replace(['_', '-'], ' ', $filename));

        $post_data = [
            'post_title'  => $clean_title,
            'post_type'   => 'crjb_song',
            'post_status' => 'draft',
        ];

        $new_song_id = wp_insert_post($post_data);

        if (!is_wp_error($new_song_id)) {
            $url = wp_get_attachment_url($attachment_id);
            
            // Map core audio metadata required by the JS frontend
            update_post_meta($new_song_id, 'crjb_audio_attachment_id', $attachment_id);
            update_post_meta($new_song_id, 'full_audio_url', esc_url_raw($url));
            update_post_meta($new_song_id, 'preview_url', esc_url_raw($url));

            // Extract exact duration from WP metadata
            require_once( ABSPATH . 'wp-admin/includes/media.php' );
            $meta = wp_read_audio_metadata($file_path);
            if (!empty($meta['length'])) {
                update_post_meta($new_song_id, 'audio_duration', ceil($meta['length']));
            }
            
            // Set base safety defaults
            update_post_meta($new_song_id, 'crjb_is_explicit', '0');
            update_post_meta($new_song_id, 'crjb_royalty_free', '0');
            update_post_meta($new_song_id, 'crjb_always_available', '0');

            $imported++;
        }
    }

    if ($imported > 0) {
        update_option('crjb_catalog_version', time(), false);
    }

    wp_send_json_success(['msg' => "Success! Imported {$imported} new MP3s as Drafts. You can now review them and use the Bulk Scanner."]);
}

add_action('wp_ajax_crjb_gemini_clear_all', 'crjb_gemini_clear_all_handler');
function crjb_gemini_clear_all_handler() {
    if (!isset($_POST['security']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['security'])), 'crjb_gemini_scan_action')) wp_send_json_error('Security check failed.');
    if (!current_user_can('edit_posts')) wp_send_json_error('Unauthorized.');
    
    $all_songs = get_posts([
        'post_type'      => 'crjb_song',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids'
    ]);

    $cleared = 0;
    foreach ($all_songs as $song_id) {
        wp_set_object_terms($song_id, [], 'crjb_genre'); 
        delete_post_meta($song_id, 'crjb_lyrics'); 
        delete_post_meta($song_id, 'crjb_is_explicit');
        $cleared++;
    }

    update_option('crjb_catalog_version', time(), false);
    wp_send_json_success(['msg' => "Successfully wiped AI data for {$cleared} tracks. Your catalog is now a blank slate for rescanning."]);
}

add_action('wp_ajax_crjb_gemini_scan', 'crjb_gemini_scan_handler');
function crjb_gemini_scan_handler() {
    if (!isset($_POST['security']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['security'])), 'crjb_gemini_scan_action')) wp_send_json_error('Security check failed.');
    if (!current_user_can('edit_posts')) wp_send_json_error('Unauthorized.');
    
    // Prevent the PHP process from dying while waiting for the AI audio transcription
    set_time_limit(150); 
    
    $song_id = isset($_POST['song_id']) ? intval($_POST['song_id']) : 0;
    if (!$song_id) wp_send_json_error('Invalid song ID.');

    $result = crjb_execute_gemini_scan($song_id);
    if (is_wp_error($result)) wp_send_json_error($result->get_error_message());
    
    wp_send_json_success($result);
}

add_action('wp_ajax_crjb_gemini_bulk_scan', 'crjb_gemini_bulk_scan_handler');
function crjb_gemini_bulk_scan_handler() {
    if (!isset($_POST['security']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['security'])), 'crjb_gemini_scan_action')) wp_send_json_error('Security check failed.');
    if (!current_user_can('edit_posts')) wp_send_json_error('Unauthorized.');
    
    // Prevent the PHP process from dying while waiting for the AI audio transcription (batch process)
    set_time_limit(300);
    
    $all_songs = get_posts([
        'post_type'      => 'crjb_song',
        'post_status'    => 'any', // Specifically adjusted to process drafts, pending, etc.
        'posts_per_page' => -1,
        'fields'         => 'ids'
    ]);

    $incomplete_songs = [];
    foreach ($all_songs as $song_id) {
        $genres = wp_get_post_terms($song_id, 'crjb_genre', ['fields' => 'ids']);
        $lyrics = get_post_meta($song_id, 'crjb_lyrics', true);
        
        if (empty($genres) || is_wp_error($genres) || empty($lyrics) || strpos($lyrics, 'No audio file provided') !== false || strpos($lyrics, 'Audio file too large') !== false) {
            $incomplete_songs[] = $song_id;
            if (count($incomplete_songs) >= 10) break;
        }
    }

    if (empty($incomplete_songs)) {
        wp_send_json_success(['processed' => 0, 'msg' => 'All songs already have genres and lyrics!']);
    }

    $processed = 0;
    $last_error = '';

    foreach ($incomplete_songs as $song_id) {
        $result = crjb_execute_gemini_scan($song_id);
        if (!is_wp_error($result)) {
            $processed++;
        } else {
            $last_error = $result->get_error_message();
        }
    }

    if ($processed === 0 && !empty($last_error)) {
        wp_send_json_error("Gemini API Error: " . $last_error);
    }

    wp_send_json_success(['processed' => $processed, 'msg' => 'Batch complete.']);
}

function crjb_execute_gemini_scan($song_id) {
    if (!function_exists('wp_ai_client_prompt') || !class_exists('\WordPress\AiClient\AiClient')) {
        return new WP_Error('ai_client_missing', 'WordPress 7.0 AI Client is required for this feature. Please ensure the Google AI provider is installed under Settings > Connectors.');
    }

    $attachment_id = get_post_meta($song_id, 'crjb_audio_attachment_id', true);
    
    // Auto-repair framework for legacy data entries missing implicit meta relationships
    if (!$attachment_id) {
        $audio_url = get_post_meta($song_id, 'full_audio_url', true);
        if ($audio_url) {
            $attachment_id = attachment_url_to_postid($audio_url);
            if ($attachment_id) {
                update_post_meta($song_id, 'crjb_audio_attachment_id', $attachment_id);
            }
        }
    }

    $title = get_the_title($song_id);

    if (!$attachment_id) {
        return new WP_Error('no_audio', "Audio unavailable for '{$title}': No valid audio attachment found in Media Library.");
    }

    $file_path = get_attached_file($attachment_id);
    if (!$file_path || !file_exists($file_path)) {
        return new WP_Error('no_audio', "Audio unavailable for '{$title}': File does not exist on server.");
    }

    $mime = mime_content_type($file_path) ?: 'audio/mp3';

    $prompt = "You are an expert music curator and strict audio transcriptionist. Listen to the provided audio track.\n\nSTRICT RULES:\n1. For 'genres', provide an array of 2 to 4 accurate standard musical genres/sub-genres based on the sonic profile.\n2. For 'lyrics', you MUST ONLY transcribe the exact words you hear in the audio file. DO NOT hallucinate, guess, or search for lyrics based on the title or artist. If there are no vocals, or if you cannot clearly hear them, output exactly: 'Instrumental'.\n3. For 'is_explicit', analyze the audio and lyrics for strong profanity, explicit sexual themes, or highly sensitive/graphic violent content. Return a boolean true if explicit content is present, or false if the track is completely clean.";

    try {
        // Explicitly bypass preference capability matching and enforce the Gemini model object
        $registry = \WordPress\AiClient\AiClient::defaultRegistry();
        $model = $registry->getProviderModel('google', 'gemini-2.5-pro');
        
        if (!$model) {
             return new WP_Error('model_missing', 'The Gemini 2.5 Pro model could not be found. Ensure the Google AI connector is configured.');
        }

        $result = wp_ai_client_prompt($prompt)
            ->using_system_instruction('You are a precise JSON generator. Output valid JSON only, with no markdown formatting or backticks.')
            ->with_file($file_path, $mime)
            ->using_model($model)
            ->as_json_response()
            ->generate_text();
            
    } catch (Exception $e) {
        return new WP_Error('ai_error', $e->getMessage());
    }

    if (is_wp_error($result)) return $result;

    $data = json_decode($result, true);
    
    if (!$data) {
        $clean_result = trim(str_replace(['```json', '```'], '', $result));
        $data = json_decode($clean_result, true);
        if (!$data) return new WP_Error('parse_error', 'Failed to parse Gemini response.');
    }
    
    $response_data = [];
    
    if (isset($data['genres']) && is_array($data['genres'])) {
        wp_set_object_terms($song_id, $data['genres'], 'crjb_genre', false);
        $response_data['genres'] = $data['genres'];
    }
    
    if (isset($data['lyrics'])) {
        update_post_meta($song_id, 'crjb_lyrics', sanitize_textarea_field($data['lyrics']));
        $response_data['lyrics_status'] = 'Transcribed';
    }

    if (isset($data['is_explicit'])) {
        update_post_meta($song_id, 'crjb_is_explicit', $data['is_explicit'] ? '1' : '0');
        $response_data['explicit_status'] = $data['is_explicit'] ? 'Explicit' : 'Clean';
    }
    
    update_option('crjb_catalog_version', time(), false);
    return $response_data;
}

// ------------------------------------------
// BULK FOLDER IMPORT HANDLER
// ------------------------------------------
add_action('wp_ajax_crjb_process_folder_upload', 'crjb_process_folder_upload_handler');
function crjb_process_folder_upload_handler() {
    // 1. Security & Permissions
    check_ajax_referer('crjb_folder_upload_nonce', 'security');
    if (!current_user_can('upload_files') || !current_user_can('edit_posts')) {
        wp_send_json_error('Unauthorized.');
    }

    if (empty($_FILES['file'])) wp_send_json_error('No file received.');

    $artist_name = sanitize_text_field(wp_unslash($_POST['artist']));
    $song_title  = sanitize_text_field(wp_unslash($_POST['title']));

    // 2. Upload the MP3 to the WP Media Library
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');

    $uploaded_file = $_FILES['file'];
    $upload_overrides = ['test_form' => false];
    
    $movefile = wp_handle_upload($uploaded_file, $upload_overrides);

    if ($movefile && !isset($movefile['error'])) {
        $filename = $movefile['file'];
        $filetype = wp_check_filetype(basename($filename), null);
        $wp_upload_dir = wp_upload_dir();

        $attachment = [
            'guid'           => $wp_upload_dir['url'] . '/' . basename($filename), 
            'post_mime_type' => $filetype['type'],
            'post_title'     => preg_replace('/\.[^.]+$/', '', basename($filename)),
            'post_content'   => '',
            'post_status'    => 'inherit'
        ];

        $attach_id = wp_insert_attachment($attachment, $filename);
        $attach_data = wp_generate_attachment_metadata($attach_id, $filename);
        wp_update_attachment_metadata($attach_id, $attach_data);

        // Extract duration via WP audio metadata
        $meta = wp_read_audio_metadata($filename);
        $duration = !empty($meta['length']) ? ceil($meta['length']) : 180;

        // 3. Create the Jukebox Song Post
        $post_id = wp_insert_post([
            'post_title'  => $song_title,
            'post_type'   => 'crjb_song',
            'post_status' => 'draft'
        ]);

        if ($post_id) {
            // Attach the audio metadata
            update_post_meta($post_id, 'crjb_audio_attachment_id', $attach_id);
            update_post_meta($post_id, 'full_audio_url', esc_url_raw($movefile['url']));
            update_post_meta($post_id, 'preview_url', esc_url_raw($movefile['url']));
            update_post_meta($post_id, 'audio_duration', $duration);
            
            // Set base safety defaults
            update_post_meta($post_id, 'crjb_is_explicit', '0');
            update_post_meta($post_id, 'crjb_royalty_free', '0');
            update_post_meta($post_id, 'crjb_always_available', '0');
            
            // Set the Artist Taxonomy (creates it if it doesn't exist)
            wp_set_object_terms($post_id, $artist_name, 'crjb_artist', false);

            update_option('crjb_catalog_version', time(), false);
            wp_send_json_success('Uploaded successfully.');
        } else {
            wp_send_json_error('Failed to create song post.');
        }
    } else {
        wp_send_json_error($movefile['error']);
    }
}

// Handler for CSV Export
add_action('admin_post_crjb_export_log', 'crjb_export_broadcast_log_handler');
function crjb_export_broadcast_log_handler() {
    if (!current_user_can('manage_options')) wp_die('Unauthorized access.');
    if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'crjb_export_action' ) ) { wp_die( 'Security check failed. The link may have expired.' ); }
    
    // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified on the preceding line.
    $month_key = isset($_POST['log_month']) ? sanitize_text_field(wp_unslash($_POST['log_month'])) : '';
    // phpcs:enable
    
    if ($month_key === 'legacy') {
        $log = get_option('crjb_broadcast_log', []);
        $filename = 'jukebox-legacy-log-' . gmdate('Y-m-d-H-i') . '.csv';
    } elseif ($month_key) {
        $log = get_option('crjb_broadcast_log_' . $month_key, []);
        $filename = 'jukebox-log-' . $month_key . '.csv';
    } else {
        $months = get_option('crjb_broadcast_log_months', []);
        if (!empty($months)) {
            $month_key = $months[0];
            $log = get_option('crjb_broadcast_log_' . $month_key, []);
            $filename = 'jukebox-log-' . $month_key . '.csv';
        } else {
            $log = [];
            $filename = 'jukebox-log-empty.csv';
        }
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Station', 'Song Title', 'Artist', 'Started At', 'Finished At', 'Active Listeners']);
    foreach ($log as $entry) {
        fputcsv($output, [
            $entry['station'] ?? 'global',
            html_entity_decode($entry['title']),
            html_entity_decode($entry['artist']),
            wp_date('Y-m-d H:i:s', $entry['start_time']),
            wp_date('Y-m-d H:i:s', $entry['end_time']),
            $entry['listeners']
        ]);
    }
    exit;
}

function crjb_tutorial_page() {
    ?>
    <div class="wrap">
        <h1 style="margin-bottom: 20px;">Community Radio Jukebox: Manual & Workflows</h1>

        <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-left: 4px solid #0073aa; box-shadow: 0 1px 1px rgba(0,0,0,.04); max-width: 800px; margin-bottom: 20px;">
            <h2 style="margin-top: 0;">1. AI Auto Tagging & Lyrics Transcription</h2>
            <p>Ensure your preferred AI model is configured in the WordPress Settings > Connectors screen. When editing a Jukebox Song, click <strong>✨ Analyze Audio</strong>. The system will upload the MP3 via the WP AI Client, letting the AI listen to the track to automatically assign the correct Genres and transcribe the Lyrics.</p>
        </div>

        <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-left: 4px solid #28a745; box-shadow: 0 1px 1px rgba(0,0,0,.04); max-width: 800px; margin-bottom: 20px;">
            <h2 style="margin-top: 0;">2. The Global Station</h2>
            <p>Paste the standard shortcode anywhere to play your entire catalog. This acts as your main venue radio.</p>
            <p><code style="font-size: 16px; padding: 5px 10px; background: #f0f0f1; border-radius: 4px;">[community_radio_jukebox]</code></p>
        </div>

        <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-left: 4px solid #8e44ad; box-shadow: 0 1px 1px rgba(0,0,0,.04); max-width: 800px; margin-bottom: 20px;">
            <h2 style="margin-top: 0;">3. Smart Routing & Custom Stations</h2>
            <p>You can create highly specific stations by passing multiple comma separated items. Each unique combination generates its own isolated timeline and queue.</p>
            <ul style="font-size: 14px;">
                <li><strong>Multiple Artists:</strong> <code>[community_radio_jukebox artist="the-beatles,the-kinks"]</code></li>
                <li><strong>Combinations (AND logic):</strong> <code>[community_radio_jukebox genre="rock" playlist="patio-mix"]</code></li>
            </ul>
        </div>

        <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-left: 4px solid #e67e22; box-shadow: 0 1px 1px rgba(0,0,0,.04); max-width: 800px; margin-bottom: 20px;">
            <h2 style="margin-top: 0;">4. Automated Station Takeovers (Schedules)</h2>
            <p>You can automate your venue's atmosphere. Navigate to <strong>Jukebox Songs > Jukebox Schedules</strong> to create time blocks.</p>
            <p>If a schedule becomes active (e.g., "Friday Happy Hour" at 5:00 PM), the Jukebox will automatically transition the <strong>Global Station</strong> to your requested tags, and temporarily lock the patrons' voting catalog to that specific vibe.</p>
            <p><em>Note: If "Strict Event Only Mode" is enabled in settings, the Jukebox will reject all songs when an event is NOT running, UNLESS you explicitly check the "Always Available" box on a specific song.</em></p>
        </div>

        <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-left: 4px solid #dc3545; box-shadow: 0 1px 1px rgba(0,0,0,.04); max-width: 800px; margin-bottom: 20px;">
            <h2 style="margin-top: 0;">5. Live Event DJ Workflow</h2>
            <p>When you have a live DJ in the booth, you can use the Jukebox to boost crowd interaction without interfering with the DJ's live set. Here is the recommended workflow:</p>
            <ol style="font-size: 14px;">
                <li><strong>The Visualizer Backdrop:</strong> Open the Jukebox on a laptop connected to a projector or venue TVs. (Fullscreen recommended).</li>
                <li><strong>The Request Line (Muted Mode):</strong> Have the DJ open the Jukebox URL on an iPad in the booth. <strong>Ensure the iPad's volume is muted.</strong> As patrons scan QR codes at their tables and vote for songs, the DJ can watch the Jukebox Queue update in real time to gauge the crowd's mood and use it as a digital request board.</li>
                <li><strong>The Autopilot Break:</strong> If the DJ needs to take a break or run to the bathroom, they simply fade up the audio channel connected to the Jukebox projector. The Jukebox takes over on autopilot, perfectly in sync, playing the crowd's highest voted track.</li>
            </ol>
        </div>

        <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-left: 4px solid #17a2b8; box-shadow: 0 1px 1px rgba(0,0,0,.04); max-width: 800px; margin-bottom: 20px;">
            <h2 style="margin-top: 0;">6. "Available Only" & Predictive Empty States</h2>
            <p>To prevent "scroll fatigue", patrons can toggle the <strong>Available Only</strong> checkbox in the catalog to hide tracks that are currently playing, on cooldown, or locked until a future event.</p>
            <p>If they toggle this on when no songs are available, the Jukebox will scan ahead and display a <strong>Predictive Empty State</strong>, letting them know the exact time (or event) when the next track will become playable.</p>
        </div>

        <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-left: 4px solid #fd7e14; box-shadow: 0 1px 1px rgba(0,0,0,.04); max-width: 800px; margin-bottom: 20px;">
            <h2 style="margin-top: 0;">7. Artist Submissions & Bulk Importing</h2>
            <p>Empower your local community to contribute their original music directly to your library! Enable Submissions in the settings, then place this shortcode on any page:</p>
            <p><code style="font-size: 16px; padding: 5px 10px; background: #f0f0f1; border-radius: 4px;">[community_radio_jukebox_submit_mp3]</code></p>
            <p>Once artists upload their files, simply navigate to the <strong>Jukebox Settings</strong> page and click <strong>Scan & Import MP3s</strong>. The system will instantly turn them into Draft songs, ready for you to review, run the AI Scanner, and publish!</p>
        </div>

        <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-left: 4px solid #6f42c1; box-shadow: 0 1px 1px rgba(0,0,0,.04); max-width: 800px; margin-bottom: 20px;">
            <h2 style="margin-top: 0;">8. DJ Drops</h2>
            <p>Bring your station to life with dynamic vocal drops! You can upload your own custom Intro and Outro voice memos on any song's edit page.</p>
        </div>

    </div>
    <?php
}

// ------------------------------------------
// DEDICATED TRACK PAGE FRONTEND DISPLAY
// ------------------------------------------

add_action('wp_enqueue_scripts', 'crjb_hide_sidebar_on_song_page');
function crjb_hide_sidebar_on_song_page() {
    if (is_singular('crjb_song')) {
        wp_register_style('crjb-song-layout', false);
        wp_enqueue_style('crjb-song-layout');
        $custom_css = "
            #secondary, #sidebar, .sidebar, .widget-area, aside#secondary { display: none !important; }
            #primary, #content, .site-main, .content-area, .site-content { width: 100% !important; max-width: none !important; float: none !important; border: none !important; }
        ";
        wp_add_inline_style('crjb-song-layout', $custom_css);
    }
}

add_action('wp_enqueue_scripts', 'crjb_enqueue_song_preview_script');
function crjb_enqueue_song_preview_script() {
    if (is_singular('crjb_song')) {
        wp_register_script('crjb-preview-script', false, [], false, true);
        wp_enqueue_script('crjb-preview-script');
        $custom_js = '
            document.addEventListener("DOMContentLoaded", function() {
                var audio = document.getElementById("crjb-dedicated-preview");
                if(audio) {
                    audio.addEventListener("timeupdate", function() {
                        if(audio.currentTime >= 30) {
                            audio.pause();
                            audio.currentTime = 0;
                        }
                    });
                }
            });
        ';
        wp_add_inline_script('crjb-preview-script', $custom_js);
    }
}

add_action('wp_head', 'crjb_inject_song_structured_data');
function crjb_inject_song_structured_data() {
    if (is_singular('crjb_song')) {
        $post_id = get_the_ID();
        $title = get_the_title($post_id);
        $artist_terms = wp_get_post_terms($post_id, 'crjb_artist', ['fields' => 'names']);
        $artist = !empty($artist_terms) ? implode(', ', $artist_terms) : 'Unknown Artist';
        $lyrics = get_post_meta($post_id, 'crjb_lyrics', true);
        $duration = get_post_meta($post_id, 'audio_duration', true);
        
        $schema = [
            "@context" => "https://schema.org",
            "@type" => "MusicRecording",
            "name" => $title,
            "byArtist" => [
                "@type" => "MusicGroup",
                "name" => $artist
            ]
        ];

        if (!empty($duration) && is_numeric($duration)) {
            $schema['duration'] = 'PT' . intval($duration) . 'S';
        }

        if (!empty($lyrics)) {
            $schema['recordingOf'] = [
                "@type" => "MusicComposition",
                "name" => $title,
                "lyrics" => [
                    "@type" => "CreativeWork",
                    "text" => wp_strip_all_tags($lyrics)
                ]
            ];
        }

        echo '<script type="application/ld+json">' . wp_json_encode($schema) . '</script>' . "\n";
    }
}

add_filter('body_class', 'crjb_song_full_width_body_classes');
function crjb_song_full_width_body_classes($classes) {
    if (is_singular('crjb_song')) {
        $classes[] = 'full-width-content';
        $classes[] = 'no-sidebar';
        $classes[] = 'full-width';
    }
    return $classes;
}

add_filter('the_content', 'crjb_song_dedicated_page_content');
function crjb_song_dedicated_page_content($content) {
    if (is_singular('crjb_song') && in_the_loop() && is_main_query()) {
        $post_id = get_the_ID();
        $lyrics = get_post_meta($post_id, 'crjb_lyrics', true);
        $is_explicit = get_post_meta($post_id, 'crjb_is_explicit', true);
        $always_available = get_post_meta($post_id, 'crjb_always_available', true);
        $is_royalty_free = get_post_meta($post_id, 'crjb_royalty_free', true);
        $tip_url = get_post_meta($post_id, 'crjb_tip_url', true);
        
        $artist_terms = wp_get_post_terms($post_id, 'crjb_artist', ['fields' => 'names']);
        $artist = !empty($artist_terms) ? implode(', ', $artist_terms) : 'Unknown Artist';
        
        $genre_terms = wp_get_post_terms($post_id, 'crjb_genre', ['fields' => 'names']);
        $genres = !empty($genre_terms) ? implode(', ', $genre_terms) : 'None';
        
        $playlist_terms = wp_get_post_terms($post_id, 'crjb_playlist', ['fields' => 'names']);
        $playlists = !empty($playlist_terms) ? implode(', ', $playlist_terms) : 'None';

        $duration = get_post_meta($post_id, 'audio_duration', true);
        $duration_fmt = '--:--';
        if ($duration && is_numeric($duration)) {
            $duration_fmt = floor($duration / 60) . ':' . str_pad($duration % 60, 2, '0', STR_PAD_LEFT);
        }

        $full_audio_url = get_post_meta($post_id, 'full_audio_url', true);
        $preview_url = get_post_meta($post_id, 'preview_url', true) ?: $full_audio_url;

        $schedules = get_posts(['post_type' => 'crjb_schedule', 'posts_per_page' => -1]);
        $matched_events = [];
        foreach($schedules as $sched) {
            if (crjb_song_matches_schedule($post_id, $sched->ID)) {
                $matched_events[] = get_the_title($sched->ID);
            }
        }
        
        if ($always_available) {
            $events_str = 'All Events (Always Available)';
        } elseif (!empty($matched_events)) {
            $events_str = implode(', ', $matched_events);
        } else {
            $events_str = 'Open Play Only';
        }
        
        $html = '<div class="crjb-dedicated-track" style="max-width: 800px; margin: 0 auto; padding: 40px 20px; font-family: system-ui, sans-serif;">';
        
        $e_badge = $is_explicit ? '<span style="font-size: 12px; font-weight: 800; background: #666; color: #fff; padding: 2px 6px; border-radius: 4px; vertical-align: middle; margin-left: 10px;" title="Explicit Content">E</span>' : '';
        $html .= '<h1 style="margin-bottom: 5px; color: #222; display: flex; align-items: center; flex-wrap: wrap;">' . esc_html(get_the_title()) . $e_badge . '</h1>';
        $html .= '<h3 style="margin-top:0; color: #555; margin-bottom: 30px;">By ' . esc_html($artist) . '</h3>';
        
        if ($tip_url) {
            $html .= '<div style="margin-bottom: 30px;"><a href="' . esc_url($tip_url) . '" target="_blank" style="display: inline-flex; align-items: center; background: #ffaa00; color: #000; font-weight: 800; padding: 10px 20px; border-radius: 8px; text-decoration: none;"><svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 8px;"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg> Tip the Artist</a></div>';
        }
        
        $html .= '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; background: #f5f5f5; padding: 25px; border-radius: 12px; border: 1px solid #e0e0e0; margin-bottom: 30px;">';
        $html .= '<div><strong style="font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #888; display: block; margin-bottom: 5px;">Duration</strong><span style="font-size: 16px; font-weight: 600; color: #333;">' . esc_html($duration_fmt) . '</span></div>';
        $html .= '<div><strong style="font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #888; display: block; margin-bottom: 5px;">Genres</strong><span style="font-size: 16px; font-weight: 600; color: #333;">' . esc_html($genres) . '</span></div>';
        $html .= '<div><strong style="font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #888; display: block; margin-bottom: 5px;">Playlists</strong><span style="font-size: 16px; font-weight: 600; color: #333;">' . esc_html($playlists) . '</span></div>';
        $html .= '<div><strong style="font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #888; display: block; margin-bottom: 5px;">Featured In</strong><span style="font-size: 15px; font-weight: 700; color: #0073aa;">' . esc_html($events_str) . '</span></div>';
        $html .= '</div>';

        if ($is_royalty_free && $full_audio_url) {
            $html .= '<div style="background: #eef7fc; padding: 20px; border-radius: 12px; border: 1px solid #bce0f4; margin-bottom: 40px; text-align: center;">';
            $html .= '<h4 style="margin: 0 0 15px 0; color: #0073aa; font-weight: 800; font-size: 16px; display:flex; justify-content:center; align-items:center;"><svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 5px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 9.9-1"></path></svg> Full Track (Royalty Free)</h4>';
            $html .= '<audio controls controlsList="nodownload" src="' . esc_url($full_audio_url) . '" style="width: 100%; max-width: 400px; outline: none; border-radius: 8px;"></audio>';
            $html .= '</div>';
        } elseif ($preview_url) {
            $html .= '<div style="background: #eef7fc; padding: 20px; border-radius: 12px; border: 1px solid #bce0f4; margin-bottom: 40px; text-align: center;">';
            $html .= '<h4 style="margin: 0 0 15px 0; color: #0073aa; font-weight: 800; font-size: 16px;">30-Second Preview</h4>';
            $html .= '<audio id="crjb-dedicated-preview" controls controlsList="nodownload" src="' . esc_url($preview_url) . '" style="width: 100%; max-width: 400px; outline: none; border-radius: 8px;"></audio>';
            $html .= '</div>';
        }
        
        if ($lyrics) {
            $html .= '<h4 style="margin-top: 30px; font-weight: 800; font-size: 22px;">Lyrics</h4>';
            $html .= '<blockquote style="white-space: pre-wrap; font-style: normal; font-size: 16px; line-height: 1.8; background: #f9f9f9; padding: 30px; border-left: 4px solid #0073aa; border-radius: 0 8px 8px 0; color: #333;">' . esc_html($lyrics) . '</blockquote>';
        } else {
            $html .= '<p style="color: #888; font-style: italic; padding: 20px; text-align: center; background: #fafafa; border-radius: 8px;">No lyrics available or track has not been scanned.</p>';
        }
        
        $html .= '</div>';
        
        return $html; 
    }
    return $content;
}

// ------------------------------------------
// HELPER: SELECT2 TAXONOMY RENDERER
// ------------------------------------------
function crjb_render_tax_select_field($tax, $saved_val, $name, $placeholder) {
    $saved_terms = array_filter(array_map('trim', explode(',', $saved_val)));
    $terms = get_terms(['taxonomy' => $tax, 'hide_empty' => false]);
    
    echo '<select multiple="multiple" class="crjb-select2 regular-text" name="' . esc_attr($name) . '[]" data-placeholder="' . esc_attr($placeholder) . '" style="width: 100%; max-width: 400px;">';
    
    $existing_slugs = [];
    if (!is_wp_error($terms)) {
        foreach($terms as $t) {
            $existing_slugs[] = $t->slug;
            $sel = in_array($t->slug, $saved_terms) ? 'selected' : '';
            echo '<option value="' . esc_attr($t->slug) . '" ' . esc_attr($sel) . '>' . esc_html($t->name) . '</option>';
        }
    }
    
    foreach($saved_terms as $st) {
        if(!in_array($st, $existing_slugs)) {
            echo '<option value="' . esc_attr($st) . '" selected>' . esc_html($st) . '</option>';
        }
    }
    echo '</select>';
}

// ------------------------------------------
// SONG META BOX
// ------------------------------------------
add_action( 'add_meta_boxes', 'crjb_add_song_meta_boxes' );
function crjb_add_song_meta_boxes() {
    add_meta_box( 'crjb_song_details', 'Network Audio File & AI Transcription', 'crjb_song_details_callback', 'crjb_song', 'normal', 'high' );
    add_meta_box( 'crjb_schedule_details', 'Automated Station Takeover Rules', 'crjb_schedule_details_callback', 'crjb_schedule', 'normal', 'high' );
}

function crjb_song_details_callback( $post ) {
    wp_nonce_field( 'crjb_save_song_data', 'crjb_song_meta_nonce' );
    $full_audio_url = get_post_meta( $post->ID, 'full_audio_url', true );
    $audio_attachment_id = get_post_meta( $post->ID, 'crjb_audio_attachment_id', true );
    $audio_duration = get_post_meta( $post->ID, 'audio_duration', true );
    $preview_url    = get_post_meta( $post->ID, 'preview_url', true );
    $always_available = get_post_meta( $post->ID, 'crjb_always_available', true );
    $play_globally  = get_post_meta( $post->ID, 'crjb_play_globally', true );
    $is_explicit    = get_post_meta( $post->ID, 'crjb_is_explicit', true );
    $is_royalty_free = get_post_meta( $post->ID, 'crjb_royalty_free', true );
    $license_override = get_post_meta( $post->ID, 'crjb_license_override', true );
    $lyrics         = get_post_meta( $post->ID, 'crjb_lyrics', true );
    $custom_banner  = get_post_meta( $post->ID, 'crjb_custom_banner_text', true );
    $tip_url        = get_post_meta( $post->ID, 'crjb_tip_url', true );
    $intro_audio_url = get_post_meta( $post->ID, 'intro_audio_url', true );
    $outro_audio_url = get_post_meta( $post->ID, 'outro_audio_url', true );
    ?>
    <table class="form-table">
        <tr>
            <th><label>Content Rating</label></th>
            <td>
                <label>
                    <input type="checkbox" name="crjb_is_explicit" value="1" <?php checked(1, $is_explicit); ?> />
                    <strong>Explicit Content:</strong> Adds an [E] badge to the track. Hidden if explicit content is globally disabled.
                </label>
            </td>
        </tr>
        <tr>
            <th><label>Licensing</label></th>
            <td>
                <label>
                    <input type="checkbox" name="crjb_royalty_free" value="1" <?php checked(1, $is_royalty_free); ?> />
                    <strong>Royalty Free:</strong> Allows the full track to be played on the dedicated song page instead of just a 30-second preview.
                </label>
            </td>
        </tr>
        <tr>
            <th><label>License Override</label></th>
            <td>
                <label>
                    <input type="checkbox" name="crjb_license_override" value="1" <?php checked(1, $license_override); ?> />
                    <strong>Bypass Global Exclusion:</strong> Force this track to remain playable even if "Exclude Licensed Music" is enabled globally in Settings.
                </label>
            </td>
        </tr>
        <tr>
            <th><label>Availability Override</label></th>
            <td>
                <label>
                    <input type="checkbox" name="crjb_always_available" value="1" <?php checked(1, $always_available); ?> />
                    <strong>Always Available:</strong> This song bypasses schedule rules and event exclusivity locks.
                </label>
            </td>
        </tr>
        <tr>
            <th><label>Open Play Global</label></th>
            <td>
                <label>
                    <input type="checkbox" name="crjb_play_globally" value="1" <?php checked(1, $play_globally); ?> />
                    <strong>Play Globally during Open Play:</strong> Prioritize this song in the Auto DJ during non-scheduled events.
                </label>
            </td>
        </tr>
        <tr>
            <th><label>Tip URL (WooCommerce/Venmo)</label></th>
            <td>
                <input type="url" name="crjb_tip_url" value="<?php echo esc_url($tip_url); ?>" class="regular-text" style="width: 100%;" placeholder="https://yoursite.com/?add-to-cart=123" />
                <p class="description">Paste a WooCommerce "Add to Cart" link, or a direct Venmo/Ko-fi link. This will automatically generate a gold "Tip Artist" button on the frontend.</p>
            </td>
        </tr>
        <tr>
            <th><label>Custom Scrolling Banner</label></th>
            <td>
                <input type="text" name="crjb_custom_banner_text" value="<?php echo esc_attr($custom_banner); ?>" class="regular-text" style="width: 100%;" />
                <p class="description">Overrides the default "Submitted by" text. HTML is allowed (e.g., <code>&lt;strong&gt;Happy Birthday Sarah!&lt;/strong&gt;</code>). This will side-scroll horizontally in the frontend Jukebox interface.</p>
            </td>
        </tr>
        <tr><th><label>Network Sync MP3</label></th><td>
            <div style="display: flex; gap: 10px;">
                <input type="url" id="full_audio_url" name="full_audio_url" value="<?php echo esc_attr($full_audio_url); ?>" style="flex-grow: 1;" readonly />
                <input type="hidden" id="crjb_audio_attachment_id" name="crjb_audio_attachment_id" value="<?php echo esc_attr($audio_attachment_id); ?>" />
                <input type="hidden" id="crjb_saved_audio_url" value="<?php echo esc_attr($full_audio_url); ?>" />
                <button type="button" class="button button-secondary" id="crjb_upload_mp3_btn">Select Track MP3</button>
                <button type="button" class="button button-primary" id="crjb_gemini_scan_btn">✨ Analyze Audio</button>
            </div>
            <p class="description">Clicking <strong>Analyze Audio</strong> will run the file through Gemini 2.5 Pro to auto assign genres, explicit classification variables, and transcribe the lyrics below.</p>
        </td></tr>
        <tr><th><label>Duration (Seconds)</label></th><td><input type="number" id="audio_duration" name="audio_duration" value="<?php echo esc_attr($audio_duration); ?>" readonly /></td></tr>
        <tr><th><label>Frontend Preview URL</label></th><td><input type="url" id="preview_url" name="preview_url" value="<?php echo esc_url($preview_url); ?>" style="width:100%;" /></td></tr>
        <tr><th><label>Intro Voice Memo (DJ Drop)</label></th><td>
            <div style="display: flex; gap: 10px;">
                <input type="url" id="intro_audio_url" name="intro_audio_url" value="<?php echo esc_attr($intro_audio_url); ?>" style="flex-grow: 1;" readonly placeholder="Plays before the song starts..." />
                <input type="hidden" id="crjb_intro_attachment_id" name="crjb_intro_attachment_id" value="" />
                <button type="button" class="button button-secondary crjb_upload_memo_btn" data-target="intro">Select Intro</button>
                <button type="button" class="button crjb_clear_memo_btn" data-target="intro">Clear</button>
            </div>
        </td></tr>
        <tr><th><label>Outro Voice Memo (DJ Drop)</label></th><td>
            <div style="display: flex; gap: 10px;">
                <input type="url" id="outro_audio_url" name="outro_audio_url" value="<?php echo esc_attr($outro_audio_url); ?>" style="flex-grow: 1;" readonly placeholder="Plays after the song ends..." />
                <input type="hidden" id="crjb_outro_attachment_id" name="crjb_outro_attachment_id" value="" />
                <button type="button" class="button button-secondary crjb_upload_memo_btn" data-target="outro">Select Outro</button>
                <button type="button" class="button crjb_clear_memo_btn" data-target="outro">Clear</button>
            </div>
        </td></tr>
        <tr>
            <th><label>Track Lyrics</label></th>
            <td>
                <textarea name="crjb_lyrics" rows="8" style="width:100%; font-family: monospace; padding: 10px;"><?php echo esc_textarea($lyrics); ?></textarea>
                <p class="description">These lyrics will be displayed on the track's dedicated permalink page.</p>
            </td>
        </tr>
    </table>
    <?php
}

// ------------------------------------------
// SCHEDULE META BOX
// ------------------------------------------
function crjb_schedule_details_callback( $post ) {
    wp_nonce_field( 'crjb_save_schedule_data', 'crjb_schedule_meta_nonce' );
    $days = get_post_meta( $post->ID, 'crjb_days', true ) ?: [];
    if (!is_array($days)) $days = [$days];
    
    $start_time = get_post_meta( $post->ID, 'crjb_start_time', true );
    $end_time   = get_post_meta( $post->ID, 'crjb_end_time', true );
    $playlist   = get_post_meta( $post->ID, 'crjb_playlist', true );
    $genre      = get_post_meta( $post->ID, 'crjb_genre', true );
    $artist     = get_post_meta( $post->ID, 'crjb_artist', true );
    
    $all_days = ['everyday' => 'Every Day', 'monday' => 'Monday', 'tuesday' => 'Tuesday', 'wednesday' => 'Wednesday', 'thursday' => 'Thursday', 'friday' => 'Friday', 'saturday' => 'Saturday', 'sunday' => 'Sunday'];
    ?>
    <table class="form-table">
        <tr>
            <th><label>Active Days</label></th>
            <td>
                <?php foreach($all_days as $val => $label): ?>
                    <label style="margin-right: 15px;">
                        <input type="checkbox" name="crjb_days[]" value="<?php echo esc_attr($val); ?>" <?php checked(in_array($val, $days)); ?> /> <?php echo esc_html($label); ?>
                    </label>
                <?php endforeach; ?>
            </td>
        </tr>
        <tr>
            <th><label>Start Time</label></th>
            <td><input type="time" name="crjb_start_time" value="<?php echo esc_attr($start_time); ?>" required /></td>
        </tr>
        <tr>
            <th><label>End Time</label></th>
            <td>
                <input type="time" name="crjb_end_time" value="<?php echo esc_attr($end_time); ?>" required />
                <p class="description">If End Time is earlier than Start Time, the schedule assumes it crosses midnight.</p>
            </td>
        </tr>
        <tr><td colspan="2"><hr><strong>Target Routing</strong> (Type or auto complete tags to lock the event).</td></tr>
        <tr>
            <th><label>Playlists</label></th>
            <td><?php crjb_render_tax_select_field('crjb_playlist', $playlist, 'crjb_playlist_arr', 'Select playlists...'); ?></td>
        </tr>
        <tr>
            <th><label>Genres</label></th>
            <td><?php crjb_render_tax_select_field('crjb_genre', $genre, 'crjb_genre_arr', 'Select genres...'); ?></td>
        </tr>
        <tr>
            <th><label>Artists</label></th>
            <td><?php crjb_render_tax_select_field('crjb_artist', $artist, 'crjb_artist_arr', 'Select artists...'); ?></td>
        </tr>
    </table>
    <?php
}

add_action( 'save_post', 'crjb_save_custom_meta_data' );
function crjb_save_custom_meta_data( $post_id ) {
    if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    $post_type = get_post_type($post_id);

    if ( $post_type === 'crjb_song' ) {
        if ( ! isset( $_POST['crjb_song_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['crjb_song_meta_nonce'] ) ), 'crjb_save_song_data' ) ) {
            return;
        }

        update_option('crjb_catalog_version', time(), false);
        
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce is verified at the top of the block.
        $always_available = isset($_POST['crjb_always_available']) ? 1 : 0;
        update_post_meta($post_id, 'crjb_always_available', $always_available);
        
        $play_globally = isset($_POST['crjb_play_globally']) ? 1 : 0;
        update_post_meta($post_id, 'crjb_play_globally', $play_globally);
        
        $is_explicit = isset($_POST['crjb_is_explicit']) ? 1 : 0;
        update_post_meta($post_id, 'crjb_is_explicit', $is_explicit);
        
        $is_royalty_free = isset($_POST['crjb_royalty_free']) ? 1 : 0;
        update_post_meta($post_id, 'crjb_royalty_free', $is_royalty_free);
        
        $license_override = isset($_POST['crjb_license_override']) ? 1 : 0;
        update_post_meta($post_id, 'crjb_license_override', $license_override);
        
        if ( isset($_POST['crjb_tip_url']) ) {
            update_post_meta($post_id, 'crjb_tip_url', esc_url_raw(wp_unslash($_POST['crjb_tip_url'])));
        }
        
        if ( isset($_POST['crjb_custom_banner_text']) ) {
            $new_banner = wp_kses_post(wp_unslash($_POST['crjb_custom_banner_text']));
            update_post_meta($post_id, 'crjb_custom_banner_text', $new_banner);
        }
        
        if ( isset($_POST['crjb_lyrics']) ) {
            update_post_meta($post_id, 'crjb_lyrics', sanitize_textarea_field(wp_unslash($_POST['crjb_lyrics'])));
        }
        
        if ( isset($_POST['preview_url']) ) update_post_meta($post_id, 'preview_url', esc_url_raw(wp_unslash($_POST['preview_url'])));
        
        if ( !empty($_POST['crjb_audio_attachment_id']) ) {
            $id = intval(wp_unslash($_POST['crjb_audio_attachment_id']));
            $url = wp_get_attachment_url($id);
            if ($url) {
                update_post_meta($post_id, 'crjb_audio_attachment_id', $id);
                update_post_meta($post_id, 'full_audio_url', esc_url_raw($url));
                require_once( ABSPATH . 'wp-admin/includes/media.php' );
                $meta = wp_read_audio_metadata( get_attached_file($id) );
                if (!empty($meta['length'])) update_post_meta($post_id, 'audio_duration', ceil($meta['length']));
            }
        }

        $process_memo = function($attachment_key, $url_key, $duration_key) use ($post_id) {
            if (!empty($_POST[$attachment_key])) {
                $id = intval(wp_unslash($_POST[$attachment_key]));
                $url = wp_get_attachment_url($id);
                if ($url) {
                    update_post_meta($post_id, $url_key, esc_url_raw($url));
                    require_once( ABSPATH . 'wp-admin/includes/media.php' );
                    $meta = wp_read_audio_metadata(get_attached_file($id));
                    if (!empty($meta['length'])) update_post_meta($post_id, $duration_key, ceil($meta['length']));
                }
            } elseif (isset($_POST[$url_key]) && empty($_POST[$url_key])) {
                delete_post_meta($post_id, $url_key);
                delete_post_meta($post_id, $duration_key);
            }
        };

        $process_memo('crjb_intro_attachment_id', 'intro_audio_url', 'intro_duration');
        $process_memo('crjb_outro_attachment_id', 'outro_audio_url', 'outro_duration');
        // phpcs:enable

    } elseif ( $post_type === 'crjb_schedule' ) {
        if ( ! isset( $_POST['crjb_schedule_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['crjb_schedule_meta_nonce'] ) ), 'crjb_save_schedule_data' ) ) {
            return;
        }

        update_option('crjb_catalog_version', time(), false);
        
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce is verified at the top of the block.
        $days = isset($_POST['crjb_days']) ? array_map('sanitize_text_field', wp_unslash($_POST['crjb_days'])) : [];
        update_post_meta($post_id, 'crjb_days', $days);
        
        if ( isset($_POST['crjb_start_time']) ) update_post_meta($post_id, 'crjb_start_time', sanitize_text_field(wp_unslash($_POST['crjb_start_time'])));
        if ( isset($_POST['crjb_end_time']) )   update_post_meta($post_id, 'crjb_end_time', sanitize_text_field(wp_unslash($_POST['crjb_end_time'])));
        
        if ( isset($_POST['crjb_playlist_arr']) ) {
            update_post_meta($post_id, 'crjb_playlist', implode(',', array_map('sanitize_text_field', wp_unslash($_POST['crjb_playlist_arr']))));
        } else {
            update_post_meta($post_id, 'crjb_playlist', '');
        }
        
        if ( isset($_POST['crjb_genre_arr']) ) {
            update_post_meta($post_id, 'crjb_genre', implode(',', array_map('sanitize_text_field', wp_unslash($_POST['crjb_genre_arr']))));
        } else {
            update_post_meta($post_id, 'crjb_genre', '');
        }
        
        if ( isset($_POST['crjb_artist_arr']) ) {
            update_post_meta($post_id, 'crjb_artist', implode(',', array_map('sanitize_text_field', wp_unslash($_POST['crjb_artist_arr']))));
        } else {
            update_post_meta($post_id, 'crjb_artist', '');
        }
        // phpcs:enable
    }
}

add_action('trashed_post', function($post_id) {
    if(in_array(get_post_type($post_id), ['crjb_song', 'crjb_schedule'])) update_option('crjb_catalog_version', time(), false);
});

// ==========================================
// 4. NETWORK LOGIC & SMART ROUTING 
// ==========================================

function crjb_validate_station_id($station_id) {
    if ($station_id === 'global') return 'global';
    // Strict Database Lock: Even if the format matches, ensure the site owner actually created this station.
    if (preg_match('/^station_[a-f0-9]{10}$/', $station_id)) {
        if (get_option('crjb_station_args_' . $station_id) !== false) {
            return $station_id;
        }
    }
    return 'global';
}

function crjb_get_explicit_meta_query() {
    if (!get_option('crjb_allow_explicit', 1)) {
        // phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
        return [
            'relation' => 'OR',
            ['key' => 'crjb_is_explicit', 'compare' => 'NOT EXISTS'],
            ['key' => 'crjb_is_explicit', 'value' => '1', 'compare' => '!=']
        ];
        // phpcs:enable
    }
    return [];
}

function crjb_get_license_meta_query() {
    if (get_option('crjb_exclude_licensed', 0)) {
        // phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
        return [
            'relation' => 'OR',
            ['key' => 'crjb_royalty_free', 'value' => '1', 'compare' => '='],
            ['key' => 'crjb_license_override', 'value' => '1', 'compare' => '=']
        ];
        // phpcs:enable
    }
    return [];
}

function crjb_get_active_schedule() {
    $now = current_datetime();
    $current_day = strtolower($now->format('l'));
    $current_time = $now->format('H:i');

    $schedules = get_posts(['post_type' => 'crjb_schedule', 'posts_per_page' => -1]);
    foreach($schedules as $sched) {
        $days = get_post_meta($sched->ID, 'crjb_days', true) ?: [];
        if (!is_array($days)) $days = [$days];
        
        $start = get_post_meta($sched->ID, 'crjb_start_time', true);
        $end = get_post_meta($sched->ID, 'crjb_end_time', true);

        if (empty($days) || empty($start) || empty($end)) continue;

        if (in_array($current_day, $days) || in_array('everyday', $days)) {
            $is_active = false;
            if ($start < $end) {
                $is_active = ($current_time >= $start && $current_time <= $end);
            } else {
                $is_active = ($current_time >= $start || $current_time <= $end);
            }

            if ($is_active) {
                return [
                    'id' => $sched->ID,
                    'title' => get_the_title($sched->ID),
                    'playlist' => get_post_meta($sched->ID, 'crjb_playlist', true),
                    'genre' => get_post_meta($sched->ID, 'crjb_genre', true),
                    'artist' => get_post_meta($sched->ID, 'crjb_artist', true),
                ];
            }
        }
    }
    return null;
}

function crjb_song_matches_schedule($post_id, $sched_id) {
    $playlist = get_post_meta($sched_id, 'crjb_playlist', true);
    $artist   = get_post_meta($sched_id, 'crjb_artist', true);
    $genre    = get_post_meta($sched_id, 'crjb_genre', true);
    
    if (empty($playlist) && empty($artist) && empty($genre)) return true;

    $match = true;
    if (!empty($playlist)) {
        $terms = array_map('sanitize_title', explode(',', $playlist));
        if (!has_term($terms, 'crjb_playlist', $post_id)) $match = false;
    }
    if (!empty($artist) && $match) {
        $terms = array_map('sanitize_title', explode(',', $artist));
        if (!has_term($terms, 'crjb_artist', $post_id)) $match = false;
    }
    if (!empty($genre) && $match) {
        $terms = array_map('sanitize_title', explode(',', $genre));
        if (!has_term($terms, 'crjb_genre', $post_id)) $match = false;
    }
    return $match;
}

function crjb_get_next_schedule_timestamp($sched_id) {
    $days = get_post_meta($sched_id, 'crjb_days', true) ?: [];
    if (!is_array($days)) $days = [$days];
    $start = get_post_meta($sched_id, 'crjb_start_time', true);
    if (empty($days) || empty($start)) return false;

    $now = current_datetime();
    $tz = wp_timezone();
    $now_ts = $now->getTimestamp();

    $today_name = strtolower($now->format('l'));
    if (in_array($today_name, $days) || in_array('everyday', $days)) {
        $today_run = (new DateTime($now->format('Y-m-d') . ' ' . $start, $tz))->getTimestamp();
        if ($today_run > $now_ts) {
            return $today_run;
        }
    }

    for ($i = 1; $i <= 7; $i++) {
        $check_date = $now->modify("+$i days");
        $check_day_name = strtolower($check_date->format('l'));
        if (in_array($check_day_name, $days) || in_array('everyday', $days)) {
            return (new DateTime($check_date->format('Y-m-d') . ' ' . $start, $tz))->getTimestamp();
        }
    }
    return false;
}

function crjb_get_base_station_args($station_id) {
    if ($station_id === 'global') return [];
    
    // phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_tax_query
    $tax_query = ['relation' => 'AND'];
    $active_atts = get_option('crjb_station_args_' . $station_id, []);
    
    if (empty($active_atts)) {
        $parts = explode('_', $station_id, 2);
        if (count($parts) === 2) {
            $tax = '';
            if ($parts[0] === 'playlist') $tax = 'crjb_playlist';
            if ($parts[0] === 'artist') $tax = 'crjb_artist';
            if ($parts[0] === 'genre') $tax = 'crjb_genre';
            if ($tax) return ['tax_query' => [ [ 'taxonomy' => $tax, 'field' => 'slug', 'terms' => sanitize_title($parts[1]) ] ]];
        }
        return [];
    }

    if (!empty($active_atts['playlist'])) $tax_query[] = [ 'taxonomy' => 'crjb_playlist', 'field' => 'slug', 'terms' => array_map('sanitize_title', explode(',', $active_atts['playlist'])), 'operator' => 'IN' ];
    if (!empty($active_atts['artist'])) $tax_query[] = [ 'taxonomy' => 'crjb_artist', 'field' => 'slug', 'terms' => array_map('sanitize_title', explode(',', $active_atts['artist'])), 'operator' => 'IN' ];
    if (!empty($active_atts['genre'])) $tax_query[] = [ 'taxonomy' => 'crjb_genre', 'field' => 'slug', 'terms' => array_map('sanitize_title', explode(',', $active_atts['genre'])), 'operator' => 'IN' ];

    if (count($tax_query) > 1) return ['tax_query' => $tax_query];
    return [];
    // phpcs:enable
}

function crjb_get_current_station_args($station_id) {
    if ($station_id === 'global') {
        $schedule = crjb_get_active_schedule();
        if ($schedule) {
            // phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_tax_query
            $tax_query = ['relation' => 'AND'];
            if (!empty($schedule['playlist'])) $tax_query[] = [ 'taxonomy' => 'crjb_playlist', 'field' => 'slug', 'terms' => array_map('sanitize_title', explode(',', $schedule['playlist'])), 'operator' => 'IN' ];
            if (!empty($schedule['artist'])) $tax_query[] = [ 'taxonomy' => 'crjb_artist', 'field' => 'slug', 'terms' => array_map('sanitize_title', explode(',', $schedule['artist'])), 'operator' => 'IN' ];
            if (!empty($schedule['genre'])) $tax_query[] = [ 'taxonomy' => 'crjb_genre', 'field' => 'slug', 'terms' => array_map('sanitize_title', explode(',', $schedule['genre'])), 'operator' => 'IN' ];
            if (count($tax_query) > 1) return ['tax_query' => $tax_query];
            return [];
            // phpcs:enable
        }
    }
    return crjb_get_base_station_args($station_id);
}

function crjb_get_station_label($station_id) {
    if ($station_id === 'global') {
        $schedule = crjb_get_active_schedule();
        if ($schedule) return 'LIVE: ' . $schedule['title'];
        
        if (get_option('crjb_strict_event_mode')) {
            // phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
            $has_overrides = get_posts([
                'post_type' => 'crjb_song',
                'meta_query' => [
                    ['key' => 'crjb_always_available', 'value' => '1', 'compare' => '=']
                ],
                'posts_per_page' => 1,
                'fields' => 'ids'
            ]);
            // phpcs:enable
            if ($has_overrides) return 'Global Broadcast';
            return 'Requests Offline (No Event)';
        }
        return 'Global Broadcast';
    }
    
    $active_atts = get_option('crjb_station_args_' . $station_id, []);
    if (!empty($active_atts)) {
        $labels = [];
        if (!empty($active_atts['playlist'])) $labels[] = 'PL: ' . esc_html($active_atts['playlist']);
        if (!empty($active_atts['artist'])) $labels[] = 'Artist: ' . esc_html($active_atts['artist']);
        if (!empty($active_atts['genre'])) $labels[] = 'Genre: ' . esc_html($active_atts['genre']);
        
        $station_label = implode(' | ', $labels);
        if (strlen($station_label) > 40) return substr($station_label, 0, 37) . '...';
        return $station_label;
    }
    return $station_id; 
}

function crjb_get_open_play_fallback($query_args, $all_schedules) {
    $query_args['posts_per_page'] = 30; 
    $potential_fallbacks = get_posts($query_args);
    foreach ($potential_fallbacks as $pf) {
        $is_event_song = false;
        if (!get_post_meta($pf->ID, 'crjb_always_available', true)) {
            foreach ($all_schedules as $sched) {
                if (crjb_song_matches_schedule($pf->ID, $sched->ID)) {
                    $is_event_song = true;
                    break;
                }
            }
        }
        if (!$is_event_song) {
            return [$pf];
        }
    }
    return false;
}

function crjb_process_queue_and_get_current($station_id = 'global') {
    $now = time(); 
    $current = get_option("crjb_now_playing_sync_{$station_id}"); 
    $active_listeners_count = count(get_option("crjb_active_listeners_{$station_id}", []));
    
    if ( !$current || $now >= ($current['start_time'] + $current['duration']) ) {
        $history = get_option("crjb_play_history_{$station_id}", []);

        if ($current) {
            $actual_finish_time = $current['start_time'] + $current['duration'];
            $history[$current['id']] = $actual_finish_time;
            
            $month_key = wp_date('Y_m', $actual_finish_time);
            $month_log_option = 'crjb_broadcast_log_' . $month_key;
            $broadcast_log = get_option($month_log_option, []);
            
            $broadcast_log[] = [
                'station' => $station_id,
                'id' => $current['id'],
                'title' => get_the_title($current['id']),
                'artist' => implode(', ', wp_get_post_terms($current['id'], 'crjb_artist', ['fields' => 'names']) ?: ['Unknown']),
                'start_time' => $current['start_time'],
                'end_time' => $actual_finish_time,
                'listeners' => $current['listeners_at_start'] ?? $active_listeners_count
            ];

            $available_months = get_option('crjb_broadcast_log_months', []);
            if (!in_array($month_key, $available_months)) {
                $available_months[] = $month_key;
                rsort($available_months); 
                update_option('crjb_broadcast_log_months', $available_months, false);
            }

            update_option($month_log_option, array_values($broadcast_log), false);

            foreach($history as $id => $time) if($now - $time > 3600) unset($history[$id]); 
            update_option("crjb_play_history_{$station_id}", $history, false);
        }

        $queue = get_transient("crjb_active_queue_{$station_id}") ?: [];
        
        if (!get_option('crjb_allow_explicit', 1)) {
            foreach($queue as $qid => $qdata) {
                if (get_post_meta($qid, 'crjb_is_explicit', true)) unset($queue[$qid]);
            }
        }

        if (get_option('crjb_exclude_licensed', 0)) {
            foreach($queue as $qid => $qdata) {
                if (!get_post_meta($qid, 'crjb_royalty_free', true) && !get_post_meta($qid, 'crjb_license_override', true)) {
                    unset($queue[$qid]);
                }
            }
        }
        
        if ( !empty($queue) ) {
            uasort($queue, function($a, $b){ return $a['votes'] == $b['votes'] ? $a['added'] <=> $b['added'] : $b['votes'] <=> $a['votes']; });
            $id = array_key_first($queue); unset($queue[$id]); set_transient("crjb_active_queue_{$station_id}", $queue, 12 * HOUR_IN_SECONDS);
            
            $intro_dur = get_post_meta($id, 'intro_duration', true) ?: 0;
            $outro_dur = get_post_meta($id, 'outro_duration', true) ?: 0;
            $song_dur  = get_post_meta($id, 'audio_duration', true) ?: 180;

            $current = [
                'id' => $id, 
                'start_time' => $now, 
                'duration' => $intro_dur + $song_dur + $outro_dur, 
                'url' => get_post_meta($id, 'full_audio_url', true),
                'listeners_at_start' => $active_listeners_count
            ];
            update_option("crjb_now_playing_sync_{$station_id}", $current, false);
        } else {
            $history_keys = !empty($history) ? array_keys($history) : [0];
            
            // phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
            // phpcs:disable WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in
            $query_args = [
                'post_type' => 'crjb_song', 
                'posts_per_page' => 1, 
                'orderby' => 'rand', 
                'post__not_in' => $history_keys, 
                'meta_query' => [['key' => 'full_audio_url', 'value' => '', 'compare' => '!=']]
            ];
            
            $explicit_block = crjb_get_explicit_meta_query();
            if (!empty($explicit_block)) $query_args['meta_query'][] = $explicit_block;
            
            $license_block = crjb_get_license_meta_query();
            if (!empty($license_block)) $query_args['meta_query'][] = $license_block;
            
            $station_args = crjb_get_current_station_args($station_id);
            $is_open_play = ($station_id === 'global' && !crjb_get_active_schedule() && !get_option('crjb_strict_event_mode'));
            $all_schedules = $is_open_play ? get_posts(['post_type' => 'crjb_schedule', 'posts_per_page' => -1]) : [];
            
            if ($station_id === 'global' && !crjb_get_active_schedule()) {
                if (get_option('crjb_strict_event_mode')) {
                    $query_args['meta_query'][] = ['key' => 'crjb_always_available', 'value' => '1', 'compare' => '='];
                    $fallback = get_posts($query_args);
                } else {
                    $global_args = $query_args;
                    $global_args['meta_query'][] = ['key' => 'crjb_play_globally', 'value' => '1', 'compare' => '='];
                    $fallback = get_posts($global_args);
                    
                    if (!$fallback) {
                        $fallback = crjb_get_open_play_fallback($query_args, $all_schedules);
                    }
                }
            } else {
                if (!empty($station_args)) $query_args = array_merge($query_args, $station_args);
                $fallback = get_posts($query_args);
            }
            
            if (!$fallback && ($station_id !== 'global' || crjb_get_active_schedule() || !get_option('crjb_strict_event_mode'))) {
                $last_id = $current ? $current['id'] : 0; 
                $history = []; 
                if ($last_id) $history[$last_id] = $now; 
                update_option("crjb_play_history_{$station_id}", $history, false);
                
                $query_args['post__not_in'] = [$last_id];
                $fallback = $is_open_play ? crjb_get_open_play_fallback($query_args, $all_schedules) : get_posts($query_args);
                
                if (!$fallback) {
                    unset($query_args['post__not_in']);
                    $fallback = $is_open_play ? crjb_get_open_play_fallback($query_args, $all_schedules) : get_posts($query_args);
                }
            }
            // phpcs:enable
            
            if ($fallback) {
                $id = $fallback[0]->ID;
                
                $intro_dur = get_post_meta($id, 'intro_duration', true) ?: 0;
                $outro_dur = get_post_meta($id, 'outro_duration', true) ?: 0;
                $song_dur  = get_post_meta($id, 'audio_duration', true) ?: 180;

                $current = [
                    'id' => $id, 
                    'start_time' => $now, 
                    'duration' => $intro_dur + $song_dur + $outro_dur, 
                    'url' => get_post_meta($id, 'full_audio_url', true),
                    'listeners_at_start' => $active_listeners_count
                ];
                update_option("crjb_now_playing_sync_{$station_id}", $current, false);
            } else {
                $current = null; 
            }
        }
    }
    return $current;
}

add_action( 'wp_ajax_crjb_vote', 'crjb_handle_vote' );
add_action( 'wp_ajax_nopriv_crjb_vote', 'crjb_handle_vote' );
function crjb_handle_vote() {
    if ( ! isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST' ) wp_send_json_error('Invalid request method.');
    
    // phpcs:disable WordPress.Security.NonceVerification.Missing -- Public voting endpoint, protected by session rate-limiting to allow edge caching.
    $station_id = isset($_POST['station']) ? sanitize_text_field(wp_unslash($_POST['station'])) : 'global';
    $station_id = crjb_validate_station_id($station_id);

    $id = isset($_POST['song_id']) ? intval(wp_unslash($_POST['song_id'])) : 0; 
    // phpcs:enable

    $now = time(); 
    
    if (!get_option('crjb_allow_explicit', 1) && get_post_meta($id, 'crjb_is_explicit', true)) {
        wp_send_json_error('This track contains explicit content and is currently disabled by the venue.');
    }

    if (get_option('crjb_exclude_licensed', 0) && !get_post_meta($id, 'crjb_royalty_free', true) && !get_post_meta($id, 'crjb_license_override', true)) {
        wp_send_json_error('Licensed music is currently disabled globally by the venue.');
    }
    
    if ($station_id === 'global') {
        $active_schedule = crjb_get_active_schedule();
        $is_always_available = get_post_meta($id, 'crjb_always_available', true);
        
        if ( ! $active_schedule ) {
            if ( get_option('crjb_strict_event_mode') && ! $is_always_available ) {
                wp_send_json_error('The request line is currently closed. This song can only be requested during a scheduled event.');
            } elseif ( ! get_option('crjb_strict_event_mode') && ! $is_always_available ) {
                $all_schedules = get_posts(['post_type' => 'crjb_schedule', 'posts_per_page' => -1]);
                foreach($all_schedules as $sched) {
                    if (crjb_song_matches_schedule($id, $sched->ID)) {
                        wp_send_json_error('This is an event exclusive track and can only be requested during its scheduled event.');
                    }
                }
            }
        } else {
            if ( ! crjb_song_matches_schedule($id, $active_schedule['id']) && ! $is_always_available ) {
                wp_send_json_error('This song is locked until its specific event block is live.');
            }
        }
    }
    
    if (!session_id()) session_start();
    
    $current = get_option("crjb_now_playing_sync_{$station_id}");
    if ($current && $current['id'] == $id) wp_send_json_error('This song is currently playing on the air.');
    
    $history = get_option("crjb_play_history_{$station_id}", []);
    if (isset($history[$id]) && ($now - $history[$id] < 3600)) wp_send_json_error('This song has played recently. Please wait for the cooldown.');
    
    $session_key = "crjb_vote_times_{$station_id}";
    $user_history = isset($_SESSION[$session_key]) && is_array($_SESSION[$session_key]) ? array_map('intval', wp_unslash($_SESSION[$session_key])) : [];
    foreach($user_history as $k => $time) if($now - $time > 3600) unset($user_history[$k]);
    if (count($user_history) >= 10) wp_send_json_error('You have reached your 10 vote limit for this hour on this station.');
    
    $user_history[] = $now; 
    $_SESSION[$session_key] = $user_history;
    session_write_close(); 
    
    $queue = get_transient("crjb_active_queue_{$station_id}") ?: [];
    if(isset($queue[$id])) $queue[$id]['votes']++; else $queue[$id] = ['votes' => 1, 'added' => $now];
    set_transient("crjb_active_queue_{$station_id}", $queue, 12 * HOUR_IN_SECONDS); wp_send_json_success('Vote counted!');
}

add_action( 'wp_ajax_crjb_get_state', 'crjb_get_state' );
add_action( 'wp_ajax_nopriv_crjb_get_state', 'crjb_get_state' );
function crjb_get_state() {
    if ( ! isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'GET' ) wp_send_json_error('Invalid request method.');
    
    $now = time(); 
    
    // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Public read-only state endpoint, relies on edge caching.
    $lid = isset($_GET['listener_id']) ? sanitize_text_field(wp_unslash($_GET['listener_id'])) : '';
    $is_listening = isset($_GET['is_listening']) ? sanitize_text_field(wp_unslash($_GET['is_listening'])) : 'false';
    
    $station_id = isset($_GET['station']) ? sanitize_text_field(wp_unslash($_GET['station'])) : 'global';
    // phpcs:enable
    
    $station_id = crjb_validate_station_id($station_id);

    $listeners = get_option("crjb_active_listeners_{$station_id}", []);
    if ($lid) { if($is_listening === 'true') $listeners[$lid] = $now; else unset($listeners[$lid]); }
    foreach($listeners as $k => $v) if($now - $v > 15) unset($listeners[$k]);
    update_option("crjb_active_listeners_{$station_id}", $listeners, false);

    $cp = crjb_process_queue_and_get_current($station_id);
    $q = get_transient("crjb_active_queue_{$station_id}") ?: [];
    uasort($q, function($a, $b){ return $a['votes'] == $b['votes'] ? $a['added'] <=> $b['added'] : $b['votes'] <=> $a['votes']; });
    $fq = [];
    foreach($q as $sid => $d) {
        $custom_banner = get_post_meta($sid, 'crjb_custom_banner_text', true);
        $submitter_terms = wp_get_post_terms($sid, 'crjb_submitter', ['fields' => 'names']);
        $submitter = !empty($submitter_terms) ? implode(', ', $submitter_terms) : '';
        $banner = !empty($custom_banner) ? $custom_banner : (!empty($submitter) ? 'Submitted by: ' . $submitter : '');

        $fq[] = ['id' => $sid, 'title' => html_entity_decode(get_the_title($sid)), 'artist' => html_entity_decode(implode(', ', wp_get_post_terms($sid, 'crjb_artist', ['fields' => 'names']) ?: ['Unknown'])), 'is_explicit' => get_post_meta($sid, 'crjb_is_explicit', true) ? true : false, 'has_lyrics' => !empty(get_post_meta($sid, 'crjb_lyrics', true)), 'banner' => $banner, 'tip_url' => get_post_meta($sid, 'crjb_tip_url', true), 'genre' => html_entity_decode(implode(', ', wp_get_post_terms($sid, 'crjb_genre', ['fields' => 'names']) ?: []), ENT_QUOTES, 'UTF-8'), 'votes' => $d['votes'], 'preview_url' => get_post_meta($sid, 'preview_url', true), 'url' => get_post_meta($sid, 'full_audio_url', true), 'permalink' => get_permalink($sid) ];
    }
    
    $np = null;
    if ($cp) {
        $custom_banner_np = get_post_meta($cp['id'], 'crjb_custom_banner_text', true);
        $submitter_terms_np = wp_get_post_terms($cp['id'], 'crjb_submitter', ['fields' => 'names']);
        $submitter_np = !empty($submitter_terms_np) ? implode(', ', $submitter_terms_np) : '';
        $banner_np = !empty($custom_banner_np) ? $custom_banner_np : (!empty($submitter_np) ? 'Submitted by: ' . $submitter_np : '');

        $np = [
            'id' => $cp['id'], 
            'title' => html_entity_decode(get_the_title($cp['id'])), 
            'artist' => html_entity_decode(implode(', ', wp_get_post_terms($cp['id'], 'crjb_artist', ['fields' => 'names']) ?: ['Unknown'])), 
            'is_explicit' => get_post_meta($cp['id'], 'crjb_is_explicit', true) ? true : false, 
            'has_lyrics' => !empty(get_post_meta($cp['id'], 'crjb_lyrics', true)), 
            'banner' => $banner_np, 
            'tip_url' => get_post_meta($cp['id'], 'crjb_tip_url', true), 
            'url' => $cp['url'], 
            'intro_url' => get_post_meta($cp['id'], 'intro_audio_url', true),
            'outro_url' => get_post_meta($cp['id'], 'outro_audio_url', true),
            'intro_duration' => get_post_meta($cp['id'], 'intro_duration', true) ?: 0,
            'song_duration' => get_post_meta($cp['id'], 'audio_duration', true) ?: 180,
            'outro_duration' => get_post_meta($cp['id'], 'outro_duration', true) ?: 0,
            'permalink' => get_permalink($cp['id']), 
            'start_timestamp' => $cp['start_time'], 
            'duration' => $cp['duration'], 
            'server_now' => $now 
        ];
    }
    $cat_version = get_option('crjb_catalog_version', 0);
    
    $all_schedules = get_posts(['post_type' => 'crjb_schedule', 'posts_per_page' => -1]);
    $upcoming_events = [];
    foreach($all_schedules as $sched) {
        $next_run = crjb_get_next_schedule_timestamp($sched->ID);
        if ($next_run) {
            $upcoming_events[] = [
                'title' => get_the_title($sched->ID),
                'timestamp' => $next_run,
                'start_time' => get_post_meta($sched->ID, 'crjb_start_time', true),
                'end_time' => get_post_meta($sched->ID, 'crjb_end_time', true)
            ];
        }
    }
    usort($upcoming_events, function($a, $b) { return $a['timestamp'] <=> $b['timestamp']; });
    $sliced_events = array_slice($upcoming_events, 0, 20);
    
    wp_send_json_success([
        'now_playing' => $np, 
        'queue' => $fq, 
        'listener_count' => count($listeners), 
        'catalog_version' => $cat_version, 
        'station_label' => crjb_get_station_label($station_id),
        'upcoming_events' => $sliced_events
    ]);
}

add_action( 'wp_ajax_crjb_get_catalog', 'crjb_get_catalog' );
add_action( 'wp_ajax_nopriv_crjb_get_catalog', 'crjb_get_catalog' );
function crjb_get_catalog() {
    if ( ! isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'GET' ) wp_send_json_error('Invalid request method.');
    
    // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Public read-only catalog endpoint, relies on edge caching.
    $station_id = isset($_GET['station']) ? sanitize_text_field(wp_unslash($_GET['station'])) : 'global';
    // phpcs:enable
    
    $station_id = crjb_validate_station_id($station_id);
    
    // phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
    $query_args = ['post_type' => 'crjb_song', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC', 'meta_query' => []];
    
    $explicit_block = crjb_get_explicit_meta_query();
    if (!empty($explicit_block)) $query_args['meta_query'][] = $explicit_block;
    
    $license_block = crjb_get_license_meta_query();
    if (!empty($license_block)) $query_args['meta_query'][] = $license_block;
    
    $station_args = crjb_get_base_station_args($station_id);
    if (!empty($station_args)) {
        $query_args = array_merge($query_args, $station_args);
    }
    
    $songs = get_posts($query_args);
    // phpcs:enable
    
    $history = get_option("crjb_play_history_{$station_id}", []); 
    $current = get_option("crjb_now_playing_sync_{$station_id}"); 
    $now = time(); 
    $cat = [];
    
    $active_schedule = ($station_id === 'global') ? crjb_get_active_schedule() : null;
    $strict_mode = get_option('crjb_strict_event_mode');
    $all_schedules = [];
    $schedule_runs = [];
    
    if ($station_id === 'global') {
        $all_schedules = get_posts(['post_type' => 'crjb_schedule', 'posts_per_page' => -1]);
        foreach($all_schedules as $sched) {
            $schedule_runs[$sched->ID] = [
                'title' => get_the_title($sched->ID),
                'next_run' => crjb_get_next_schedule_timestamp($sched->ID)
            ];
        }
    }
    
    foreach($songs as $p) {
        $last_play = $history[$p->ID] ?? 0; 
        $remaining = ($now - $last_play < 3600) ? 3600 - ($now - $last_play) : 0; 
        $is_playing = ($current && $current['id'] == $p->ID);
        $is_always_available = get_post_meta($p->ID, 'crjb_always_available', true);
        $is_explicit = get_post_meta($p->ID, 'crjb_is_explicit', true) ? true : false;
        $has_lyrics = !empty(get_post_meta($p->ID, 'crjb_lyrics', true));

        $custom_banner = get_post_meta($p->ID, 'crjb_custom_banner_text', true);
        $submitter_terms = wp_get_post_terms($p->ID, 'crjb_submitter', ['fields' => 'names']);
        $submitter = !empty($submitter_terms) ? implode(', ', $submitter_terms) : '';
        $banner = !empty($custom_banner) ? $custom_banner : (!empty($submitter) ? 'Submitted by: ' . $submitter : '');
        
        $is_locked_by_schedule = false;
        $unlock_msg = '';
        $unlock_timestamp = null;

        if ($station_id === 'global') {
            if ($active_schedule) {
                if (!crjb_song_matches_schedule($p->ID, $active_schedule['id']) && !$is_always_available) {
                    $is_locked_by_schedule = true;
                    $closest_time = PHP_INT_MAX;
                    $next_sched_name = '';
                    
                    foreach($all_schedules as $sched) {
                        if ($sched->ID == $active_schedule['id']) continue;
                        if (crjb_song_matches_schedule($p->ID, $sched->ID)) {
                            $run = $schedule_runs[$sched->ID]['next_run'];
                            if ($run && $run < $closest_time) {
                                $closest_time = $run;
                                $next_sched_name = $schedule_runs[$sched->ID]['title'];
                            }
                        }
                    }
                    if ($next_sched_name) {
                        $unlock_msg = "Unlocks at " . $next_sched_name;
                        $unlock_timestamp = $closest_time;
                    } else if (!$strict_mode) {
                        $unlock_msg = "Unlocks at Open Play";
                    } else {
                        $unlock_msg = "Event Locked";
                    }
                }
            } else {
                if ($strict_mode && !$is_always_available) {
                    $is_locked_by_schedule = true;
                    $closest_time = PHP_INT_MAX;
                    $next_sched_name = '';
                    
                    foreach($all_schedules as $sched) {
                        if (crjb_song_matches_schedule($p->ID, $sched->ID)) {
                            $run = $schedule_runs[$sched->ID]['next_run'] ?? crjb_get_next_schedule_timestamp($sched->ID);
                            if ($run && $run < $closest_time) {
                                $closest_time = $run;
                                $next_sched_name = get_the_title($sched->ID);
                            }
                        }
                    }
                    if ($next_sched_name) {
                        $unlock_msg = "Unlocks at " . $next_sched_name;
                        $unlock_timestamp = $closest_time;
                    } else {
                        $unlock_msg = "Event Locked";
                    }
                } elseif (!$strict_mode && !$is_always_available) {
                    $belongs_to_schedule = false;
                    $closest_time = PHP_INT_MAX;
                    $next_sched_name = '';
                    
                    foreach($all_schedules as $sched) {
                        if (crjb_song_matches_schedule($p->ID, $sched->ID)) {
                            $belongs_to_schedule = true;
                            $run = $schedule_runs[$sched->ID]['next_run'] ?? crjb_get_next_schedule_timestamp($sched->ID);
                            if ($run && $run < $closest_time) {
                                $closest_time = $run;
                                $next_sched_name = get_the_title($sched->ID);
                            }
                        }
                    }
                    if ($belongs_to_schedule) {
                        $is_locked_by_schedule = true;
                        if ($next_sched_name) {
                            $unlock_msg = "Unlocks at " . $next_sched_name;
                            $unlock_timestamp = $closest_time;
                        } else {
                            $unlock_msg = "Event Locked";
                        }
                    }
                }
            }
        }

        $cat[] = [
            'id' => $p->ID, 
            'title' => html_entity_decode($p->post_title), 
            'artist' => html_entity_decode(implode(', ', wp_get_post_terms($p->ID, 'crjb_artist', ['fields' => 'names']) ?: ['Unknown Artist'])), 
            'genre' => html_entity_decode(implode(', ', wp_get_post_terms($p->ID, 'crjb_genre', ['fields' => 'names']) ?: []), ENT_QUOTES, 'UTF-8'), 
            'is_explicit' => $is_explicit,
            'has_lyrics' => $has_lyrics,
            'banner' => $banner,
            'tip_url' => get_post_meta($p->ID, 'crjb_tip_url', true),
            'preview_url' => get_post_meta($p->ID, 'preview_url', true), 
            'url' => get_post_meta($p->ID, 'full_audio_url', true), 
            'permalink' => get_permalink($p->ID),
            'cooldown' => $remaining, 
            'is_playing' => $is_playing,
            'is_locked_by_schedule' => $is_locked_by_schedule,
            'unlock_msg' => $unlock_msg,
            'unlock_timestamp' => ($unlock_timestamp !== null && $unlock_timestamp !== PHP_INT_MAX) ? $unlock_timestamp : null
        ];
    }
    wp_send_json_success(['catalog' => $cat]);
}

// ==========================================
// 5. FRONTEND UI & STYLING
// ==========================================
add_shortcode( 'community_radio_jukebox', 'crjb_render_frontend_app' );
function crjb_render_frontend_app($atts) {
    
    $atts = shortcode_atts([
        'playlist' => '',
        'artist'   => '',
        'genre'    => ''
    ], $atts, 'community_radio_jukebox');
    
    $active_atts = array_filter([
        'playlist' => sanitize_text_field(wp_unslash($atts['playlist'])),
        'artist'   => sanitize_text_field(wp_unslash($atts['artist'])),
        'genre'    => sanitize_text_field(wp_unslash($atts['genre']))
    ]);
    
    $station_id = 'global';
    
    if (!empty($active_atts)) { 
        ksort($active_atts);
        $station_hash = substr(md5(json_encode($active_atts)), 0, 10);
        $station_id = 'station_' . $station_hash;
        add_option('crjb_station_args_' . $station_id, $active_atts, '', false);
    }

    $station_label = crjb_get_station_label($station_id);

    $ajax_url = admin_url( 'admin-ajax.php' );
    $security_nonce = wp_create_nonce( 'crjb_frontend_action' );
    $submit_enabled = get_option('crjb_enable_submissions') == '1';
    $submit_url = get_option('crjb_submission_url');

    ob_start();
    ?>
    <div id="crjb-alert-container"></div>

    <div class="crjb-app-container" id="crjb-app-root" data-theme="light">
        <div style="display:flex; justify-content:space-between; border-bottom:2px solid var(--crjb-border); margin-bottom:20px; padding-bottom:10px; align-items:center; flex-wrap:wrap; gap: 10px;">
            <div>
                <h2 style="margin:0; font-size:22px; display:flex; align-items:center;">
                    <svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="2"></circle><path d="M16.24 7.76a6 6 0 0 1 0 8.49m-8.48-.01a6 6 0 0 1 0-8.49m11.31-2.82a10 10 0 0 1 0 14.14m-14.14 0a10 10 0 0 1 0-14.14"></path></svg> 
                    <span style="margin-left:8px;">JUKEBOX</span>
                </h2>
                <div class="crjb-station-badge" id="crjb-station-badge-text" title="Active Filters"><?php echo esc_html($station_label); ?></div>
            </div>
            <div style="display:flex; gap:15px; font-size:20px; color:var(--crjb-accent);">
                <?php if ($submit_enabled && !empty($submit_url)): ?><a href="<?php echo esc_url($submit_url); ?>" target="_blank" style="color:inherit; text-decoration:none;"><svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg></a><?php endif; ?>
                <div id="crjb-catalog-toggle" style="cursor:pointer; display:flex; align-items:center;" title="Toggle Catalog"><svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg></div>
                <div id="crjb-schedule-toggle" style="cursor:pointer; display:flex; align-items:center;" title="View Schedule"><svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg></div>
                <div id="crjb-info-toggle" style="cursor:pointer; display:flex; align-items:center;" title="How it works"><svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg></div>
                <div id="crjb-theme-toggle" style="cursor:pointer; display:flex; align-items:center;" title="Toggle Theme"><svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg></div>
            </div>
        </div>

        <div class="crjb-dashboard-grid">
            
            <div class="crjb-dashboard-column crjb-sticky-pane">
                
                <div id="crjb-info-panel" style="display:none; background:var(--crjb-bg); border:1px solid var(--crjb-accent); border-radius:12px; padding:15px; font-size:13px; line-height:1.5; box-shadow: inset 0 0 10px rgba(0,0,0,0.05); text-align:left;">
                    <p style="font-weight:800; margin-bottom:10px; font-size:15px; color:var(--crjb-accent); display:flex; align-items:center;"><svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:8px;"><circle cx="12" cy="12" r="2"></circle><path d="M16.24 7.76a6 6 0 0 1 0 8.49m-8.48-.01a6 6 0 0 1 0-8.49m11.31-2.82a10 10 0 0 1 0 14.14m-14.14 0a10 10 0 0 1 0-14.14"></path></svg> Community Radio Jukebox</p>
                    <ul style="padding-left:20px; margin-bottom:0;">
                        <li style="margin-bottom:6px;"><strong>Connect:</strong> Lock your audio exactly in sync with everyone else in town.</li>
                        <li style="margin-bottom:6px;"><strong>Voting:</strong> You get <strong>10 votes per hour</strong>. Use them to boost your favorite tracks.</li>
                        <li style="margin-bottom:6px;"><strong>Offline Mode:</strong> A green checkmark <svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:#28a745; vertical-align:-0.125em;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg> indicates the track is safely cached on your device.</li>
                    </ul>
                </div>

                <div id="crjb-schedule-panel" style="display:none; background:var(--crjb-bg); border:1px solid var(--crjb-accent); border-radius:12px; padding:15px; font-size:13px; line-height:1.5; box-shadow: inset 0 0 10px rgba(0,0,0,0.05); text-align:left;">
                    <h3 style="margin-top:0; font-size:16px; font-weight:800; border-bottom:1px solid var(--crjb-border); padding-bottom:10px; margin-bottom:10px; display:flex; align-items:center;"><svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:8px;"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg> Upcoming Events</h3>
                    <ul style="list-style:none; padding:0; margin:0;" id="crjb-schedule-list">
                        <li style="color:var(--crjb-sec); font-style:italic;">Loading schedule...</li>
                    </ul>
                </div>

                <div class="crjb-now-playing" id="crjb-np-panel" style="text-align:center; padding:15px; background:var(--crjb-panel); border-radius:16px; border-left:6px solid var(--crjb-accent);">
                    <div style="display:flex; justify-content: space-between; font-size: 11px; font-weight: 800; text-transform: uppercase;">
                        <span id="crjb-np-status-label" style="color: var(--crjb-accent);">On Air</span>
                        <span id="crjb-listener-count" style="color: var(--crjb-accent); display:flex; align-items:center;"><svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:4px;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg> 0</span>
                    </div>
                    <h3 id="crjb-np-title" style="margin:12px 0 4px 0; font-size: 20px;">Awaiting...</h3>
                    <p id="crjb-np-artist" style="margin:0; color: var(--crjb-sec); font-weight:600;">...</p>
                    <div id="crjb-np-time" style="font-size:14px; margin-top:10px; font-weight:800; color: var(--crjb-sec); display:flex; align-items:center; justify-content:center;"><svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;"><path d="M12 2v20"></path><path d="M8.5 6.5a5 5 0 0 0 0 7"></path><path d="M15.5 6.5a5 5 0 0 1 0 7"></path><path d="M5.5 3.5a10 10 0 0 0 0 13"></path><path d="M18.5 3.5a10 10 0 0 1 0 13"></path></svg> --:--</div>
                    
                    <div id="crjb-np-tip-container" style="display:none; margin: 20px 0 10px 0;">
                        <a id="crjb-np-tip-btn" href="#" target="_blank" class="w-100 btn btn-warning btn-lg" style="display:flex; justify-content:center; align-items:center; background: #ffaa00; color: #000; font-weight: 800; border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(255, 170, 0, 0.3); transition: transform 0.2s;">
                            <svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 8px;"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg> Tip Active Artist
                        </a>
                    </div>

                    <div id="crjb-np-banner" style="display:none;" class="crjb-marquee-container">
                        <div class="crjb-marquee-content" id="crjb-np-banner-text"></div>
                    </div>

                    <div style="display:flex; gap:10px; margin-top:15px;">
                        <button id="crjb-sync-btn" class="crjb-btn crjb-btn-sync" style="margin-top:0; flex:1;"><svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:8px;"><path d="M12 2v20"></path><path d="M8.5 6.5a5 5 0 0 0 0 7"></path><path d="M15.5 6.5a5 5 0 0 1 0 7"></path><path d="M5.5 3.5a10 10 0 0 0 0 13"></path><path d="M18.5 3.5a10 10 0 0 1 0 13"></path></svg> Connect</button>
                        <button id="crjb-disconnect-btn" class="crjb-btn crjb-btn-disconnect" style="margin-top:0; flex:1;">Disconnect</button>
                        <button id="crjb-stop-preview-btn" class="crjb-btn" style="background:#ffc107; color:#000; flex:1; padding:18px; border-radius:50px; display:none; margin-top:0; font-size:17px;"><svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:8px;"><rect x="6" y="6" width="12" height="12"></rect></svg> End Preview</button>
                    </div>
                </div>
            </div>
            
            <div class="crjb-dashboard-column">
                <div>
                    <h3 style="font-size:15px; margin-bottom:12px; font-weight:800;">Queue</h3>
                    <ul id="crjb-queue-list" style="list-style:none; padding:0; margin:0;"></ul>
                </div>
                
                <div id="crjb-catalog-container">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; flex-wrap:wrap; gap:10px;">
                        <h3 style="font-size:15px; font-weight:800; margin:0;">Catalog</h3>
                        
                        <div style="flex-grow: 1; min-width: 200px; position: relative;">
                            <svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--crjb-sec);"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                            <input type="search" id="crjb-search-input" placeholder="Search track or artist..." style="width: 100%; padding: 8px 10px 8px 32px; border-radius: 12px; border: 1px solid var(--crjb-border); background: var(--crjb-bg); color: var(--crjb-text); font-size: 14px; outline: none; box-sizing: border-box;" />
                        </div>

                        <div style="display:flex; align-items:center; gap:10px;">
                            <label style="font-size:11px; font-weight:700; cursor:pointer; display:flex; align-items:center; gap:5px; margin:0;">
                                <input type="checkbox" id="crjb-available-only" style="margin:0; cursor:pointer;"> Available Only
                            </label>
                            <select id="crjb-catalog-sort" style="padding:6px; border-radius:8px; font-size:12px; background:var(--crjb-panel); color:inherit; border:1px solid var(--crjb-border);">
                                <option value="title">Title A-Z</option><option value="artist">Artist</option><option value="newest">Newest</option>
                            </select>
                        </div>
                    </div>
                    <div id="crjb-artist-filter-header" style="display:none; justify-content:space-between; align-items:center; background:var(--crjb-accent); color:#fff; padding:10px 15px; border-radius:12px; margin-bottom:12px;">
                        <span style="font-weight:700; font-size:13px;" id="crjb-filter-text">Showing Artist</span>
                        <button onclick="clearArtistFilter()" style="display:flex; align-items:center; background:rgba(0,0,0,0.2); border:none; color:#fff; padding:4px 10px; border-radius:6px; font-size:11px; font-weight:700; cursor:pointer;"><svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:4px;"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg> Clear</button>
                    </div>
                    <ul id="crjb-catalog-list" style="list-style:none; padding:0; margin:0;"></ul>
                </div>
            </div>

        </div>
        
        <audio id="crjb-live-player" style="display:none;" crossorigin="anonymous"></audio>
        <audio id="crjb-preview-player" style="display:none;" crossorigin="anonymous"></audio>
    </div>

    <?php
    // Enqueue the new separated JavaScript file
    wp_enqueue_script( 'crjb-frontend-app', CRJB_PLUGIN_URL . 'assets/js/jukebox-app.js', [], CRJB_VERSION, true );
    
    // Pass the PHP variables seamlessly to the JS file
    wp_localize_script( 'crjb-frontend-app', 'crjbJukeboxData', [
        'ajaxUrl'       => $ajax_url,
        'securityNonce' => $security_nonce,
        'stationId'     => $station_id
    ]);

    return ob_get_clean();
}

// ==========================================
// 6. FRONTEND VISITOR MP3 UPLOADER
// ==========================================

add_shortcode('community_radio_jukebox_submit_mp3', 'crjb_render_frontend_uploader_shortcode');

function crjb_render_frontend_uploader_shortcode() {
    // Only allow rendering if the admin enabled submissions in the Jukebox settings
    if (get_option('crjb_enable_submissions') != '1') {
        return '<p>Song submissions are currently closed.</p>';
    }

    ob_start();
    ?>
    <div id="crjb-upload-container" style="max-width: 450px; margin: 0 auto; padding: 25px; background: #fff; border-radius: 16px; border: 1px solid #e0e0e0; box-shadow: 0 4px 15px rgba(0,0,0,0.05); font-family: system-ui, sans-serif;">
        <h3 style="margin-top: 0; font-size: 20px; font-weight: 800; color: #222; margin-bottom: 15px;">Submit Your Track</h3>
        <p style="font-size: 14px; color: #555; margin-bottom: 20px;">Upload your original music (MP3 format only). Our team will review the track before adding it to the public jukebox queue.</p>
        
        <div id="crjb-upload-alert" style="display:none; padding: 12px; margin-bottom: 20px; border-radius: 8px; font-weight: 600; font-size: 14px;"></div>
        
        <form id="crjb-upload-form" enctype="multipart/form-data">
            <?php wp_nonce_field('crjb_frontend_upload_action', 'crjb_upload_nonce'); ?>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; font-weight: 700; margin-bottom: 8px; font-size: 14px; color: #333;">Select MP3 File</label>
                <input type="file" id="crjb_audio_file" name="crjb_audio_file" accept=".mp3,audio/mpeg" required style="width: 100%; padding: 10px; border: 1px dashed #ccc; border-radius: 8px; background: #fafafa;">
            </div>
            
            <button type="submit" id="crjb-upload-submit" style="background: #0073aa; color: #fff; padding: 14px 20px; border: none; border-radius: 10px; cursor: pointer; font-weight: 800; font-size: 15px; width: 100%; transition: background 0.2s;">
                Upload Track
            </button>
        </form>
    </div>
    <?php
    return ob_get_clean();
}

add_action('wp_enqueue_scripts', 'crjb_enqueue_visitor_upload_script');
function crjb_enqueue_visitor_upload_script() {
    global $post;
    if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'community_radio_jukebox_submit_mp3' ) ) {
        wp_register_script('crjb-upload-script', false, [], false, true);
        wp_enqueue_script('crjb-upload-script');
        $custom_js = "
            document.addEventListener('DOMContentLoaded', function() {
                const form = document.getElementById('crjb-upload-form');
                const alertBox = document.getElementById('crjb-upload-alert');
                const submitBtn = document.getElementById('crjb-upload-submit');

                if(form) {
                    form.addEventListener('submit', function(e) {
                        e.preventDefault();
                        
                        const fileInput = document.getElementById('crjb_audio_file');
                        if (!fileInput.files.length) return;

                        const fileSize = fileInput.files[0].size / 1024 / 1024;
                        if (fileSize > 25) {
                            alertBox.style.display = 'block';
                            alertBox.style.backgroundColor = '#f8d7da';
                            alertBox.style.color = '#721c24';
                            alertBox.innerText = 'File is too large. Please upload an MP3 under 25MB.';
                            return;
                        }

                        const formData = new FormData(form);
                        formData.append('action', 'crjb_process_visitor_upload');

                        submitBtn.innerText = 'Uploading... Please wait.';
                        submitBtn.style.opacity = '0.7';
                        submitBtn.disabled = true;
                        alertBox.style.display = 'none';

                        fetch('" . esc_url(admin_url('admin-ajax.php')) . "', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            alertBox.style.display = 'block';
                            if (data.success) {
                                alertBox.style.backgroundColor = '#d4edda';
                                alertBox.style.color = '#155724';
                                alertBox.style.border = '1px solid #c3e6cb';
                                alertBox.innerText = 'Success! Your track has been uploaded securely.';
                                form.reset();
                            } else {
                                alertBox.style.backgroundColor = '#f8d7da';
                                alertBox.style.color = '#721c24';
                                alertBox.style.border = '1px solid #f5c6cb';
                                alertBox.innerText = 'Error: ' + data.data;
                            }
                        })
                        .catch(error => {
                            alertBox.style.display = 'block';
                            alertBox.style.backgroundColor = '#f8d7da';
                            alertBox.style.color = '#721c24';
                            alertBox.innerText = 'A network error occurred. The file may be larger than your server limits allow.';
                        })
                        .finally(() => {
                            submitBtn.innerText = 'Upload Track';
                            submitBtn.style.opacity = '1';
                            submitBtn.disabled = false;
                        });
                    });
                }
            });
        ";
        wp_add_inline_script('crjb-upload-script', $custom_js);
    }
}

add_action('wp_ajax_crjb_process_visitor_upload', 'crjb_process_visitor_upload_handler');
add_action('wp_ajax_nopriv_crjb_process_visitor_upload', 'crjb_process_visitor_upload_handler');

function crjb_process_visitor_upload_handler() {
    // 1. Strict Nonce & Security Verification
    if (!isset($_POST['crjb_upload_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['crjb_upload_nonce'])), 'crjb_frontend_upload_action')) {
        wp_send_json_error('Security check failed. Please refresh the page and try again.');
    }

    if (get_option('crjb_enable_submissions') != '1') {
        wp_send_json_error('Submissions are currently closed.');
    }

    if (empty($_FILES['crjb_audio_file']) || $_FILES['crjb_audio_file']['error'] !== UPLOAD_ERR_OK) {
        wp_send_json_error('No valid file was uploaded, or the file exceeded the server upload limit.');
    }

    $file = $_FILES['crjb_audio_file'];

    // 2. Strict MIME Type Validation
    $allowed_mimes = ['audio/mpeg', 'audio/mp3'];
    $file_type = wp_check_filetype($file['name'], ['mp3' => 'audio/mpeg']);
    
    if (!in_array($file_type['type'], $allowed_mimes, true)) {
        wp_send_json_error('Invalid file type. Only MP3 audio files are allowed.');
    }

    // 3. Import Core WordPress Media Handlers
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');

    // 4. Safely handle the upload and create the Media Library Attachment
    $attachment_id = media_handle_upload('crjb_audio_file', 0);

    if (is_wp_error($attachment_id)) {
        wp_send_json_error($attachment_id->get_error_message());
    }

    wp_send_json_success('File successfully uploaded to the Media Library.');
}

add_action('wp_ajax_crjb_cleanup_orphaned_audio', 'crjb_cleanup_orphaned_audio_handler');
function crjb_cleanup_orphaned_audio_handler() {
    // 1. Verify Security & Permissions
    if (!isset($_POST['security']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['security'])), 'crjb_gemini_scan_action')) {
        wp_send_json_error('Security check failed.');
    }
    // Note: Deleting files requires higher privileges
    if (!current_user_can('delete_posts')) {
        wp_send_json_error('Unauthorized.');
    }

    global $wpdb;

    // 2. Collect all actively used attachment IDs across main tracks, intros, and outros
    $used_main = $wpdb->get_col("SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'crjb_audio_attachment_id' AND meta_value != ''");
    $used_intro = $wpdb->get_col("SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'crjb_intro_attachment_id' AND meta_value != ''");
    $used_outro = $wpdb->get_col("SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'crjb_outro_attachment_id' AND meta_value != ''");

    // Merge and clean the list of protected IDs
    $all_used_ids = array_unique(array_filter(array_map('intval', array_merge($used_main, $used_intro, $used_outro))));

    // 3. Query all audio attachments in the Media Library
    $args = [
        'post_type'      => 'attachment',
        'post_mime_type' => 'audio/mpeg', // Targets MP3s
        'post_status'    => 'inherit',
        'posts_per_page' => -1,
        'fields'         => 'ids'
    ];

    // Exclude the protected ones
    if (!empty($all_used_ids)) {
        $args['post__not_in'] = $all_used_ids;
    }

    $orphaned_mp3s = get_posts($args);

    if (empty($orphaned_mp3s)) {
        wp_send_json_success(['msg' => 'Your library is perfectly clean. No orphaned MP3s found.']);
    }

    // 4. Delete the orphaned files and their WP attachment posts
    $deleted_count = 0;
    foreach ($orphaned_mp3s as $attachment_id) {
        // The 'true' parameter forces deletion, bypassing the trash
        if (wp_delete_attachment($attachment_id, true)) {
            $deleted_count++;
        }
    }

    wp_send_json_success(['msg' => "Success! Permanently deleted {$deleted_count} orphaned MP3s from your server."]);
}
