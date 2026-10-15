<?php
/**
 * Plugin Name:  CS2FS Converter
 * Plugin URI:   https://github.com/FollaKy/Folla-CS2FS-Converter
 * Description:  Convert Code Snippets (from wp_snippets table) into Fluent Snippets compatible JSON, save to uploads, and add a one-click "Import local" button in Fluent Snippets.
 * Author:       Olivier Fontana | Folla Ky
 * Author URI:   https://github.com/follaky
 * License:      GPL-2.0-or-later
 * License URI:  https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:  cs2fs-converter
 * Domain Path:  /languages
 * Version:      1.1
 * Requires PHP: 7.4
 * Requires at least: 6.2
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
add_action('init', 'csfc_load_textdomain');
add_action('admin_notices', 'csfc_dependency_notices');
add_action('admin_enqueue_scripts', 'csfc_enqueue_admin_styles');

function csfc_load_textdomain() {
    // Bundled de_DE and fi translations; translate.wordpress.org packs take precedence once they exist.
    load_plugin_textdomain( // phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound
        'cs2fs-converter', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

function csfc_plugin_action_links($links) {
    $settings_link = '<a href="' . esc_url(admin_url('tools.php?page=cs2fs-converter')) . '">' . esc_html__('Converter', 'cs2fs-converter') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}

function csfc_enqueue_admin_styles($hook) {
    if ($hook === 'toplevel_page_fluent-snippets') {
        $css = '.csfc-import-local-item{margin-left:12px;display:flex;align-items:center;}' .
            '.csfc-import-local{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;line-height:20px;height:auto;}' .
            '.csfc-import-local .dashicons{margin:0;}';
        wp_add_inline_style('dashicons', $css);
    }
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

/**
 * Code Snippets scope → Fluent Snippets type and run location.
 *
 * Fluent Snippets run locations: PHP all/backend; js wp_head/wp_footer/
 * admin_head/admin_footer; css wp_head/admin_head; php_content wp_head/
 * wp_footer/wp_body_open/before_content/after_content/shortcode.
 *
 * @return array
 */
function csfc_scope_map() {
    return [
        'global'         => ['type' => 'PHP', 'run_at' => 'all'],
        'admin'          => ['type' => 'PHP', 'run_at' => 'backend'],
        // Fluent Snippets has no front-end-only PHP location, so guard the code instead.
        'front-end'      => ['type' => 'PHP', 'run_at' => 'all', 'prefix' => "if ( is_admin() ) {\n\treturn;\n}\n"],
        'single-use'     => ['type' => 'PHP', 'run_at' => 'all', 'note' => 'single_use'],
        'content'        => ['type' => 'php_content', 'run_at' => 'shortcode', 'note' => 'shortcode'],
        'head-content'   => ['type' => 'php_content', 'run_at' => 'wp_head'],
        'body-content'   => ['type' => 'php_content', 'run_at' => 'wp_body_open'],
        'footer-content' => ['type' => 'php_content', 'run_at' => 'wp_footer'],
        'admin-css'      => ['type' => 'css', 'run_at' => 'admin_head'],
        'site-css'       => ['type' => 'css', 'run_at' => 'wp_head'],
        'site-head-js'   => ['type' => 'js', 'run_at' => 'wp_head'],
        'site-footer-js' => ['type' => 'js', 'run_at' => 'wp_footer'],
    ];
}

/**
 * Default run location when the user overrides the type.
 *
 * @return array
 */
function csfc_type_defaults() {
    return [
        'PHP'         => 'all',
        'php_content' => 'wp_footer',
        'js'          => 'wp_footer',
        'css'         => 'wp_head',
    ];
}

/**
 * Human-readable notes for the listing and the import report.
 *
 * @return array
 */
function csfc_note_texts() {
    return [
        'was_active'      => __('Active in Code Snippets: publish it in Fluent Snippets after deactivating Code Snippets.', 'cs2fs-converter'),
        'condition'       => __('Had a Code Snippets condition. Fluent Snippets conditions work differently; recreate it by hand.', 'cs2fs-converter'),
        'single_use'      => __('Single-use snippet. Fluent Snippets has no "run once"; publishing it runs it on every request.', 'cs2fs-converter'),
        'shortcode'       => __('Content snippet. Replace [code_snippet] shortcodes on your pages with the Fluent Snippets shortcode.', 'cs2fs-converter'),
        'unknown_scope'   => __('Unknown scope; imported as PHP that runs everywhere. Check it before publishing.', 'cs2fs-converter'),
        'priority_raised' => __('Priority below 1 raised to 1 (Fluent Snippets minimum).', 'cs2fs-converter'),
    ];
}

