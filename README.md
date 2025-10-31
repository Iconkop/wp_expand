# WP Expand - WordPress 扩展插件集合

这是一个包含多个实用 WordPress 插件的集合，旨在增强 WordPress 网站的功能和性能。

## 📦 包含的插件

### 1. Custom Code Manager (自定义代码管理器)

一个强大的 WordPress 插件，用于管理和插入自定义代码到网站的不同位置。

#### 功能特点
- ✨ 在网站头部（`<head>`）插入自定义 HTML、CSS 和 JavaScript
- ✨ 在网站底部（`<footer>`）插入自定义代码
- ✨ 内置 CodeMirror 代码编辑器，支持语法高亮
- ✨ 简洁直观的管理界面
- ✨ 支持添加统计代码、自定义样式等

#### 使用场景
- 添加 Google Analytics、百度统计等第三方统计代码
- 插入自定义 CSS 样式
- 添加自定义 JavaScript 功能
- 集成第三方服务（如在线客服、广告代码等）

---

### 2. Smart Sitemap (智能站点地图)

符合国际标准的 XML 站点地图生成器，支持 Google、Bing 和百度搜索引擎。

#### 功能特点
- 🗺️ 自动生成符合标准的 `sitemap.xml`
- 🗺️ 支持 Google、Bing、百度搜索引擎标准
- 🗺️ 智能缓存机制，提高性能
- 🗺️ 内容更新时自动清除缓存
- 🗺️ 简单易用，无需复杂配置

#### 访问方式
安装并激活插件后，访问：`https://yourdomain.com/sitemap.xml`

#### SEO 优化
该插件可以帮助搜索引擎更好地索引您的网站内容，提高网站在搜索结果中的可见性。

---

### 3. Tencent EdgeOne Cache Manager (腾讯 EdgeOne 缓存管理器)
**版本:** 1.0.3  
**作者:** RV

专为腾讯云 EdgeOne CDN 设计的缓存管理插件，使用官方 PHP SDK。

#### 功能特点
- 🚀 **智能缓存清理策略**
  - **单篇文章更新**: 使用 `purge_url` 精准清理
  - **首次发布**: 使用 `purge_host` (invalidate) 清理整个域名
  - **全站刷新**: 使用 `purge_all` (invalidate) 清理所有缓存
- 🚀 **连接测试功能**: 一键测试 SDK 和 API 配置是否正确
- 🚀 **可视化状态显示**: 实时显示插件配置状态（已配置/待配置/SDK未安装）
- 🚀 **密钥保护**: SecretKey 密码输入框，支持显示/隐藏切换
- 🚀 **美观UI界面**: 卡片式布局，响应式设计，完美适配移动端
- 🚀 **集成腾讯云官方 PHP SDK**
- 🚀 **自动化缓存管理**: 无需手动操作
- 🚀 **文章列表快捷操作**: 支持在文章列表直接清理单篇缓存

#### 安装要求
```bash
cd teo-cache-purge
composer require tencentcloud/teo
```

#### 配置参数
- **Secret ID**: 腾讯云 API 密钥 ID
- **Secret Key**: 腾讯云 API 密钥 Key (支持显示/隐藏)
- **Zone ID**: EdgeOne 站点 ID
- **Default Host**: 默认域名

#### 新功能亮点 (v1.0.3)
- ✅ **测试连接**: 在配置页面点击"测试连接"按钮，实时验证 API 配置
- ✅ **配置向导**: 提供完整的配置说明和腾讯云控制台链接
- ✅ **策略说明**: 可视化展示四种缓存清理策略的使用场景
- ✅ **安全警告**: 全站清理功能增加二次确认和详细警告
- ✅ **响应式设计**: 完美支持桌面端和移动端访问

## 🛠️ 安装说明

### 方式一：手动安装
1. 下载本仓库或单个插件文件夹
2. 将插件文件夹上传到 WordPress 的 `wp-content/plugins/` 目录
3. 在 WordPress 后台 "插件" 页面激活相应插件

### 方式二：通过 Git 克隆
```bash
cd wp-content/plugins/
git clone https://github.com/Iconkop/wp_expand.git
```

## 📋 系统要求

- WordPress 5.0 或更高版本
- PHP 7.4 或更高版本
- 对于 Tencent EdgeOne Cache Manager:
  - Composer (用于安装依赖)
  - 腾讯云 EdgeOne 账户和 API 密钥

## 🔧 配置指南

### Custom Code Manager
1. 激活插件后，在 WordPress 后台左侧菜单找到 "自定义代码"
2. 在相应的编辑器中输入自定义代码
3. 点击保存即可生效

### Smart Sitemap
1. 激活插件即可自动生成站点地图
2. 访问 `https://yourdomain.com/sitemap.xml` 查看
3. 可在搜索引擎站长工具中提交此 URL

### Tencent EdgeOne Cache Manager
1. 在插件目录执行 `composer install` 安装依赖
2. 在 WordPress 后台找到插件设置页面
3. 填入腾讯云 API 凭证和 Zone ID
4. 保存配置后插件将自动工作

## 📝 依赖包说明

本项目中的 `teo-cache-purge` 插件使用了以下主要依赖：

- **GuzzleHTTP/Guzzle**: HTTP 客户端库
- **TencentCloud SDK**: 腾讯云官方 PHP SDK
  - `tencentcloud/common`: 腾讯云公共组件
  - `tencentcloud/teo`: EdgeOne 服务 SDK

## 🤝 贡献

欢迎提交 Issue 和 Pull Request！

## 📄 许可证

本项目采用 GPL v2 或更高版本许可证。

## 👤 作者

- **Custom Code Manager & Smart Sitemap**: Shinko
- **Tencent EdgeOne Cache Manager**: RV
- **仓库维护**: Iconkop

## 🔗 相关链接

- [WordPress 官方文档](https://wordpress.org/documentation/)
- [腾讯云 EdgeOne](https://cloud.tencent.com/product/teo)

## ⚠️ 注意事项

1. 使用 Custom Code Manager 时，请确保插入的代码安全可靠
2. Smart Sitemap 会自动缓存站点地图，内容更新时自动刷新
3. Tencent EdgeOne Cache Manager 需要正确配置 API 密钥才能正常工作
4. 建议在测试环境中先测试插件功能，确认无误后再部署到生产环境

## 📮 问题反馈

如有问题或建议，请在 GitHub Issues 中提出。

---

**最后更新日期**: 2025年10月31日
