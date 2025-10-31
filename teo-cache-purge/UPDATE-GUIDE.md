# GitHub 自动更新功能说明

## 功能概述

Tencent EdgeOne Cache Manager 现已集成 GitHub Release 自动更新功能，无需手动下载和安装，直接在 WordPress 后台即可更新到最新版本。

---

## 🎯 工作原理

### 1. 自动检测
- 插件每次访问插件列表页面时，自动检查 GitHub Release 最新版本
- API 响应缓存 12 小时，避免频繁请求
- 版本比较采用语义化版本规则

### 2. 更新提示
当检测到新版本时，会在以下位置显示：
- ✅ WordPress 插件列表页面（红色更新提示）
- ✅ 插件设置页面（绿色提示框）
- ✅ WordPress 仪表盘更新计数

### 3. 一键更新
- 点击"立即更新"按钮
- WordPress 自动从 GitHub 下载最新版本
- 自动安装并激活新版本

---

## 📋 使用方式

### 方式一：自动检测（推荐）
1. 进入 WordPress 后台 → "插件" 页面
2. 系统会自动检测更新
3. 如有新版本，会显示更新提示
4. 点击"立即更新"即可

### 方式二：手动检查
1. 进入 WordPress 后台 → "插件" 页面
2. 找到 "Tencent EdgeOne Cache Manager"
3. 点击插件描述下方的 "检查更新" 链接
4. 系统会清除缓存并重新检查更新

### 方式三：设置页面查看
1. 进入 "设置" → "EdgeOne 缓存"
2. 滚动到页面底部的 "插件信息" 卡片
3. 查看当前版本和可用更新
4. 如有新版本，点击 "前往更新" 按钮

---

## 🔧 技术细节

### GitHub API 集成
```
GitHub API: https://api.github.com/repos/Iconkop/wp_expand/releases/latest
缓存时间: 12 小时
请求头: Accept: application/vnd.github.v3+json
```

### 版本号格式
- GitHub Tag: `v1.0.4` 或 `1.0.4`
- 插件版本: `1.0.4`
- 自动去除 `v` 前缀进行比较

### 下载优先级
1. **优先**: Release Assets 中包含 `teo-cache-purge*.zip` 的文件
2. **备选**: GitHub 源码 zipball

### WordPress 过滤器
```php
// 检查更新
add_filter('pre_set_site_transient_update_plugins', 'tenc_teo_check_for_update');

// 插件信息
add_filter('plugins_api', 'tenc_teo_plugin_info', 20, 3);

// 插件行链接
add_filter('plugin_row_meta', 'tenc_teo_plugin_row_meta', 10, 2);
```

---

## 📦 Release 资源准备

### 对于开发者

#### 1. 创建 Release Zip（推荐）
为了更好的更新体验，建议在 GitHub Release 中上传插件的 zip 包：

```bash
# 打包插件
cd wp-content/plugins
zip -r teo-cache-purge-1.0.4.zip teo-cache-purge/ \
  -x "*.git*" "*.DS_Store" "node_modules/*" "*.backup"

# 上传到 GitHub Release 作为 Asset
```

#### 2. 版本号规范
- Tag 名称: `v1.0.4` 或 `1.0.4`
- Release 标题: `Tencent EdgeOne Cache Manager v1.0.4`
- 描述: 详细的更新日志

#### 3. Release 描述模板
```markdown
## 🎉 新增功能
- 功能描述...

## 🐛 Bug 修复
- 修复内容...

## 🔧 功能改进
- 改进内容...

## 📚 文档更新
- 文档更新...
```

---

## 🔒 安全性

### API 请求安全
- ✅ 使用 WordPress HTTP API (`wp_remote_get`)
- ✅ 设置 10 秒超时
- ✅ 仅请求公开 API，无需认证
- ✅ 错误处理和异常捕获

### 更新包验证
- ✅ WordPress 自动验证 zip 包完整性
- ✅ 仅管理员可以执行更新操作
- ✅ Nonce 验证防止 CSRF 攻击

### 缓存机制
- ✅ 使用 WordPress Transient API
- ✅ 12 小时自动过期
- ✅ 手动检查时清除缓存

---

## ❓ 常见问题

### Q1: 更新检查频率是多少？
**A**: 
- 自动检查：每次访问插件页面时检查（有 12 小时缓存）
- 手动检查：点击"检查更新"立即检查
- 后台更新：WordPress 定时任务（通常 12 小时）

### Q2: 如果 GitHub API 请求失败怎么办？
**A**: 
- 插件会继续正常工作
- 不会显示更新提示
- 可以手动下载更新

### Q3: 更新会丢失配置吗？
**A**: 
- 不会！配置保存在 WordPress 数据库中
- `vendor/` 目录会保留（如果存在）
- 更新只替换插件文件

### Q4: 可以回退到旧版本吗？
**A**: 
可以，有两种方式：
1. 从 GitHub Release 下载旧版本手动安装
2. 使用 WordPress 插件回滚功能（需要备份插件）

### Q5: 更新失败怎么办？
**A**: 
1. 检查网络连接
2. 确认服务器可以访问 GitHub
3. 查看 WordPress 错误日志
4. 手动下载并安装

### Q6: 如何暂时禁用自动更新检查？
**A**: 
在 `wp-config.php` 中添加：
```php
define('TENC_TEO_DISABLE_UPDATE_CHECK', true);
```

---

## 🎓 高级用法

### 自定义更新源
如果需要使用私有仓库或镜像，可以修改：

```php
// 在 functions.php 或自定义插件中
add_filter('tenc_teo_github_api_url', function($url) {
    return 'https://your-mirror.com/api/repos/owner/repo/releases/latest';
});
```

### 修改缓存时间
```php
add_filter('tenc_teo_update_cache_time', function($time) {
    return 6 * HOUR_IN_SECONDS; // 改为 6 小时
});
```

### 自定义下载链接处理
```php
add_filter('tenc_teo_download_url', function($url, $release_data) {
    // 自定义逻辑
    return $url;
}, 10, 2);
```

---

## 📊 更新统计

更新功能会记录以下信息（可选）：
- 更新检查次数
- 更新成功/失败次数
- 最后检查时间
- 当前版本

可以在设置页面查看这些统计信息。

---

## 🚀 未来计划

- [ ] 支持 Beta 版本订阅
- [ ] 更新前自动备份
- [ ] 更新日志预览
- [ ] 一键回滚功能
- [ ] 更新通知邮件
- [ ] 更新统计图表

---

## 📞 技术支持

如遇到更新相关问题：
1. 查看 [GitHub Issues](https://github.com/Iconkop/wp_expand/issues)
2. 提交新的 Issue 并附上错误信息
3. 查看 WordPress 错误日志

---

**版本**: 1.0.4  
**更新日期**: 2025-10-31
