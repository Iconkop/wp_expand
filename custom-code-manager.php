<?php
/**
 * Plugin Name: 自定义代码管理器
 * Plugin URI: https://example.com
 * Description: 自定义HTML头部、底部、CSS和JS代码的WordPress插件
 * Version: 1.0.3
 * Author: Shinko
 * Text Domain: custom-code-manager
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

class Custom_Code_Manager {
    
    private $option_name = 'ccm_settings';
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_head', array($this, 'output_header_code'), 999);
        add_action('wp_footer', array($this, 'output_footer_code'), 999);
    }
    
    // 添加管理菜单
    public function add_admin_menu() {
        add_menu_page(
            __('自定义代码', 'custom-code-manager'),
            __('自定义代码', 'custom-code-manager'),
            'manage_options',
            'custom-code-manager',
            array($this, 'admin_page'),
            'dashicons-editor-code',
            80
        );
    }
    
    // 加载CodeMirror编辑器
    public function enqueue_admin_scripts($hook) {
        if ('toplevel_page_custom-code-manager' !== $hook) {
            return;
        }
        
        // 加载WordPress内置的CodeMirror
        wp_enqueue_code_editor(array('type' => 'text/html'));
        wp_enqueue_script('wp-theme-plugin-editor');
        wp_enqueue_style('wp-codemirror');
    }
    
    // 注册设置
    public function register_settings() {
        register_setting(
            'ccm_settings_group',
            $this->option_name,
            array(
                'sanitize_callback' => array($this, 'sanitize_settings'),
                'default' => array(
                    'header_code' => '',
                    'footer_code' => '',
                    'custom_css' => '',
                    'custom_js' => ''
                )
            )
        );
    }
    
    // 增强的输入清理和验证
    public function sanitize_settings($input) {
        // 检查当前用户权限
        if (!current_user_can('manage_options')) {
            add_settings_error(
                $this->option_name,
                'permission_denied',
                __('您没有权限修改这些设置。', 'custom-code-manager'),
                'error'
            );
            return get_option($this->option_name);
        }
        
        // 验证 nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'ccm_settings_group-options')) {
            add_settings_error(
                $this->option_name,
                'nonce_failed',
                __('安全验证失败，请重试。', 'custom-code-manager'),
                'error'
            );
            return get_option($this->option_name);
        }
        
        $sanitized = array();
        
        // 清理 HTML 头部代码
        if (isset($input['header_code'])) {
            $sanitized['header_code'] = $this->sanitize_code($input['header_code'], 'html');
        }
        
        // 清理 HTML 底部代码
        if (isset($input['footer_code'])) {
            $sanitized['footer_code'] = $this->sanitize_code($input['footer_code'], 'html');
        }
        
        // 清理 CSS 代码
        if (isset($input['custom_css'])) {
            $sanitized['custom_css'] = $this->sanitize_code($input['custom_css'], 'css');
        }
        
        // 清理 JavaScript 代码
        if (isset($input['custom_js'])) {
            $sanitized['custom_js'] = $this->sanitize_code($input['custom_js'], 'js');
        }
        
        // 记录更改日志
        $this->log_settings_change($sanitized);
        
        add_settings_error(
            $this->option_name,
            'settings_updated',
            __('设置已成功保存。', 'custom-code-manager'),
            'success'
        );
        
        return $sanitized;
    }
    
    // 代码清理函数
    private function sanitize_code($code, $type) {
        if (empty($code)) {
            return '';
        }
        
        // 移除 UTF-8 BOM 和零宽字符
        $code = preg_replace('/\x{FEFF}/u', '', $code);
        $code = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $code);
        
        // 根据类型进行特定清理
        switch ($type) {
            case 'css':
                // CSS 特定验证
                $code = wp_strip_all_tags($code, true);
                // 检测可疑的 CSS 注入
                if (preg_match('/expression\s*\(|javascript\s*:/i', $code)) {
                    $code = '';
                    add_settings_error(
                        $this->option_name,
                        'css_injection_detected',
                        __('检测到可疑的CSS代码，已被阻止。', 'custom-code-manager'),
                        'error'
                    );
                }
                break;
                
            case 'js':
                // JavaScript 特定验证
                $code = wp_strip_all_tags($code, true);
                // 检测明显的恶意代码模式
                if ($this->contains_malicious_patterns($code)) {
                    $code = '';
                    add_settings_error(
                        $this->option_name,
                        'js_malicious_detected',
                        __('检测到可疑的JavaScript代码，已被阻止。', 'custom-code-manager'),
                        'error'
                    );
                }
                break;
                
            case 'html':
                // HTML 代码保留允许的标签
                $allowed_tags = wp_kses_allowed_html('post');
                // 添加额外允许的标签
                $allowed_tags['script'] = array(
                    'type' => true,
                    'src' => true,
                    'async' => true,
                    'defer' => true,
                );
                $allowed_tags['link'] = array(
                    'rel' => true,
                    'href' => true,
                    'type' => true,
                );
                $allowed_tags['meta'] = array(
                    'name' => true,
                    'content' => true,
                    'property' => true,
                );
                $code = wp_kses($code, $allowed_tags);
                break;
        }
        
        return $code;
    }
    
    // 检测恶意代码模式
    private function contains_malicious_patterns($code) {
        $malicious_patterns = array(
            '/eval\s*\(/i',
            '/base64_decode/i',
            '/gzinflate/i',
            '/str_rot13/i',
            '/exec\s*\(/i',
            '/shell_exec/i',
            '/system\s*\(/i',
            '/passthru/i',
            '/document\.cookie/i',
        );
        
        foreach ($malicious_patterns as $pattern) {
            if (preg_match($pattern, $code)) {
                return true;
            }
        }
        
        return false;
    }
    
    // 记录设置更改
    private function log_settings_change($new_settings) {
        $user = wp_get_current_user();
        $log_entry = sprintf(
            '[%s] 用户 %s (ID: %d) 更新了自定义代码设置',
            current_time('mysql'),
            $user->user_login,
            $user->ID
        );
        error_log($log_entry);
    }
    
    // 输出头部代码（带安全检查）
    public function output_header_code() {
        if (!$this->can_output_code()) {
            return;
        }
        
        $options = get_option($this->option_name);
        
        // 输出自定义CSS
        if (!empty($options['custom_css'])) {
            echo "\n<style type=\"text/css\" id=\"ccm-custom-css\">\n";
            echo wp_strip_all_tags($options['custom_css'], true);
            echo "\n</style>\n";
        }
        
        // 输出头部HTML
        if (!empty($options['header_code'])) {
            echo "\n<!-- Custom Code Manager: Header Code -->\n";
            echo wp_kses_post($options['header_code']);
            echo "\n<!-- /Custom Code Manager: Header Code -->\n";
        }
    }
    
    // 输出底部代码（带安全检查）
    public function output_footer_code() {
        if (!$this->can_output_code()) {
            return;
        }
        
        $options = get_option($this->option_name);
        
        // 输出自定义JS
        if (!empty($options['custom_js'])) {
            echo "\n<script type=\"text/javascript\" id=\"ccm-custom-js\">\n";
            echo "/* Custom Code Manager: Custom JavaScript */\n";
            echo wp_strip_all_tags($options['custom_js'], true);
            echo "\n</script>\n";
        }
        
        // 输出底部HTML
        if (!empty($options['footer_code'])) {
            echo "\n<!-- Custom Code Manager: Footer Code -->\n";
            echo wp_kses_post($options['footer_code']);
            echo "\n<!-- /Custom Code Manager: Footer Code -->\n";
        }
    }
    
    // 检查是否可以输出代码
    private function can_output_code() {
        // 在管理后台不输出
        if (is_admin()) {
            return false;
        }
        
        // 在登录页面不输出
        if (in_array($GLOBALS['pagenow'], array('wp-login.php', 'wp-register.php'))) {
            return false;
        }
        
        return true;
    }
    
    // 管理页面
    public function admin_page() {
        // 权限检查
        if (!current_user_can('manage_options')) {
            wp_die(__('您没有足够的权限访问此页面。', 'custom-code-manager'));
        }
        
        $options = get_option($this->option_name);
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="notice notice-warning">
                <p><strong><?php _e('安全提示：', 'custom-code-manager'); ?></strong> 
                <?php _e('此插件允许插入自定义代码到您的网站。请确保您信任要添加的代码来源，恶意代码可能会危害您的网站安全。', 'custom-code-manager'); ?>
                </p>
            </div>
            
            <?php settings_errors($this->option_name); ?>
            
            <form method="post" action="options.php">
                <?php 
                settings_fields('ccm_settings_group');
                wp_nonce_field('ccm_settings_group-options');
                ?>
                
                <div class="ccm-container">
                    <!-- HTML 头部代码 -->
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2 class="hndle"><?php _e('HTML 头部代码', 'custom-code-manager'); ?></h2>
                        </div>
                        <div class="inside">
                            <p class="description">
                                <?php _e('此代码将被插入到 &lt;head&gt; 标签中。适合添加 meta 标签、预加载资源等。', 'custom-code-manager'); ?>
                            </p>
                            <textarea 
                                id="ccm_header_code"
                                name="<?php echo esc_attr($this->option_name); ?>[header_code]" 
                                rows="10" 
                                class="large-text code ccm-editor"
                                data-mode="htmlmixed"
                            ><?php echo isset($options['header_code']) ? esc_textarea($options['header_code']) : ''; ?></textarea>
                        </div>
                    </div>
                    
                    <!-- HTML 底部代码 -->
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2 class="hndle"><?php _e('HTML 底部代码', 'custom-code-manager'); ?></h2>
                        </div>
                        <div class="inside">
                            <p class="description">
                                <?php _e('此代码将被插入到 &lt;/body&gt; 标签之前。适合添加统计代码、聊天插件等。', 'custom-code-manager'); ?>
                            </p>
                            <textarea 
                                id="ccm_footer_code"
                                name="<?php echo esc_attr($this->option_name); ?>[footer_code]" 
                                rows="10" 
                                class="large-text code ccm-editor"
                                data-mode="htmlmixed"
                            ><?php echo isset($options['footer_code']) ? esc_textarea($options['footer_code']) : ''; ?></textarea>
                        </div>
                    </div>
                    
                    <!-- 自定义 CSS -->
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2 class="hndle"><?php _e('自定义 CSS', 'custom-code-manager'); ?></h2>
                        </div>
                        <div class="inside">
                            <p class="description">
                                <?php _e('添加自定义 CSS 样式。无需添加 &lt;style&gt; 标签。', 'custom-code-manager'); ?>
                            </p>
                            <textarea 
                                id="ccm_custom_css"
                                name="<?php echo esc_attr($this->option_name); ?>[custom_css]" 
                                rows="15" 
                                class="large-text code ccm-editor"
                                data-mode="css"
                            ><?php echo isset($options['custom_css']) ? esc_textarea($options['custom_css']) : ''; ?></textarea>
                        </div>
                    </div>
                    
                    <!-- 自定义 JavaScript -->
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2 class="hndle"><?php _e('自定义 JavaScript', 'custom-code-manager'); ?></h2>
                        </div>
                        <div class="inside">
                            <p class="description">
                                <?php _e('添加自定义 JavaScript 代码。无需添加 &lt;script&gt; 标签。', 'custom-code-manager'); ?>
                            </p>
                            <textarea 
                                id="ccm_custom_js"
                                name="<?php echo esc_attr($this->option_name); ?>[custom_js]" 
                                rows="15" 
                                class="large-text code ccm-editor"
                                data-mode="javascript"
                            ><?php echo isset($options['custom_js']) ? esc_textarea($options['custom_js']) : ''; ?></textarea>
                        </div>
                    </div>
                </div>
                
                <?php submit_button(__('保存所有更改', 'custom-code-manager'), 'primary large'); ?>
            </form>
        </div>
        
        <style>
            .ccm-container {
                max-width: 1200px;
            }
            
            .postbox {
                margin-bottom: 20px;
            }
            
            .postbox .inside {
                padding: 12px;
            }
            
            .postbox textarea {
                font-family: Consolas, Monaco, monospace;
                font-size: 13px;
                line-height: 1.5;
            }
            
            .postbox .description {
                margin-bottom: 10px;
                color: #646970;
            }
            
            .CodeMirror {
                border: 1px solid #ddd;
                border-radius: 3px;
                min-height: 200px;
            }
            
            /* 移除选中行的蓝色高亮 */
            .CodeMirror-activeline-background {
                background: transparent !important;
            }
            
            @media screen and (max-width: 782px) {
                .postbox textarea {
                    font-size: 14px;
                }
            }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // 初始化所有代码编辑器
            $('.ccm-editor').each(function() {
                var $textarea = $(this);
                var mode = $textarea.data('mode');
                
                // CodeMirror设置
                var editorSettings = wp.codeEditor.defaultSettings ? _.clone(wp.codeEditor.defaultSettings) : {};
                editorSettings.codemirror = _.extend(
                    {},
                    editorSettings.codemirror,
                    {
                        mode: mode,
                        lineNumbers: true,
                        lineWrapping: true,
                        indentUnit: 2,
                        tabSize: 2,
                        indentWithTabs: false,
                        autoCloseBrackets: true,
                        matchBrackets: true,
                        lint: false,
                        gutters: ['CodeMirror-lint-markers'],
                        theme: 'default',
                        styleActiveLine: false
                    }
                );
                
                // 初始化CodeMirror
                var editor = wp.codeEditor.initialize($textarea.attr('id'), editorSettings);
                
                // 确保在提交表单前同步内容
                $textarea.closest('form').on('submit', function() {
                    if (editor && editor.codemirror) {
                        editor.codemirror.save();
                    }
                });
            });
        });
        </script>
        <?php
    }
}

// 初始化插件
new Custom_Code_Manager();