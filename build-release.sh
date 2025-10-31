#!/bin/bash

# Tencent EdgeOne Cache Manager - Release 打包脚本
# 使用方法: ./build-release.sh 1.0.4

VERSION=$1

if [ -z "$VERSION" ]; then
    echo "❌ 错误: 请提供版本号"
    echo "使用方法: ./build-release.sh 1.0.4"
    exit 1
fi

echo "🚀 开始打包 Tencent EdgeOne Cache Manager v${VERSION}"
echo ""

# 定义目录
PLUGIN_DIR="teo-cache-purge"
BUILD_DIR="build"
RELEASE_NAME="teo-cache-purge-${VERSION}"
RELEASE_DIR="${BUILD_DIR}/${RELEASE_NAME}"

# 创建构建目录
echo "📁 创建构建目录..."
rm -rf ${BUILD_DIR}
mkdir -p ${RELEASE_DIR}

# 复制必要文件
echo "📋 复制插件文件..."
cp -r ${PLUGIN_DIR}/*.php ${RELEASE_DIR}/
cp -r ${PLUGIN_DIR}/*.md ${RELEASE_DIR}/
cp -r ${PLUGIN_DIR}/composer.json ${RELEASE_DIR}/

# 复制 vendor 目录（如果存在）
if [ -d "${PLUGIN_DIR}/vendor" ]; then
    echo "📦 复制 vendor 目录..."
    cp -r ${PLUGIN_DIR}/vendor ${RELEASE_DIR}/
else
    echo "⚠️  警告: vendor 目录不存在，请确保在安装前运行 composer install"
fi

# 创建 README 文件
echo "📝 创建 README..."
cat > ${RELEASE_DIR}/README.txt << EOF
=== Tencent EdgeOne Cache Manager ===
Contributors: Shinko
Tags: cache, cdn, edgeone, tencent cloud, performance
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: ${VERSION}
License: GPL v2 or later

腾讯云 EdgeOne CDN 缓存管理插件，支持智能缓存清理和自动更新。

== Description ==

专为腾讯云 EdgeOne CDN 设计的缓存管理插件，使用官方 PHP SDK。

= 主要功能 =

* 智能缓存清理策略
* 文章更新自动清理
* 首次发布域名清理
* 全站缓存一键清理
* 连接测试功能
* GitHub 自动更新
* 现代化管理界面
* 响应式设计

== Installation ==

1. 上传插件到 /wp-content/plugins/ 目录
2. 在插件目录执行: composer install
3. 在 WordPress 后台激活插件
4. 进入 设置 > EdgeOne 缓存 配置 API 密钥

== Frequently Asked Questions ==

= 如何获取 API 密钥？ =

访问腾讯云控制台 > 访问管理 > API密钥管理

= 需要安装 Composer 吗？ =

是的，需要安装依赖: composer require tencentcloud/teo

= 支持自动更新吗？ =

支持！插件已集成 GitHub Release 自动更新功能。

== Changelog ==

= ${VERSION} =
* 查看完整更新日志: https://github.com/Iconkop/wp_expand/blob/main/teo-cache-purge/CHANGELOG.md

== Upgrade Notice ==

= ${VERSION} =
修复测试连接跳转问题，新增 GitHub 自动更新功能。

EOF

# 压缩打包
echo "🗜️  压缩打包..."
cd ${BUILD_DIR}
zip -r ${RELEASE_NAME}.zip ${RELEASE_NAME} -q

# 移动到当前目录
mv ${RELEASE_NAME}.zip ../
cd ..

# 清理构建目录
echo "🧹 清理临时文件..."
rm -rf ${BUILD_DIR}

# 计算文件大小和哈希
FILESIZE=$(du -h ${RELEASE_NAME}.zip | cut -f1)
MD5HASH=$(md5sum ${RELEASE_NAME}.zip | cut -d' ' -f1)
SHA256HASH=$(sha256sum ${RELEASE_NAME}.zip | cut -d' ' -f1)

echo ""
echo "✅ 打包完成！"
echo ""
echo "📦 文件信息:"
echo "   文件名: ${RELEASE_NAME}.zip"
echo "   大小: ${FILESIZE}"
echo "   MD5: ${MD5HASH}"
echo "   SHA256: ${SHA256HASH}"
echo ""
echo "📋 发布检查清单:"
echo "   [ ] 更新插件版本号"
echo "   [ ] 更新 CHANGELOG.md"
echo "   [ ] 提交并推送代码"
echo "   [ ] 创建 Git Tag: git tag -a v${VERSION} -m 'Release v${VERSION}'"
echo "   [ ] 推送 Tag: git push origin v${VERSION}"
echo "   [ ] 在 GitHub 创建 Release"
echo "   [ ] 上传 ${RELEASE_NAME}.zip 作为 Release Asset"
echo "   [ ] 填写 Release 描述（使用 CHANGELOG.md）"
echo ""
echo "🚀 GitHub Release 命令:"
echo "   gh release create v${VERSION} ${RELEASE_NAME}.zip --title 'v${VERSION}' --notes-file teo-cache-purge/CHANGELOG.md"
echo ""
