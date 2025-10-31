<?php
/**
 * Plugin Name: Tencent EdgeOne Cache Manager
 * Description: EO 缓存清理：单篇/更新用 purge_url，首次发布用 purge_host(invalidate)，全站 purge_all(invalidate)；使用官方 PHP SDK。
 * Version:     1.0.2
 * Author:      RV
 * Text Domain: tenc-teo
 */

if (!defined('ABSPATH')) { exit; }

define('TENC_TEO_SLUG', 'tencent-edgeone-cache');
define('TENC_TEO_OPT_KEY', 'tenc_teo_options');

/** ========== 激活：默认配置（无 Region/无 purge_url 方法设置） ========== */
register_activation_hook(__FILE__, function () {
    $defaults = array(
        'secret_id'    => '',
        'secret_key'   => '',
        'zone_id'      => '',
        'default_host' => '',
    );
    $opt = get_option(TENC_TEO_OPT_KEY);
    update_option(TENC_TEO_OPT_KEY, $opt ? array_merge($defaults, $opt) : $defaults);
});

/** ========== SDK 可用性 & 配置 ========== */
function tenc_teo_sdk_available() {
    $vendor = plugin_dir_path(__FILE__) . 'vendor/autoload.php';
    if (file_exists($vendor)) {
        require_once $vendor;
        return true;
    }
    return false;
}

function tenc_teo_get_options() {
    $opt = get_option(TENC_TEO_OPT_KEY, array());
    if (empty($opt['default_host'])) {
        $host = parse_url(home_url(), PHP_URL_HOST);
        if ($host) $opt['default_host'] = $host;
    }
    return $opt;
}

function tenc_teo_require_ready_or_throw() {
    if (!tenc_teo_sdk_available()) {
        throw new Exception(__('未找到 EO SDK，请在插件目录执行：composer require tencentcloud/teo', 'tenc-teo'));
    }
    $opt = tenc_teo_get_options();
    foreach (array('secret_id','secret_key','zone_id') as $k) {
        if (empty($opt[$k])) {
            throw new Exception(sprintf(__('配置缺失：%s，请到设置页完善。', 'tenc-teo'), $k));
        }
    }
}

/** ========== EO 客户端（不再提供 Region 设置） ========== */
function tenc_teo_client() {
    tenc_teo_require_ready_or_throw();

    $credClass          = 'TencentCloud\Common\Credential';
    $httpProfileClass   = 'TencentCloud\Common\Profile\HttpProfile';
    $clientProfileClass = 'TencentCloud\Common\Profile\ClientProfile';
    $teoClientClass     = 'TencentCloud\Teo\V20220901\TeoClient';

    $opt  = tenc_teo_get_options();
    $cred = new $credClass($opt['secret_id'], $opt['secret_key']);

    $http = new $httpProfileClass();
    $http->setEndpoint('teo.tencentcloudapi.com');

    $cp = new $clientProfileClass();
    $cp->setHttpProfile($http);

    // TEO 多数接口地域无关；此处传空字符串
    return new $teoClientClass($cred, '', $cp);
}

/** ========== CreatePurgeTask 封装 ========== */
function tenc_teo_create_purge_task(array $params) {
    tenc_teo_require_ready_or_throw();
    $opt = tenc_teo_get_options();

    if (empty($params['ZoneId'])) $params['ZoneId'] = $opt['zone_id'];
    if (($params['Type'] ?? '') === 'purge_url' && !isset($params['EncodeUrl'])) {
        $params['EncodeUrl'] = true; // 官方建议：URL 编码
    }

    $reqClass = 'TencentCloud\Teo\V20220901\Models\CreatePurgeTaskRequest';
    $client   = tenc_teo_client();
    $req      = new $reqClass();
    $req->fromJsonString(wp_json_encode($params));
    $resp = $client->CreatePurgeTask($req);

    return array(
        'RequestId' => method_exists($resp,'getRequestId') ? $resp->getRequestId() : '',
        'TaskId'    => method_exists($resp,'getJobId')     ? $resp->getJobId()     : '',
        'Raw'       => $resp,
    );
}

/** ========== 具体动作：按照你的三条规则实现 ========== */
// 1) 单篇 & 已发布文章更新 → purge_url（不带 Method）
function tenc_teo_purge_url(array $urls) {
    $urls = array_values(array_unique(array_filter($urls)));
    if (!$urls) throw new Exception('无可用 URL。');
    return tenc_teo_create_purge_task(array(
        'Type'    => 'purge_url',
        'Targets' => $urls,
    ));
}

