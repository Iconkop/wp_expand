<?php
/**
 * Plugin Name: Tencent EdgeOne Cache Manager
 * Description: EO 缓存清理：单篇/更新用 purge_url，首次发布用 purge_host(invalidate)，全站 purge_all(invalidate)；使用官方 PHP SDK。
 * Version:     1.0.4
 * Author:      Shinko
 * Text Domain: tenc-teo
 * Plugin URI:  https://github.com/Iconkop/wp_expand
 */

if (!defined('ABSPATH')) { exit; }

define('TENC_TEO_SLUG', 'tencent-edgeone-cache');
define('TENC_TEO_OPT_KEY', 'tenc_teo_options');
define('TENC_TEO_VERSION', '1.0.4');
define('TENC_TEO_GITHUB_REPO', 'Iconkop/wp_expand');
define('TENC_TEO_PLUGIN_FILE', __FILE__);

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

/** ========== 测试连接功能 ========== */
add_action('admin_post_tenc_teo_test_connection', function () {
    if (!current_user_can('manage_options')) wp_die(__('无权操作。', 'tenc-teo'));
    check_admin_referer('tenc_teo_test_connection');

    $ok = true; $msg = '';
    try {
        if (!tenc_teo_sdk_available()) {
            throw new Exception(__('SDK 未安装，请执行：composer require tencentcloud/teo', 'tenc-teo'));
        }

        $opt = tenc_teo_get_options();
        foreach (array('secret_id','secret_key','zone_id') as $k) {
            if (empty($opt[$k])) {
                throw new Exception(sprintf(__('配置缺失：%s，请先完善配置。', 'tenc-teo'), $k));
            }
        }

        // 测试连接：调用 DescribeZones 接口获取站点信息
        $client = tenc_teo_client();
        $reqClass = 'TencentCloud\Teo\V20220901\Models\DescribeZonesRequest';
        $req = new $reqClass();
        
        $params = array(
            'Filters' => array(
                array(
                    'Name' => 'zone-id',
                    'Values' => array($opt['zone_id'])
                )
            )
        );
        $req->fromJsonString(wp_json_encode($params));
        $resp = $client->DescribeZones($req);
        
        if (method_exists($resp, 'getTotalCount') && $resp->getTotalCount() > 0) {
            $zones = method_exists($resp, 'getZones') ? $resp->getZones() : array();
            if (!empty($zones)) {
                $zone = $zones[0];
                $zoneName = method_exists($zone, 'getZoneName') ? $zone->getZoneName() : 'N/A';
                $zoneStatus = method_exists($zone, 'getStatus') ? $zone->getStatus() : 'N/A';
                $msg = sprintf(
                    __('✅ 连接成功！站点信息：%s (状态: %s)', 'tenc-teo'),
                    $zoneName,
                    $zoneStatus
                );
            } else {
                $msg = __('✅ API 连接成功，但未找到站点详细信息。', 'tenc-teo');
            }
        } else {
            throw new Exception(__('未找到对应的 Zone ID，请检查配置是否正确。', 'tenc-teo'));
        }
    } catch (\Throwable $e) {
        $ok = false; 
        $msg = __('❌ 连接失败：', 'tenc-teo') . $e->getMessage();
    }
    
    $redirect = add_query_arg(array(
        'page' => TENC_TEO_SLUG,
        'tenc_teo_notice' => $ok ? 'success' : 'error',
        'tenc_teo_msg' => rawurlencode($msg),
    ), admin_url('options-general.php'));
    wp_safe_redirect($redirect); exit;
});

