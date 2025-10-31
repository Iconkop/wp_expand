# Tencent EdgeOne Cache Manager - Release 打包脚本 (PowerShell)
# 使用方法: .\build-release.ps1 -Version "1.0.4"

param(
    [Parameter(Mandatory=$true)]
    [string]$Version
)

Write-Host "🚀 开始打包 Tencent EdgeOne Cache Manager v$Version" -ForegroundColor Green
Write-Host ""

# 定义目录
$PluginDir = "teo-cache-purge"
$BuildDir = "build"
$ReleaseName = "teo-cache-purge-$Version"
$ReleaseDir = "$BuildDir\$ReleaseName"

# 创建构建目录
Write-Host "📁 创建构建目录..." -ForegroundColor Yellow
if (Test-Path $BuildDir) {
    Remove-Item -Recurse -Force $BuildDir
}
New-Item -ItemType Directory -Path $ReleaseDir -Force | Out-Null

# 复制必要文件
Write-Host "📋 复制插件文件..." -ForegroundColor Yellow
Copy-Item "$PluginDir\*.php" -Destination $ReleaseDir -Force
Copy-Item "$PluginDir\*.md" -Destination $ReleaseDir -Force
if (Test-Path "$PluginDir\composer.json") {
    Copy-Item "$PluginDir\composer.json" -Destination $ReleaseDir -Force
}

# 复制 vendor 目录（如果存在）
if (Test-Path "$PluginDir\vendor") {
    Write-Host "📦 复制 vendor 目录..." -ForegroundColor Yellow
    Copy-Item "$PluginDir\vendor" -Destination $ReleaseDir -Recurse -Force
} else {
    Write-Host "⚠️  警告: vendor 目录不存在，请确保在安装前运行 composer install" -ForegroundColor Yellow
}

# 创建 README 文件
Write-Host "📝 创建 README..." -ForegroundColor Yellow
$ReadmeContent = @"
=== Tencent EdgeOne Cache Manager ===
Contributors: Shinko
Tags: cache, cdn, edgeone, tencent cloud, performance
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: $Version
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

= $Version =
* 查看完整更新日志: https://github.com/Iconkop/wp_expand/blob/main/teo-cache-purge/CHANGELOG.md

== Upgrade Notice ==

= $Version =
修复测试连接跳转问题，新增 GitHub 自动更新功能。
"@

Set-Content -Path "$ReleaseDir\README.txt" -Value $ReadmeContent -Encoding UTF8

# 压缩打包
Write-Host "🗜️  压缩打包..." -ForegroundColor Yellow
$ZipPath = "$ReleaseName.zip"
if (Test-Path $ZipPath) {
    Remove-Item $ZipPath -Force
}

Compress-Archive -Path "$ReleaseDir\*" -DestinationPath $ZipPath -CompressionLevel Optimal

# 清理构建目录
Write-Host "🧹 清理临时文件..." -ForegroundColor Yellow
Remove-Item -Recurse -Force $BuildDir

# 计算文件信息
$FileInfo = Get-Item $ZipPath
$FileSize = "{0:N2} MB" -f ($FileInfo.Length / 1MB)
$MD5 = (Get-FileHash -Path $ZipPath -Algorithm MD5).Hash
$SHA256 = (Get-FileHash -Path $ZipPath -Algorithm SHA256).Hash

Write-Host ""
Write-Host "✅ 打包完成！" -ForegroundColor Green
Write-Host ""
Write-Host "📦 文件信息:" -ForegroundColor Cyan
Write-Host "   文件名: $ReleaseName.zip"
Write-Host "   大小: $FileSize"
Write-Host "   MD5: $MD5"
Write-Host "   SHA256: $SHA256"
Write-Host ""
Write-Host "📋 发布检查清单:" -ForegroundColor Cyan
Write-Host "   [ ] 更新插件版本号"
Write-Host "   [ ] 更新 CHANGELOG.md"
Write-Host "   [ ] 提交并推送代码"
Write-Host "   [ ] 创建 Git Tag: git tag -a v$Version -m 'Release v$Version'"
Write-Host "   [ ] 推送 Tag: git push origin v$Version"
Write-Host "   [ ] 在 GitHub 创建 Release"
Write-Host "   [ ] 上传 $ReleaseName.zip 作为 Release Asset"
Write-Host "   [ ] 填写 Release 描述（使用 CHANGELOG.md）"
Write-Host ""
Write-Host "🚀 GitHub CLI 命令:" -ForegroundColor Yellow
Write-Host "   gh release create v$Version $ReleaseName.zip --title 'v$Version' --notes-file teo-cache-purge\CHANGELOG.md"
Write-Host ""
