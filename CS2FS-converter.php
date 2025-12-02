<?php
/*
Plugin Name: CS2FS Converter
Description: Converts Code Snippets (from wp_snippets table) into FluentSnippets-compatible JSON.
Version: 1.0
Author: Olivier Fontana | Folla Ky
*/

if (!defined('ABSPATH')) exit;

add_action('admin_menu', function() {
    add_management_page(
        'Snippets Converter',
        'Snippets Converter',
        'manage_options',
        'code-to-fluent-converter',
        'csfc_render_converter_page'
    );
}, 30);

add_action('admin_init', 'csfc_handle_export');

function csfc_handle_export() {
    if (!isset($_POST['generate_json'])) return;
    if (!isset($_GET['page']) || $_GET['page'] !== 'code-to-fluent-converter') return;
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

        $export_snippets[] = [
            'code' => $encoded_code,
            'code_hash' => md5($code),
            'info' => [
                'name' => sanitize_text_field($s['name'] ?? ''),
                'status' => 'draft',
                'tags' => '',
                'description' => sanitize_textarea_field($s['desc'] ?? ''),
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

    echo '<div class="wrap"><h1>Code Snippets to FluentSnippets Converter</h1>';
    if (empty($snippets)) {
        echo '<p>No Code Snippets found in wp_snippets table.</p></div>';
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
        echo '<td>' . esc_html(wp_trim_words($snippet->desc, 20)) . '</td>';
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
        echo '<input type="hidden" name="snippet[' . $i . '][desc]" value="' . esc_attr($snippet->desc) . '">';
        echo '<input type="hidden" name="snippet[' . $i . '][code]" value="' . esc_attr($snippet->code) . '">';
        echo '</tr>';
    }
    echo '</tbody></table><br><input type="submit" name="generate_json" class="button button-primary" value="Generate FluentSnippets JSON"></form></div>';
}
