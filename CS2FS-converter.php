<?php
/**
 * Plugin Name:  CS2FS Converter
 * Plugin URI:   https://github.com/follaky/Folla-CS2FS-Converter
 * Description:  Convert Code Snippets (from wp_snippets table) into Fluent Snippets–compatible JSON, save to uploads, and add a one-click “Import local” button in Fluent Snippets.
 * Author:       Olivier Fontana | Folla Ky
 * Author URI:   https://github.com/follaky
 * License:      GPL-2.0-or-later
 * License URI:  https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:  cs2fs-converter
 * Domain Path:  /languages
 * Version:      1.0
 * Requires PHP: 7.4
 * Requires at least: 6.0
 */

if (!defined('ABSPATH')) exit;

add_action('admin_menu', function() {
    add_management_page(
        __('CS2FS Converter', 'cs2fs-converter'),
        __('CS2FS Converter', 'cs2fs-converter'),
        'manage_options',
        'cs2fs-converter',
        'csfc_render_converter_page'
    );
}, 30);

add_action('admin_init', 'csfc_handle_export');
add_action('wp_ajax_fluent_snippets_import_local', 'csfc_import_local_snippets');
add_action('admin_enqueue_scripts', 'csfc_enqueue_local_import_button');
add_filter('fluent_snippets_asset_listed_slugs', 'csfc_whitelist_assets');
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'csfc_plugin_action_links');
add_action('plugins_loaded', 'csfc_load_textdomain');
add_action('admin_notices', 'csfc_dependency_notices');

