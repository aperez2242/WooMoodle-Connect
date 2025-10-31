/**
 * Plugin Name: Moodle Academy Auto Enrol
 * Description: Enrol WooCommerce customers (and manual test users) into a Moodle course via Web Services (form-encoded, Moodle 5.1-ready) with verbose logging and per-product course overrides.
 * Version:     2.0
 * Author:      Alvaro Perez Blanco – Moodle
 */

if ( ! defined('ABSPATH') ) exit;

/** =========================
 *  Constants
 *  ========================= */
define('MAE_LOG_PATH', WP_CONTENT_DIR . '/moodle-debug.log');

/** =========================
 *  Admin Menu (attach to existing Moodle parent if present, otherwise create it)
 *  ========================= */
add_action('admin_menu', function () {
    if ( ! current_user_can('manage_options') ) return;

    global $menu;
    $existing_parent = 'moodle_invoice_report_main';
    $menu_exists = false;

    foreach ($menu as $item) {
        if (isset($item[2]) && $item[2] === $existing_parent) {
            $menu_exists = true; break;
        }
    }

    $parent_slug = $menu_exists ? $existing_parent : 'moodle_main_menu';

    if (!$menu_exists) {
        add_menu_page(
            'Moodle',
            'Moodle',
            'manage_options',
            $parent_slug,
            '',
            'dashicons-welcome-learn-more',
            56
        );
    }

    add_submenu_page(
        $parent_slug,
        'Moodle Academy',
        'Moodle Academy',
        'manage_options',
        'moodle-academy-settings',
        'mae_render_settings_page'
    );
});

/** =========================
 *  Admin Notices (missing settings)
 *  ========================= */
add_action('admin_notices', function () {
    if ( ! current_user_can('manage_options') ) return;
    $screen = get_current_screen();
    if ( ! $screen || strpos($screen->id, 'moodle-academy-settings') === false ) return;

    $domain   = trim((string) get_option('mae_moodle_domain'));
    $token    = trim((string) get_option('mae_moodle_token'));
    $courseid = (int) get_option('mae_course_id');

    if (!$domain || !$token || !$courseid) {
        echo '<div class="notice notice-error"><p><strong>Moodle Academy:</strong> Please complete Moodle domain, token, and global Course ID.</p></div>';
    }
});

/** =========================
 *  Settings
 *  ========================= */
add_action('admin_init', function () {
    register_setting('mae_settings_group', 'mae_moodle_domain', [
        'type' => 'string',
        'sanitize_callback' => function($v){ return esc_url_raw(rtrim((string)$v)); },
        'default' => ''
    ]);
    register_setting('mae_settings_group', 'mae_moodle_token', [
        'type' => 'string',
        'sanitize_callback' => function($v){ return preg_replace('/[^a-zA-Z0-9]/', '', (string)$v); },
        'default' => ''
    ]);
    register_setting('mae_settings_group', 'mae_course_id', [
        'type' => 'integer',
        'sanitize_callback' => 'absint',
        'default' => 0
    ]);
    register_setting('mae_settings_group', 'mae_auth_method', [
        'type' => 'string',
        'sanitize_callback' => function($v){ return sanitize_text_field($v); },
        'default' => ''
    ]);
    // Logging controls
    register_setting('mae_settings_group', 'mae_logging_enabled', [
        'type' => 'boolean',
        'sanitize_callback' => function($v){ return (int)!empty($v); },
        'default' => 1
    ]);
    register_setting('mae_settings_group', 'mae_log_max_bytes', [
        'type' => 'integer',
        'sanitize_callback' => function($v){ $n = (int)$v; return ($n > 0 ? $n : 1048576); }, // default 1MB
        'default' => 1048576
    ]);
});

/** =========================
 *  Settings Page + Manual Enrol Form + Clear Log + Site Info Test
 *  ========================= */