/**
 * Work out how one Code Snippets row maps to Fluent Snippets.
 *
 * @param object $row      Row from the Code Snippets table.
 * @param string $override Optional type chosen by the user.
 * @return array|null Mapping, or null for rows that are not code (condition definitions).
 */
function csfc_map_snippet($row, $override = '') {
    $scope = strtolower((string) ($row->scope ?? 'global'));
    if ($scope === 'condition') {
        return null;
    }

    $map = csfc_scope_map();
    $notes = [];

    if (isset($map[$scope])) {
        $mapping = $map[$scope];
    } else {
        $mapping = ['type' => 'PHP', 'run_at' => 'all'];
        $notes[] = 'unknown_scope';
    }

    $defaults = csfc_type_defaults();
    if ($override && isset($defaults[$override]) && $override !== $mapping['type']) {
        $mapping = ['type' => $override, 'run_at' => $defaults[$override]];
    }

    if (!empty($mapping['note'])) {
        $notes[] = $mapping['note'];
    }
    if ((int) ($row->active ?? 0) === 1) {
        $notes[] = 'was_active';
    }
    if ((int) ($row->condition_id ?? 0) > 0) {
        $notes[] = 'condition';
    }

    $priority = (int) ($row->priority ?? 10);
    if ($priority < 1) {
        $priority = 1;
        $notes[] = 'priority_raised';
    }

    return [
        'type'     => $mapping['type'],
        'run_at'   => $mapping['run_at'],
        'prefix'   => $mapping['prefix'] ?? '',
        'priority' => $priority,
        'notes'    => $notes,
    ];
}

/**
 * Read Code Snippets rows (trash excluded), optionally limited to some IDs.
 *
 * @param int[]|null $ids
 * @return object[]
 */
function csfc_get_code_snippets($ids = null) {
    global $wpdb;
    $table = $wpdb->prefix . 'snippets';

    // Reads another plugin's table once per request; caching would not help.
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
        return [];
    }

    // Code Snippets marks trashed snippets with active = -1.
    if (!is_array($ids)) {
        $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM %i WHERE active >= 0 ORDER BY id', $table));
        return $rows ?: [];
    }

    $ids = array_values(array_filter(array_map('absint', $ids)));
    if (!$ids) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($ids), '%d'));
    $rows = $wpdb->get_results(
        // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders built above.
        $wpdb->prepare("SELECT * FROM %i WHERE active >= 0 AND id IN ($placeholders) ORDER BY id", array_merge([$table], $ids))
    );
    // phpcs:enable

    return $rows ?: [];
}

/**
 * Flatten a Code Snippets description to one line (Fluent Snippets keeps meta in a docblock).
 *
 * @param string $html
 * @return string
 */
function csfc_plain_description($html) {
    $text = html_entity_decode(wp_strip_all_tags((string) $html), ENT_QUOTES, 'UTF-8');
    return trim(preg_replace('/\s+/', ' ', $text));
}