function csfc_load_textdomain() {
    load_plugin_textdomain('cs2fs-converter', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

function csfc_plugin_action_links($links) {
    $settings_link = '<a href="' . esc_url(admin_url('tools.php?page=cs2fs-converter')) . '">' . esc_html__('Converter', 'cs2fs-converter') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}

/**
 * Show admin notice if required plugins are missing/inactive.
 */
function csfc_dependency_notices() {
    if (!current_user_can('manage_options')) {
        return;
    }

    if (!function_exists('is_plugin_active')) {
        include_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $code_snippets_active = defined('CODE_SNIPPETS_VERSION') || class_exists('Code_Snippets') || is_plugin_active('code-snippets/code-snippets.php');
    $fluent_snippets_active = defined('FLUENT_SNIPPETS_PLUGIN_VERSION') || class_exists('\\FluentSnippets\\App\\Helpers\\Helper') || is_plugin_active('easy-code-manager/easy-code-manager.php');

    $messages = [];
    if (!$code_snippets_active) {
        $messages[] = __('Code Snippets (or Code Snippets Pro) is not detected. Export will be empty unless the wp_snippets table exists.', 'cs2fs-converter');
    }
    if (!$fluent_snippets_active) {
        $messages[] = __('Fluent Snippets is not detected. The inline import button will not work until it is installed and active.', 'cs2fs-converter');
    }

    if (empty($messages)) {
        return;
    }

    echo '<div class="notice notice-warning"><p><strong>' . esc_html__('CS2FS Converter', 'cs2fs-converter') . ':</strong></p><ul>';
    foreach ($messages as $msg) {
        echo '<li>' . esc_html($msg) . '</li>';
    }
    echo '</ul></div>';
}

function csfc_handle_export() {
    if (!isset($_POST['generate_json'])) return;
    if (!isset($_GET['page']) || $_GET['page'] !== 'cs2fs-converter') return;
    if (!current_user_can('manage_options')) return;
    check_admin_referer('csfc_export', 'csfc_nonce');

    $export_snippets = [];
    $posted_snippets = isset($_POST['snippet']) ? wp_unslash($_POST['snippet']) : [];
    $includes = isset($_POST['include']) ? wp_unslash($_POST['include']) : [];
    $overrides = isset($_POST['override_type']) ? wp_unslash($_POST['override_type']) : [];

    foreach ($posted_snippets as $i => $s) {
        if (empty($includes[$i])) continue;

        $type = $overrides[$i] ?? 'php';
        $type = in_array($type, ['php', 'php_content', 'js', 'css'], true) ? $type : 'php';

        $code = (string) ($s['code'] ?? '');
        $encoded_code = base64_encode($code);

        // Accept both desc (form) and description (fallback) keys.
        $desc_raw = $s['desc'] ?? ($s['description'] ?? '');
        $desc = html_entity_decode(wp_kses_post($desc_raw));

        $export_snippets[] = [
            'code' => $encoded_code,
            'code_hash' => md5($code),
            'info' => [
                'name' => sanitize_text_field($s['name'] ?? ''),
                'status' => 'draft',
                'tags' => '',
                'description' => $desc,
                'type' => $type,
                'run_at' => 'wp_footer',
                'group' => '',
                'condition' => [
                    'status' => 'no',
                    'run_if' => 'assertive',
                    'items' => [
                        []
                    ]
                ],
                'load_as_file' => '',
                'created_by' => (string) get_current_user_id(),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
                'is_valid' => '1',
                'updated_by' => (string) get_current_user_id(),
                'priority' => '10'
            ]
        ];
    }

    $payload = [
        'file_type' => 'fluent_code_snippets',
        'version' => csfc_get_fluent_snippets_version(),
        'snippets' => $export_snippets,
        'snippets_count' => count($export_snippets)
    ];

    $json = wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        wp_die('Failed to generate JSON.');
    }

    csfc_save_export_locally($json);

    header('Content-disposition: attachment; filename=fluent-snippets-export-' . date('Y-m-d') . '.json');
    header('Content-type: application/json; charset=' . get_option('blog_charset'));
    echo $json;
    exit;
}

function csfc_get_fluent_snippets_version() {
    if (defined('FLUENT_SNIPPETS_VERSION')) {
        return FLUENT_SNIPPETS_VERSION;
    }

    $stored = get_option('fluent_snippets_version');
    if (!empty($stored)) {
        return $stored;
    }

    return '1.0';
}

function csfc_save_export_locally($json) {
    $uploads = wp_upload_dir();
    if (!empty($uploads['error'])) {
        return;
    }

    $dir = trailingslashit($uploads['basedir']) . 'cs2fs_export';
    wp_mkdir_p($dir);

    $filename = 'fluent-snippets-export-' . date('Y-m-d') . '.json';
    $path = trailingslashit($dir) . $filename;

    // Suppress errors; user still gets the download even if write fails.
    file_put_contents($path, $json);
}

// Expose server-side exports to FluentSnippets import (if the plugin checks this filter).
add_filter('fluent_snippets_local_import_files', 'csfc_register_local_exports');
function csfc_register_local_exports($files) {
    $uploads = wp_upload_dir();
    if (!empty($uploads['error'])) {
        return $files;
    }

    $dir = trailingslashit($uploads['basedir']) . 'cs2fs_export';
    if (!is_dir($dir)) {
        return $files;
    }

    $local_files = glob(trailingslashit($dir) . '*.json') ?: [];
    foreach ($local_files as $file) {
        $files[] = [
            'path' => $file,
            'label' => basename($file),
        ];
    }

    return $files;
}

function csfc_render_converter_page() {
    global $wpdb;
    $snippets_table = $wpdb->prefix . 'snippets';
    $snippets = $wpdb->get_results("SELECT * FROM $snippets_table");

    echo '<div class="wrap"><h1>' . esc_html__('Code Snippets → Fluent Snippets Converter', 'cs2fs-converter') . '</h1>';
    echo '<p>' . esc_html__('Select the Code Snippets entries you want to export. The generated Fluent Snippets JSON is downloaded and also saved to uploads/cs2fs_export for one-click import.', 'cs2fs-converter') . '</p>';
    if (empty($snippets)) {
        echo '<p>' . esc_html__('No Code Snippets found in wp_snippets table.', 'cs2fs-converter') . '</p></div>';
        return;
    }

    echo '<form method="post">';
    wp_nonce_field('csfc_export', 'csfc_nonce');
    echo '<table class="widefat"><thead><tr>
        <th>Name</th><th>Description</th><th>Type</th><th>Change Type</th><th>Include</th>
    </tr></thead><tbody>';
    foreach ($snippets as $i => $snippet) {
        $type = strtolower($snippet->scope ?? 'php');
        if ($type === 'site-css') $type = 'css';
        elseif (str_contains($type, 'js')) $type = 'js';
        elseif ($type === 'content') $type = 'php_content';
        elseif (!in_array($type, ['php', 'js', 'css', 'php_content'])) $type = 'php';

        echo '<tr>';
        echo '<td>' . esc_html($snippet->name) . '</td>';
        $snippet_desc = isset($snippet->desc) ? (string) $snippet->desc : ((isset($snippet->description) ? (string) $snippet->description : ''));
        echo '<td>' . esc_html(wp_trim_words($snippet_desc, 20)) . '</td>';
        echo '<td>' . esc_html($type) . '</td>';
        echo '<td><select name="override_type[' . $i . ']">
            <option value="">Auto (' . esc_attr($type) . ')</option>
            <option value="php">PHP</option>
            <option value="php_content">PHP + HTML</option>
            <option value="js">JS</option>
            <option value="css">CSS</option>
        </select></td>';
        echo '<td><input type="checkbox" name="include[' . $i . ']" value="1" checked></td>';
        echo '<input type="hidden" name="snippet[' . $i . '][name]" value="' . esc_attr($snippet->name) . '">';
        echo '<textarea name="snippet[' . $i . '][desc]" style="display:none;">' . esc_textarea($snippet_desc) . '</textarea>';
        echo '<input type="hidden" name="snippet[' . $i . '][code]" value="' . esc_attr($snippet->code) . '">';
        echo '</tr>';
    }
    echo '</tbody></table><br><input type="submit" name="generate_json" class="button button-primary" value="Generate FluentSnippets JSON"></form></div>';
}

/**
 * AJAX endpoint to import the newest local CS2FS export into Fluent Snippets.
 */
function csfc_import_local_snippets() {
    if (!current_user_can('install_plugins')) {
        wp_send_json_error(['message' => __('You do not have permission to import snippets.', 'cs2fs')], 403);
    }

    check_ajax_referer('fluent-snippets', '_nonce');

    $uploads = wp_get_upload_dir();
    $dir = trailingslashit($uploads['basedir']) . 'cs2fs_export';
    if (!is_dir($dir)) {
        wp_send_json_error(['message' => __('Local export directory not found. Generate an export first.', 'cs2fs')], 400);
    }

    $latest_file = csfc_get_latest_export_file($dir);
    if (!$latest_file) {
        wp_send_json_error(['message' => __('No local export JSON files found.', 'cs2fs')], 400);
    }

    $raw = file_get_contents($latest_file);
    if ($raw === false) {
        wp_send_json_error(['message' => __('Unable to read the latest export file.', 'cs2fs')], 500);
    }

    $payload = json_decode($raw, true);
    if (!is_array($payload) || ($payload['file_type'] ?? '') !== 'fluent_code_snippets' || empty($payload['snippets']) || !is_array($payload['snippets'])) {
        wp_send_json_error(['message' => __('Invalid export file. Expected Fluent Snippets payload.', 'cs2fs')], 400);
    }

    $Arr = '\\FluentSnippets\\App\\Helpers\\Arr';
    $Helper = '\\FluentSnippets\\App\\Helpers\\Helper';
    $SnippetsController = '\\FluentSnippets\\App\\Http\\Controllers\\SnippetsController';
    $Snippet = '\\FluentSnippets\\App\\Model\\Snippet';

    if (!class_exists($Arr) || !class_exists($Helper) || !class_exists($SnippetsController) || !class_exists($Snippet)) {
        wp_send_json_error(['message' => __('Fluent Snippets is required for this import.', 'cs2fs')], 400);
    }

    $createdSnippets = [];
    $skipped = [];
    $snippetModel = new $Snippet();
    $existingHashes = csfc_get_existing_snippet_hashes($snippetModel);

    foreach ((array) $payload['snippets'] as $snippet) {
        $encoded = $Arr::get($snippet, 'code', '');
        $decoded = base64_decode($encoded, true);
        if ($decoded === false) {
            $skipped[] = ['name' => $Arr::get($snippet, 'info.name', ''), 'reason' => 'decode_failed'];
            continue;
        }

        $hash = md5($decoded);
        $expectedHash = (string) $Arr::get($snippet, 'code_hash', '');

        if (!$expectedHash || !hash_equals($expectedHash, $hash)) {
            $skipped[] = ['name' => $Arr::get($snippet, 'info.name', ''), 'reason' => 'hash_mismatch'];
            continue;
        }

        if (in_array($hash, $existingHashes, true)) {
            $skipped[] = ['name' => $Arr::get($snippet, 'info.name', ''), 'reason' => 'duplicate'];
            continue;
        }

        $meta = $Arr::get($snippet, 'info', []);
        $meta = wp_parse_args($meta, [
            'name' => '',
            'status' => 'draft',
            'type' => 'PHP',
            'run_at' => 'wp_footer',
            'tags' => '',
            'description' => '',
            'group' => '',
            'condition' => [
                'status' => 'no',
                'run_if' => 'assertive',
                'items' => [[]]
            ],
            'load_as_file' => '',
            'priority' => 10,
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
            'updated_by' => get_current_user_id(),
            'is_valid' => 1
        ]);
        $meta['status'] = 'draft';
        if (empty($meta['name'])) {
            $meta['name'] = sprintf(__('Imported Snippet %s', 'cs2fs'), current_time('mysql'));
        }
        $meta['priority'] = is_numeric($meta['priority']) ? (int) $meta['priority'] : 10;

        // Normalize type to match Fluent Snippets expectations.
        if (($meta['type'] ?? '') === 'php') {
            $meta['type'] = 'PHP';
        }

        $validated = $SnippetsController::validateMeta($meta);
        if (is_wp_error($validated)) {
            $skipped[] = ['name' => $meta['name'] ?? '', 'reason' => 'meta_invalid', 'message' => $validated->get_error_message()];
            continue;
        }

        if (($meta['type'] ?? '') === 'PHP') {
            // Ensure wrapped with PHP open tag like core createSnippet does.
            $decoded = rtrim($decoded, '?>');
            $decoded = ltrim($decoded, "\n\r");
            if (stripos($decoded, '<?php') !== 0) {
                $decoded = '<?php' . PHP_EOL . $decoded;
            }
        } elseif (($meta['type'] ?? '') === 'php_content') {
            $decoded = apply_filters('fluent_snippets/sanitize_mixed_content', $decoded, $meta);
            if (is_wp_error($decoded)) {
                $skipped[] = ['name' => $meta['name'] ?? '', 'reason' => 'sanitize_failed', 'message' => $decoded->get_error_message()];
                continue;
            }
        }

        $validatedCode = $Helper::validateCode($meta['type'], $decoded);
        if (is_wp_error($validatedCode)) {
            if ($validatedCode->get_error_code() !== 'duplicate_error') {
                $skipped[] = ['name' => $meta['name'] ?? '', 'reason' => 'code_invalid', 'message' => $validatedCode->get_error_message()];
                continue;
            }
            // Allow duplicate function/class names; Code Snippets may still be active.
        }

        $created = $snippetModel->createSnippet($decoded, $meta);
        if (is_wp_error($created)) {
            $skipped[] = ['name' => $meta['name'] ?? '', 'reason' => 'create_failed', 'message' => $created->get_error_message()];
            continue;
        }

        $createdSnippets[] = [
            'name' => $meta['name'] ?? '',
            'status' => $meta['status'],
            'file_name' => $created,
            'hash' => $hash,
            'is_success' => 'yes',
            'reason' => 'Imported'
        ];
        $existingHashes[] = $hash;
    }

    $Helper::cacheSnippetIndex();

    wp_send_json([
        'snippets' => $createdSnippets,
        'skipped' => $skipped
    ]);
}

/**
 * Return a sorted list of existing Fluent Snippets hashes, if available.
 *
 * @param string $snippetClass
 * @return array
 */
function csfc_get_existing_snippet_hashes($snippetModel) {
    $hashes = [];

    if (!is_object($snippetModel)) {
        return $hashes;
    }

    $records = $snippetModel->get();
    if (is_array($records)) {
        foreach ($records as $record) {
            if (is_array($record) && isset($record['code'])) {
                $hashes[] = md5((string) $record['code']);
            }
        }
    }

    return array_values(array_filter(array_unique($hashes)));
}

/**
 * Find the newest JSON export file in the CS2FS export directory.
 *
 * @param string $dir
 * @return string|null
 */
function csfc_get_latest_export_file($dir) {
    $files = glob(trailingslashit($dir) . '*.json');
    if (empty($files)) {
        return null;
    }

    usort($files, function($a, $b) {
        return filemtime($b) <=> filemtime($a);
    });

    return $files[0] ?? null;
}

/**
 * Add an inline admin button to trigger local import from the Fluent Snippets screen.
 *
 * @param string $hook
 */
function csfc_enqueue_local_import_button($hook) {
    if (strpos((string) $hook, 'fluent-snippets') === false) {
        return;
    }

    $handle = 'csfc-import-local';

    wp_enqueue_script(
        $handle,
        plugins_url('assets/csfc-import-local.js', __FILE__),
        ['jquery'],
        '1.0.0',
        true
    );

    wp_localize_script($handle, 'csfcImportLocal', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('fluent-snippets'),
        'menuSelector' => '.fsnip_menu',
        'strings' => [
            'buttonText' => __('Import local (CS2FS Converter)', 'cs2fs'),
            'importingText' => __('Importing...', 'cs2fs'),
            'successWithCount' => __('Import completed. Added %d snippet(s).', 'cs2fs'),
            'successGeneric' => __('Import finished.', 'cs2fs'),
            'failure' => __('Import failed.', 'cs2fs'),
        ]
    ]);
}

/**
 * Allow CS2FS assets to remain enqueued when Fluent Snippets prunes scripts.
 *
 * @param array $slugs
 * @return array
 */
function csfc_whitelist_assets($slugs) {
    $dir = basename(dirname(__FILE__));
    $slugs[] = $dir;
    return array_unique(array_filter($slugs));
}
