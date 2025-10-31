# Tencent EdgeOne Cache Manager - 快速开始

## 🚀 5分钟快速上手

### 前置条件
- ✅ WordPress 5.0+
- ✅ PHP 7.4+
- ✅ Composer (用于安装依赖)
- ✅ 腾讯云 EdgeOne 账户

---

## 📥 安装步骤

### 1. 上传插件
将 `teo-cache-purge` 文件夹上传到 `wp-content/plugins/` 目录

### 2. 安装依赖
```bash
cd wp-content/plugins/teo-cache-purge
composer install
# 或者
composer require tencentcloud/teo
```

### 3. 激活插件
在 WordPress 后台 "插件" 页面激活 "Tencent EdgeOne Cache Manager"

---

## ⚙️ 配置步骤

### Step 1: 获取腾讯云 API 凭证

#### 方法一：通过控制台（推荐）
1. 访问 https://console.cloud.tencent.com/cam/capi
2. 点击"新建密钥"
3. 记录 `SecretId` 和 `SecretKey`

#### 方法二：使用子账号（更安全）
1. 创建子账号
2. 授予 EdgeOne 相关权限
3. 生成 API 密钥

### Step 2: 获取 Zone ID
1. 访问 https://console.cloud.tencent.com/edgeone
2. 选择您的站点
3. 在站点概览中找到 `Zone ID`
4. 格式：`zone-xxxxxxxxxxxxxxxx`

### Step 3: 配置插件
1. WordPress 后台 → "设置" → "EdgeOne 缓存"
2. 填写配置：
   ```
   SecretId:     AKID... (从步骤1获取)
   SecretKey:    *****   (从步骤1获取，支持显示/隐藏)
   Zone ID:      zone-...  (从步骤2获取)
   默认主机名:    example.com (可选)
   ```
3. 点击 "保存设置"

### Step 4: 测试连接 ✨
1. 点击 "测试连接" 按钮
2. 看到 ✅ 连接成功提示即可
3. 如果失败，根据错误提示调整配置

---

## ✅ 验证安装

### 检查清单
- [ ] SDK 安装成功（页面显示"已配置"状态）
- [ ] API 配置完整（3个必填项都有绿色✓）
- [ ] 测试连接成功
- [ ] 文章列表出现"清理缓存"选项
- [ ] 全站清理按钮可点击

### 功能测试

#### 测试1: 文章更新自动清理
1. 编辑一篇已发布的文章
2. 点击"更新"
3. 检查是否自动清理缓存

#### 测试2: 手动清理单篇
1. 进入 "文章" → "所有文章"
2. 找到任意已发布文章
3. 悬停显示 "清理缓存" 链接
4. 点击后看到成功提示

#### 测试3: 全站清理
1. 进入 "设置" → "EdgeOne 缓存"
2. 滚动到 "全站缓存清理" 区域
3. 点击按钮并确认
4. 看到成功提示

---

## 🎯 使用场景

### 场景1: 日常文章维护
```
编辑文章 → 点击更新 → 自动清理该文章缓存
```
**无需任何手动操作！**

### 场景2: 发布新文章
```
撰写文章 → 点击发布 → 自动清理整站缓存
```
**确保首页、归档页等立即显示新文章**

### 场景3: 主题更换
```
更换主题 → EdgeOne设置 → 一键清理全站缓存
```
**确保所有页面使用新样式**

### 场景4: 紧急修复
```
发现错误 → 快速修复 → 清理全站缓存
```
**立即生效，无需等待缓存过期**

---

## 🔧 故障排除

### 问题1: Composer 未安装
```bash
# Windows (使用 Chocolatey)
choco install composer

# macOS (使用 Homebrew)
brew install composer

# Linux (Ubuntu/Debian)
sudo apt-get install composer

# 或下载安装
https://getcomposer.org/download/
```

### 问题2: vendor 目录缺失
```bash
cd wp-content/plugins/teo-cache-purge
rm -rf vendor composer.lock
composer install
```

### 问题3: 权限问题
```bash
# Linux/macOS
sudo chmod -R 755 teo-cache-purge
sudo chown -R www-data:www-data teo-cache-purge

# 或者
chmod -R 777 teo-cache-purge  # 不推荐，仅用于测试
```

### 问题4: API 调用失败
检查项：
- [ ] 服务器能否访问外网
- [ ] 防火墙是否拦截腾讯云 API
- [ ] API 密钥是否过期
- [ ] 账户是否欠费

### 问题5: 插件冲突
尝试禁用其他缓存相关插件：
- WP Super Cache
- W3 Total Cache
- Redis Object Cache
- 其他 CDN 插件

---

## 📊 性能优化建议

### 1. 合理使用全站清理
```
❌ 不要：每次更新文章都全站清理
✅ 应该：仅在必要时使用（主题更换、重大更新等）
```

### 2. 利用自动清理
```
✅ 文章更新 → 自动清理 → 无需手动操作
✅ 新文章发布 → 自动清理 → 确保及时更新
```

### 3. 监控缓存命中率
在 EdgeOne 控制台查看：
- 缓存命中率
- 回源请求量
- 带宽使用情况

---

## 🔐 安全建议

### API 密钥管理
1. ✅ 定期轮换密钥
2. ✅ 使用子账号（最小权限原则）
3. ✅ 不要在代码中硬编码
4. ❌ 不要分享给他人
5. ❌ 不要提交到公开仓库

### 权限控制
插件仅允许具有 `manage_options` 权限的用户访问设置页面（通常为管理员）

---

## 📞 获取帮助

### 官方资源
- **EdgeOne 文档**: https://cloud.tencent.com/document/product/1552
- **API 文档**: https://cloud.tencent.com/document/api/1552/70789
- **SDK GitHub**: https://github.com/TencentCloud/tencentcloud-sdk-php

### 社区支持
- **GitHub Issues**: https://github.com/Iconkop/wp_expand/issues
- **WordPress 论坛**: https://wordpress.org/support/

### 商业支持
如需定制开发或技术支持，请联系插件作者。

---

## 🎓 进阶配置

### 自定义清理策略
可以通过 WordPress 钩子自定义行为：

```php
// 自定义文章更新后的清理行为
add_action('transition_post_status', function($new, $old, $post) {
    // 您的自定义逻辑
}, 5, 3);
```

### 集成其他插件
```php
// 清理 WooCommerce 产品缓存
add_action('woocommerce_update_product', function($product_id) {
    if (function_exists('tenc_teo_purge_url')) {
        $url = get_permalink($product_id);
        tenc_teo_purge_url([$url]);
    }
});
```

---

## ✨ 下一步

配置完成后，您可以：
1. 📝 查看 [FEATURES.md](FEATURES.md) 了解详细功能
2. 📋 查看 [CHANGELOG.md](CHANGELOG.md) 了解版本历史
3. 🎯 开始使用插件管理缓存

---

**祝您使用愉快！** 🎉
