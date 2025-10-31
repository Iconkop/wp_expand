<?php
/**
 * Plugin Name: sitemap
 * Plugin URI: https://xcbgn.cn
 * Description: 生成符合Google、Bing、百度标准的文章站点地图
 * Version: 1.0.0
 * Author: Shinko
 * License: GPL v2 or later
 * Text Domain: standard-sitemap
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

class Standard_Sitemap_Generator {
    
    public function __construct() {
        // 添加重写规则
        add_action('init', array($this, 'add_rewrite_rules'));
        add_filter('query_vars', array($this, 'add_query_vars'));
        add_action('template_redirect', array($this, 'handle_sitemap_request'));
        
        // 激活和停用钩子
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // 添加管理菜单
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // 当内容更新时清除缓存
        add_action('save_post', array($this, 'clear_cache'));
        add_action('deleted_post', array($this, 'clear_cache'));
    }
    
    /**
     * 添加重写规则
     */
    public function add_rewrite_rules() {
        add_rewrite_rule('^sitemap\.xml$', 'index.php?custom_sitemap=1', 'top');
    }
    
    /**
     * 添加查询变量
     */
    public function add_query_vars($vars) {
        $vars[] = 'custom_sitemap';
        return $vars;
    }
    
    /**
     * 处理站点地图请求
     */
    public function handle_sitemap_request() {
        $is_sitemap = get_query_var('custom_sitemap');
        
        if (!$is_sitemap) {
            return;
        }
        
        // 设置正确的HTTP头
        status_header(200);
        header('Content-Type: application/xml; charset=utf-8');
        header('X-Robots-Tag: noindex, follow', true);
        
        // 生成并输出站点地图
        echo $this->generate_sitemap();
        exit;
    }
    
    /**
     * 生成完整站点地图
     */
    private function generate_sitemap() {
        // 尝试从缓存获取
        $cached = get_transient('standard_sitemap_cache');
        if ($cached !== false) {
            return $cached;
        }
        
        $output = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $output .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        
        // 获取所有已发布的文章
        $posts = get_posts(array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'modified',
            'order' => 'DESC',
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false
        ));
        
        foreach ($posts as $post) {
            $output .= $this->generate_url_entry(
                get_permalink($post->ID),
                $post->post_modified_gmt,
                'weekly',
                '0.8'
            );
        }
        
        $output .= '</urlset>';
        
        // 缓存1小时
        set_transient('standard_sitemap_cache', $output, HOUR_IN_SECONDS);
        
        return $output;
    }
    
    /**
     * 生成单个URL条目
     */
    private function generate_url_entry($url, $lastmod = null, $changefreq = 'weekly', $priority = '0.5') {
        $entry = "\t<url>\n";
        $entry .= "\t\t<loc>" . esc_url($url) . "</loc>\n";
        
        if ($lastmod) {
            // 确保时间格式正确
            if (strtotime($lastmod)) {
                $entry .= "\t\t<lastmod>" . mysql2date('Y-m-d\TH:i:s+00:00', $lastmod, false) . "</lastmod>\n";
            }
        }
        
        $entry .= "\t\t<changefreq>" . esc_xml($changefreq) . "</changefreq>\n";
        $entry .= "\t\t<priority>" . esc_xml($priority) . "</priority>\n";
        $entry .= "\t</url>\n";
        
        return $entry;
    }
    
    /**
     * 清除缓存
     */
    public function clear_cache() {
        delete_transient('standard_sitemap_cache');
    }
    
    /**
     * 添加管理菜单
     */
    public function add_admin_menu() {
        add_options_page(
            '站点地图设置',
            '站点地图',
            'manage_options',
            'standard-sitemap',
            array($this, 'admin_page')
        );
    }
    
    /**
     * 管理页面
     */
    public function admin_page() {
        // 处理手动刷新
        if (isset($_POST['clear_cache']) && check_admin_referer('sitemap_clear_cache')) {
            $this->clear_cache();
            echo '<div class="notice notice-success"><p>站点地图缓存已清除！</p></div>';
        }
        
        $sitemap_url = home_url('/sitemap.xml');
        $posts_count = wp_count_posts('post')->publish;
        $total_urls = $posts_count;
        ?>
        <div class="wrap">
            <h1>📍 标准站点地图生成器</h1>
            
            <div class="card">
                <h2>站点地图地址</h2>
                <p style="font-size: 16px;">
                    <strong>您的站点地图URL：</strong><br>
                    <a href="<?php echo esc_url($sitemap_url); ?>" target="_blank" style="color: #2271b1; font-size: 18px;">
                        <?php echo esc_html($sitemap_url); ?>
                    </a>
                    <button type="button" class="button" onclick="navigator.clipboard.writeText('<?php echo esc_js($sitemap_url); ?>'); alert('已复制到剪贴板！');">📋 复制链接</button>
                </p>
                <p style="color: #666;">
                    <a href="<?php echo esc_url($sitemap_url); ?>" target="_blank" class="button button-primary">查看站点地图</a>
                </p>
            </div>
            
            <div class="card">
                <h2>📊 站点地图统计</h2>
                <table class="widefat" style="max-width: 600px;">
                    <tbody>
                        <tr>
                            <td><strong>文章 (Posts)</strong></td>
                            <td><?php echo $posts_count; ?> 个</td>
                        </tr>
                        <tr class="alternate" style="background: #f0f0f1;">
                            <td><strong>总URL数量</strong></td>
                            <td><strong><?php echo $total_urls; ?> 个</strong></td>
                        </tr>
                    </tbody>
                </table>
                <p style="color: #666; margin-top: 15px;">
                    <em>注意：此站点地图仅包含文章页面</em>
                </p>
            </div>
            
            <div class="card">
                <h2>🔄 缓存管理</h2>
                <p>站点地图会自动缓存1小时，以提高性能。如果您刚刚发布了新内容，可以手动刷新缓存。</p>
                <form method="post">
                    <?php wp_nonce_field('sitemap_clear_cache'); ?>
                    <button type="submit" name="clear_cache" class="button button-secondary">清除缓存并重新生成</button>
                </form>
            </div>
            
            <div class="card">
                <h2>🌐 提交到搜索引擎</h2>
                <p>请将站点地图URL提交到以下搜索引擎平台：</p>
                <ol style="line-height: 2;">
                    <li>
                        <strong>Google Search Console</strong><br>
                        <a href="https://search.google.com/search-console" target="_blank" class="button">访问 Google Search Console</a><br>
                        <small>在"站点地图"部分添加：sitemap.xml</small>
                    </li>
                    <li>
                        <strong>Bing Webmaster Tools</strong><br>
                        <a href="https://www.bing.com/webmasters" target="_blank" class="button">访问 Bing Webmaster</a><br>
                        <small>在"站点地图"菜单提交您的站点地图URL</small>
                    </li>
                    <li>
                        <strong>百度站长平台</strong><br>
                        <a href="https://ziyuan.baidu.com/" target="_blank" class="button">访问百度站长平台</a><br>
                        <small>在"数据引入 > 链接提交"中提交站点地图</small>
                    </li>
                </ol>
            </div>
            
            <div class="card">
                <h2>✅ 检查站点地图</h2>
                <p>使用以下工具验证您的站点地图是否正确：</p>
                <ul>
                    <li><a href="https://www.xml-sitemaps.com/validate-xml-sitemap.html" target="_blank">XML Sitemap Validator</a></li>
                    <li><a href="https://support.google.com/webmasters/answer/7451001" target="_blank">Google 站点地图指南</a></li>
                </ul>
            </div>
        </div>
        
        <style>
            .card { 
                background: #fff; 
                padding: 20px; 
                margin: 20px 0; 
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                border-left: 4px solid #2271b1;
            }
            .card h2 { 
                margin-top: 0; 
                color: #1d2327;
                font-size: 20px;
            }
            .card ul, .card ol { 
                padding-left: 20px; 
            }
            .card li { 
                margin: 15px 0; 
            }
            .card small {
                color: #666;
                display: block;
                margin-top: 5px;
            }
            .button {
                margin: 5px 5px 5px 0;
            }
        </style>
        <?php
    }
    
    /**
     * 激活插件
     */
    public function activate() {
        $this->add_rewrite_rules();
        flush_rewrite_rules();
        $this->clear_cache();
    }
    
    /**
     * 停用插件
     */
    public function deactivate() {
        flush_rewrite_rules();
        $this->clear_cache();
    }
}

// 初始化插件
new Standard_Sitemap_Generator();