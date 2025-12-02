<?php
/*
Plugin Name: Code Snippets to FluentSnippets Converter
Description: Converts Code Snippets (from wp_snippets table) into FluentSnippets-compatible JSON.
Version: 1.1
Author: ChatGPT
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

function csfc_render_converter_page() {
    global $wpdb;
    $snippets_table = $wpdb->prefix . 'snippets';
    $snippets = $wpdb->get_results("SELECT * FROM $snippets_table");

    echo '<div class="wrap"><h1>Code Snippets to FluentSnippets Converter</h1>';
    if (empty($snippets)) {
        echo '<p>No Code Snippets found in wp_snippets table.</p></div>';
        return;
    }

    echo '<form method="post"><table class="widefat"><thead><tr>
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

    if (isset($_POST['generate_json'])) {
        $export = [];
        foreach ($_POST['snippet'] as $i => $s) {
            if (!isset($_POST['include'][$i])) continue;
            $type = $_POST['override_type'][$i] ?: 'php';
            $type = in_array($type, ['php', 'php_content', 'js', 'css']) ? $type : 'php';
            $export[] = [
                'name' => sanitize_text_field($s['name']),
                'description' => sanitize_text_field($s['desc']),
                'type' => $type,
                'status' => 'draft',
                'code' => $s['code']
            ];
        }
        header('Content-disposition: attachment; filename=fluent-snippets-export-' . date('Y-m-d') . '.json');
        header('Content-type: application/json');
        echo json_encode(['snippets' => $export], JSON_PRETTY_PRINT);
        exit;
    }
}