function mae_render_settings_page() {
    if ( ! current_user_can('manage_options') ) wp_die('Unauthorized');

    // Clear log action
    if (isset($_POST['mae_clear_log']) && check_admin_referer('mae_clear_log_action')) {
        if (file_exists(MAE_LOG_PATH)) {
            @unlink(MAE_LOG_PATH);
            echo '<div class="notice notice-success"><p>Log cleared.</p></div>';
        } else {
            echo '<div class="notice notice-info"><p>No log file found.</p></div>';
        }
    }
    ?>
    <div class="wrap">
        <h1>Moodle Academy</h1>

        <form method="post" action="options.php">
            <?php settings_fields('mae_settings_group'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="mae_moodle_domain">Moodle domain (base URL)</label></th>
                    <td>
                        <input type="text" name="mae_moodle_domain" id="mae_moodle_domain"
                               value="<?php echo esc_attr(get_option('mae_moodle_domain')); ?>"
                               class="regular-text" placeholder="https://moodle.example.com/public">
                        <p class="description">Include the subfolder if Moodle runs under it (for example, “/public”).</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="mae_moodle_token">Moodle token</label></th>
                    <td><input type="text" name="mae_moodle_token" id="mae_moodle_token" value="<?php echo esc_attr(get_option('mae_moodle_token')); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="mae_course_id">Global Course ID</label></th>
                    <td><input type="number" name="mae_course_id" id="mae_course_id" value="<?php echo esc_attr(get_option('mae_course_id')); ?>" class="small-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="mae_auth_method">Auth method (optional)</label></th>
                    <td>
                        <input type="text" name="mae_auth_method" id="mae_auth_method"
                               value="<?php echo esc_attr(get_option('mae_auth_method')); ?>"
                               class="regular-text" placeholder="">
                        <p class="description">Leave empty to omit the “auth” field. Use “manual” only if Manual accounts is enabled in Moodle.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Logging</th>
                    <td>
                        <label>
                            <input type="checkbox" name="mae_logging_enabled" value="1" <?php checked( (int)get_option('mae_logging_enabled', 1), 1 ); ?>>
                            Enable debug logging to <code>wp-content/moodle-debug.log</code>
                        </label>
                        <p>
                            <label for="mae_log_max_bytes">Max log size (bytes):</label>
                            <input type="number" name="mae_log_max_bytes" id="mae_log_max_bytes" class="small-text" value="<?php echo esc_attr( (int)get_option('mae_log_max_bytes', 1048576) ); ?>">
                            <span class="description">Log rotates when exceeding this size.</span>
                        </p>
                    </td>
                </tr>
            </table>
            <?php submit_button('Save Settings'); ?>
        </form>

        <form method="post" style="margin-top:10px;">
            <?php wp_nonce_field('mae_clear_log_action'); ?>
            <button class="button button-secondary" name="mae_clear_log" value="1">Clear Log</button>
        </form>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:10px;">
            <?php wp_nonce_field('mae_siteinfo_action'); ?>
            <input type="hidden" name="action" value="mae_siteinfo_test">
            <?php submit_button('Test Site Info (core_webservice_get_site_info)', 'secondary'); ?>
        </form>

        <hr>
        <h2>Manual Enrolment Test</h2>

        <?php if (isset($_GET['mae_msg'])): ?>
            <div class="notice notice-info is-dismissible"><p><?php echo esc_html($_GET['mae_msg']); ?></p></div>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('mae_manual_enrol_action'); ?>
            <input type="hidden" name="action" value="mae_manual_enrol">
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="mae_firstname">First name</label></th>
                    <td><input type="text" name="firstname" id="mae_firstname" required class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="mae_lastname">Last name</label></th>
                    <td><input type="text" name="lastname" id="mae_lastname" required class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="mae_email">Email</label></th>
                    <td><input type="email" name="email" id="mae_email" required class="regular-text"></td>
                </tr>
            </table>
            <?php submit_button('Manually Enrol User'); ?>
        </form>
    </div>
<?php }

/** =========================
 *  Admin-post handlers: Manual enrol and Site Info test
 *  ========================= */
add_action('admin_post_mae_manual_enrol', function () {
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    check_admin_referer('mae_manual_enrol_action');

    $firstname = sanitize_text_field($_POST['firstname'] ?? '');
    $lastname  = sanitize_text_field($_POST['lastname'] ?? '');
    $email     = sanitize_email($_POST['email'] ?? '');

    $msg = mae_enrol_in_moodle($firstname, $lastname, $email, 0, 0);
    wp_redirect(add_query_arg('mae_msg', urlencode($msg), wp_get_referer()));
    exit;
});

add_action('admin_post_mae_siteinfo_test', function () {
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    check_admin_referer('mae_siteinfo_action');

    $domain = rtrim((string) get_option('mae_moodle_domain'), '/');
    $token  = (string) get_option('mae_moodle_token');

    $log = function ($label, $data) {
        $out = "=== {$label} ===\n";
        if (is_array($data) || is_object($data)) $out .= print_r($data, true);
        else $out .= (string) $data;
        $out .= "\n\n";
        @file_put_contents(MAE_LOG_PATH, $out, FILE_APPEND);
    };

    $url = $domain . '/webservice/rest/server.php?wstoken=' . rawurlencode($token)
        . '&wsfunction=core_webservice_get_site_info&moodlewsrestformat=json';

    // Log full URL including token as requested
    $log('core_webservice_get_site_info URL', $url);

    $resp = wp_remote_get(
        $url,
        [
            'headers' => [
                'Accept'     => 'application/json',
                'User-Agent' => 'WordPress; MAE/2.0',
                'Referer'    => home_url('/'),
            ],
            'timeout' => 20,
            // 'redirection' => 0,
        ]
    );

    if (is_wp_error($resp)) {
        $log('core_webservice_get_site_info ERROR', $resp->get_error_message());
    } else {
        $log('core_webservice_get_site_info CODE', wp_remote_retrieve_response_code($resp));
        $log('core_webservice_get_site_info HEADERS', wp_remote_retrieve_headers($resp));
        $log('core_webservice_get_site_info BODY', wp_remote_retrieve_body($resp));
    }

    wp_redirect(add_query_arg('mae_msg', urlencode('Ran Site Info test — check the log file.'), admin_url('admin.php?page=moodle-academy-settings')));
    exit;
});

/** =========================
 *  Product Meta (per-product course mapping)
 *  ========================= */
add_action('add_meta_boxes', function () {
    if ( ! current_user_can('edit_products') ) return;
    add_meta_box(
        'mae_product_course_box',
        'Moodle Course Mapping',
        function ($post) {
            wp_nonce_field('mae_save_product_course', 'mae_product_course_nonce');
            $value = (int) get_post_meta($post->ID, '_mae_course_id', true);
            echo '<p><label for="mae_product_course_id">Moodle Course ID (override global):</label> ';
            echo '<input type="number" class="small-text" id="mae_product_course_id" name="mae_product_course_id" value="' . esc_attr($value) . '"></p>';
        },
        'product',
        'side',
        'default'
    );
});

add_action('save_post_product', function ($post_id) {
    if ( ! isset($_POST['mae_product_course_nonce']) || ! wp_verify_nonce($_POST['mae_product_course_nonce'], 'mae_save_product_course') ) return;
    if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
    if ( ! current_user_can('edit_post', $post_id) ) return;

    $val = isset($_POST['mae_product_course_id']) ? absint($_POST['mae_product_course_id']) : 0;
    if ($val > 0) {
        update_post_meta($post_id, '_mae_course_id', $val);
    } else {
        delete_post_meta($post_id, '_mae_course_id');
    }
});

/** =========================
 *  WooCommerce hook: enrol on order completed
 *  ========================= */
add_action('woocommerce_order_status_completed', function ($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return;

    $firstname = $order->get_billing_first_name();
    $lastname  = $order->get_billing_last_name();
    $email     = $order->get_billing_email();

    // Determine course: product override if present, else global
    $course_override = 0;
    foreach ($order->get_items() as $item) {
        $pid = $item->get_product_id();
        $cid = (int) get_post_meta($pid, '_mae_course_id', true);
        if ($cid > 0) { $course_override = $cid; break; }
    }

    $msg = mae_enrol_in_moodle($firstname, $lastname, $email, $order_id, $course_override);
    $order->add_order_note($msg);
});

/** =========================
 *  HTTP helper with verbose logging and retries
 *  ========================= */
function mae_post_with_retries($url, $args, $retries = 2, $sleep = 1, $log = null, $label = 'HTTP') {
    // Always log the full URL being called (including token if present)
    if (is_callable($log)) {
        $log($label . ' URL (pre-request)', $url);
        $log($label . ' ARGS (pre-request)', $args);
    }

    for ($i = 0; $i <= $retries; $i++) {
        $resp = wp_remote_post($url, $args);

        if (is_callable($log)) {
            if (is_wp_error($resp)) {
                $log($label . " try {$i} ERROR", $resp->get_error_message());
            } else {
                $code    = wp_remote_retrieve_response_code($resp);
                $headers = wp_remote_retrieve_headers($resp);
                $body    = wp_remote_retrieve_body($resp);

                $log($label . " try {$i} URL", $url);
                $log($label . " try {$i} ARGS", $args);
                $log($label . " try {$i} CODE", $code);
                $log($label . " try {$i} HEADERS", $headers);
                $log($label . " try {$i} BODY", $body);
            }
        }

        if (is_wp_error($resp)) {
            if ($i === $retries) return $resp;
        } else {
            $code = wp_remote_retrieve_response_code($resp);
            if ($code >= 500 || $code == 429) {
                if ($i === $retries) return $resp;
            } else {
                return $resp;
            }
        }
        sleep($sleep);
    }
    return $resp;
}

/** =========================
 *  Core enrolment logic
 *  ========================= */
function mae_enrol_in_moodle(string $firstname, string $lastname, string $email, int $order_id = 0, int $course_override = 0): string {
    $domain    = rtrim((string) get_option('mae_moodle_domain'), '/');
    $token     = (string) get_option('mae_moodle_token');
    $global_id = (int) get_option('mae_course_id');
    $auth      = trim((string) get_option('mae_auth_method'));
    $courseid  = $course_override > 0 ? $course_override : $global_id;

    if (!$domain || !$token || !$courseid) {
        return 'Moodle enrolment error: missing configuration (domain/token/course).';
    }

    $log_enabled = (int) get_option('mae_logging_enabled', 1);
    $max_bytes   = (int) get_option('mae_log_max_bytes', 1048576);

    // logger with rotation
    $log = function ($label, $data) use ($log_enabled, $max_bytes) {
        if (!$log_enabled) return;
        if (file_exists(MAE_LOG_PATH) && filesize(MAE_LOG_PATH) > $max_bytes) {
            @rename(MAE_LOG_PATH, MAE_LOG_PATH . '.1');
        }
        $out = "=== {$label} ===\n";
        if (is_array($data) || is_object($data)) $out .= print_r($data, true);
        else $out .= (string) $data;
        $out .= "\n\n";
        @file_put_contents(MAE_LOG_PATH, $out, FILE_APPEND);
    };

    $rest = $domain . '/webservice/rest/server.php';
    $fmt  = 'json';

    // Step 1: find user by email (form-encoded)
    $get_user_url = $rest . '?wstoken=' . rawurlencode($token)
        . '&wsfunction=core_user_get_users_by_field&moodlewsrestformat=' . $fmt;

    // Log full URL including token as requested
    $log('core_user_get_users_by_field URL', $get_user_url);

    $resp = mae_post_with_retries(
        $get_user_url,
        [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept'       => 'application/json',
                'User-Agent'   => 'WordPress; MAE/2.0',
                'Referer'      => home_url('/'),
            ],
            'body'    => [
                'field'     => 'email',
                'values[0]' => $email,
            ],
            'timeout' => 20,
        ],
        2, 1, $log, 'core_user_get_users_by_field'
    );

    if (is_wp_error($resp)) {
        return 'Moodle lookup failed: ' . $resp->get_error_message();
    }

    $body = wp_remote_retrieve_body($resp);
    $log('core_user_get_users_by_field parsed', $body);
    $arr  = json_decode($body, true);
    $userid = (is_array($arr) && isset($arr[0]['id'])) ? (int) $arr[0]['id'] : 0;

    // Step 2: create user if not exists (form-encoded)
    if (!$userid) {
        // Username: ascii lower, safe chars only; ensure not empty
        $seed = strtolower($firstname . '.' . $lastname . '.' . wp_generate_password(4, false));
        $username = preg_replace('/[^a-z0-9._-]/', '', $seed);
        if (!$username) $username = 'user.' . strtolower(wp_generate_password(6, false));

        $create_user_url = $rest . '?wstoken=' . rawurlencode($token)
            . '&wsfunction=core_user_create_users&moodlewsrestformat=' . $fmt;

        // Log full URL including token
        $log('core_user_create_users URL', $create_user_url);

        $form = [
            'users[0][username]'       => $username,
            'users[0][firstname]'      => $firstname,
            'users[0][lastname]'       => $lastname,
            'users[0][email]'          => $email,
            'users[0][createpassword]' => 1,
        ];
        if ($auth !== '') { $form['users[0][auth]'] = $auth; }

        $resp = mae_post_with_retries(
            $create_user_url,
            [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Accept'       => 'application/json',
                    'User-Agent'   => 'WordPress; MAE/2.0',
                    'Referer'      => home_url('/'),
                ],
                'body'    => $form,
                'timeout' => 20,
            ],
            2, 1, $log, 'core_user_create_users'
        );

        if (is_wp_error($resp)) {
            if ($order_id && $order = wc_get_order($order_id)) {
                $order->add_order_note('Moodle enrolment FAILED during user creation: ' . $resp->get_error_message());
            }
            return 'Moodle enrolment failed: user creation error (HTTP). See log.';
        }

        $response_body = wp_remote_retrieve_body($resp);
        $log('core_user_create_users BODY parsed', $response_body);
        $created = json_decode($response_body, true);

        if (is_array($created) && isset($created[0]['id'])) {
            $userid = (int) $created[0]['id'];
        } else {
            if ($order_id && $order = wc_get_order($order_id)) {
                $order->add_order_note('Moodle enrolment FAILED during user creation. See log file.');
            }
            return 'Moodle enrolment failed: user creation error. See wp-content/moodle-debug.log.';
        }
    }

    // Step 3: enrol user (form-encoded)
    $enrol_url = $rest . '?wstoken=' . rawurlencode($token)
        . '&wsfunction=enrol_manual_enrol_users&moodlewsrestformat=' . $fmt;

    // Log full URL including token
    $log('enrol_manual_enrol_users URL', $enrol_url);

    $enrol_form = [
        'enrolments[0][roleid]'   => 5,          // student
        'enrolments[0][userid]'   => $userid,
        'enrolments[0][courseid]' => $courseid,
    ];

    $resp = mae_post_with_retries(
        $enrol_url,
        [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept'       => 'application/json',
                'User-Agent'   => 'WordPress; MAE/2.0',
                'Referer'      => home_url('/'),
            ],
            'body'    => $enrol_form,
            'timeout' => 20,
        ],
        2, 1, $log, 'enrol_manual_enrol_users'
    );

    if (is_wp_error($resp)) {
        if ($order_id && $order = wc_get_order($order_id)) {
            $order->add_order_note(sprintf('Moodle enrolment FAILED (HTTP) for user_id=%d course_id=%d: %s', $userid, $courseid, $resp->get_error_message()));
        }
        return 'Moodle enrolment failed during enrol call (HTTP). See log.';
    }

    $enrol_body = wp_remote_retrieve_body($resp);
    $log('enrol_manual_enrol_users BODY parsed', $enrol_body);

    if (is_string($enrol_body) && stripos($enrol_body, 'exception') !== false) {
        if ($order_id && $order = wc_get_order($order_id)) {
            $order->add_order_note(sprintf('Moodle enrolment FAILED for user_id=%d course_id=%d. See log.', $userid, $courseid));
        }
        return 'Moodle enrolment failed during enrol call. See wp-content/moodle-debug.log.';
    }

    // Success: add a clear order note with Moodle user and course
    if ($order_id && $order = wc_get_order($order_id)) {
        $course_url_hint = esc_url_raw( rtrim((string) get_option('mae_moodle_domain'), '/') . '/enrol/index.php?id=' . $courseid );
        $order->add_order_note(sprintf('Moodle enrolment SUCCESS. user_id=%d in course_id=%d. Participants: %s', $userid, $courseid, $course_url_hint));
    }

    return 'User successfully enrolled in the course!';
}
