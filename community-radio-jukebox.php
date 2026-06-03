<?php
/**
 * Plugin Name: Community Radio Jukebox
 * Plugin URI:  https://github.com/corlett201660/community-radio-jukebox
 * Description: Interactive Jukebox with Auto DJ Flush Prediction, WooCommerce Artist Tipping, Marquee Patches, DJ Drops, Visual Schedules, Monthly Logging, AI Explicit Profiling, and Gemini 2.5 Pro.
 * Version:     4.60.0
 * Author:      Local Jukebox Architecture
 * License:     GPL v2 or later
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ==========================================
// 1. ASSET MANAGER
// ==========================================
define( 'LJ_VERSION', '4.60.0' );
define( 'LJ_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

class LJ_Asset_Manager {
    public function __construct() {
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );
        add_filter( 'script_loader_tag', [ $this, 'add_module_type_attribute' ], 10, 3 );
    }
    
    public function enqueue_admin_assets() { 
        wp_enqueue_media(); 
        wp_enqueue_style('lj-select2-css', LJ_PLUGIN_URL . 'assets/css/select2.min.css', [], '4.1.0');
        wp_enqueue_script('lj-select2-js', LJ_PLUGIN_URL . 'assets/js/select2.min.js', ['jquery'], '4.1.0', true);
    }

    public function enqueue_frontend_assets() {
        global $post;
        
        if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'community_radio_jukebox' ) ) {
            wp_enqueue_style( 'lj-fontawesome', LJ_PLUGIN_URL . 'assets/css/all.min.css', [], '6.4.0' );
            wp_enqueue_style( 'lj-bootstrap', LJ_PLUGIN_URL . 'assets/css/bootstrap.min.css', [], '5.3.0' );
            wp_enqueue_script( 'lj-bootstrap-js', LJ_PLUGIN_URL . 'assets/js/bootstrap.bundle.min.js', [], '5.3.0', true );
        }
    }

    public function add_module_type_attribute( $tag, $handle, $src ) {
        if ( in_array( $handle, ['lj-admin-app', 'lj-frontend-app'], true ) ) {
            return str_replace( '<script ', '<script type="module" ', $tag );
        }
        return $tag;
    }
}
new LJ_Asset_Manager();

// ==========================================
// 2. CORE SETUP, CPTS, & TAXONOMIES
// ==========================================
add_action( 'init', 'lj_register_cpts_and_taxonomies' );
function lj_register_cpts_and_taxonomies() {
    register_post_type( 'lj_song', [
        'labels' => [ 'name' => 'Jukebox Songs', 'singular_name' => 'Song', 'add_new_item' => 'Add New Song', 'all_items' => 'All Songs' ],
        'public' => true, 'menu_icon' => 'dashicons-format-audio', 'supports' => [ 'title', 'thumbnail' ], 
    ]);
    
    register_post_type( 'lj_schedule', [
        'labels' => [ 'name' => 'Jukebox Schedules', 'singular_name' => 'Schedule', 'add_new_item' => 'Add New Schedule', 'all_items' => 'All Schedules' ],
        'public' => false, 'show_ui' => true, 'show_in_menu' => 'edit.php?post_type=lj_song', 'supports' => [ 'title' ],
    ]);

    register_taxonomy( 'lj_playlist', 'lj_song', [ 'labels' => [ 'name' => 'Playlists' ], 'hierarchical' => true, 'show_ui' => true ]);
    register_taxonomy( 'lj_artist', 'lj_song', [ 'labels' => [ 'name' => 'Artists' ], 'hierarchical' => false, 'show_ui' => true ]);
    register_taxonomy( 'lj_submitter', 'lj_song', [ 'labels' => [ 'name' => 'Submitters' ], 'hierarchical' => false, 'show_ui' => true ]);
    register_taxonomy( 'lj_genre', 'lj_song', [ 'labels' => [ 'name' => 'Genres', 'singular_name' => 'Genre' ], 'hierarchical' => true, 'show_ui' => true, 'show_admin_column' => true ]);
}

// ==========================================
// 3. ADMIN SETTINGS, GEMINI SCANNER, EXPORT
// ==========================================
add_action('admin_menu', 'lj_add_admin_menu');
function lj_add_admin_menu() {
    add_submenu_page('edit.php?post_type=lj_song', 'Jukebox Settings', 'Settings', 'manage_options', 'lj_settings', 'lj_settings_page');
    add_submenu_page('edit.php?post_type=lj_song', 'Jukebox Tutorial', 'Tutorial & Setup', 'manage_options', 'lj_tutorial', 'lj_tutorial_page');
}

add_action('admin_init', 'lj_register_settings');
function lj_register_settings() {
    register_setting('lj_settings_group', 'lj_gemini_api_key', ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field']);
    register_setting('lj_settings_group', 'lj_enable_submissions', ['type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean']);
    register_setting('lj_settings_group', 'lj_allow_explicit', ['type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean']);
    register_setting('lj_settings_group', 'lj_exclude_licensed', ['type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean']);
    register_setting('lj_settings_group', 'lj_strict_event_mode', ['type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean']);
    register_setting('lj_settings_group', 'lj_submission_url', ['type' => 'string', 'sanitize_callback' => 'esc_url_raw']);
}

function lj_settings_page() {
    $gemini_nonce = wp_create_nonce('lj_gemini_scan_action');
    ?>
    <div class="wrap">
        <h1>Jukebox Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields('lj_settings_group'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">Google Gemini API Key</th>
                    <td>
                        <input type="password" name="lj_gemini_api_key" value="<?php echo esc_attr(get_option('lj_gemini_api_key')); ?>" class="regular-text" placeholder="AIzaSy..." />
                        <p class="description">Required for Gemini 2.5 Pro AI Audio Scanning (Explicit Flags, Genres & Lyrics). Get this from Google AI Studio.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Allow Explicit Content</th>
                    <td>
                        <input type="checkbox" name="lj_allow_explicit" value="1" <?php checked(1, get_option('lj_allow_explicit', 1), true); ?> />
                        <label>If unchecked, all songs marked as "Explicit" are instantly hidden from the catalog and skipped by the Auto DJ.</label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Exclude Licensed Music</th>
                    <td>
                        <input type="checkbox" name="lj_exclude_licensed" value="1" <?php checked(1, get_option('lj_exclude_licensed'), true); ?> />
                        <label>If checked, all standard tracks will be hidden and skipped. Only tracks marked as <strong>Royalty Free</strong> or with a <strong>License Override</strong> will play.</label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Strict Event Only Mode</th>
                    <td>
                        <input type="checkbox" name="lj_strict_event_mode" value="1" <?php checked(1, get_option('lj_strict_event_mode'), true); ?> />
                        <label>If checked, the Global Station will completely lock all song requests when no scheduled event is active.</label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Enable MP3 Submissions</th>
                    <td>
                        <input type="checkbox" name="lj_enable_submissions" value="1" <?php checked(1, get_option('lj_enable_submissions'), true); ?> />
                        <label>Show public upload link in the Jukebox header.</label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Submission URL</th>
                    <td>
                        <input type="url" name="lj_submission_url" value="<?php echo esc_attr(get_option('lj_submission_url')); ?>" class="regular-text" placeholder="https://dropbox.com/... or Google Drive link" />
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
                    $available_months = get_option('lj_broadcast_log_months', []);
                    $legacy_log = get_option('lj_broadcast_log', []);
                    
                    if (empty($available_months) && empty($legacy_log)) {
                        echo '<p>No broadcast history available yet.</p>';
                    } else {
                        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:flex; gap:10px; align-items:center;">';
                        echo '<input type="hidden" name="action" value="lj_export_log">';
                        wp_nonce_field('lj_export_action');
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

        <hr style="margin: 30px 0;">
        <h2>Gemini AI Bulk Catalog Scanner</h2>
        <table class="form-table">
            <tr>
                <th scope="row">Auto Tag Missing Genres, Explicit Flags & Lyrics</th>
                <td>
                    <div style="display:flex; gap:10px; align-items:center;">
                        <button type="button" id="lj_bulk_scan_btn" class="button button-primary">Scan Incomplete Library</button>
                        <button type="button" id="lj_clear_ai_data_btn" class="button button-secondary" style="color: #d63638; border-color: #d63638;">Wipe All AI Data</button>
                    </div>
                    <span id="lj_bulk_status" style="display:block; margin-top:10px; font-weight:bold;"></span>
                    <p class="description"><strong>Scan Incomplete Library:</strong> Processes up to 10 songs missing standard layout vectors via the Gemini API.<br>
                    <strong>Wipe All AI Data:</strong> Instantly deletes all AI generated Genres and Lyrics from every track in your catalog so you can start a fresh rescan.</p>
                </td>
            </tr>
        </table>
    </div>

    <script>
    jQuery(document).ready(function($){
        $('#lj_bulk_scan_btn').click(function(e) {
            e.preventDefault();
            if(!confirm('This will scan a batch of 10 incomplete audio files via the Gemini API. This may take a minute. Proceed?')) return;
            
            let btn = $(this);
            let wipeBtn = $('#lj_clear_ai_data_btn');
            let status = $('#lj_bulk_status');
            btn.prop('disabled', true);
            wipeBtn.prop('disabled', true);
            status.css('color', '#000').text('Fetching incomplete songs and sending to Gemini...');

            $.post(ajaxurl, { action: 'lj_gemini_bulk_scan', security: '<?php echo esc_js($gemini_nonce); ?>' }, function(res) {
                if(res.success) {
                    if(res.data.processed === 0) {
                        status.css('color', '#28a745').text(res.data.msg);
                    } else {
                        status.css('color', '#28a745').text('Success! Scanned ' + res.data.processed + ' tracks. Click again to scan the next batch.');
                    }
                } else {
                    status.css('color', '#d63638').text('Error: ' + res.data);
                }
                btn.prop('disabled', false);
                wipeBtn.prop('disabled', false);
            }).fail(function() {
                status.css('color', '#d63638').text('Server timeout or error. Check PHP error logs.');
                btn.prop('disabled', false);
                wipeBtn.prop('disabled', false);
            });
        });

        $('#lj_clear_ai_data_btn').click(function(e) {
            e.preventDefault();
            if(!confirm('WARNING: This will permanently delete ALL genres and lyrics from EVERY song in your library. You will need to rescan them afterwards. Are you sure?')) return;
            
            let btn = $(this);
            let scanBtn = $('#lj_bulk_scan_btn');
            let status = $('#lj_bulk_status');
            btn.prop('disabled', true);
            scanBtn.prop('disabled', true);
            status.css('color', '#d63638').text('Wiping all AI data...');

            $.post(ajaxurl, { action: 'lj_gemini_clear_all', security: '<?php echo esc_js($gemini_nonce); ?>' }, function(res) {
                if(res.success) {
                    status.css('color', '#28a745').text(res.data.msg);
                } else {
                    status.css('color', '#d63638').text('Error: ' + res.data);
                }
                btn.prop('disabled', false);
                scanBtn.prop('disabled', false);
            }).fail(function() {
                status.css('color', '#d63638').text('Server timeout or error.');
                btn.prop('disabled', false);
                scanBtn.prop('disabled', false);
            });
        });
    });
    </script>
    <?php
}

// ------------------------------------------
// GEMINI API HANDLERS
// ------------------------------------------

add_action('wp_ajax_lj_gemini_clear_all', 'lj_gemini_clear_all_handler');
function lj_gemini_clear_all_handler() {
    if (!isset($_POST['security']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['security'])), 'lj_gemini_scan_action')) wp_send_json_error('Security check failed.');
    if (!current_user_can('edit_posts')) wp_send_json_error('Unauthorized.');
    
    $all_songs = get_posts([
        'post_type' => 'lj_song',
        'posts_per_page' => -1,
        'fields' => 'ids'
    ]);

    $cleared = 0;
    foreach ($all_songs as $song_id) {
        wp_set_object_terms($song_id, [], 'lj_genre'); 
        delete_post_meta($song_id, 'lj_lyrics'); 
        delete_post_meta($song_id, 'lj_is_explicit');
        $cleared++;
    }

    update_option('lj_catalog_version', time());
    wp_send_json_success(['msg' => "Successfully wiped AI data for {$cleared} tracks. Your catalog is now a blank slate for rescanning."]);
}

add_action('wp_ajax_lj_gemini_scan', 'lj_gemini_scan_handler');
function lj_gemini_scan_handler() {
    if (!isset($_POST['security']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['security'])), 'lj_gemini_scan_action')) wp_send_json_error('Security check failed.');
    if (!current_user_can('edit_posts')) wp_send_json_error('Unauthorized.');
    
    $song_id = isset($_POST['song_id']) ? intval($_POST['song_id']) : 0;
    if (!$song_id) wp_send_json_error('Invalid song ID.');

    $result = lj_execute_gemini_scan($song_id);
    if (is_wp_error($result)) wp_send_json_error($result->get_error_message());
    
    wp_send_json_success($result);
}

add_action('wp_ajax_lj_gemini_bulk_scan', 'lj_gemini_bulk_scan_handler');
function lj_gemini_bulk_scan_handler() {
    if (!isset($_POST['security']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['security'])), 'lj_gemini_scan_action')) wp_send_json_error('Security check failed.');
    if (!current_user_can('edit_posts')) wp_send_json_error('Unauthorized.');
    
    $all_songs = get_posts([
        'post_type' => 'lj_song',
        'posts_per_page' => -1,
        'fields' => 'ids'
    ]);

    $incomplete_songs = [];
    foreach ($all_songs as $song_id) {
        $genres = wp_get_post_terms($song_id, 'lj_genre', ['fields' => 'ids']);
        $lyrics = get_post_meta($song_id, 'lj_lyrics', true);
        
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
        $result = lj_execute_gemini_scan($song_id);
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

function lj_execute_gemini_scan($song_id) {
    $api_key = get_option('lj_gemini_api_key');
    if (empty($api_key)) return new WP_Error('no_key', 'Gemini API key is missing in Jukebox Settings.');

    $attachment_id = get_post_meta($song_id, 'lj_audio_attachment_id', true);
    $audio_url = get_post_meta($song_id, 'full_audio_url', true);
    $title = get_the_title($song_id);
    $artist_terms = wp_get_post_terms($song_id, 'lj_artist', ['fields' => 'names']);
    $artist = !empty($artist_terms) ? implode(', ', $artist_terms) : 'Unknown Artist';

    $audio_data = '';
    $mime = 'audio/mp3';
    $file_error = '';

    if ($attachment_id) {
        $file_path = get_attached_file($attachment_id);
        if ($file_path && file_exists($file_path)) {
            if (filesize($file_path) < 19000000) {
                $mime = mime_content_type($file_path) ?: 'audio/mp3';
                $audio_data = base64_encode(file_get_contents($file_path));
            } else {
                $file_error = 'Internal file exceeds 19MB API limit.';
            }
        }
    }

    if (empty($audio_data) && $audio_url && empty($file_error)) {
        $response = wp_safe_remote_get($audio_url, ['timeout' => 20]);
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $body = wp_remote_retrieve_body($response);
            if (strlen($body) < 19000000) {
                $mime = wp_remote_retrieve_header($response, 'content-type') ?: 'audio/mp3';
                $audio_data = base64_encode($body);
            } else {
                $file_error = 'Remote file exceeds 19MB API limit.';
            }
        } else {
            $file_error = 'Could not securely download audio from URL.';
        }
    }

    if (empty($audio_data)) {
        $reason = $file_error ?: 'No valid audio attachment or URL found.';
        return new WP_Error('no_audio', "Audio unavailable for '{$title}': {$reason}");
    }

    $prompt = "You are an expert music curator and strict audio transcriptionist. Listen to the provided audio track.\n\nSTRICT RULES:\n1. For 'genres', provide an array of 2 to 4 accurate standard musical genres/sub-genres based on the sonic profile.\n2. For 'lyrics', you MUST ONLY transcribe the exact words you hear in the audio file. DO NOT hallucinate, guess, or search for lyrics based on the title or artist. If there are no vocals, or if you cannot clearly hear them, output exactly: 'Instrumental'.\n3. For 'is_explicit', analyze the audio and lyrics for strong profanity, explicit sexual themes, or highly sensitive/graphic violent content. Return a boolean true if explicit content is present, or false if the track is completely clean.\n\nReturn a JSON object with exactly three keys: 'genres', 'lyrics', and 'is_explicit'.";

    $payload = [
        "contents" => [
            [
                "parts" => [
                    ["text" => $prompt],
                    [
                        "inline_data" => [
                            "mime_type" => $mime,
                            "data" => $audio_data
                        ]
                    ]
                ]
            ]
        ],
        "generationConfig" => [
            "response_mime_type" => "application/json",
            "temperature" => 0.1 
        ]
    ];

    $response = wp_remote_post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-pro:generateContent?key=" . $api_key, [
        'headers' => ['Content-Type' => 'application/json'],
        'body' => wp_json_encode($payload),
        'timeout' => 60 
    ]);

    if (is_wp_error($response)) return $response;

    $body = json_decode(wp_remote_retrieve_body($response), true);
    
    if (isset($body['error'])) {
        return new WP_Error('api_error', $body['error']['message'] ?? 'Unknown API Error');
    }

    if (isset($body['candidates'][0]['content']['parts'][0]['text'])) {
        $json_str = $body['candidates'][0]['content']['parts'][0]['text'];
        $data = json_decode($json_str, true);
        
        $response_data = [];
        
        if (isset($data['genres']) && is_array($data['genres'])) {
            wp_set_object_terms($song_id, $data['genres'], 'lj_genre', false);
            $response_data['genres'] = $data['genres'];
        }
        
        if (isset($data['lyrics'])) {
            update_post_meta($song_id, 'lj_lyrics', sanitize_textarea_field($data['lyrics']));
            $response_data['lyrics_status'] = 'Transcribed';
        }

        if (isset($data['is_explicit'])) {
            update_post_meta($song_id, 'lj_is_explicit', $data['is_explicit'] ? '1' : '0');
            $response_data['explicit_status'] = $data['is_explicit'] ? 'Explicit' : 'Clean';
        }
        
        update_option('lj_catalog_version', time());
        return $response_data;
    }
    
    return new WP_Error('parse_error', 'Failed to parse Gemini response.');
}

// Handler for CSV Export
add_action('admin_post_lj_export_log', 'lj_export_broadcast_log_handler');
function lj_export_broadcast_log_handler() {
    if (!current_user_can('manage_options')) wp_die('Unauthorized access.');
    if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'lj_export_action' ) ) { wp_die( 'Security check failed. The link may have expired.' ); }
    
    $month_key = isset($_POST['log_month']) ? sanitize_text_field(wp_unslash($_POST['log_month'])) : '';
    
    if ($month_key === 'legacy') {
        $log = get_option('lj_broadcast_log', []);
        $filename = 'jukebox-legacy-log-' . gmdate('Y-m-d-H-i') . '.csv';
    } elseif ($month_key) {
        $log = get_option('lj_broadcast_log_' . $month_key, []);
        $filename = 'jukebox-log-' . $month_key . '.csv';
    } else {
        $months = get_option('lj_broadcast_log_months', []);
        if (!empty($months)) {
            $month_key = $months[0];
            $log = get_option('lj_broadcast_log_' . $month_key, []);
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

function lj_tutorial_page() {
    ?>
    <div class="wrap">
        <h1 style="margin-bottom: 20px;">Community Radio Jukebox: Manual & Workflows</h1>

        <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-left: 4px solid #0073aa; box-shadow: 0 1px 1px rgba(0,0,0,.04); max-width: 800px; margin-bottom: 20px;">
            <h2 style="margin-top: 0;">1. AI Auto Tagging & Lyrics Transcription</h2>
            <p>Go to the Jukebox Settings page and paste your Google Gemini API key. When editing a Jukebox Song, click <strong>✨ Analyze Audio</strong>. The system will upload the MP3 to Gemini 2.5 Pro, letting the AI listen to the track to automatically assign the correct Genres and transcribe the Lyrics.</p>
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
            <p>If they toggle this on when no songs are available, the Jukebox will scan ahead and display a <strong>Predictive Empty State</strong>, letting them know the exact time (or event) when the next track will become playable (e.g., "Next track available at 8:15 PM" or "Unlocks at Royalty Hour").</p>
        </div>
    </div>
    <?php
}

// ------------------------------------------
// DEDICATED TRACK PAGE FRONTEND DISPLAY
// ------------------------------------------

add_action('wp_head', 'lj_hide_sidebar_on_song_page');
function lj_hide_sidebar_on_song_page() {
    if (is_singular('lj_song')) {
        echo '<style>
            #secondary, #sidebar, .sidebar, .widget-area, aside#secondary { display: none !important; }
            #primary, #content, .site-main, .content-area, .site-content { width: 100% !important; max-width: none !important; float: none !important; border: none !important; }
        </style>';
    }
}

add_action('wp_head', 'lj_inject_song_structured_data');
function lj_inject_song_structured_data() {
    if (is_singular('lj_song')) {
        $post_id = get_the_ID();
        $title = get_the_title($post_id);
        $artist_terms = wp_get_post_terms($post_id, 'lj_artist', ['fields' => 'names']);
        $artist = !empty($artist_terms) ? implode(', ', $artist_terms) : 'Unknown Artist';
        $lyrics = get_post_meta($post_id, 'lj_lyrics', true);
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

add_filter('body_class', 'lj_song_full_width_body_classes');
function lj_song_full_width_body_classes($classes) {
    if (is_singular('lj_song')) {
        $classes[] = 'full-width-content';
        $classes[] = 'no-sidebar';
        $classes[] = 'full-width';
    }
    return $classes;
}

add_filter('the_content', 'lj_song_dedicated_page_content');
function lj_song_dedicated_page_content($content) {
    if (is_singular('lj_song') && in_the_loop() && is_main_query()) {
        $post_id = get_the_ID();
        $lyrics = get_post_meta($post_id, 'lj_lyrics', true);
        $is_explicit = get_post_meta($post_id, 'lj_is_explicit', true);
        $always_available = get_post_meta($post_id, 'lj_always_available', true);
        $is_royalty_free = get_post_meta($post_id, 'lj_royalty_free', true);
        $tip_url = get_post_meta($post_id, 'lj_tip_url', true);
        
        $artist_terms = wp_get_post_terms($post_id, 'lj_artist', ['fields' => 'names']);
        $artist = !empty($artist_terms) ? implode(', ', $artist_terms) : 'Unknown Artist';
        
        $genre_terms = wp_get_post_terms($post_id, 'lj_genre', ['fields' => 'names']);
        $genres = !empty($genre_terms) ? implode(', ', $genre_terms) : 'None';
        
        $playlist_terms = wp_get_post_terms($post_id, 'lj_playlist', ['fields' => 'names']);
        $playlists = !empty($playlist_terms) ? implode(', ', $playlist_terms) : 'None';

        $duration = get_post_meta($post_id, 'audio_duration', true);
        $duration_fmt = '--:--';
        if ($duration && is_numeric($duration)) {
            $duration_fmt = floor($duration / 60) . ':' . str_pad($duration % 60, 2, '0', STR_PAD_LEFT);
        }

        $full_audio_url = get_post_meta($post_id, 'full_audio_url', true);
        $preview_url = get_post_meta($post_id, 'preview_url', true) ?: $full_audio_url;

        $schedules = get_posts(['post_type' => 'lj_schedule', 'posts_per_page' => -1]);
        $matched_events = [];
        foreach($schedules as $sched) {
            if (lj_song_matches_schedule($post_id, $sched->ID)) {
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
        
        $html = '<div class="lj-dedicated-track" style="max-width: 800px; margin: 0 auto; padding: 40px 20px; font-family: system-ui, sans-serif;">';
        
        $e_badge = $is_explicit ? '<span style="font-size: 12px; font-weight: 800; background: #666; color: #fff; padding: 2px 6px; border-radius: 4px; vertical-align: middle; margin-left: 10px;" title="Explicit Content">E</span>' : '';
        $html .= '<h1 style="margin-bottom: 5px; color: #222; display: flex; align-items: center; flex-wrap: wrap;">' . esc_html(get_the_title()) . $e_badge . '</h1>';
        $html .= '<h3 style="margin-top:0; color: #555; margin-bottom: 30px;">By ' . esc_html($artist) . '</h3>';
        
        if ($tip_url) {
            $html .= '<div style="margin-bottom: 30px;"><a href="' . esc_url($tip_url) . '" target="_blank" style="display: inline-block; background: #ffaa00; color: #000; font-weight: 800; padding: 10px 20px; border-radius: 8px; text-decoration: none;"><i class="fa-solid fa-hand-holding-dollar"></i> Tip the Artist</a></div>';
        }
        
        $html .= '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; background: #f5f5f5; padding: 25px; border-radius: 12px; border: 1px solid #e0e0e0; margin-bottom: 30px;">';
        $html .= '<div><strong style="font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #888; display: block; margin-bottom: 5px;">Duration</strong><span style="font-size: 16px; font-weight: 600; color: #333;">' . $duration_fmt . '</span></div>';
        $html .= '<div><strong style="font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #888; display: block; margin-bottom: 5px;">Genres</strong><span style="font-size: 16px; font-weight: 600; color: #333;">' . esc_html($genres) . '</span></div>';
        $html .= '<div><strong style="font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #888; display: block; margin-bottom: 5px;">Playlists</strong><span style="font-size: 16px; font-weight: 600; color: #333;">' . esc_html($playlists) . '</span></div>';
        $html .= '<div><strong style="font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #888; display: block; margin-bottom: 5px;">Featured In</strong><span style="font-size: 15px; font-weight: 700; color: #0073aa;">' . esc_html($events_str) . '</span></div>';
        $html .= '</div>';

        if ($is_royalty_free && $full_audio_url) {
            $html .= '<div style="background: #eef7fc; padding: 20px; border-radius: 12px; border: 1px solid #bce0f4; margin-bottom: 40px; text-align: center;">';
            $html .= '<h4 style="margin: 0 0 15px 0; color: #0073aa; font-weight: 800; font-size: 16px;"><i class="fa-solid fa-unlock" style="margin-right: 5px;"></i> Full Track (Royalty Free)</h4>';
            $html .= '<audio controls controlsList="nodownload" src="' . esc_url($full_audio_url) . '" style="width: 100%; max-width: 400px; outline: none; border-radius: 8px;"></audio>';
            $html .= '</div>';
        } elseif ($preview_url) {
            $html .= '<div style="background: #eef7fc; padding: 20px; border-radius: 12px; border: 1px solid #bce0f4; margin-bottom: 40px; text-align: center;">';
            $html .= '<h4 style="margin: 0 0 15px 0; color: #0073aa; font-weight: 800; font-size: 16px;">30-Second Preview</h4>';
            $html .= '<audio id="lj-dedicated-preview" controls controlsList="nodownload" src="' . esc_url($preview_url) . '" style="width: 100%; max-width: 400px; outline: none; border-radius: 8px;"></audio>';
            $html .= '<script>
                document.addEventListener("DOMContentLoaded", function() {
                    var audio = document.getElementById("lj-dedicated-preview");
                    if(audio) {
                        audio.addEventListener("timeupdate", function() {
                            if(audio.currentTime >= 30) {
                                audio.pause();
                                audio.currentTime = 0;
                            }
                        });
                    }
                });
            </script>';
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
function lj_render_tax_select_field($tax, $saved_val, $name, $placeholder) {
    $saved_terms = array_filter(array_map('trim', explode(',', $saved_val)));
    $terms = get_terms(['taxonomy' => $tax, 'hide_empty' => false]);
    
    echo '<select multiple="multiple" class="lj-select2 regular-text" name="' . esc_attr($name) . '[]" data-placeholder="' . esc_attr($placeholder) . '" style="width: 100%; max-width: 400px;">';
    
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
add_action( 'add_meta_boxes', 'lj_add_song_meta_boxes' );
function lj_add_song_meta_boxes() {
    add_meta_box( 'lj_song_details', 'Network Audio File & AI Transcription', 'lj_song_details_callback', 'lj_song', 'normal', 'high' );
    add_meta_box( 'lj_schedule_details', 'Automated Station Takeover Rules', 'lj_schedule_details_callback', 'lj_schedule', 'normal', 'high' );
}

function lj_song_details_callback( $post ) {
    wp_nonce_field( 'lj_save_song_data', 'lj_song_meta_nonce' );
    $gemini_nonce = wp_create_nonce('lj_gemini_scan_action');
    $full_audio_url = get_post_meta( $post->ID, 'full_audio_url', true );
    $audio_duration = get_post_meta( $post->ID, 'audio_duration', true );
    $preview_url    = get_post_meta( $post->ID, 'preview_url', true );
    $always_available = get_post_meta( $post->ID, 'lj_always_available', true );
    $play_globally  = get_post_meta( $post->ID, 'lj_play_globally', true );
    $is_explicit    = get_post_meta( $post->ID, 'lj_is_explicit', true );
    $is_royalty_free = get_post_meta( $post->ID, 'lj_royalty_free', true );
    $license_override = get_post_meta( $post->ID, 'lj_license_override', true );
    $lyrics         = get_post_meta( $post->ID, 'lj_lyrics', true );
    $custom_banner  = get_post_meta( $post->ID, 'lj_custom_banner_text', true );
    $tip_url        = get_post_meta( $post->ID, 'lj_tip_url', true );
    $intro_audio_url = get_post_meta( $post->ID, 'intro_audio_url', true );
    $outro_audio_url = get_post_meta( $post->ID, 'outro_audio_url', true );
    ?>
    <table class="form-table">
        <tr>
            <th><label>Content Rating</label></th>
            <td>
                <label>
                    <input type="checkbox" name="lj_is_explicit" value="1" <?php checked(1, $is_explicit); ?> />
                    <strong>Explicit Content:</strong> Adds an [E] badge to the track. Hidden if explicit content is globally disabled.
                </label>
            </td>
        </tr>
        <tr>
            <th><label>Licensing</label></th>
            <td>
                <label>
                    <input type="checkbox" name="lj_royalty_free" value="1" <?php checked(1, $is_royalty_free); ?> />
                    <strong>Royalty Free:</strong> Allows the full track to be played on the dedicated song page instead of just a 30-second preview.
                </label>
            </td>
        </tr>
        <tr>
            <th><label>License Override</label></th>
            <td>
                <label>
                    <input type="checkbox" name="lj_license_override" value="1" <?php checked(1, $license_override); ?> />
                    <strong>Bypass Global Exclusion:</strong> Force this track to remain playable even if "Exclude Licensed Music" is enabled globally in Settings.
                </label>
            </td>
        </tr>
        <tr>
            <th><label>Availability Override</label></th>
            <td>
                <label>
                    <input type="checkbox" name="lj_always_available" value="1" <?php checked(1, $always_available); ?> />
                    <strong>Always Available:</strong> This song bypasses schedule rules and event exclusivity locks.
                </label>
            </td>
        </tr>
        <tr>
            <th><label>Open Play Global</label></th>
            <td>
                <label>
                    <input type="checkbox" name="lj_play_globally" value="1" <?php checked(1, $play_globally); ?> />
                    <strong>Play Globally during Open Play:</strong> Prioritize this song in the Auto DJ during non-scheduled events.
                </label>
            </td>
        </tr>
        <tr>
            <th><label>Tip URL (WooCommerce/Venmo)</label></th>
            <td>
                <input type="url" name="lj_tip_url" value="<?php echo esc_url($tip_url); ?>" class="regular-text" style="width: 100%;" placeholder="https://yoursite.com/?add-to-cart=123" />
                <p class="description">Paste a WooCommerce "Add to Cart" link, or a direct Venmo/Ko-fi link. This will automatically generate a gold "Tip Artist" button on the frontend.</p>
            </td>
        </tr>
        <tr>
            <th><label>Custom Scrolling Banner</label></th>
            <td>
                <input type="text" name="lj_custom_banner_text" value="<?php echo esc_attr($custom_banner); ?>" class="regular-text" style="width: 100%;" />
                <p class="description">Overrides the default "Submitted by" text. HTML is allowed (e.g., <code>&lt;strong&gt;Happy Birthday Sarah!&lt;/strong&gt;</code>). This will side-scroll horizontally in the frontend Jukebox interface.</p>
            </td>
        </tr>
        <tr><th><label>Network Sync MP3</label></th><td>
            <div style="display: flex; gap: 10px;">
                <input type="url" id="full_audio_url" name="full_audio_url" value="<?php echo esc_attr($full_audio_url); ?>" style="flex-grow: 1;" readonly />
                <input type="hidden" id="lj_audio_attachment_id" name="lj_audio_attachment_id" value="" />
                <button type="button" class="button button-secondary" id="lj_upload_mp3_btn">Select Track MP3</button>
                <button type="button" class="button button-primary" id="lj_gemini_scan_btn">✨ Analyze Audio</button>
            </div>
            <p class="description">Clicking <strong>Analyze Audio</strong> will run the file through Gemini 2.5 Pro to auto assign genres, explicit classification variables, and transcribe the lyrics below.</p>
        </td></tr>
        <tr><th><label>Duration (Seconds)</label></th><td><input type="number" id="audio_duration" name="audio_duration" value="<?php echo esc_attr($audio_duration); ?>" readonly /></td></tr>
        <tr><th><label>Frontend Preview URL</label></th><td><input type="url" id="preview_url" name="preview_url" value="<?php echo esc_url($preview_url); ?>" style="width:100%;" /></td></tr>
        <tr><th><label>Intro Voice Memo (DJ Drop)</label></th><td>
            <div style="display: flex; gap: 10px;">
                <input type="url" id="intro_audio_url" name="intro_audio_url" value="<?php echo esc_attr($intro_audio_url); ?>" style="flex-grow: 1;" readonly placeholder="Plays before the song starts..." />
                <input type="hidden" id="lj_intro_attachment_id" name="lj_intro_attachment_id" value="" />
                <button type="button" class="button button-secondary lj_upload_memo_btn" data-target="intro">Select Intro</button>
            </div>
        </td></tr>
        <tr><th><label>Outro Voice Memo (DJ Drop)</label></th><td>
            <div style="display: flex; gap: 10px;">
                <input type="url" id="outro_audio_url" name="outro_audio_url" value="<?php echo esc_attr($outro_audio_url); ?>" style="flex-grow: 1;" readonly placeholder="Plays after the song ends..." />
                <input type="hidden" id="lj_outro_attachment_id" name="lj_outro_attachment_id" value="" />
                <button type="button" class="button button-secondary lj_upload_memo_btn" data-target="outro">Select Outro</button>
            </div>
        </td></tr>
        <tr>
            <th><label>Track Lyrics</label></th>
            <td>
                <textarea name="lj_lyrics" rows="8" style="width:100%; font-family: monospace; padding: 10px;"><?php echo esc_textarea($lyrics); ?></textarea>
                <p class="description">These lyrics will be displayed on the track's dedicated permalink page.</p>
            </td>
        </tr>
    </table>
    <script>
    jQuery(document).ready(function($){
        var uploader;
        $('#lj_upload_mp3_btn').click(function(e) {
            e.preventDefault();
            if (uploader) { uploader.open(); return; }
            uploader = wp.media({ title: 'Choose Network MP3', button: { text: 'Select Audio' }, multiple: false, library: { type: 'audio' } });
            uploader.on('select', function() {
                var attachment = uploader.state().get('selection').first().toJSON();
                $('#lj_audio_attachment_id').val(attachment.id);
                $('#full_audio_url').val(attachment.url);
                if($('#preview_url').val() === '') $('#preview_url').val(attachment.url);
            });
            uploader.open();
        });

        $('.lj_upload_memo_btn').click(function(e) {
            e.preventDefault();
            var target = $(this).data('target');
            var memoUploader = wp.media({ title: 'Choose Voice Memo', button: { text: 'Select Audio' }, multiple: false, library: { type: 'audio' } });
            memoUploader.on('select', function() {
                var attachment = memoUploader.state().get('selection').first().toJSON();
                $('#lj_' + target + '_attachment_id').val(attachment.id);
                $('#' + target + '_audio_url').val(attachment.url);
            });
            memoUploader.open();
        });

        $('#lj_gemini_scan_btn').click(function(e) {
            e.preventDefault();
            
            let current_url = $('#full_audio_url').val();
            let saved_url = "<?php echo esc_js($full_audio_url); ?>";
            let isAutoDraft = $('#original_post_status').val() === 'auto-draft' || $('#post_status').val() === 'auto-draft';

            if (current_url === '') {
                alert('Please select a Track MP3 first.');
                return;
            }

            if (current_url !== saved_url || isAutoDraft) {
                alert('Please click "Publish" or "Update" first!\n\nThe MP3 needs to be saved to the database before the AI can securely analyze it.');
                return;
            }

            let btn = $(this);
            let id = $('#post_ID').val();
            btn.text('Scanning Audio...').prop('disabled', true);
            $.post(ajaxurl, { action: 'lj_gemini_scan', song_id: id, security: '<?php echo esc_js($gemini_nonce); ?>' }, function(res) {
                if(res.success) {
                    let msg = "Success!";
                    if(res.data.genres) msg += '\nGenres assigned: ' + res.data.genres.join(', ');
                    if(res.data.explicit_status) msg += '\nRating status: ' + res.data.explicit_status;
                    if(res.data.lyrics_status) msg += '\nLyrics status: ' + res.data.lyrics_status;
                    alert(msg);
                    location.reload(); 
                } else {
                    alert('Error: ' + res.data);
                    btn.text('✨ Analyze Audio').prop('disabled', false);
                }
            }).fail(function() {
                alert('Server timeout or error. The file may be too large.');
                btn.text('✨ Analyze Audio').prop('disabled', false);
            });
        });
    });
    </script>
    <?php
}

// ------------------------------------------
// SCHEDULE META BOX
// ------------------------------------------
function lj_schedule_details_callback( $post ) {
    wp_nonce_field( 'lj_save_schedule_data', 'lj_schedule_meta_nonce' );
    $days = get_post_meta( $post->ID, 'lj_days', true ) ?: [];
    if (!is_array($days)) $days = [$days];
    
    $start_time = get_post_meta( $post->ID, 'lj_start_time', true );
    $end_time   = get_post_meta( $post->ID, 'lj_end_time', true );
    $playlist   = get_post_meta( $post->ID, 'lj_playlist', true );
    $genre      = get_post_meta( $post->ID, 'lj_genre', true );
    $artist     = get_post_meta( $post->ID, 'lj_artist', true );
    
    $all_days = ['everyday' => 'Every Day', 'monday' => 'Monday', 'tuesday' => 'Tuesday', 'wednesday' => 'Wednesday', 'thursday' => 'Thursday', 'friday' => 'Friday', 'saturday' => 'Saturday', 'sunday' => 'Sunday'];
    ?>
    <table class="form-table">
        <tr>
            <th><label>Active Days</label></th>
            <td>
                <?php foreach($all_days as $val => $label): ?>
                    <label style="margin-right: 15px;">
                        <input type="checkbox" name="lj_days[]" value="<?php echo esc_attr($val); ?>" <?php checked(in_array($val, $days)); ?> /> <?php echo esc_html($label); ?>
                    </label>
                <?php endforeach; ?>
            </td>
        </tr>
        <tr>
            <th><label>Start Time</label></th>
            <td><input type="time" name="lj_start_time" value="<?php echo esc_attr($start_time); ?>" required /></td>
        </tr>
        <tr>
            <th><label>End Time</label></th>
            <td>
                <input type="time" name="lj_end_time" value="<?php echo esc_attr($end_time); ?>" required />
                <p class="description">If End Time is earlier than Start Time, the schedule assumes it crosses midnight.</p>
            </td>
        </tr>
        <tr><td colspan="2"><hr><strong>Target Routing</strong> (Type or auto complete tags to lock the event).</td></tr>
        <tr>
            <th><label>Playlists</label></th>
            <td><?php lj_render_tax_select_field('lj_playlist', $playlist, 'lj_playlist_arr', 'Select playlists...'); ?></td>
        </tr>
        <tr>
            <th><label>Genres</label></th>
            <td><?php lj_render_tax_select_field('lj_genre', $genre, 'lj_genre_arr', 'Select genres...'); ?></td>
        </tr>
        <tr>
            <th><label>Artists</label></th>
            <td><?php lj_render_tax_select_field('lj_artist', $artist, 'lj_artist_arr', 'Select artists...'); ?></td>
        </tr>
    </table>
    
    <script>
    jQuery(document).ready(function($){
        if ($.fn.select2) {
            $('.lj-select2').select2({
                tags: true,
                tokenSeparators: [','],
                allowClear: true
            });
        }
    });
    </script>
    <?php
}

add_action( 'save_post', 'lj_save_custom_meta_data' );
function lj_save_custom_meta_data( $post_id ) {
    if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    $post_type = get_post_type($post_id);

    if ( $post_type === 'lj_song' ) {
        if ( ! isset( $_POST['lj_song_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lj_song_meta_nonce'] ) ), 'lj_save_song_data' ) ) {
            return;
        }

        update_option('lj_catalog_version', time());
        
        $always_available = isset($_POST['lj_always_available']) ? 1 : 0;
        update_post_meta($post_id, 'lj_always_available', $always_available);
        
        $play_globally = isset($_POST['lj_play_globally']) ? 1 : 0;
        update_post_meta($post_id, 'lj_play_globally', $play_globally);
        
        $is_explicit = isset($_POST['lj_is_explicit']) ? 1 : 0;
        update_post_meta($post_id, 'lj_is_explicit', $is_explicit);
        
        $is_royalty_free = isset($_POST['lj_royalty_free']) ? 1 : 0;
        update_post_meta($post_id, 'lj_royalty_free', $is_royalty_free);
        
        $license_override = isset($_POST['lj_license_override']) ? 1 : 0;
        update_post_meta($post_id, 'lj_license_override', $license_override);
        
        if ( isset($_POST['lj_tip_url']) ) {
            update_post_meta($post_id, 'lj_tip_url', esc_url_raw(wp_unslash($_POST['lj_tip_url'])));
        }
        
        if ( isset($_POST['lj_custom_banner_text']) ) {
            update_post_meta($post_id, 'lj_custom_banner_text', wp_kses_post(wp_unslash($_POST['lj_custom_banner_text'])));
        }
        
        if ( isset($_POST['lj_lyrics']) ) {
            update_post_meta($post_id, 'lj_lyrics', sanitize_textarea_field(wp_unslash($_POST['lj_lyrics'])));
        }
        
        if ( isset($_POST['preview_url']) ) update_post_meta($post_id, 'preview_url', esc_url_raw(wp_unslash($_POST['preview_url'])));
        
        if ( !empty($_POST['lj_audio_attachment_id']) ) {
            $id = intval(wp_unslash($_POST['lj_audio_attachment_id']));
            $url = wp_get_attachment_url($id);
            if ($url) {
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

        $process_memo('lj_intro_attachment_id', 'intro_audio_url', 'intro_duration');
        $process_memo('lj_outro_attachment_id', 'outro_audio_url', 'outro_duration');

    } elseif ( $post_type === 'lj_schedule' ) {
        if ( ! isset( $_POST['lj_schedule_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lj_schedule_meta_nonce'] ) ), 'lj_save_schedule_data' ) ) {
            return;
        }

        update_option('lj_catalog_version', time());
        
        $days = isset($_POST['lj_days']) ? array_map('sanitize_text_field', wp_unslash($_POST['lj_days'])) : [];
        update_post_meta($post_id, 'lj_days', $days);
        
        if ( isset($_POST['lj_start_time']) ) update_post_meta($post_id, 'lj_start_time', sanitize_text_field(wp_unslash($_POST['lj_start_time'])));
        if ( isset($_POST['lj_end_time']) )   update_post_meta($post_id, 'lj_end_time', sanitize_text_field(wp_unslash($_POST['lj_end_time'])));
        
        if ( isset($_POST['lj_playlist_arr']) ) {
            update_post_meta($post_id, 'lj_playlist', implode(',', array_map('sanitize_text_field', wp_unslash($_POST['lj_playlist_arr']))));
        } else {
            update_post_meta($post_id, 'lj_playlist', '');
        }
        
        if ( isset($_POST['lj_genre_arr']) ) {
            update_post_meta($post_id, 'lj_genre', implode(',', array_map('sanitize_text_field', wp_unslash($_POST['lj_genre_arr']))));
        } else {
            update_post_meta($post_id, 'lj_genre', '');
        }
        
        if ( isset($_POST['lj_artist_arr']) ) {
            update_post_meta($post_id, 'lj_artist', implode(',', array_map('sanitize_text_field', wp_unslash($_POST['lj_artist_arr']))));
        } else {
            update_post_meta($post_id, 'lj_artist', '');
        }
    }
}

add_action('trashed_post', function($post_id) {
    if(in_array(get_post_type($post_id), ['lj_song', 'lj_schedule'])) update_option('lj_catalog_version', time());
});

// ==========================================
// 4. NETWORK LOGIC & SMART ROUTING 
// ==========================================

function lj_get_explicit_meta_query() {
    if (!get_option('lj_allow_explicit', 1)) {
        return [
            'relation' => 'OR',
            ['key' => 'lj_is_explicit', 'compare' => 'NOT EXISTS'],
            ['key' => 'lj_is_explicit', 'value' => '1', 'compare' => '!=']
        ];
    }
    return [];
}

function lj_get_license_meta_query() {
    if (get_option('lj_exclude_licensed', 0)) {
        return [
            'relation' => 'OR',
            ['key' => 'lj_royalty_free', 'value' => '1', 'compare' => '='],
            ['key' => 'lj_license_override', 'value' => '1', 'compare' => '=']
        ];
    }
    return [];
}

function lj_get_active_schedule() {
    $now = current_datetime();
    $current_day = strtolower($now->format('l'));
    $current_time = $now->format('H:i');

    $schedules = get_posts(['post_type' => 'lj_schedule', 'posts_per_page' => -1]);
    foreach($schedules as $sched) {
        $days = get_post_meta($sched->ID, 'lj_days', true) ?: [];
        if (!is_array($days)) $days = [$days];
        
        $start = get_post_meta($sched->ID, 'lj_start_time', true);
        $end = get_post_meta($sched->ID, 'lj_end_time', true);

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
                    'playlist' => get_post_meta($sched->ID, 'lj_playlist', true),
                    'genre' => get_post_meta($sched->ID, 'lj_genre', true),
                    'artist' => get_post_meta($sched->ID, 'lj_artist', true),
                ];
            }
        }
    }
    return null;
}

function lj_song_matches_schedule($post_id, $sched_id) {
    $playlist = get_post_meta($sched_id, 'lj_playlist', true);
    $artist   = get_post_meta($sched_id, 'lj_artist', true);
    $genre    = get_post_meta($sched_id, 'lj_genre', true);
    
    if (empty($playlist) && empty($artist) && empty($genre)) return true;

    $match = true;
    if (!empty($playlist)) {
        $terms = array_map('sanitize_title', explode(',', $playlist));
        if (!has_term($terms, 'lj_playlist', $post_id)) $match = false;
    }
    if (!empty($artist) && $match) {
        $terms = array_map('sanitize_title', explode(',', $artist));
        if (!has_term($terms, 'lj_artist', $post_id)) $match = false;
    }
    if (!empty($genre) && $match) {
        $terms = array_map('sanitize_title', explode(',', $genre));
        if (!has_term($terms, 'lj_genre', $post_id)) $match = false;
    }
    return $match;
}

function lj_get_next_schedule_timestamp($sched_id) {
    $days = get_post_meta($sched_id, 'lj_days', true) ?: [];
    if (!is_array($days)) $days = [$days];
    $start = get_post_meta($sched_id, 'lj_start_time', true);
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

function lj_get_base_station_args($station_id) {
    if ($station_id === 'global') return [];
    
    $tax_query = ['relation' => 'AND'];
    $active_atts = get_option('lj_station_args_' . $station_id, []);
    
    if (empty($active_atts)) {
        $parts = explode('_', $station_id, 2);
        if (count($parts) === 2) {
            $tax = '';
            if ($parts[0] === 'playlist') $tax = 'lj_playlist';
            if ($parts[0] === 'artist') $tax = 'lj_artist';
            if ($parts[0] === 'genre') $tax = 'lj_genre';
            if ($tax) return ['tax_query' => [ [ 'taxonomy' => $tax, 'field' => 'slug', 'terms' => sanitize_title($parts[1]) ] ]];
        }
        return [];
    }

    if (!empty($active_atts['playlist'])) $tax_query[] = [ 'taxonomy' => 'lj_playlist', 'field' => 'slug', 'terms' => array_map('sanitize_title', explode(',', $active_atts['playlist'])), 'operator' => 'IN' ];
    if (!empty($active_atts['artist'])) $tax_query[] = [ 'taxonomy' => 'lj_artist', 'field' => 'slug', 'terms' => array_map('sanitize_title', explode(',', $active_atts['artist'])), 'operator' => 'IN' ];
    if (!empty($active_atts['genre'])) $tax_query[] = [ 'taxonomy' => 'lj_genre', 'field' => 'slug', 'terms' => array_map('sanitize_title', explode(',', $active_atts['genre'])), 'operator' => 'IN' ];

    if (count($tax_query) > 1) return ['tax_query' => $tax_query];
    return [];
}

function lj_get_current_station_args($station_id) {
    if ($station_id === 'global') {
        $schedule = lj_get_active_schedule();
        if ($schedule) {
            $tax_query = ['relation' => 'AND'];
            if (!empty($schedule['playlist'])) $tax_query[] = [ 'taxonomy' => 'lj_playlist', 'field' => 'slug', 'terms' => array_map('sanitize_title', explode(',', $schedule['playlist'])), 'operator' => 'IN' ];
            if (!empty($schedule['artist'])) $tax_query[] = [ 'taxonomy' => 'lj_artist', 'field' => 'slug', 'terms' => array_map('sanitize_title', explode(',', $schedule['artist'])), 'operator' => 'IN' ];
            if (!empty($schedule['genre'])) $tax_query[] = [ 'taxonomy' => 'lj_genre', 'field' => 'slug', 'terms' => array_map('sanitize_title', explode(',', $schedule['genre'])), 'operator' => 'IN' ];
            if (count($tax_query) > 1) return ['tax_query' => $tax_query];
            return [];
        }
    }
    return lj_get_base_station_args($station_id);
}

function lj_get_station_label($station_id) {
    if ($station_id === 'global') {
        $schedule = lj_get_active_schedule();
        if ($schedule) return 'LIVE: ' . $schedule['title'];
        
        if (get_option('lj_strict_event_mode')) {
            $has_overrides = get_posts([
                'post_type' => 'lj_song',
                'meta_query' => [
                    ['key' => 'lj_always_available', 'value' => '1', 'compare' => '=']
                ],
                'posts_per_page' => 1,
                'fields' => 'ids'
            ]);
            if ($has_overrides) return 'Global Broadcast';
            return 'Requests Offline (No Event)';
        }
        return 'Global Broadcast';
    }
    
    $active_atts = get_option('lj_station_args_' . $station_id, []);
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

function lj_get_open_play_fallback($query_args, $all_schedules) {
    $query_args['posts_per_page'] = 30; 
    $potential_fallbacks = get_posts($query_args);
    foreach ($potential_fallbacks as $pf) {
        $is_event_song = false;
        if (!get_post_meta($pf->ID, 'lj_always_available', true)) {
            foreach ($all_schedules as $sched) {
                if (lj_song_matches_schedule($pf->ID, $sched->ID)) {
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

function lj_process_queue_and_get_current($station_id = 'global') {
    $now = time(); 
    $current = get_option("lj_now_playing_sync_{$station_id}"); 
    $active_listeners_count = count(get_option("lj_active_listeners_{$station_id}", []));
    
    if ( !$current || $now >= ($current['start_time'] + $current['duration']) ) {
        $history = get_option("lj_play_history_{$station_id}", []);

        if ($current) {
            $actual_finish_time = $current['start_time'] + $current['duration'];
            $history[$current['id']] = $actual_finish_time;
            
            $month_key = wp_date('Y_m', $actual_finish_time);
            $month_log_option = 'lj_broadcast_log_' . $month_key;
            $broadcast_log = get_option($month_log_option, []);
            
            $broadcast_log[] = [
                'station' => $station_id,
                'id' => $current['id'],
                'title' => get_the_title($current['id']),
                'artist' => implode(', ', wp_get_post_terms($current['id'], 'lj_artist', ['fields' => 'names']) ?: ['Unknown']),
                'start_time' => $current['start_time'],
                'end_time' => $actual_finish_time,
                'listeners' => $current['listeners_at_start'] ?? $active_listeners_count
            ];

            $available_months = get_option('lj_broadcast_log_months', []);
            if (!in_array($month_key, $available_months)) {
                $available_months[] = $month_key;
                rsort($available_months); 
                update_option('lj_broadcast_log_months', $available_months);
            }

            update_option($month_log_option, array_values($broadcast_log));

            foreach($history as $id => $time) if($now - $time > 3600) unset($history[$id]); 
            update_option("lj_play_history_{$station_id}", $history);
        }

        $queue = get_transient("lj_active_queue_{$station_id}") ?: [];
        
        if (!get_option('lj_allow_explicit', 1)) {
            foreach($queue as $qid => $qdata) {
                if (get_post_meta($qid, 'lj_is_explicit', true)) unset($queue[$qid]);
            }
        }

        if (get_option('lj_exclude_licensed', 0)) {
            foreach($queue as $qid => $qdata) {
                if (!get_post_meta($qid, 'lj_royalty_free', true) && !get_post_meta($qid, 'lj_license_override', true)) {
                    unset($queue[$qid]);
                }
            }
        }
        
        if ( !empty($queue) ) {
            uasort($queue, function($a, $b){ return $a['votes'] == $b['votes'] ? $a['added'] <=> $b['added'] : $b['votes'] <=> $a['votes']; });
            $id = array_key_first($queue); unset($queue[$id]); set_transient("lj_active_queue_{$station_id}", $queue, 12 * HOUR_IN_SECONDS);
            
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
            update_option("lj_now_playing_sync_{$station_id}", $current);
        } else {
            $history_keys = !empty($history) ? array_keys($history) : [0];
            
            $query_args = [
                'post_type' => 'lj_song', 
                'posts_per_page' => 1, 
                'orderby' => 'rand', 
                'post__not_in' => $history_keys, 
                'meta_query' => [['key' => 'full_audio_url', 'value' => '', 'compare' => '!=']]
            ];
            
            $explicit_block = lj_get_explicit_meta_query();
            if (!empty($explicit_block)) $query_args['meta_query'][] = $explicit_block;
            
            $license_block = lj_get_license_meta_query();
            if (!empty($license_block)) $query_args['meta_query'][] = $license_block;
            
            $station_args = lj_get_current_station_args($station_id);
            $is_open_play = ($station_id === 'global' && !lj_get_active_schedule() && !get_option('lj_strict_event_mode'));
            $all_schedules = $is_open_play ? get_posts(['post_type' => 'lj_schedule', 'posts_per_page' => -1]) : [];
            
            if ($station_id === 'global' && !lj_get_active_schedule()) {
                if (get_option('lj_strict_event_mode')) {
                    $query_args['meta_query'][] = ['key' => 'lj_always_available', 'value' => '1', 'compare' => '='];
                    $fallback = get_posts($query_args);
                } else {
                    $global_args = $query_args;
                    $global_args['meta_query'][] = ['key' => 'lj_play_globally', 'value' => '1', 'compare' => '='];
                    $fallback = get_posts($global_args);
                    
                    if (!$fallback) {
                        $fallback = lj_get_open_play_fallback($query_args, $all_schedules);
                    }
                }
            } else {
                if (!empty($station_args)) $query_args = array_merge($query_args, $station_args);
                $fallback = get_posts($query_args);
            }
            
            if (!$fallback && ($station_id !== 'global' || lj_get_active_schedule() || !get_option('lj_strict_event_mode'))) {
                $last_id = $current ? $current['id'] : 0; 
                $history = []; 
                if ($last_id) $history[$last_id] = $now; 
                update_option("lj_play_history_{$station_id}", $history);
                
                $query_args['post__not_in'] = [$last_id];
                $fallback = $is_open_play ? lj_get_open_play_fallback($query_args, $all_schedules) : get_posts($query_args);
                
                if (!$fallback) {
                    unset($query_args['post__not_in']);
                    $fallback = $is_open_play ? lj_get_open_play_fallback($query_args, $all_schedules) : get_posts($query_args);
                }
            }
            
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
                update_option("lj_now_playing_sync_{$station_id}", $current);
            } else {
                $current = null; 
            }
        }
    }
    return $current;
}

add_action( 'wp_ajax_lj_vote', 'lj_handle_vote' );
add_action( 'wp_ajax_nopriv_lj_vote', 'lj_handle_vote' );
function lj_handle_vote() {
    if ( ! isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST' ) wp_send_json_error('Invalid request method.');
    if ( !isset($_POST['security']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['security'])), 'lj_frontend_action') ) wp_send_json_error('Security validation failed.');

    $station_id = isset($_POST['station']) ? sanitize_text_field(wp_unslash($_POST['station'])) : 'global';
    if ($station_id !== 'global' && !preg_match('/^station_[a-f0-9]{10}$/', $station_id)) {
        $station_id = 'global';
    }

    $id = isset($_POST['song_id']) ? intval(wp_unslash($_POST['song_id'])) : 0; 
    $now = time(); 
    
    if (!get_option('lj_allow_explicit', 1) && get_post_meta($id, 'lj_is_explicit', true)) {
        wp_send_json_error('This track contains explicit content and is currently disabled by the venue.');
    }

    if (get_option('lj_exclude_licensed', 0) && !get_post_meta($id, 'lj_royalty_free', true) && !get_post_meta($id, 'lj_license_override', true)) {
        wp_send_json_error('Licensed music is currently disabled globally by the venue.');
    }
    
    if ($station_id === 'global') {
        $active_schedule = lj_get_active_schedule();
        $is_always_available = get_post_meta($id, 'lj_always_available', true);
        
        if ( ! $active_schedule ) {
            if ( get_option('lj_strict_event_mode') && ! $is_always_available ) {
                wp_send_json_error('The request line is currently closed. This song can only be requested during a scheduled event.');
            } elseif ( ! get_option('lj_strict_event_mode') && ! $is_always_available ) {
                $all_schedules = get_posts(['post_type' => 'lj_schedule', 'posts_per_page' => -1]);
                foreach($all_schedules as $sched) {
                    if (lj_song_matches_schedule($id, $sched->ID)) {
                        wp_send_json_error('This is an event exclusive track and can only be requested during its scheduled event.');
                    }
                }
            }
        } else {
            if ( ! lj_song_matches_schedule($id, $active_schedule['id']) && ! $is_always_available ) {
                wp_send_json_error('This song is locked until its specific event block is live.');
            }
        }
    }
    
    if (!session_id()) session_start();
    
    $current = get_option("lj_now_playing_sync_{$station_id}");
    if ($current && $current['id'] == $id) wp_send_json_error('This song is currently playing on the air.');
    
    $history = get_option("lj_play_history_{$station_id}", []);
    if (isset($history[$id]) && ($now - $history[$id] < 3600)) wp_send_json_error('This song has played recently. Please wait for the cooldown.');
    
    $session_key = "lj_vote_times_{$station_id}";
    $user_history = isset($_SESSION[$session_key]) && is_array($_SESSION[$session_key]) ? array_map('intval', wp_unslash($_SESSION[$session_key])) : [];
    foreach($user_history as $k => $time) if($now - $time > 3600) unset($user_history[$k]);
    if (count($user_history) >= 10) wp_send_json_error('You have reached your 10 vote limit for this hour on this station.');
    
    $user_history[] = $now; 
    $_SESSION[$session_key] = $user_history;
    session_write_close(); 
    
    $queue = get_transient("lj_active_queue_{$station_id}") ?: [];
    if(isset($queue[$id])) $queue[$id]['votes']++; else $queue[$id] = ['votes' => 1, 'added' => $now];
    set_transient("lj_active_queue_{$station_id}", $queue, 12 * HOUR_IN_SECONDS); wp_send_json_success('Vote counted!');
}

add_action( 'wp_ajax_lj_get_state', 'lj_get_state' );
add_action( 'wp_ajax_nopriv_lj_get_state', 'lj_get_state' );
function lj_get_state() {
    if ( ! isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'GET' ) wp_send_json_error('Invalid request method.');
    if ( !isset($_GET['security']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['security'])), 'lj_frontend_action') ) wp_send_json_error('Security validation failed.');

    $now = time(); 
    $lid = isset($_GET['listener_id']) ? sanitize_text_field(wp_unslash($_GET['listener_id'])) : '';
    $is_listening = isset($_GET['is_listening']) ? sanitize_text_field(wp_unslash($_GET['is_listening'])) : 'false';
    
    $station_id = isset($_GET['station']) ? sanitize_text_field(wp_unslash($_GET['station'])) : 'global';
    if ($station_id !== 'global' && !preg_match('/^station_[a-f0-9]{10}$/', $station_id)) {
        $station_id = 'global';
    }

    $listeners = get_option("lj_active_listeners_{$station_id}", []);
    if ($lid) { if($is_listening === 'true') $listeners[$lid] = $now; else unset($listeners[$lid]); }
    foreach($listeners as $k => $v) if($now - $v > 15) unset($listeners[$k]);
    update_option("lj_active_listeners_{$station_id}", $listeners);

    $cp = lj_process_queue_and_get_current($station_id);
    $q = get_transient("lj_active_queue_{$station_id}") ?: [];
    uasort($q, function($a, $b){ return $a['votes'] == $b['votes'] ? $a['added'] <=> $b['added'] : $b['votes'] <=> $a['votes']; });
    $fq = [];
    foreach($q as $sid => $d) {
        $custom_banner = get_post_meta($sid, 'lj_custom_banner_text', true);
        $submitter_terms = wp_get_post_terms($sid, 'lj_submitter', ['fields' => 'names']);
        $submitter = !empty($submitter_terms) ? implode(', ', $submitter_terms) : '';
        $banner = !empty($custom_banner) ? $custom_banner : (!empty($submitter) ? 'Submitted by: ' . $submitter : '');

        $fq[] = ['id' => $sid, 'title' => html_entity_decode(get_the_title($sid)), 'artist' => html_entity_decode(implode(', ', wp_get_post_terms($sid, 'lj_artist', ['fields' => 'names']) ?: ['Unknown'])), 'is_explicit' => get_post_meta($sid, 'lj_is_explicit', true) ? true : false, 'has_lyrics' => !empty(get_post_meta($sid, 'lj_lyrics', true)), 'banner' => $banner, 'tip_url' => get_post_meta($sid, 'lj_tip_url', true), 'genre' => implode(', ', wp_get_post_terms($sid, 'lj_genre', ['fields' => 'names']) ?: []), 'votes' => $d['votes'], 'preview_url' => get_post_meta($sid, 'preview_url', true), 'url' => get_post_meta($sid, 'full_audio_url', true), 'permalink' => get_permalink($sid) ];
    }
    
    $np = null;
    if ($cp) {
        $custom_banner_np = get_post_meta($cp['id'], 'lj_custom_banner_text', true);
        $submitter_terms_np = wp_get_post_terms($cp['id'], 'lj_submitter', ['fields' => 'names']);
        $submitter_np = !empty($submitter_terms_np) ? implode(', ', $submitter_terms_np) : '';
        $banner_np = !empty($custom_banner_np) ? $custom_banner_np : (!empty($submitter_np) ? 'Submitted by: ' . $submitter_np : '');

        $np = [
            'id' => $cp['id'], 
            'title' => html_entity_decode(get_the_title($cp['id'])), 
            'artist' => html_entity_decode(implode(', ', wp_get_post_terms($cp['id'], 'lj_artist', ['fields' => 'names']) ?: ['Unknown'])), 
            'is_explicit' => get_post_meta($cp['id'], 'lj_is_explicit', true) ? true : false, 
            'has_lyrics' => !empty(get_post_meta($cp['id'], 'lj_lyrics', true)), 
            'banner' => $banner_np, 
            'tip_url' => get_post_meta($cp['id'], 'lj_tip_url', true), 
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
    $cat_version = get_option('lj_catalog_version', 0);
    
    $all_schedules = get_posts(['post_type' => 'lj_schedule', 'posts_per_page' => -1]);
    $upcoming_events = [];
    foreach($all_schedules as $sched) {
        $next_run = lj_get_next_schedule_timestamp($sched->ID);
        if ($next_run) {
            $upcoming_events[] = [
                'title' => get_the_title($sched->ID),
                'timestamp' => $next_run,
                'start_time' => get_post_meta($sched->ID, 'lj_start_time', true),
                'end_time' => get_post_meta($sched->ID, 'lj_end_time', true)
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
        'station_label' => lj_get_station_label($station_id),
        'upcoming_events' => $sliced_events
    ]);
}

add_action( 'wp_ajax_lj_get_catalog', 'lj_get_catalog' );
add_action( 'wp_ajax_nopriv_lj_get_catalog', 'lj_get_catalog' );
function lj_get_catalog() {
    if ( ! isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'GET' ) wp_send_json_error('Invalid request method.');
    if ( !isset($_GET['security']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['security'])), 'lj_frontend_action') ) wp_send_json_error('Security validation failed.');

    $station_id = isset($_GET['station']) ? sanitize_text_field(wp_unslash($_GET['station'])) : 'global';
    if ($station_id !== 'global' && !preg_match('/^station_[a-f0-9]{10}$/', $station_id)) {
        $station_id = 'global';
    }
    
    $query_args = ['post_type' => 'lj_song', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC', 'meta_query' => []];
    
    $explicit_block = lj_get_explicit_meta_query();
    if (!empty($explicit_block)) $query_args['meta_query'][] = $explicit_block;
    
    $license_block = lj_get_license_meta_query();
    if (!empty($license_block)) $query_args['meta_query'][] = $license_block;
    
    $station_args = lj_get_base_station_args($station_id);
    if (!empty($station_args)) {
        $query_args = array_merge($query_args, $station_args);
    }
    
    $songs = get_posts($query_args);
    $history = get_option("lj_play_history_{$station_id}", []); 
    $current = get_option("lj_now_playing_sync_{$station_id}"); 
    $now = time(); 
    $cat = [];
    
    $active_schedule = ($station_id === 'global') ? lj_get_active_schedule() : null;
    $strict_mode = get_option('lj_strict_event_mode');
    $all_schedules = [];
    $schedule_runs = [];
    
    if ($station_id === 'global') {
        $all_schedules = get_posts(['post_type' => 'lj_schedule', 'posts_per_page' => -1]);
        foreach($all_schedules as $sched) {
            $schedule_runs[$sched->ID] = [
                'title' => get_the_title($sched->ID),
                'next_run' => lj_get_next_schedule_timestamp($sched->ID)
            ];
        }
    }
    
    foreach($songs as $p) {
        $last_play = $history[$p->ID] ?? 0; 
        $remaining = ($now - $last_play < 3600) ? 3600 - ($now - $last_play) : 0; 
        $is_playing = ($current && $current['id'] == $p->ID);
        $is_always_available = get_post_meta($p->ID, 'lj_always_available', true);
        $is_explicit = get_post_meta($p->ID, 'lj_is_explicit', true) ? true : false;
        $has_lyrics = !empty(get_post_meta($p->ID, 'lj_lyrics', true));

        $custom_banner = get_post_meta($p->ID, 'lj_custom_banner_text', true);
        $submitter_terms = wp_get_post_terms($p->ID, 'lj_submitter', ['fields' => 'names']);
        $submitter = !empty($submitter_terms) ? implode(', ', $submitter_terms) : '';
        $banner = !empty($custom_banner) ? $custom_banner : (!empty($submitter) ? 'Submitted by: ' . $submitter : '');
        
        $is_locked_by_schedule = false;
        $unlock_msg = '';
        $unlock_timestamp = null;

        if ($station_id === 'global') {
            if ($active_schedule) {
                if (!lj_song_matches_schedule($p->ID, $active_schedule['id']) && !$is_always_available) {
                    $is_locked_by_schedule = true;
                    $closest_time = PHP_INT_MAX;
                    $next_sched_name = '';
                    
                    foreach($all_schedules as $sched) {
                        if ($sched->ID == $active_schedule['id']) continue;
                        if (lj_song_matches_schedule($p->ID, $sched->ID)) {
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
                        if (lj_song_matches_schedule($p->ID, $sched->ID)) {
                            $run = $schedule_runs[$sched->ID]['next_run'] ?? lj_get_next_schedule_timestamp($sched->ID);
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
                        if (lj_song_matches_schedule($p->ID, $sched->ID)) {
                            $belongs_to_schedule = true;
                            $run = $schedule_runs[$sched->ID]['next_run'] ?? lj_get_next_schedule_timestamp($sched->ID);
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
            'artist' => html_entity_decode(implode(', ', wp_get_post_terms($p->ID, 'lj_artist', ['fields' => 'names']) ?: ['Unknown Artist'])), 
            'genre' => implode(', ', wp_get_post_terms($p->ID, 'lj_genre', ['fields' => 'names']) ?: []), 
            'is_explicit' => $is_explicit,
            'has_lyrics' => $has_lyrics,
            'banner' => $banner,
            'tip_url' => get_post_meta($p->ID, 'lj_tip_url', true),
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
add_shortcode( 'community_radio_jukebox', 'lj_render_frontend_app' );
function lj_render_frontend_app($atts) {
    
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
        add_option('lj_station_args_' . $station_id, $active_atts, '', 'no');
    }

    $station_label = lj_get_station_label($station_id);

    $ajax_url = admin_url( 'admin-ajax.php' );
    $security_nonce = wp_create_nonce( 'lj_frontend_action' );
    $submit_enabled = get_option('lj_enable_submissions') == '1';
    $submit_url = get_option('lj_submission_url');

    ob_start();
    ?>
    <style>
        :root { --lj-bg: #fff; --lj-text: #222; --lj-panel: rgba(245,245,245,0.92); --lj-border: #ccc; --lj-accent: #0073aa; --lj-sec: #555; }
        [data-theme="dark"] { --lj-bg: #000; --lj-text: #fff; --lj-panel: rgba(10,10,10,0.85); --lj-border: #444; --lj-accent: #3598dc; --lj-sec: #bbb; }
        
        .lj-app-container { position: relative; z-index: 10; background: var(--lj-panel); color: var(--lj-text); font-family: system-ui, sans-serif; width: 94%; max-width: 480px; margin: 20px auto; padding: 20px; border: 1px solid var(--lj-border); border-radius: 24px; transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1); backdrop-filter: blur(15px); box-shadow: 0 20px 50px rgba(0,0,0,0.4); }
        
        .lj-dashboard-grid { display: flex; flex-direction: column; gap: 20px; }
        .lj-dashboard-column { display: flex; flex-direction: column; gap: 15px; }

        .lj-track-item { background: var(--lj-panel); border: 1px solid var(--lj-border); padding: 15px; border-radius: 12px; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; transition: opacity 0.3s; width: 100%; max-width: none; contain: layout; position: relative; box-sizing: border-box; }
        .lj-track-info { flex: 1; min-width: 160px; max-width: none; overflow: hidden; }
        
        .lj-locked .lj-track-info, .lj-locked .lj-btn-vote { opacity: 0.5; filter: grayscale(0.8); }
        .lj-locked .lj-btn-vote { pointer-events: none; cursor: not-allowed; }
        .lj-cooldown-badge { font-size: 10px; font-weight: 800; background: rgba(220, 53, 69, 0.15); color: #dc3545; padding: 4px 10px; border-radius: 6px; display: inline-block; margin-top: 6px; }
        .lj-explicit-badge { font-size: 9px; font-weight: 800; background: #666; color: #fff; padding: 2px 5px; border-radius: 4px; margin-left: 6px; vertical-align: middle; }
        .lj-genre-badge { font-size: 10px; font-weight: 700; background: var(--lj-accent); color: #fff; padding: 2px 6px; border-radius: 6px; margin-left: 6px; text-transform: uppercase; }
        .lj-clickable-artist { cursor: pointer; color: var(--lj-accent); transition: opacity 0.2s; text-decoration: underline; text-decoration-style: dotted; text-underline-offset: 3px; }
        .lj-btn { background: var(--lj-accent); color: #fff; border: none; padding: 10px 16px; border-radius: 10px; cursor: pointer; font-weight: 700; transition: transform 0.1s; display: inline-flex; align-items: center; justify-content: center; }
        .lj-btn-vote { background: #28a745; transition: all 0.2s ease; }
        .lj-btn-vote.lj-voted { background: #155d27; opacity: 0.7; cursor: default; }
        .lj-btn-sync { background: #d63638; width: 100%; padding: 18px; border-radius: 50px; animation: pulse 2s infinite; margin-top: 15px; font-size: 17px;}
        .lj-btn-disconnect { background: var(--lj-sec); width: 100%; padding: 18px; border-radius: 50px; display: none; margin-top: 15px;}
        .lj-station-badge { font-size: 11px; font-weight: 800; background: var(--lj-accent); color: #fff; padding: 4px 10px; border-radius: 12px; margin-top: 5px; display: inline-block; vertical-align: middle; white-space: nowrap; }
        
        .lj-marquee-container { width: 100%; max-width: 100%; overflow: hidden; white-space: nowrap; background: rgba(0,0,0,0.05); border-radius: 6px; margin-top: 10px; padding: 5px 0; box-sizing: border-box; contain: layout paint; position: relative; display: block; }
        [data-theme="dark"] .lj-marquee-container { background: rgba(255,255,255,0.05); }
        .lj-marquee-content { display: inline-block; padding-left: 100%; animation: lj-marquee 12s linear infinite; font-weight: 600; font-size: 12px; color: var(--lj-accent); }
        
        #lj-schedule-list { max-height: 250px; overflow-y: auto; padding-right: 5px; scrollbar-width: thin; scrollbar-color: var(--lj-border) transparent; }
        #lj-schedule-list::-webkit-scrollbar { width: 4px; }
        #lj-schedule-list::-webkit-scrollbar-track { background: transparent; }
        #lj-schedule-list::-webkit-scrollbar-thumb { background: var(--lj-border); border-radius: 4px; }
        
        @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(214, 54, 56, 0.6); } 70% { box-shadow: 0 0 0 15px rgba(214, 54, 56, 0); } 100% { box-shadow: 0 0 0 0 rgba(214, 54, 56, 0); } }
        @keyframes lj-marquee { 0% { transform: translate3d(0, 0, 0); } 100% { transform: translate3d(-100%, 0, 0); } }
        
        #lj-alert-container { position: fixed; top: 20px; left: 50%; transform: translateX(-50%); z-index: 9999; width: 90%; max-width: 460px; pointer-events: none; }
        #lj-alert-container .alert { pointer-events: auto; font-weight: 600; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); border: none; }

        @media (min-width: 768px) {
            .lj-app-container { max-width: 1140px; width: 92%; padding: 30px; margin: 40px auto; }
            .lj-dashboard-grid { display: grid; grid-template-columns: 340px 1fr; gap: 25px; align-items: start; }
            
            .lj-sticky-pane { 
                position: sticky; 
                top: 100px; 
                max-height: calc(100vh - 120px); 
                overflow-y: auto; 
                scrollbar-width: none;
            }
            .lj-sticky-pane::-webkit-scrollbar { display: none; }
            
            #lj-queue-list, #lj-catalog-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 15px; }
            .lj-track-item { margin-bottom: 0; }
        }

        @media (min-width: 1200px) {
            .lj-dashboard-grid { grid-template-columns: 380px 1fr; gap: 35px; }
            #lj-queue-list, #lj-catalog-list { grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 18px; }
        }
    </style>

    <div id="lj-alert-container"></div>

    <div class="lj-app-container" id="lj-app-root" data-theme="light">
        <div style="display:flex; justify-content:space-between; border-bottom:2px solid var(--lj-border); margin-bottom:20px; padding-bottom:10px; align-items:center; flex-wrap:wrap; gap: 10px;">
            <div>
                <h2 style="margin:0; font-size:22px; display:flex; align-items:center;"><i class="fa-solid fa-radio"></i> <span style="margin-left:8px;">JUKEBOX</span></h2>
                <div class="lj-station-badge" id="lj-station-badge-text" title="Active Filters"><?php echo esc_html($station_label); ?></div>
            </div>
            <div style="display:flex; gap:15px; font-size:20px; color:var(--lj-accent);">
                <?php if ($submit_enabled && !empty($submit_url)): ?><a href="<?php echo esc_url($submit_url); ?>" target="_blank" style="color:inherit; text-decoration:none;"><i class="fa-solid fa-cloud-arrow-up"></i></a><?php endif; ?>
                <i class="fa-solid fa-list-ul" id="lj-catalog-toggle" style="cursor:pointer;" title="Toggle Catalog"></i>
                <i class="fa-regular fa-calendar-alt" id="lj-schedule-toggle" style="cursor:pointer;" title="View Schedule"></i>
                <i class="fa-solid fa-circle-info" id="lj-info-toggle" style="cursor:pointer;" title="How it works"></i>
                <div id="lj-theme-toggle" style="cursor:pointer;" title="Toggle Theme"><i class="fa-solid fa-moon"></i></div>
            </div>
        </div>

        <div class="lj-dashboard-grid">
            
            <div class="lj-dashboard-column lj-sticky-pane">
                
                <div id="lj-info-panel" style="display:none; background:var(--lj-bg); border:1px solid var(--lj-accent); border-radius:12px; padding:15px; font-size:13px; line-height:1.5; box-shadow: inset 0 0 10px rgba(0,0,0,0.05); text-align:left;">
                    <p style="font-weight:800; margin-bottom:10px; font-size:15px; color:var(--lj-accent);"><i class="fa-solid fa-radio"></i> Community Radio Jukebox</p>
                    <ul style="padding-left:20px; margin-bottom:0;">
                        <li style="margin-bottom:6px;"><strong>Connect:</strong> Lock your audio exactly in sync with everyone else in town.</li>
                        <li style="margin-bottom:6px;"><strong>Voting:</strong> You get <strong>10 votes per hour</strong>. Use them to boost your favorite tracks.</li>
                        <li style="margin-bottom:6px;"><strong>Offline Mode:</strong> A green checkmark <i class="fa-solid fa-circle-check" style="color:#28a745;"></i> indicates the track is safely cached on your device.</li>
                    </ul>
                </div>

                <div id="lj-schedule-panel" style="display:none; background:var(--lj-bg); border:1px solid var(--lj-accent); border-radius:12px; padding:15px; font-size:13px; line-height:1.5; box-shadow: inset 0 0 10px rgba(0,0,0,0.05); text-align:left;">
                    <h3 style="margin-top:0; font-size:16px; font-weight:800; border-bottom:1px solid var(--lj-border); padding-bottom:10px; margin-bottom:10px;"><i class="fa-regular fa-calendar-alt"></i> Upcoming Events</h3>
                    <ul style="list-style:none; padding:0; margin:0;" id="lj-schedule-list">
                        <li style="color:var(--lj-sec); font-style:italic;">Loading schedule...</li>
                    </ul>
                </div>

                <div class="lj-now-playing" id="lj-np-panel" style="text-align:center; padding:15px; background:var(--lj-panel); border-radius:16px; border-left:6px solid var(--lj-accent);">
                    <div style="display:flex; justify-content: space-between; font-size: 11px; font-weight: 800; text-transform: uppercase;">
                        <span id="lj-np-status-label" style="color: var(--lj-accent);">On Air</span>
                        <span id="lj-listener-count" style="color: var(--lj-accent);"><i class="fa-solid fa-users"></i> 0</span>
                    </div>
                    <h3 id="lj-np-title" style="margin:12px 0 4px 0; font-size: 20px;">Awaiting...</h3>
                    <p id="lj-np-artist" style="margin:0; color: var(--lj-sec); font-weight:600;">...</p>
                    <div id="lj-np-time" style="font-size:14px; margin-top:10px; font-weight:800; color: var(--lj-sec);"><i class="fa-solid fa-hourglass-half"></i> --:--</div>
                    
                    <div id="lj-np-tip-container" style="display:none; margin: 20px 0 10px 0;">
                        <a id="lj-np-tip-btn" href="#" target="_blank" class="w-100 btn btn-warning btn-lg" style="background: #ffaa00; color: #000; font-weight: 800; border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(255, 170, 0, 0.3); transition: transform 0.2s;">
                            <i class="fa-solid fa-hand-holding-dollar" style="margin-right: 8px;"></i> Tip Active Artist
                        </a>
                    </div>

                    <div id="lj-np-banner" style="display:none;" class="lj-marquee-container">
                        <div class="lj-marquee-content" id="lj-np-banner-text"></div>
                    </div>

                    <div style="display:flex; gap:10px; margin-top:15px;">
                        <button id="lj-sync-btn" class="lj-btn lj-btn-sync" style="margin-top:0; flex:1;"><i class="fa-solid fa-broadcast-tower"></i> Connect</button>
                        <button id="lj-disconnect-btn" class="lj-btn lj-btn-disconnect" style="margin-top:0; flex:1;">Disconnect</button>
                        <button id="lj-stop-preview-btn" class="lj-btn" style="background:#ffc107; color:#000; flex:1; padding:18px; border-radius:50px; display:none; margin-top:0; font-size:17px;"><i class="fa-solid fa-stop"></i> End Preview</button>
                    </div>
                </div>
            </div>
            
            <div class="lj-dashboard-column">
                <div>
                    <h3 style="font-size:15px; margin-bottom:12px; font-weight:800;">Queue</h3>
                    <ul id="lj-queue-list" style="list-style:none; padding:0; margin:0;"></ul>
                </div>
                
                <div id="lj-catalog-container">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; flex-wrap:wrap; gap:10px;">
                        <h3 style="font-size:15px; font-weight:800; margin:0;">Catalog</h3>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <label style="font-size:11px; font-weight:700; cursor:pointer; display:flex; align-items:center; gap:5px; margin:0;">
                                <input type="checkbox" id="lj-available-only" style="margin:0; cursor:pointer;"> Available Only
                            </label>
                            <select id="lj-catalog-sort" style="padding:6px; border-radius:8px; font-size:12px; background:var(--lj-panel); color:inherit; border:1px solid var(--lj-border);">
                                <option value="title">Title A-Z</option><option value="artist">Artist</option><option value="newest">Newest</option>
                            </select>
                        </div>
                    </div>
                    <div id="lj-artist-filter-header" style="display:none; justify-content:space-between; align-items:center; background:var(--lj-accent); color:#fff; padding:10px 15px; border-radius:12px; margin-bottom:12px;">
                        <span style="font-weight:700; font-size:13px;" id="lj-filter-text">Showing Artist</span>
                        <button onclick="clearArtistFilter()" style="background:rgba(0,0,0,0.2); border:none; color:#fff; padding:4px 10px; border-radius:6px; font-size:11px; font-weight:700; cursor:pointer;"><i class="fa-solid fa-xmark"></i> Clear</button>
                    </div>
                    <ul id="lj-catalog-list" style="list-style:none; padding:0; margin:0;"></ul>
                </div>
            </div>

        </div>
        
        <audio id="lj-live-player" style="display:none;" crossorigin="anonymous"></audio>
        <audio id="lj-preview-player" style="display:none;" crossorigin="anonymous"></audio>
    </div>

    <?php
    add_action('wp_footer', function() use ($ajax_url, $security_nonce, $station_id) {
        ?>
        <script>
        document.addEventListener("DOMContentLoaded", function() {
            const root = document.getElementById('lj-app-root'), themeBtn = document.getElementById('lj-theme-toggle');
            const infoToggleBtn = document.getElementById('lj-info-toggle'), infoPanel = document.getElementById('lj-info-panel');
            const scheduleToggleBtn = document.getElementById('lj-schedule-toggle'), schedulePanel = document.getElementById('lj-schedule-panel');
            const catalogToggleBtn = document.getElementById('lj-catalog-toggle'), catalogContainer = document.getElementById('lj-catalog-container');
            const alertContainer = document.getElementById('lj-alert-container');
            
            const ajaxUrl = "<?php echo esc_js( $ajax_url ); ?>";
            const securityNonce = "<?php echo esc_js( $security_nonce ); ?>";
            const stationId = "<?php echo esc_js( $station_id ); ?>";

            const live = document.getElementById('lj-live-player'), prev = document.getElementById('lj-preview-player');
            const syncBtn = document.getElementById('lj-sync-btn'), discBtn = document.getElementById('lj-disconnect-btn'), countDisp = document.getElementById('lj-listener-count');
            const stopPreviewBtn = document.getElementById('lj-stop-preview-btn');

            const LJ_CACHE_NAME = 'lj-offline-buffer-' + stationId;
            let cId = null, isSync = false, isOffline = false, catData = [], timer, offlineQueue = [];
            let lId = localStorage.getItem('lj_l_id') || 'lj_'+Math.random().toString(36).substr(2,9);
            let clientCatalogVersion = 0; let isPreviewing = false; let currentPreviewUrl = ''; 
            localStorage.setItem('lj_l_id', lId);

            const availableOnlyCheckbox = document.getElementById('lj-available-only');
            const savedAvailableOnly = localStorage.getItem('lj_available_only') === 'true';
            availableOnlyCheckbox.checked = savedAvailableOnly;

            availableOnlyCheckbox.addEventListener('change', (e) => {
                localStorage.setItem('lj_available_only', e.target.checked);
                renderCat();
            });
            
            function escapeHTML(str) {
                if (typeof str !== 'string') return str;
                return str.replace(/[&<>'"]/g, function(tag) {
                    const charsToReplace = {
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        "'": '&#39;',
                        '"': '&quot;'
                    };
                    return charsToReplace[tag] || tag;
                });
            }

            function trackJukeboxEvent(action, name, value = null) {
                if (typeof window._paq !== 'undefined') {
                    if (value !== null) {
                        window._paq.push(['trackEvent', 'Jukebox', action, name, value]);
                    } else {
                        window._paq.push(['trackEvent', 'Jukebox', action, name]);
                    }
                }
            }

            function recordSongPlay(title, isPreview = false) {
                if (isPreview) {
                    trackJukeboxEvent('Preview Track', title);
                } else {
                    let currentCount = parseInt(sessionStorage.getItem('lj_session_songs') || 0) + 1;
                    sessionStorage.setItem('lj_session_songs', currentCount);
                    trackJukeboxEvent('Play Track', title);
                    trackJukeboxEvent('Session Total Plays', currentCount.toString(), currentCount);
                }
            }

            function getVotedSongs() {
                let votes = JSON.parse(localStorage.getItem('lj_user_votes_' + stationId) || '{}');
                let now = Date.now();
                let validIds = [];
                for (let id in votes) {
                    if (now - votes[id] < 3600000) validIds.push(parseInt(id));
                    else delete votes[id];
                }
                localStorage.setItem('lj_user_votes_' + stationId, JSON.stringify(votes));
                return validIds;
            }

            function addVotedSong(id) {
                let votes = JSON.parse(localStorage.getItem('lj_user_votes_' + stationId) || '{}');
                votes[id] = Date.now();
                localStorage.setItem('lj_user_votes_' + stationId, JSON.stringify(votes));
            }

            let cachedUrls = new Set();
            async function refreshCacheSet() {
                if (!('caches' in window)) return;
                const cache = await caches.open(LJ_CACHE_NAME);
                const keys = await cache.keys();
                cachedUrls.clear();
                keys.forEach(req => cachedUrls.add(req.url));
            }
            refreshCacheSet(); 

            const isMobile = /iPhone|iPad|iPod|Android/i.test(navigator.userAgent) || (navigator.maxTouchPoints && navigator.maxTouchPoints > 2 && /MacIntel/.test(navigator.platform));

            if (infoToggleBtn && infoPanel) {
                infoToggleBtn.onclick = () => { 
                    infoPanel.style.display = infoPanel.style.display === 'none' ? 'block' : 'none'; 
                    if (schedulePanel) schedulePanel.style.display = 'none';
                };
            }

            if (scheduleToggleBtn && schedulePanel) {
                scheduleToggleBtn.onclick = () => { 
                    schedulePanel.style.display = schedulePanel.style.display === 'none' ? 'block' : 'none'; 
                    if (infoPanel) infoPanel.style.display = 'none';
                };
            }

            const catalogVisible = localStorage.getItem('lj_catalog_visible') !== 'false';
            catalogContainer.style.display = catalogVisible ? 'block' : 'none';
            catalogToggleBtn.style.opacity = catalogVisible ? '1' : '0.5';
            catalogToggleBtn.onclick = () => {
                const isHidden = catalogContainer.style.display === 'none';
                catalogContainer.style.display = isHidden ? 'block' : 'none';
                localStorage.setItem('lj_catalog_visible', isHidden);
                catalogToggleBtn.style.opacity = isHidden ? '1' : '0.5';
            };

            if (stopPreviewBtn) {
                stopPreviewBtn.onclick = () => { stopPreview(); };
            }

            function showNotification(message, type) {
                var alertType = type ? type : 'danger';
                var icon = alertType === 'danger' ? 'fa-circle-exclamation' : (alertType === 'warning' ? 'fa-triangle-exclamation' : 'fa-circle-check');
                var alertHtml = '<div class="alert alert-' + alertType + ' alert-dismissible fade show" role="alert"><i class="fa-solid ' + icon + '"></i> ' + escapeHTML(message) + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
                alertContainer.insertAdjacentHTML('beforeend', alertHtml);
                var newAlert = alertContainer.lastElementChild;
                setTimeout(function() { 
                    if (newAlert) {
                        newAlert.style.opacity = '0';
                        newAlert.style.transition = 'opacity 0.3s ease';
                        setTimeout(function() { newAlert.remove(); }, 300);
                    }
                }, 4000);
            }

            function renderQueueUI(queueArray) {
                let votedIds = getVotedSongs();
                const ql = document.getElementById('lj-queue-list'); 
                ql.innerHTML = '';
                queueArray.forEach(s => {
                    let sTitle = escapeHTML(s.title);
                    let sArtist = escapeHTML(s.artist);
                    let sLink = escapeHTML(s.permalink);
                    
                    let eBadge = s.is_explicit ? `<span class="lj-explicit-badge" title="Explicit Content">E</span>` : '';
                    let cIcon = (s.url && cachedUrls.has(s.url)) ? `<i class="fa-solid fa-circle-check" style="color:#28a745; font-size:12px; margin-left:5px;" title="Buffered for Offline"></i>` : '';
                    
                    let lyricsBtn = `<a href="${sLink}" target="_blank" class="lj-btn" title="View Track Details" style="background:var(--lj-sec); padding:10px 14px;"><i class="fa-solid fa-file-lines"></i></a>`;
                    
                    let safeVoteTitle = sTitle.replace(/'/g, "\\'");
                    let safeArtistQuote = sArtist.replace(/'/g, "\\'");
                    let safePreviewUrl = escapeHTML(s.preview_url);
                    
                    let genresArray = s.genre ? s.genre.split(', ') : [];
                    let gBadge = genresArray.length > 0 
                        ? `<div style="margin-top: 6px;">` + genresArray.map(g => `<span class="lj-genre-badge" style="margin-left: 0; margin-right: 6px; cursor: pointer; transition: opacity 0.2s;" onmouseover="this.style.opacity=0.8" onmouseout="this.style.opacity=1" onclick="viewGenre('${escapeHTML(g).replace(/'/g, "\\'")}')">${escapeHTML(g)}</span>`).join('') + `</div>`
                        : '';
                    
                    let voteBtnHtml = votedIds.includes(s.id) 
                        ? `<button class="lj-btn lj-btn-vote lj-voted" disabled><i class="fa-solid fa-check"></i> ${s.votes || 0}</button>`
                        : `<button class="lj-btn lj-btn-vote" onclick="voteSong(${s.id}, '${safeVoteTitle}')"><i class="fa-solid fa-arrow-up"></i> ${s.votes || 0}</button>`;

                    ql.innerHTML += `<li class="lj-track-item"><div class="lj-track-info"><h4 style="margin:0 0 5px 0; display:flex; align-items:center;"><a href="${sLink}" style="color:inherit; text-decoration:none;" target="_blank">${sTitle}</a> ${eBadge} ${cIcon}</h4><div style="margin-bottom: 2px;"><span class="lj-clickable-artist" onclick="viewArtist(this.innerText)">${sArtist}</span></div>${gBadge}</div><div style="display:flex; gap:8px; align-items: center;">${lyricsBtn}<button class="lj-btn" onclick="previewSong('${safePreviewUrl}', '${safeVoteTitle}', '${safeArtistQuote}')"><i class="fa-solid fa-play"></i></button>${voteBtnHtml}</div></li>`;
                });
            }

            async function bufferNextTracks(tracks) {
                if (!('caches' in window)) return;
                const cache = await caches.open(LJ_CACHE_NAME);
                let updated = false;
                
                for (const song of tracks.slice(0, 5)) {
                    if(song && song.url) {
                        const response = await cache.match(song.url);
                        if (!response) { try { await cache.add(song.url); updated = true; } catch(e) { } }
                    }
                    if(song && song.preview_url) {
                        const responsePrev = await cache.match(song.preview_url);
                        if (!responsePrev) { try { await cache.add(song.preview_url); updated = true; } catch(e) { } }
                    }
                }

                if (catData && catData.length > 0) {
                    let unCached = catData.filter(s => s.url && !cachedUrls.has(s.url));
                    if (unCached.length > 0) {
                        unCached = unCached.sort(() => 0.5 - Math.random()).slice(0, 3);
                        for (const song of unCached) {
                            try { await cache.add(song.url); updated = true; } catch(e) { }
                        }
                    }
                }

                if (updated) {
                    await refreshCacheSet();
                    renderCat(); 
                    if (typeof offlineQueue !== 'undefined') renderQueueUI(offlineQueue); 
                }
            }

            let liveBlobUrl = null;
            let prevBlobUrl = null;

            async function getAndSetCachedAudio(url, audioElement, isPreview = false) {
                if (!('caches' in window)) return false;
                const cache = await caches.open(LJ_CACHE_NAME);
                const response = await cache.match(url);
                if (response) { 
                    const blob = await response.blob(); 
                    if (isPreview) {
                        if (prevBlobUrl) URL.revokeObjectURL(prevBlobUrl);
                        prevBlobUrl = URL.createObjectURL(blob);
                        audioElement.src = prevBlobUrl;
                    } else {
                        if (liveBlobUrl) URL.revokeObjectURL(liveBlobUrl);
                        liveBlobUrl = URL.createObjectURL(blob);
                        audioElement.src = liveBlobUrl;
                    }
                    return true;
                }
                return false;
            }

            const savedTheme = localStorage.getItem('lj_theme') || 'light';
            root.dataset.theme = savedTheme; themeBtn.innerHTML = savedTheme === 'light' ? '<i class="fa-solid fa-moon"></i>' : '<i class="fa-solid fa-sun"></i>';
            
            themeBtn.onclick = () => { 
                let t = root.dataset.theme === 'light' ? 'dark' : 'light'; root.dataset.theme = t; localStorage.setItem('lj_theme', t);
                themeBtn.innerHTML = t === 'light' ? '<i class="fa-solid fa-moon"></i>' : '<i class="fa-solid fa-sun"></i>';
            };

            syncBtn.onclick = () => { 
                isSync = true; syncBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Connecting...'; poll(); 
            };

            discBtn.onclick = () => { 
                isSync = false; isOffline = false; live.pause(); live.removeAttribute('src'); live.load(); 
                if(!isPreviewing) { prev.pause(); prev.removeAttribute('src'); prev.load(); }
                cId = null; window.currentPhaseId = null; discBtn.style.display = 'none'; syncBtn.style.display = 'block'; 
                syncBtn.innerHTML = '<i class="fa-solid fa-broadcast-tower"></i> Connect'; poll(); 
            };

            document.addEventListener("visibilitychange", () => {
                if (document.visibilityState === "visible" && isSync && live.paused) {
                    poll(); 
                }
            });

            if ('mediaSession' in navigator) {
                navigator.mediaSession.setActionHandler('play', () => {
                    if (isSync) { poll(); live.play().catch(e=>{}); }
                });
                navigator.mediaSession.setActionHandler('pause', () => {
                    if (isSync) {
                        live.pause();
                        syncBtn.style.display = 'block'; discBtn.style.display = 'none';
                        syncBtn.innerHTML = '<i class="fa-solid fa-play"></i> Resume Sync';
                    }
                });
            }
            
            live.addEventListener('play', () => {
                if (isSync && !isPreviewing) { syncBtn.style.display = 'none'; discBtn.style.display = 'block'; }
            });

            live.onended = async () => {
                if (isSync && !isOffline) poll(); 
                
                if (isOffline && isSync) {
                    let nextSong = null;
                    
                    if (offlineQueue.length > 0) {
                        nextSong = offlineQueue.shift();
                        renderQueueUI(offlineQueue);
                    } 
                    else {
                        const playableSongs = catData.filter(s => s.url && cachedUrls.has(s.url) && !s.is_locked_by_schedule && s.id !== cId);
                        if (playableSongs.length > 0) {
                            nextSong = playableSongs[Math.floor(Math.random() * playableSongs.length)];
                        }
                    }

                    if (nextSong) {
                        const success = await getAndSetCachedAudio(nextSong.url, live, false);
                        if (success) {
                            cId = nextSong.id;
                            root.dataset.currentSongId = nextSong.id;
                            
                            let eBadge = nextSong.is_explicit ? `<span class="lj-explicit-badge">E</span>` : '';
                            let sLink = escapeHTML(nextSong.permalink);
                            let lyricsLink = `<a href="${sLink}" target="_blank" style="margin-left:8px; font-size:14px; color:var(--lj-accent);" title="View Track Details"><i class="fa-solid fa-file-lines"></i></a>`;
                            
                            document.getElementById('lj-np-title').innerHTML = escapeHTML(nextSong.title) + ' ' + eBadge + lyricsLink; 
                            document.getElementById('lj-np-artist').innerHTML = `<span class="lj-clickable-artist" onclick="viewArtist(this.innerText)">${escapeHTML(nextSong.artist)}</span>`;
                            
                            let tipContainer = document.getElementById('lj-np-tip-container');
                            let tipBtn = document.getElementById('lj-np-tip-btn');
                            if (nextSong.tip_url && !isPreviewing) {
                                tipBtn.href = escapeHTML(nextSong.tip_url);
                                tipContainer.style.display = 'block';
                                tipBtn.style.transform = 'scale(1.03)';
                                setTimeout(() => { tipBtn.style.transform = 'scale(1)'; }, 300);
                            } else {
                                tipContainer.style.display = 'none';
                            }

                            let bannerEl = document.getElementById('lj-np-banner');
                            let bannerTextEl = document.getElementById('lj-np-banner-text');
                            if (nextSong.banner && !isPreviewing) {
                                bannerEl.style.display = 'block';
                                bannerTextEl.innerHTML = nextSong.banner; 
                            } else {
                                bannerEl.style.display = 'none';
                                bannerTextEl.innerHTML = '';
                            }
                            
                            if ('mediaSession' in navigator) {
                                navigator.mediaSession.metadata = new MediaMetadata({ title: nextSong.title, artist: nextSong.artist, album: 'Community Radio Jukebox' });
                            }
                            
                            recordSongPlay(nextSong.title, false);

                            live.onloadedmetadata = () => {
                                let dur = Math.floor(live.duration);
                                if (isNaN(dur)) dur = 180;
                                live.currentTime = 0; 
                                clearInterval(timer); 
                                let localStart = Math.floor(Date.now()/1000);
                                timer = setInterval(() => {
                                    let rem = dur - (Math.floor(Date.now()/1000) - localStart); if(rem < 0) rem = 0;
                                    let m = Math.floor(rem/60).toString().padStart(2,'0'), s = (rem%60).toString().padStart(2,'0');
                                    if (!isPreviewing) document.getElementById('lj-np-time').innerHTML = `<i class="fa-solid fa-hourglass-half" style="color:#dc3545;"></i> ${m}:${s}`;
                                    if(rem === 0) clearInterval(timer);
                                }, 1000);
                            };
                            if (!isPreviewing) {
                                live.play().catch(e => {
                                    syncBtn.style.display = 'block'; discBtn.style.display = 'none';
                                    syncBtn.innerHTML = '<i class="fa-solid fa-play"></i> Resume Sync';
                                });
                            }
                        } else {
                            showNotification('Next track unavailable offline.', 'warning');
                            live.onended(); 
                        }
                    } else {
                        if(catData.length > 0) {
                            let emergencySongs = catData.filter(s => s.url && cachedUrls.has(s.url) && !s.is_locked_by_schedule);
                            if(emergencySongs.length > 0) {
                                 nextSong = emergencySongs[Math.floor(Math.random() * emergencySongs.length)];
                                 const s = await getAndSetCachedAudio(nextSong.url, live, false);
                                 if (s) {
                                     cId = nextSong.id;
                                     root.dataset.currentSongId = nextSong.id;
                                     let eBadge = nextSong.is_explicit ? `<span class="lj-explicit-badge">E</span>` : '';
                                     let sLink = escapeHTML(nextSong.permalink);
                                     let lyricsLink = `<a href="${sLink}" target="_blank" style="margin-left:8px; font-size:14px; color:var(--lj-accent);" title="View Track Details"><i class="fa-solid fa-file-lines"></i></a>`;
                                     
                                     document.getElementById('lj-np-title').innerHTML = escapeHTML(nextSong.title) + ' ' + eBadge + lyricsLink; 
                                     document.getElementById('lj-np-artist').innerHTML = `<span class="lj-clickable-artist" onclick="viewArtist(this.innerText)">${escapeHTML(nextSong.artist)}</span>`;
                                     
                                     let tipContainer = document.getElementById('lj-np-tip-container');
                                     let tipBtn = document.getElementById('lj-np-tip-btn');
                                     if (nextSong.tip_url && !isPreviewing) {
                                         tipBtn.href = escapeHTML(nextSong.tip_url);
                                         tipContainer.style.display = 'block';
                                         tipBtn.style.transform = 'scale(1.03)';
                                         setTimeout(() => { tipBtn.style.transform = 'scale(1)'; }, 300);
                                     } else {
                                         tipContainer.style.display = 'none';
                                     }
                                     
                                     let bannerEl = document.getElementById('lj-np-banner');
                                     let bannerTextEl = document.getElementById('lj-np-banner-text');
                                     if (nextSong.banner && !isPreviewing) {
                                         bannerEl.style.display = 'block';
                                         bannerTextEl.innerHTML = nextSong.banner; 
                                     } else {
                                         bannerEl.style.display = 'none';
                                         bannerTextEl.innerHTML = '';
                                     }
                                     
                                     live.onloadedmetadata = () => { live.currentTime = 0; if(!isPreviewing) live.play().catch(e=>{}); };
                                 }
                            }
                        } else {
                            showNotification('No cached songs available.', 'warning');
                        }
                    }
                }
            };

            function startClock(dur, start, serv) {
                clearInterval(timer); let localStart = Math.floor(Date.now()/1000) - (serv - start);
                timer = setInterval(() => {
                    let rem = dur - (Math.floor(Date.now()/1000) - localStart); if(rem < 0) rem = 0;
                    let m = Math.floor(rem/60).toString().padStart(2,'0'), s = (rem%60).toString().padStart(2,'0');
                    if (!isPreviewing) document.getElementById('lj-np-time').innerHTML = `<i class="fa-solid fa-hourglass-half"></i> ${m}:${s}`;
                    if(rem === 0 && !isOffline) { clearInterval(timer); poll(); }
                }, 1000);
            }

            function poll() {
                fetch(ajaxUrl + '?action=lj_get_state&listener_id=' + lId + '&is_listening=' + isSync + '&security=' + securityNonce + '&station=' + stationId)
                .then(r => r.json()).then(d => {
                    if(!d.success) return;
                    
                    if (d.data.upcoming_events) {
                        const sl = document.getElementById('lj-schedule-list');
                        if (sl) {
                            if (d.data.upcoming_events.length === 0) {
                                sl.innerHTML = '<li style="color:var(--lj-sec); font-style:italic;">No upcoming events scheduled. Enjoy Open Play!</li>';
                            } else {
                                sl.innerHTML = '';
                                d.data.upcoming_events.forEach(ev => {
                                    let startTs = parseInt(ev.timestamp);
                                    let sParts = ev.start_time.split(':');
                                    let eParts = ev.end_time.split(':');
                                    let sMins = (parseInt(sParts[0]) * 60) + parseInt(sParts[1]);
                                    let eMins = (parseInt(eParts[0]) * 60) + parseInt(eParts[1]);
                                    
                                    if (eMins < sMins) { eMins += 1440; } 
                                    let endTs = startTs + ((eMins - sMins) * 60);

                                    let startD = new Date(startTs * 1000);
                                    let endD = new Date(endTs * 1000);
                                    let formatTime = (d) => d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
                                    let timeFmt = formatTime(startD) + ' - ' + formatTime(endD);
                                    
                                    let today = new Date();
                                    let isToday = startD.getDate() === today.getDate() && startD.getMonth() === today.getMonth() && startD.getFullYear() === today.getFullYear();
                                    let dayLabel = isToday ? 'Today' : startD.toLocaleDateString([], { weekday: 'long' });

                                    sl.innerHTML += `<li style="display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid rgba(0,0,0,0.05);">
                                        <div>
                                            <strong style="display:block; color:var(--lj-accent); font-size:14px;">${escapeHTML(ev.title)}</strong>
                                            <span style="color:var(--lj-sec); font-size:12px;">${timeFmt}</span>
                                        </div>
                                        <div style="background:var(--lj-panel); padding:4px 10px; border-radius:8px; font-weight:700; font-size:11px; border:1px solid var(--lj-border);">
                                            ${dayLabel}
                                        </div>
                                    </li>`;
                                });
                            }
                        }
                    }
                    
                    if (d.data.station_label) {
                        let badge = document.getElementById('lj-station-badge-text');
                        if(badge) badge.innerText = escapeHTML(d.data.station_label);
                    }

                    if (d.data.now_playing === null && d.data.queue.length === 0) {
                        document.getElementById('lj-np-title').innerText = "Station Empty";
                        document.getElementById('lj-np-artist').innerHTML = "No tracks found for this criteria.";
                        document.getElementById('lj-np-tip-container').style.display = 'none';
                        document.getElementById('lj-np-banner').style.display = 'none';
                        return;
                    }

                    if (isOffline && isSync) {
                        showNotification("Connection restored. Re-syncing.", "success");
                        isOffline = false; cId = null; window.currentPhaseId = null;
                        document.getElementById('lj-np-status-label').innerText = 'On Air';
                        document.getElementById('lj-np-status-label').style.color = '';
                    }
                    countDisp.innerHTML = `<i class="fa-solid fa-users"></i> ${d.data.listener_count}`;
                    
                    if (d.data.catalog_version && d.data.catalog_version > clientCatalogVersion) {
                        if (clientCatalogVersion !== 0) { loadCat(); if ('caches' in window) caches.delete(LJ_CACHE_NAME); }
                        clientCatalogVersion = d.data.catalog_version;
                    }

                    if (d.data.queue && d.data.now_playing) {
                        offlineQueue = d.data.queue.map(s => ({...s})); 
                        bufferNextTracks([d.data.now_playing, ...d.data.queue]);
                    }

                    const np = d.data.now_playing; 
                    window.currentNpData = np ? np : null;
                    if(!np) return;
                    
                    let uiId = root.dataset.currentSongId;
                    if(uiId !== String(np.id) && !isPreviewing) {
                        root.dataset.currentSongId = np.id;
                        let eBadge = np.is_explicit ? `<span class="lj-explicit-badge">E</span>` : '';
                        let sLink = escapeHTML(np.permalink);
                        let lyricsLink = `<a href="${sLink}" target="_blank" style="margin-left:8px; font-size:14px; color:var(--lj-accent);" title="View Track Details"><i class="fa-solid fa-file-lines"></i></a>`;
                        document.getElementById('lj-np-title').innerHTML = escapeHTML(np.title) + ' ' + eBadge + lyricsLink; 
                        document.getElementById('lj-np-artist').innerHTML = `<span class="lj-clickable-artist" onclick="viewArtist(this.innerText)">${escapeHTML(np.artist)}</span>`;
                        
                        let tipContainer = document.getElementById('lj-np-tip-container');
                        let tipBtn = document.getElementById('lj-np-tip-btn');
                        if (np.tip_url && !isPreviewing) {
                            tipBtn.href = escapeHTML(np.tip_url);
                            tipContainer.style.display = 'block';
                            tipBtn.style.transform = 'scale(1.03)';
                            setTimeout(() => { tipBtn.style.transform = 'scale(1)'; }, 300);
                        } else {
                            tipContainer.style.display = 'none';
                        }

                        let bannerEl = document.getElementById('lj-np-banner');
                        let bannerTextEl = document.getElementById('lj-np-banner-text');
                        if (np.banner) {
                            bannerEl.style.display = 'block';
                            bannerTextEl.innerHTML = np.banner; 
                        } else {
                            bannerEl.style.display = 'none';
                            bannerTextEl.innerHTML = '';
                        }

                        if ('mediaSession' in navigator) {
                            navigator.mediaSession.metadata = new MediaMetadata({ title: np.title, artist: np.artist, album: 'Community Radio Jukebox' });
                        }
                        
                        recordSongPlay(np.title, false); 
                        
                        startClock(np.duration, np.start_timestamp, np.server_now);
                        loadCat(); 
                    }

                    if(isSync && prev.paused) {
                        let offset = np.server_now - np.start_timestamp;
                        let iDur = parseFloat(np.intro_duration) || 0;
                        let sDur = parseFloat(np.song_duration) || 0;
                        let oDur = parseFloat(np.outro_duration) || 0;

                        let activeUrl = np.url;
                        let activeOffset = offset;
                        let targetPhase = 'song';

                        if (iDur > 0 && offset < iDur) {
                            activeUrl = np.intro_url;
                            activeOffset = offset;
                            targetPhase = 'intro';
                        } else if (offset < iDur + sDur) {
                            activeUrl = np.url;
                            activeOffset = offset - iDur;
                            targetPhase = 'song';
                        } else if (oDur > 0 && offset < iDur + sDur + oDur) {
                            activeUrl = np.outro_url;
                            activeOffset = offset - iDur - sDur;
                            targetPhase = 'outro';
                        }

                        let phaseId = np.id + '_' + targetPhase;

                        if(window.currentPhaseId !== phaseId) {
                            window.currentPhaseId = phaseId;
                            cId = np.id; 
                            
                            live.src = activeUrl;
                            live.onloadedmetadata = () => { 
                                if (!isSync) return; 
                                live.currentTime = activeOffset > 0 ? activeOffset : 0; 
                                if (!isPreviewing) {
                                    live.play().then(() => { 
                                        if(syncBtn) syncBtn.style.display = 'none'; 
                                        if(discBtn) discBtn.style.display = 'block'; 
                                    }).catch(e => {
                                        if(syncBtn) {
                                            syncBtn.style.display = 'block'; 
                                            syncBtn.innerHTML = '<i class="fa-solid fa-play"></i> Resume Sync';
                                        }
                                        if(discBtn) discBtn.style.display = 'none';
                                    });
                                }
                            };
                            live.load();
                        } else if(live.paused && !isPreviewing) { 
                            live.currentTime = activeOffset; 
                            live.play().then(() => { 
                                if(syncBtn) syncBtn.style.display = 'none'; 
                                if(discBtn) discBtn.style.display = 'block'; 
                            }).catch(e => { }); 
                        }
                        else if(Math.abs(live.currentTime - activeOffset) > 3) {
                            live.currentTime = activeOffset;
                        }
                    }

                    if (!isPreviewing && isSync) {
                        if (syncBtn) syncBtn.style.display = 'none';
                        if (discBtn) discBtn.style.display = 'block';
                    }
                    
                    renderQueueUI(d.data.queue);
                })
                .catch(async (err) => {
                    if (isSync && !isOffline) {
                        isOffline = true; 
                        
                        if (!cId) {
                            showNotification("Offline Mode active. Starting Local Radio.", "warning");
                        } else {
                            showNotification("Connection lost. Switching to Local Buffer.", "warning");
                        }

                        document.getElementById('lj-np-status-label').innerText = 'Offline Mode';
                        document.getElementById('lj-np-status-label').style.color = '#dc3545';
                        
                        const currentPlayTime = live.currentTime;
                        const currentSong = cId ? catData.find(s => s.id === cId) : null;
                        
                        if (currentSong && currentSong.url) {
                            const wasPaused = live.paused;
                            const success = await getAndSetCachedAudio(currentSong.url, live, false);
                            if (success) {
                                live.onloadedmetadata = () => { 
                                    live.currentTime = currentPlayTime || 0; 
                                    if (!wasPaused && !isPreviewing) {
                                        live.play().catch(e => {
                                            if(syncBtn) {
                                                syncBtn.style.display = 'block';
                                                syncBtn.innerHTML = '<i class="fa-solid fa-play"></i> Resume Sync';
                                            }
                                            if(discBtn) discBtn.style.display = 'none';
                                        });
                                    } 
                                };
                                live.load();
                            } else {
                                live.onended();
                            }
                        } else {
                            live.onended();
                        }
                    }
                });
            }

            let currentArtistFilter = null;
            let currentGenreFilter = null;

            window.viewArtist = (artistName) => {
                currentArtistFilter = artistName; currentGenreFilter = null; 
                document.getElementById('lj-filter-text').innerText = 'Showing tracks by: ' + artistName;
                document.getElementById('lj-artist-filter-header').style.display = 'flex'; renderCat();
                document.getElementById('lj-artist-filter-header').scrollIntoView({behavior: 'smooth', block: 'start'});
            };
            
            window.viewGenre = (genreName) => {
                currentGenreFilter = genreName; currentArtistFilter = null; 
                document.getElementById('lj-filter-text').innerText = 'Showing genre: ' + genreName;
                document.getElementById('lj-artist-filter-header').style.display = 'flex'; renderCat();
                document.getElementById('lj-artist-filter-header').scrollIntoView({behavior: 'smooth', block: 'start'});
            };

            window.clearArtistFilter = () => { 
                currentArtistFilter = null; currentGenreFilter = null; 
                document.getElementById('lj-artist-filter-header').style.display = 'none'; 
                renderCat(); 
            };

            function loadCat() { 
                fetch(ajaxUrl + '?action=lj_get_catalog&security=' + securityNonce + '&station=' + stationId).then(r => r.json()).then(async d => { 
                    if(d.success) { 
                        catData = d.data.catalog; 
                        localStorage.setItem('lj_offline_catalog_' + stationId, JSON.stringify(catData));
                        await refreshCacheSet();
                        renderCat(); 
                    } 
                }).catch(async e => {
                    const savedCat = localStorage.getItem('lj_offline_catalog_' + stationId);
                    if (savedCat) {
                        catData = JSON.parse(savedCat);
                        await refreshCacheSet();
                        renderCat();
                    }
                }); 
            }

            function renderCat() {
                const l = document.getElementById('lj-catalog-list'), s = document.getElementById('lj-catalog-sort').value;
                const showAvailable = availableOnlyCheckbox.checked;

                let sorted = [...catData];
                if (currentArtistFilter) sorted = sorted.filter(song => song.artist === currentArtistFilter);
                if (currentGenreFilter) sorted = sorted.filter(song => song.genre && song.genre.split(', ').includes(currentGenreFilter));
                
                if (showAvailable) {
                    sorted = sorted.filter(song => song.cooldown <= 0 && !song.is_playing && !song.is_locked_by_schedule);
                }
                
                if(s === 'title') sorted.sort((a,b) => a.title.localeCompare(b.title)); else if(s === 'artist') sorted.sort((a,b) => a.artist.localeCompare(b.artist)); else if(s === 'newest') sorted.sort((a,b) => b.id - a.id);
                
                if(sorted.length === 0) { 
                    let emptyMsg = '<li style="padding:15px; text-align:center; grid-column: 1 / -1;">No tracks found.</li>';
                    
                    if (catData.length > 0) {
                        let targetData = [...catData];
                        if (currentArtistFilter) targetData = targetData.filter(song => song.artist === currentArtistFilter);
                        if (currentGenreFilter) targetData = targetData.filter(song => song.genre && song.genre.split(', ').includes(currentGenreFilter));
                        
                        if (targetData.length > 0) {
                            let nextUnlockTs = Infinity;
                            let nextUnlockMsg = "";
                            
                            targetData.forEach(s => {
                                if (s.cooldown > 0 && !s.is_locked_by_schedule) {
                                    let cdTs = Date.now() + (s.cooldown * 1000);
                                    if (cdTs < nextUnlockTs) {
                                        nextUnlockTs = cdTs;
                                        nextUnlockMsg = "Next track available at " + new Date(cdTs).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
                                    }
                                } else if (s.is_locked_by_schedule && s.unlock_timestamp) {
                                    let evTs = s.unlock_timestamp * 1000;
                                    if (evTs < nextUnlockTs) {
                                        nextUnlockTs = evTs;
                                        nextUnlockMsg = escapeHTML(s.unlock_msg); 
                                    }
                                }
                            });

                            if (window.currentNpData && typeof offlineQueue !== 'undefined' && offlineQueue.length === 0) {
                                let autoDjCanPlay = catData.some(s => s.cooldown <= 0 && !s.is_locked_by_schedule && s.id != window.currentNpData.id);
                                if (!autoDjCanPlay) {
                                    let serverEndTime = window.currentNpData.start_timestamp + window.currentNpData.duration;
                                    let localOffset = Date.now() - (window.currentNpData.server_now * 1000);
                                    let localEndsAt = (serverEndTime * 1000) + localOffset;
                                    
                                    if (localEndsAt < nextUnlockTs && localEndsAt > Date.now()) {
                                        nextUnlockTs = localEndsAt;
                                        nextUnlockMsg = "Available when current song ends";
                                    }
                                }
                            }
                            
                            if (nextUnlockTs !== Infinity) {
                                emptyMsg = `<li style="padding:30px 15px; text-align:center; color:var(--lj-sec); background:var(--lj-panel); border:1px dashed var(--lj-border); border-radius:12px; grid-column: 1 / -1;">
                                    <i class="fa-regular fa-clock" style="font-size:28px; margin-bottom:12px; color:var(--lj-accent);"></i><br>
                                    <strong style="font-size:15px; color:var(--lj-text);">No tracks currently available</strong><br>
                                    <span style="font-size:13px; font-weight:600; display:inline-block; margin-top:8px; background:rgba(0,115,170,0.1); color:var(--lj-accent); padding:4px 12px; border-radius:12px;">${nextUnlockMsg}</span>
                                </li>`;
                            } else {
                                emptyMsg = '<li style="padding:15px; text-align:center; grid-column: 1 / -1;">No tracks currently available to request.</li>';
                            }
                        } else if (currentArtistFilter) {
                            emptyMsg = '<li style="padding:15px; text-align:center; grid-column: 1 / -1;">No tracks found for this artist.</li>';
                        } else if (currentGenreFilter) {
                            emptyMsg = '<li style="padding:15px; text-align:center; grid-column: 1 / -1;">No tracks found for this genre.</li>';
                        }
                    }
                    
                    l.innerHTML = emptyMsg; 
                    return; 
                }
                
                l.innerHTML = '';
                let votedIds = getVotedSongs();

                sorted.forEach(s => {
                    let sTitle = escapeHTML(s.title);
                    let sArtist = escapeHTML(s.artist);
                    let sLink = escapeHTML(s.permalink);

                    let badge = ''; let isLocked = s.cooldown > 0 || s.is_playing || s.is_locked_by_schedule;
                    let eBadge = s.is_explicit ? `<span class="lj-explicit-badge" title="Explicit Content">E</span>` : '';
                    
                    if (s.is_locked_by_schedule) {
                        badge = `<div class="lj-cooldown-badge" style="background:#8e44ad; color:#fff; border:1px solid #732d91;"><i class="fa-solid fa-lock"></i> ${escapeHTML(s.unlock_msg)}</div>`;
                    } else if (s.is_playing) {
                        badge = `<div class="lj-cooldown-badge" style="background:var(--lj-accent); color:#fff;">ON AIR</div>`;
                    } else if (s.cooldown > 0) {
                        badge = `<div class="lj-cooldown-badge"><i class="fa-regular fa-clock"></i> Avail ${new Date(Date.now() + s.cooldown * 1000).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })}</div>`;
                    }
                    
                    let cIcon = (s.url && cachedUrls.has(s.url)) ? `<i class="fa-solid fa-circle-check" style="color:#28a745; font-size:12px; margin-left:5px;" title="Buffered for Offline"></i>` : '';
                    
                    let lyricsBtn = `<a href="${sLink}" target="_blank" class="lj-btn" title="View Track Details" style="background:var(--lj-sec); padding:10px 14px;"><i class="fa-solid fa-file-lines"></i></a>`;
                    
                    let safeVoteTitle = sTitle.replace(/'/g, "\\'");
                    let safeArtistQuote = sArtist.replace(/'/g, "\\'");
                    let safePreviewUrl = escapeHTML(s.preview_url);

                    let genresArray = s.genre ? s.genre.split(', ') : [];
                    let gBadge = genresArray.length > 0 
                        ? `<div style="margin-top: 6px;">` + genresArray.map(g => `<span class="lj-genre-badge" style="margin-left: 0; margin-right: 6px; cursor: pointer; transition: opacity 0.2s;" onmouseover="this.style.opacity=0.8" onmouseout="this.style.opacity=1" onclick="viewGenre('${escapeHTML(g).replace(/'/g, "\\'")}')">${escapeHTML(g)}</span>`).join('') + `</div>`
                        : '';

                    let voteBtnHtml = votedIds.includes(s.id)
                        ? `<button class="lj-btn lj-btn-vote lj-voted" disabled><i class="fa-solid fa-check"></i></button>`
                        : `<button class="lj-btn lj-btn-vote" onclick="voteSong(${s.id}, '${safeVoteTitle}')"><i class="fa-solid fa-plus"></i></button>`;

                    l.innerHTML += `<li class="lj-track-item ${isLocked ? 'lj-locked' : ''}"><div class="lj-track-info"><h4 style="margin:0 0 5px 0; display:flex; align-items:center;"><a href="${sLink}" style="color:inherit; text-decoration:none;" target="_blank">${sTitle}</a> ${eBadge} ${cIcon}</h4><div style="margin-bottom: 2px;"><span class="lj-clickable-artist" onclick="viewArtist(this.innerText)">${sArtist}</span></div>${badge}${gBadge}</div><div style="display:flex; gap:8px; align-items: center;">${lyricsBtn}<button class="lj-btn" onclick="previewSong('${safePreviewUrl}', '${safeVoteTitle}', '${safeArtistQuote}')"><i class="fa-solid fa-play"></i></button>${voteBtnHtml}</div></li>`;
                });
            }
            
            function stopPreview() {
                isPreviewing = false; currentPreviewUrl = ''; prev.pause(); prev.removeAttribute('src');
                document.getElementById('lj-np-status-label').innerText = isOffline ? 'Offline Mode' : 'On Air';
                document.getElementById('lj-np-status-label').style.color = isOffline ? '#dc3545' : '';
                if (stopPreviewBtn) stopPreviewBtn.style.display = 'none';
                root.dataset.currentSongId = null; 
                
                if (isSync) {
                    if (syncBtn) syncBtn.style.display = 'none';
                    if (discBtn) discBtn.style.display = 'block';
                    live.play().catch(e => {
                        if (syncBtn) {
                            syncBtn.style.display = 'block'; 
                            syncBtn.innerHTML = '<i class="fa-solid fa-play"></i> Resume Sync';
                        }
                        if (discBtn) discBtn.style.display = 'none';
                    });
                } else {
                    if (syncBtn) {
                        syncBtn.style.display = 'block';
                        syncBtn.innerHTML = '<i class="fa-solid fa-broadcast-tower"></i> Connect';
                    }
                    if (discBtn) discBtn.style.display = 'none';
                }
                
                poll(); 
            }

            window.previewSong = async (u, title, artist) => { 
                if(!u) return; 
                if(isPreviewing && currentPreviewUrl === u) { stopPreview(); return; }
                if(isSync) live.pause(); 
                
                isPreviewing = true; currentPreviewUrl = u;
                document.getElementById('lj-np-status-label').innerText = 'Local Broadcast'; document.getElementById('lj-np-status-label').style.color = '#28a745';
                document.getElementById('lj-np-title').innerText = title; document.getElementById('lj-np-artist').innerHTML = artist;
                
                document.getElementById('lj-np-tip-container').style.display = 'none'; 
                
                var existingBanner = document.getElementById('lj-np-banner');
                if(existingBanner) existingBanner.style.display = 'none';
                
                if (stopPreviewBtn) stopPreviewBtn.style.display = 'block';
                if (syncBtn) syncBtn.style.display = 'none';
                if (discBtn) discBtn.style.display = 'none';

                let playUrl = u;
                if (isOffline) {
                    const success = await getAndSetCachedAudio(u, prev, true);
                    if (!success) {
                        showNotification('Preview audio not buffered for offline use.', 'warning');
                        stopPreview();
                        return;
                    }
                } else {
                    prev.src = u;
                }

                recordSongPlay(title, true); 

                prev.play().catch(e=>{}); 
                prev.ontimeupdate = () => { 
                    if(isPreviewing) { let rem = 30 - Math.floor(prev.currentTime); document.getElementById('lj-np-time').innerHTML = `<i class="fa-solid fa-stopwatch" style="color:#28a745;"></i> 0:${(rem<0?0:rem).toString().padStart(2,'0')}`; }
                    if(prev.currentTime >= 30) stopPreview();
                }; 
            };
            
            window.voteSong = (id, title) => { 
                const f = new FormData(); 
                f.append('action', 'lj_vote'); 
                f.append('song_id', id); 
                f.append('security', securityNonce); 
                f.append('station', stationId);
                
                fetch(ajaxUrl, { method: 'POST', body: f }).then(r => r.json()).then(d => { 
                    if(!d.success) showNotification(d.data, 'danger'); 
                    else { 
                        addVotedSong(id);
                        trackJukeboxEvent('Vote Track', title); 
                        showNotification('Vote added!', 'success'); 
                        poll(); loadCat(); 
                    }
                }).catch(e => showNotification('Cannot vote offline.', 'warning')); 
            };
            
            poll(); loadCat(); setInterval(loadCat, 60000); setInterval(poll, 5000);
            document.getElementById('lj-catalog-sort').onchange = renderCat;
        });
        </script>
        <?php
    }, 100);

    return ob_get_clean();
}