function csfc_handle_export() {
    if (!isset($_POST['generate_json'])) return;
    if (!isset($_GET['page']) || $_GET['page'] !== 'cs2fs-converter') return;
    if (!current_user_can('manage_options')) return;
    check_admin_referer('csfc_export', 'csfc_nonce');

    $includes = isset($_POST['include']) ? array_map('absint', array_keys((array) wp_unslash($_POST['include']))) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- keys only, absint.
    $overrides = isset($_POST['override_type']) ? array_map('sanitize_key', (array) wp_unslash($_POST['override_type'])) : [];

    $export_snippets = [];
    foreach (csfc_get_code_snippets($includes) as $row) {
        $override = sanitize_key($overrides[$row->id] ?? '');
        $override = $override === 'php' ? 'PHP' : $override;
        $mapping = csfc_map_snippet($row, $override);
        if (!$mapping) {
            continue;
        }

        $code = (string) $row->code;
        if ($mapping['type'] === 'PHP') {
            // Code Snippets stores PHP without the open tag; Fluent Snippets files need it.
            $code = preg_replace('/^\s*<\?php\s*/i', '', $code);
            $code = '<?php' . PHP_EOL . $mapping['prefix'] . $code;
        }

        $tags = implode(', ', array_filter(array_map('trim', explode(',', (string) ($row->tags ?? '')))));

        $export_snippets[] = [
            'code' => base64_encode($code),
            'code_hash' => md5($code),
            'info' => [
                'name' => sanitize_text_field($row->name) ?: sprintf('Code Snippet #%d', $row->id),
                'status' => 'draft',
                'tags' => sanitize_text_field($tags),
                'description' => csfc_plain_description($row->description ?? ''),
                'type' => $mapping['type'],
                'run_at' => $mapping['run_at'],
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
                'priority' => (string) $mapping['priority']
            ],
            // Ignored by Fluent Snippets' own importer; used for the CS2FS import report.
            'cs2fs' => [
                'source_id' => (int) $row->id,
                'scope' => (string) $row->scope,
                'notes' => $mapping['notes']
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

    header('Content-disposition: attachment; filename=fluent-snippets-export-' . gmdate('Y-m-d') . '.json');
    header('Content-type: application/json; charset=' . get_option('blog_charset'));
    echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON file download, not HTML.
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

    // The export holds site code: block direct access and use an unguessable name.
    if (!file_exists($dir . '/index.php')) {
        file_put_contents($dir . '/index.php', "<?php\n// Silence is golden.\n"); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
    }
    if (!file_exists($dir . '/.htaccess')) {
        file_put_contents($dir . '/.htaccess', "<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tDeny from all\n</IfModule>\n"); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
    }

    $filename = 'fluent-snippets-export-' . gmdate('Y-m-d') . '-' . wp_generate_password(16, false) . '.json';
    $path = trailingslashit($dir) . $filename;

    // The user still gets the download if this write fails.
    file_put_contents($path, $json); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
}

function csfc_render_converter_page() {
    $snippets = csfc_get_code_snippets();
    $note_texts = csfc_note_texts();

    echo '<div class="wrap"><h1>' . esc_html__('Code Snippets → Fluent Snippets Converter', 'cs2fs-converter') . '</h1>';
    echo '<p>' . esc_html__('Select the Code Snippets entries you want to export. The generated Fluent Snippets JSON is downloaded and also saved to uploads/cs2fs_export for one-click import.', 'cs2fs-converter') . '</p>';
    echo '<p>' . esc_html__('Snippets are imported as drafts. Trashed snippets are not listed.', 'cs2fs-converter') . '</p>';
    if (empty($snippets)) {
        echo '<p>' . esc_html__('No Code Snippets found in wp_snippets table.', 'cs2fs-converter') . '</p></div>';
        return;
    }

    echo '<form method="post">';
    wp_nonce_field('csfc_export', 'csfc_nonce');
    echo '<table class="widefat striped"><thead><tr>
        <th>' . esc_html__('Include', 'cs2fs-converter') . '</th>
        <th>' . esc_html__('Name', 'cs2fs-converter') . '</th>
        <th>' . esc_html__('Code Snippets scope', 'cs2fs-converter') . '</th>
        <th>' . esc_html__('Fluent Snippets type / location', 'cs2fs-converter') . '</th>
        <th>' . esc_html__('Change Type', 'cs2fs-converter') . '</th>
        <th>' . esc_html__('Notes', 'cs2fs-converter') . '</th>
    </tr></thead><tbody>';
    foreach ($snippets as $snippet) {
        $id = (int) $snippet->id;
        $mapping = csfc_map_snippet($snippet);
        if (!$mapping) {
            continue; // Condition definitions, not code.
        }

        echo '<tr>';
        echo '<td><input type="checkbox" name="include[' . esc_attr($id) . ']" value="1" checked aria-label="' . esc_attr__('Include', 'cs2fs-converter') . '"></td>';
        echo '<td><strong>' . esc_html($snippet->name) . '</strong><br><span class="description">' . esc_html(wp_trim_words(csfc_plain_description($snippet->description ?? ''), 20)) . '</span></td>';
        echo '<td><code>' . esc_html($snippet->scope) . '</code></td>';
        echo '<td>' . esc_html($mapping['type'] . ' / ' . $mapping['run_at']) . '</td>';
        echo '<td><select name="override_type[' . esc_attr($id) . ']">
            <option value="">' . esc_html__('Auto', 'cs2fs-converter') . '</option>
            <option value="php">' . esc_html__('PHP', 'cs2fs-converter') . '</option>
            <option value="php_content">' . esc_html__('PHP + HTML', 'cs2fs-converter') . '</option>
            <option value="js">' . esc_html__('JS', 'cs2fs-converter') . '</option>
            <option value="css">' . esc_html__('CSS', 'cs2fs-converter') . '</option>
        </select></td>';
        echo '<td>';
        foreach ($mapping['notes'] as $note) {
            echo '<div>' . esc_html($note_texts[$note] ?? $note) . '</div>';
        }
        echo '</td>';
        echo '</tr>';
    }
    echo '</tbody></table><br><input type="submit" name="generate_json" class="button button-primary" value="' . esc_attr__('Generate Fluent Snippets JSON', 'cs2fs-converter') . '"></form></div>';
}

/**
 * AJAX endpoint to import the newest local CS2FS export into Fluent Snippets.
 */
function csfc_import_local_snippets() {
    // Same capability as the converter page; Fluent Snippets itself needs unfiltered_html to save code.
    if (!current_user_can('manage_options') || !current_user_can('unfiltered_html')) {
        wp_send_json_error(['message' => __('You do not have permission to import snippets.', 'cs2fs-converter')], 403);
    }

    check_ajax_referer('fluent-snippets', '_nonce');

    $uploads = wp_get_upload_dir();
    $dir = trailingslashit($uploads['basedir']) . 'cs2fs_export';
    if (!is_dir($dir)) {
        wp_send_json_error(['message' => __('Local export directory not found. Generate an export first.', 'cs2fs-converter')], 400);
    }

    $latest_file = csfc_get_latest_export_file($dir);
    if (!$latest_file) {
        wp_send_json_error(['message' => __('No local export JSON files found.', 'cs2fs-converter')], 400);
    }

    $raw = file_get_contents($latest_file);
    if ($raw === false) {
        wp_send_json_error(['message' => __('Unable to read the latest export file.', 'cs2fs-converter')], 500);
    }

    $payload = json_decode($raw, true);
    if (!is_array($payload) || ($payload['file_type'] ?? '') !== 'fluent_code_snippets' || empty($payload['snippets']) || !is_array($payload['snippets'])) {
        wp_send_json_error(['message' => __('Invalid export file. Expected Fluent Snippets payload.', 'cs2fs-converter')], 400);
    }

    $Arr = '\\FluentSnippets\\App\\Helpers\\Arr';
    $Helper = '\\FluentSnippets\\App\\Helpers\\Helper';
    $SnippetsController = '\\FluentSnippets\\App\\Http\\Controllers\\SnippetsController';
    $Snippet = '\\FluentSnippets\\App\\Model\\Snippet';

    if (!class_exists($Arr) || !class_exists($Helper) || !class_exists($SnippetsController) || !class_exists($Snippet)) {
        wp_send_json_error(['message' => __('Fluent Snippets is required for this import.', 'cs2fs-converter')], 400);
    }

    $createdSnippets = [];
    $skipped = [];
    $attention = [];
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
            /* translators: %s: date and time of the import */
            $meta['name'] = sprintf(__('Imported Snippet %s', 'cs2fs-converter'), current_time('mysql'));
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
            // Fluent Snippets' own filter, applied as its importer does.
            $decoded = apply_filters('fluent_snippets/sanitize_mixed_content', $decoded, $meta); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
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

        $notes = (array) $Arr::get($snippet, 'cs2fs.notes', []);
        if ($notes) {
            $noteTexts = csfc_note_texts();
            $attention[] = [
                'name' => $meta['name'] ?? '',
                'notes' => array_map(function ($note) use ($noteTexts) {
                    return $noteTexts[$note] ?? $note;
                }, $notes)
            ];
        }
    }

    $Helper::cacheSnippetIndex();

    wp_send_json([
        'snippets' => $createdSnippets,
        'skipped' => $skipped,
        'attention' => $attention
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
        '1.1.0',
        true
    );

    wp_localize_script($handle, 'csfcImportLocal', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('fluent-snippets'),
        'menuSelector' => '.fsnip_menu',
        'strings' => [
            'buttonText' => __('Import local (CS2FS Converter)', 'cs2fs-converter'),
            'importingText' => __('Importing...', 'cs2fs-converter'),
            /* translators: %d: number of imported snippets */
            'successWithCount' => __('Import completed. Added %d snippet(s).', 'cs2fs-converter'),
            'successGeneric' => __('Import finished.', 'cs2fs-converter'),
            'failure' => __('Import failed.', 'cs2fs-converter'),
            'attention' => __('Check these before publishing:', 'cs2fs-converter'),
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