// 2) 首次发布 → purge_host + Method=invalidate
function tenc_teo_purge_host_invalidate(array $hosts) {
    $hosts = array_values(array_unique(array_filter($hosts)));
    if (!$hosts) throw new Exception('无可用 Host。');
    return tenc_teo_create_purge_task(array(
        'Type'    => 'purge_host',
        'Targets' => $hosts,
        'Method'  => 'invalidate',
    ));
}

// 3) 全站 → purge_all + Method=invalidate
function tenc_teo_purge_all_invalidate() {
    return tenc_teo_create_purge_task(array(
        'Type'   => 'purge_all',
        'Method' => 'invalidate',
    ));
}

/** ========== 业务钩子：状态流转 ========== */
add_action('transition_post_status', function ($new_status, $old_status, $post) {
    if (wp_is_post_revision($post) || $post->post_type !== 'post') return;

    try {
        // 首次发布（非 publish → publish）：仅做 purge_host invalidate
        if ($new_status === 'publish' && $old_status !== 'publish') {
            $host = tenc_teo_get_options()['default_host'] ?? '';
            if (!$host) throw new Exception('未识别默认 Host，请在设置页配置。');
            tenc_teo_purge_host_invalidate([$host]);
            return;
        }

        // 已发布文章更新（publish → publish）：purge_url
        if ($new_status === 'publish' && $old_status === 'publish') {
            $permalink = get_permalink($post);
            tenc_teo_purge_url([$permalink]);
        }
    } catch (\Throwable $e) {
        error_log('[tenc-teo] transition_post_status error: ' . $e->getMessage());
    }
}, 10, 3);

/** ========== 文章列表行内操作：单篇手动清理（仅已发布） ========== */
add_filter('post_row_actions', function ($actions, $post) {
    if ($post->post_type === 'post' && $post->post_status === 'publish' && current_user_can('edit_post', $post->ID)) {
        $url = wp_nonce_url(
            add_query_arg(array(
                'action'  => 'tenc_teo_purge_post',
                'post_id' => $post->ID,
            ), admin_url('admin-post.php')),
            'tenc_teo_purge_post_' . $post->ID
        );
        $actions['tenc_teo_purge'] = '<a href="' . esc_url($url) . '">' . esc_html__('清理缓存', 'tenc-teo') . '</a>';
    }
    return $actions;
}, 10, 2);

add_action('admin_post_tenc_teo_purge_post', function () {
    $post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;
    if (!$post_id || !current_user_can('edit_post', $post_id)) wp_die(__('权限不足或参数无效。', 'tenc-teo'));
    check_admin_referer('tenc_teo_purge_post_' . $post_id);

    $ok = true; $msg = '';
    try {
        $permalink = get_permalink($post_id);
        tenc_teo_purge_url([$permalink]);
        $msg = __('已提交清理任务（该文章 URL）。', 'tenc-teo');
    } catch (\Throwable $e) {
        $ok = false; $msg = __('清理失败：', 'tenc-teo') . $e->getMessage();
    }
    $redirect = wp_get_referer() ?: admin_url('edit.php');
    $redirect = add_query_arg(array('tenc_teo_notice'=>$ok?'success':'error','tenc_teo_msg'=>rawurlencode($msg)), $redirect);
    wp_safe_redirect($redirect); exit;
});

/** ========== 设置页（仅必要项） + 全站清理按钮（purge_all invalidate） ========== */
add_action('admin_menu', function () {
    add_options_page(
        __('EdgeOne 缓存', 'tenc-teo'),
        __('EdgeOne 缓存', 'tenc-teo'),
        'manage_options',
        TENC_TEO_SLUG,
        'tenc_teo_render_settings_page'
    );
});

add_action('admin_init', function () {
    register_setting(TENC_TEO_OPT_KEY, TENC_TEO_OPT_KEY, function ($input) {
        $out = array();
        $out['secret_id']    = sanitize_text_field($input['secret_id'] ?? '');
        $out['secret_key']   = sanitize_text_field($input['secret_key'] ?? '');
        $out['zone_id']      = sanitize_text_field($input['zone_id'] ?? '');
        $out['default_host'] = sanitize_text_field($input['default_host'] ?? '');
        return $out;
    });
});