/** ========== 加载管理页面样式 ========== */
add_action('admin_enqueue_scripts', function($hook) {
    if ('settings_page_' . TENC_TEO_SLUG !== $hook) return;
    
    wp_add_inline_style('common', '
        .tenc-teo-card {
            background: #fff;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            padding: 20px;
            margin: 20px 0;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        .tenc-teo-card h2 {
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #dcdcde;
            font-size: 18px;
        }
        .tenc-teo-card h3 {
            font-size: 14px;
            margin: 15px 0 10px;
            color: #50575e;
        }
        .tenc-teo-status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }
        .tenc-teo-status-badge.success {
            background: #d4edda;
            color: #155724;
        }
        .tenc-teo-status-badge.error {
            background: #f8d7da;
            color: #721c24;
        }
        .tenc-teo-status-badge.warning {
            background: #fff3cd;
            color: #856404;
        }
        .tenc-teo-button-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 15px;
        }
        .tenc-teo-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin: 15px 0;
        }
        .tenc-teo-info-item {
            padding: 12px;
            background: #f6f7f7;
            border-left: 3px solid #2271b1;
            border-radius: 3px;
        }
        .tenc-teo-info-item strong {
            display: block;
            font-size: 13px;
            color: #1d2327;
            margin-bottom: 5px;
        }
        .tenc-teo-info-item span {
            font-size: 12px;
            color: #50575e;
        }
        .tenc-teo-help-text {
            background: #f0f6fc;
            border-left: 3px solid #0073aa;
            padding: 12px 15px;
            margin: 15px 0;
            font-size: 13px;
            line-height: 1.6;
        }
        .tenc-teo-help-text code {
            background: #fff;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 12px;
        }
        .tenc-teo-danger-zone {
            border-color: #dc3232;
            border-left: 4px solid #dc3232;
        }
        .tenc-teo-danger-zone h2 {
            color: #d63638;
        }
        @media screen and (max-width: 782px) {
            .tenc-teo-button-group {
                flex-direction: column;
            }
            .tenc-teo-button-group .button {
                width: 100%;
                text-align: center;
            }
            .tenc-teo-info-grid {
                grid-template-columns: 1fr;
            }
        }
    ');
});