function tenc_teo_admin_notice_from_query() {
    if (!empty($_GET['tenc_teo_notice']) && !empty($_GET['tenc_teo_msg'])) {
        $class = $_GET['tenc_teo_notice'] === 'success' ? 'updated' : 'error';
        echo '<div class="notice ' . esc_attr($class) . ' is-dismissible"><p>' . esc_html(rawurldecode($_GET['tenc_teo_msg'])) . '</p></div>';
    }
}
add_action('admin_notices', 'tenc_teo_admin_notice_from_query');

function tenc_teo_render_settings_page() {
    if (!current_user_can('manage_options')) return;
    $opt = tenc_teo_get_options(); ?>
    <div class="wrap">
        <h1><?php echo esc_html__('EdgeOne 缓存管理', 'tenc-teo'); ?></h1>

        <?php if (!tenc_teo_sdk_available()): ?>
            <div class="notice notice-error"><p><?php echo esc_html__('未检测到 EO SDK。请在本插件目录执行：composer require tencentcloud/teo', 'tenc-teo'); ?></p></div>
        <?php endif; ?>

        <form method="post" action="options.php" style="max-width:800px;">
            <?php settings_fields(TENC_TEO_OPT_KEY); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="secret_id">SecretId</label></th>
                    <td><input type="text" id="secret_id" name="<?php echo esc_attr(TENC_TEO_OPT_KEY); ?>[secret_id]" class="regular-text" value="<?php echo esc_attr($opt['secret_id'] ?? ''); ?>" placeholder="AKIDxxxxxxxxxxxx" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="secret_key">SecretKey</label></th>
                    <td><input type="text" id="secret_key" name="<?php echo esc_attr(TENC_TEO_OPT_KEY); ?>[secret_key]" class="regular-text" value="<?php echo esc_attr($opt['secret_key'] ?? ''); ?>" placeholder="xxxxxxxxxxxxxxxxxxxx" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="zone_id">ZoneId</label></th>
                    <td><input type="text" id="zone_id" name="<?php echo esc_attr(TENC_TEO_OPT_KEY); ?>[zone_id]" class="regular-text" value="<?php echo esc_attr($opt['zone_id'] ?? ''); ?>" placeholder="zone-xxxxxxxx" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="default_host">默认 Host（首次发布用）</label></th>
                    <td>
                        <input type="text" id="default_host" name="<?php echo esc_attr(TENC_TEO_OPT_KEY); ?>[default_host]" class="regular-text" value="<?php echo esc_attr($opt['default_host'] ?? ''); ?>" placeholder="example.com">
                        <p class="description">首次发布自动对该主机名执行 purge_host(invalidate)；留空则使用站点主域。</p>
                    </td>
                </tr>
            </table>
            <?php submit_button(__('保存设置', 'tenc-teo')); ?>
        </form>

        <hr>

        <h2><?php echo esc_html__('全站缓存', 'tenc-teo'); ?></h2>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('tenc_teo_purge_all'); ?>
            <input type="hidden" name="action" value="tenc_teo_purge_all">
            <p>
                <button type="submit" class="button button-primary" onclick="return confirm('<?php echo esc_js(__('确认提交 “全站缓存清理（purge_all, invalidate）”？', 'tenc-teo')); ?>');">
                    <?php echo esc_html__('一键清理全站缓存（purge_all, invalidate）', 'tenc-teo'); ?>
                </button>
            </p>
            <p class="description"><?php echo esc_html__('用于重大改动或紧急情况，请谨慎使用。', 'tenc-teo'); ?></p>
        </form>
    </div>
<?php }

add_action('admin_post_tenc_teo_purge_all', function () {
    if (!current_user_can('manage_options')) wp_die(__('无权操作。', 'tenc-teo'));
    check_admin_referer('tenc_teo_purge_all');

    $ok = true; $msg = '';
    try {
        tenc_teo_purge_all_invalidate();
        $msg = __('已提交全站清理任务（purge_all, invalidate）。', 'tenc-teo');
    } catch (\Throwable $e) {
        $ok = false; $msg = __('提交失败：', 'tenc-teo') . $e->getMessage();
    }
    $redirect = add_query_arg(array(
        'page' => TENC_TEO_SLUG,
        'tenc_teo_notice' => $ok ? 'success' : 'error',
        'tenc_teo_msg' => rawurlencode($msg),
    ), admin_url('options-general.php'));
    wp_safe_redirect($redirect); exit;
});