/** ========== 设置页面渲染 ========== */
function tenc_teo_render_settings_page() {
    if (!current_user_can('manage_options')) return;
    $opt = tenc_teo_get_options();
    $sdk_ok = tenc_teo_sdk_available();
    $config_complete = !empty($opt['secret_id']) && !empty($opt['secret_key']) && !empty($opt['zone_id']);
    ?>
    <div class="wrap">
        <h1>
            <span class="dashicons dashicons-cloud" style="font-size: 28px; vertical-align: middle; color: #2271b1;"></span>
            <?php echo esc_html__('EdgeOne 缓存管理', 'tenc-teo'); ?>
            <?php if ($sdk_ok && $config_complete): ?>
                <span class="tenc-teo-status-badge success"><?php echo esc_html__('已配置', 'tenc-teo'); ?></span>
            <?php elseif (!$sdk_ok): ?>
                <span class="tenc-teo-status-badge error"><?php echo esc_html__('SDK 未安装', 'tenc-teo'); ?></span>
            <?php else: ?>
                <span class="tenc-teo-status-badge warning"><?php echo esc_html__('待配置', 'tenc-teo'); ?></span>
            <?php endif; ?>
        </h1>

        <?php if (!$sdk_ok): ?>
            <div class="notice notice-error">
                <p>
                    <strong><?php echo esc_html__('SDK 未安装', 'tenc-teo'); ?></strong><br>
                    <?php echo esc_html__('请在插件目录执行以下命令安装依赖：', 'tenc-teo'); ?>
                </p>
                <p><code style="background: #f0f0f0; padding: 8px 12px; display: inline-block; border-radius: 3px;">cd <?php echo esc_html(plugin_dir_path(__FILE__)); ?> && composer require tencentcloud/teo</code></p>
            </div>
        <?php endif; ?>

        <!-- 配置卡片 -->
        <div class="tenc-teo-card">
            <h2>
                <span class="dashicons dashicons-admin-settings" style="color: #2271b1;"></span>
                <?php echo esc_html__('API 配置', 'tenc-teo'); ?>
            </h2>
            
            <div class="tenc-teo-help-text">
                <strong><?php echo esc_html__('📘 配置说明', 'tenc-teo'); ?></strong><br>
                <?php echo esc_html__('1. 登录', 'tenc-teo'); ?> <a href="https://console.cloud.tencent.com/cam/capi" target="_blank"><?php echo esc_html__('腾讯云控制台', 'tenc-teo'); ?></a> <?php echo esc_html__('获取 SecretId 和 SecretKey', 'tenc-teo'); ?><br>
                <?php echo esc_html__('2. 在', 'tenc-teo'); ?> <a href="https://console.cloud.tencent.com/edgeone" target="_blank"><?php echo esc_html__('EdgeOne 控制台', 'tenc-teo'); ?></a> <?php echo esc_html__('查看 Zone ID', 'tenc-teo'); ?><br>
                <?php echo esc_html__('3. 填写配置后点击"测试连接"验证配置是否正确', 'tenc-teo'); ?>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields(TENC_TEO_OPT_KEY); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="secret_id">
                                <?php echo esc_html__('SecretId', 'tenc-teo'); ?>
                                <span style="color: #d63638;">*</span>
                            </label>
                        </th>
                        <td>
                            <input type="text" id="secret_id" name="<?php echo esc_attr(TENC_TEO_OPT_KEY); ?>[secret_id]" class="regular-text" value="<?php echo esc_attr($opt['secret_id'] ?? ''); ?>" placeholder="AKIDxxxxxxxxxxxx" required>
                            <?php if (!empty($opt['secret_id'])): ?>
                                <span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="secret_key">
                                <?php echo esc_html__('SecretKey', 'tenc-teo'); ?>
                                <span style="color: #d63638;">*</span>
                            </label>
                        </th>
                        <td>
                            <input type="password" id="secret_key" name="<?php echo esc_attr(TENC_TEO_OPT_KEY); ?>[secret_key]" class="regular-text" value="<?php echo esc_attr($opt['secret_key'] ?? ''); ?>" placeholder="xxxxxxxxxxxxxxxxxxxx" required>
                            <?php if (!empty($opt['secret_key'])): ?>
                                <span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span>
                            <?php endif; ?>
                            <button type="button" class="button button-small" onclick="var input = document.getElementById('secret_key'); input.type = input.type === 'password' ? 'text' : 'password';" style="margin-left: 5px;">
                                <span class="dashicons dashicons-visibility" style="vertical-align: middle;"></span>
                            </button>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="zone_id">
                                <?php echo esc_html__('Zone ID', 'tenc-teo'); ?>
                                <span style="color: #d63638;">*</span>
                            </label>
                        </th>
                        <td>
                            <input type="text" id="zone_id" name="<?php echo esc_attr(TENC_TEO_OPT_KEY); ?>[zone_id]" class="regular-text" value="<?php echo esc_attr($opt['zone_id'] ?? ''); ?>" placeholder="zone-xxxxxxxx" required>
                            <?php if (!empty($opt['zone_id'])): ?>
                                <span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="default_host"><?php echo esc_html__('默认主机名', 'tenc-teo'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="default_host" name="<?php echo esc_attr(TENC_TEO_OPT_KEY); ?>[default_host]" class="regular-text" value="<?php echo esc_attr($opt['default_host'] ?? ''); ?>" placeholder="example.com">
                            <p class="description">
                                <span class="dashicons dashicons-info" style="color: #2271b1;"></span>
                                <?php echo esc_html__('首次发布文章时，自动对该主机名执行 purge_host(invalidate)；留空则使用站点主域', 'tenc-teo'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(__('保存设置', 'tenc-teo'), 'primary', 'submit', true); ?>
            </form>
            
            <?php if ($sdk_ok): ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top: 10px;">
                    <?php wp_nonce_field('tenc_teo_test_connection'); ?>
                    <input type="hidden" name="action" value="tenc_teo_test_connection">
                    <button type="submit" class="button button-secondary">
                        <span class="dashicons dashicons-update" style="vertical-align: middle;"></span>
                        <?php echo esc_html__('测试连接', 'tenc-teo'); ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <!-- 缓存清理策略说明 -->
        <div class="tenc-teo-card">
            <h2>
                <span class="dashicons dashicons-info" style="color: #2271b1;"></span>
                <?php echo esc_html__('缓存清理策略', 'tenc-teo'); ?>
            </h2>
            
            <div class="tenc-teo-info-grid">
                <div class="tenc-teo-info-item">
                    <strong>
                        <span class="dashicons dashicons-edit" style="color: #2271b1; vertical-align: middle;"></span>
                        <?php echo esc_html__('文章更新', 'tenc-teo'); ?>
                    </strong>
                    <span><?php echo esc_html__('已发布文章更新时，使用 purge_url 精准清理该文章 URL 缓存', 'tenc-teo'); ?></span>
                </div>
                
                <div class="tenc-teo-info-item">
                    <strong>
                        <span class="dashicons dashicons-welcome-write-blog" style="color: #00a32a; vertical-align: middle;"></span>
                        <?php echo esc_html__('首次发布', 'tenc-teo'); ?>
                    </strong>
                    <span><?php echo esc_html__('新文章首次发布时，使用 purge_host(invalidate) 清理整个域名缓存', 'tenc-teo'); ?></span>
                </div>
                
                <div class="tenc-teo-info-item">
                    <strong>
                        <span class="dashicons dashicons-admin-page" style="color: #8c8f94; vertical-align: middle;"></span>
                        <?php echo esc_html__('文章列表', 'tenc-teo'); ?>
                    </strong>
                    <span><?php echo esc_html__('在文章列表中可以手动清理单篇文章缓存（已发布文章）', 'tenc-teo'); ?></span>
                </div>
                
                <div class="tenc-teo-info-item">
                    <strong>
                        <span class="dashicons dashicons-admin-site-alt3" style="color: #d63638; vertical-align: middle;"></span>
                        <?php echo esc_html__('全站清理', 'tenc-teo'); ?>
                    </strong>
                    <span><?php echo esc_html__('使用 purge_all(invalidate) 清理全站所有缓存，适用于重大改动', 'tenc-teo'); ?></span>
                </div>
            </div>
        </div>

        <!-- 危险操作区 -->
        <div class="tenc-teo-card tenc-teo-danger-zone">
            <h2>
                <span class="dashicons dashicons-warning" style="color: #d63638;"></span>
                <?php echo esc_html__('全站缓存清理', 'tenc-teo'); ?>
            </h2>
            
            <p><?php echo esc_html__('此操作将清理全站所有缓存，可能会导致短时间内服务器负载增加。请谨慎使用！', 'tenc-teo'); ?></p>
            
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('tenc_teo_purge_all'); ?>
                <input type="hidden" name="action" value="tenc_teo_purge_all">
                <button type="submit" class="button button-secondary" onclick="return confirm('<?php echo esc_js(__('⚠️ 警告：此操作将清理全站所有缓存！\n\n这可能会导致短时间内服务器负载增加，源站压力增大。\n\n确定要继续吗？', 'tenc-teo')); ?>');" <?php echo (!$sdk_ok || !$config_complete) ? 'disabled' : ''; ?>>
                    <span class="dashicons dashicons-trash" style="vertical-align: middle;"></span>
                    <?php echo esc_html__('一键清理全站缓存', 'tenc-teo'); ?>
                </button>
                <p class="description">
                    <span class="dashicons dashicons-info"></span>
                    <?php echo esc_html__('使用场景：网站主题更换、重大功能更新、紧急问题修复等', 'tenc-teo'); ?>
                </p>
            </form>
        </div>

        <!-- 插件信息和更新 -->
        <div class="tenc-teo-card" style="background: #f6f7f7;">
            <h3>
                <span class="dashicons dashicons-info" style="color: #2271b1;"></span>
                <?php echo esc_html__('插件信息', 'tenc-teo'); ?>
            </h3>
            <p>
                <strong><?php echo esc_html__('当前版本：', 'tenc-teo'); ?></strong> <?php echo esc_html(TENC_TEO_VERSION); ?><br>
                <strong><?php echo esc_html__('GitHub：', 'tenc-teo'); ?></strong> 
                <a href="https://github.com/<?php echo esc_attr(TENC_TEO_GITHUB_REPO); ?>" target="_blank">
                    <?php echo esc_html(TENC_TEO_GITHUB_REPO); ?>
                </a>
            </p>
            <?php
            $update_info = tenc_teo_get_github_release_info();
            if ($update_info && version_compare(TENC_TEO_VERSION, $update_info->version, '<')):
            ?>
                <div style="background: #d4edda; border-left: 3px solid #00a32a; padding: 12px; margin: 10px 0; border-radius: 3px;">
                    <p style="margin: 0;">
                        <strong style="color: #155724;">
                            <span class="dashicons dashicons-update-alt" style="vertical-align: middle;"></span>
                            <?php echo esc_html__('有新版本可用：', 'tenc-teo'); ?> v<?php echo esc_html($update_info->version); ?>
                        </strong><br>
                        <a href="<?php echo esc_url(admin_url('plugins.php')); ?>" class="button button-primary" style="margin-top: 10px;">
                            <?php echo esc_html__('前往更新', 'tenc-teo'); ?>
                        </a>
                    </p>
                </div>
            <?php else: ?>
                <p style="color: #00a32a;">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php echo esc_html__('您使用的是最新版本', 'tenc-teo'); ?>
                </p>
            <?php endif; ?>
        </div>

        <!-- 帮助信息 -->
        <div class="tenc-teo-card" style="background: #f6f7f7;">
            <h3>
                <span class="dashicons dashicons-sos" style="color: #2271b1;"></span>
                <?php echo esc_html__('需要帮助？', 'tenc-teo'); ?>
            </h3>
            <p>
                <?php echo esc_html__('相关文档：', 'tenc-teo'); ?>
                <a href="https://cloud.tencent.com/document/product/1552" target="_blank"><?php echo esc_html__('EdgeOne 产品文档', 'tenc-teo'); ?></a> | 
                <a href="https://cloud.tencent.com/document/api/1552/70789" target="_blank"><?php echo esc_html__('API 参考', 'tenc-teo'); ?></a> | 
                <a href="https://github.com/<?php echo esc_attr(TENC_TEO_GITHUB_REPO); ?>/issues" target="_blank"><?php echo esc_html__('问题反馈', 'tenc-teo'); ?></a>
            </p>
        </div>
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

/** ========== GitHub 自动更新功能 ========== */
// 检查更新
add_filter('pre_set_site_transient_update_plugins', 'tenc_teo_check_for_update');

function tenc_teo_check_for_update($transient) {
    if (empty($transient->checked)) {
        return $transient;
    }

    $plugin_slug = plugin_basename(TENC_TEO_PLUGIN_FILE);
    $current_version = TENC_TEO_VERSION;

    // 获取 GitHub 最新版本信息
    $remote_info = tenc_teo_get_github_release_info();

    if ($remote_info && version_compare($current_version, $remote_info->version, '<')) {
        $plugin_info = array(
            'slug' => dirname($plugin_slug),
            'plugin' => $plugin_slug,
            'new_version' => $remote_info->version,
            'url' => $remote_info->homepage,
            'package' => $remote_info->download_url,
            'tested' => $remote_info->tested ?? '6.4',
            'requires_php' => '7.4',
        );

        $transient->response[$plugin_slug] = (object) $plugin_info;
    }

    return $transient;
}

// 获取插件信息（用于更新页面）
add_filter('plugins_api', 'tenc_teo_plugin_info', 20, 3);

function tenc_teo_plugin_info($res, $action, $args) {
    if ($action !== 'plugin_information') {
        return $res;
    }

    $plugin_slug = dirname(plugin_basename(TENC_TEO_PLUGIN_FILE));

    if ($args->slug !== $plugin_slug) {
        return $res;
    }

    $remote_info = tenc_teo_get_github_release_info();

    if (!$remote_info) {
        return $res;
    }

    $res = new stdClass();
    $res->name = 'Tencent EdgeOne Cache Manager';
    $res->slug = $plugin_slug;
    $res->version = $remote_info->version;
    $res->tested = $remote_info->tested ?? '6.4';
    $res->requires = '5.0';
    $res->requires_php = '7.4';
    $res->author = '<a href="https://github.com/Iconkop">Shinko</a>';
    $res->homepage = $remote_info->homepage;
    $res->download_link = $remote_info->download_url;
    $res->sections = array(
        'description' => $remote_info->description,
        'changelog' => $remote_info->changelog,
    );

    return $res;
}

// 从 GitHub 获取最新版本信息
function tenc_teo_get_github_release_info() {
    $transient_key = 'tenc_teo_github_release';
    $cached = get_transient($transient_key);

    if ($cached !== false) {
        return $cached;
    }

    $api_url = sprintf('https://api.github.com/repos/%s/releases/latest', TENC_TEO_GITHUB_REPO);
    
    $response = wp_remote_get($api_url, array(
        'timeout' => 10,
        'headers' => array(
            'Accept' => 'application/vnd.github.v3+json',
        ),
    ));

    if (is_wp_error($response)) {
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body);

    if (empty($data) || !isset($data->tag_name)) {
        return false;
    }

    // 查找 teo-cache-purge 的 zip 文件
    $download_url = '';
    if (!empty($data->assets)) {
        foreach ($data->assets as $asset) {
            if (strpos($asset->name, 'teo-cache-purge') !== false && strpos($asset->name, '.zip') !== false) {
                $download_url = $asset->browser_download_url;
                break;
            }
        }
    }

    // 如果没有找到专门的 zip，使用源码 zip
    if (empty($download_url)) {
        $download_url = $data->zipball_url;
    }

    $info = new stdClass();
    $info->version = ltrim($data->tag_name, 'v');
    $info->download_url = $download_url;
    $info->homepage = sprintf('https://github.com/%s', TENC_TEO_GITHUB_REPO);
    $info->description = $data->body ?? '腾讯云 EdgeOne 缓存管理插件';
    $info->changelog = $data->body ?? '';
    $info->tested = '6.4';

    // 缓存 12 小时
    set_transient($transient_key, $info, 12 * HOUR_IN_SECONDS);

    return $info;
}

// 添加查看更新详情链接
add_filter('plugin_row_meta', 'tenc_teo_plugin_row_meta', 10, 2);

function tenc_teo_plugin_row_meta($links, $file) {
    if ($file === plugin_basename(TENC_TEO_PLUGIN_FILE)) {
        $new_links = array(
            'github' => sprintf(
                '<a href="https://github.com/%s" target="_blank">%s</a>',
                TENC_TEO_GITHUB_REPO,
                __('GitHub', 'tenc-teo')
            ),
            'check_update' => sprintf(
                '<a href="%s">%s</a>',
                wp_nonce_url(admin_url('admin-post.php?action=tenc_teo_check_update'), 'tenc_teo_check_update'),
                __('检查更新', 'tenc-teo')
            ),
        );
        $links = array_merge($links, $new_links);
    }
    return $links;
}

// 手动检查更新
add_action('admin_post_tenc_teo_check_update', function() {
    if (!current_user_can('update_plugins')) {
        wp_die(__('权限不足。', 'tenc-teo'));
    }
    check_admin_referer('tenc_teo_check_update');

    // 清除缓存
    delete_transient('tenc_teo_github_release');
    delete_site_transient('update_plugins');

    // 强制检查更新
    wp_update_plugins();

    wp_safe_redirect(admin_url('plugins.php?tenc_teo_update_checked=1'));
    exit;
});

// 显示检查更新的提示
add_action('admin_notices', function() {
    if (isset($_GET['tenc_teo_update_checked'])) {
        echo '<div class="notice notice-success is-dismissible"><p>' . 
             esc_html__('已检查更新。如有新版本将在插件列表中显示。', 'tenc-teo') . 
             '</p></div>';
    }
});
