# 轻墨 (Qingmo)

> 一个轻量纯粹的纯文件博客系统。无数据库、无框架，解压即用。

[![Version](https://img.shields.io/badge/version-2.5.1-blue)](https://github.com/muyi3919/Qingmo)
[![License](https://img.shields.io/badge/license-MIT-green)](https://github.com/muyi3919/Qingmo/blob/main/LICENSE)

---

## 更新日志 v2.5.1

- ✅ **修复图文混排撑破评论区**：颜文字/表情代码等无空格长文本与图片混排时不再横向撑开版面（`overflow-wrap: anywhere` 自动折行），四个主题同步

---

## 更新日志 v2.5.0

- ✅ **内置 B站小黄脸表情（194 枚）**：图片随系统内置在 `assets/emotions/bilibili`，评论框表情面板新增「B站」分组，输入 `:doge:` `:huaji:` `:ciya:` 等即渲染；另含 74 个补充表情（`:bili-extra-N:`）；素材为 GPLv3/第三方适配（见 `includes/emotion-bilibili.php` 顶部注释，图片版权归 Bilibili）
- ✅ **表情面板改版**：顶部一排分类名 Tab（颜文字 / Emoji / B站…），点击切换只显示当前分组，组再多也不会横向铺开
- ✅ **图片点击放大（灯箱）**：正文与评论区图片点击全屏查看，支持 ‹ › 翻页、Esc / 点空白 / ✕ 关闭；评论里 Markdown 图片限宽显示（缩略 240px），点开看原图
- ✅ **评论支持 Markdown**：加粗、链接、行内代码、图片等（图片自动限宽防撑破评论区）
- ✅ **登录页重做**：全新内嵌样式卡片登录页
- ✅ **B站风评论框占位提示**：每次聚焦随机换一句俏皮话，并提示「（支持md格式哦）」

---

## 更新日志 v2.4.0

- ✅ **邮件发送明确化**：SMTP/PHP mail 失败现在给出具体原因（连接失败/登录失败/被服务器拒绝/mail 不可用），测试邮件成功/失败都有明确提示与排查建议
- ✅ **友链增强**：友链支持 **分类** 与 **图标**；友链页按分类分组展示；站点设置新增「按名称 / 每次刷新随机」排序
- ✅ **评论区头像**：每条评论显示 Cravatar/Gravatar 头像（邮箱 md5，不暴露邮箱）
- ✅ **评论无限叠楼 + 默认折叠**：取消 3 层限制可任意深；子回复默认收起，点「展开回复(N 条)」逐层展开/收起

---

## 更新日志 v2.3.4

- ✅ **按日归档页**：新增 `index.php?page=date&date=YYYY-MM-DD`（写作热力图等点击某天直达当日文章）
- ✅ **侧栏顶部钩子** `qm_sidebar_top`：位于分类之前，供公告等置顶小部件使用
- ✅ **侧栏公告 v1.2**：默认显示在侧栏顶部，可设置公告标题与显示位置（顶部/底部）

---

## 更新日志 v2.3.3

- ✅ **修复在线更新 GitHub 404**：`owner/repo` 此前被整段 URL 编码（`/` 变成 `%2F`），现改为拆成 owner 与 repo 两段分别编码；404 提示也会区分“仓库/分支不存在或为私有”

---

## 更新日志 v2.3.2

- ✅ **在线更新可靠性修复**：临时目录改到服务器系统 temp（不再依赖 `data/` 建子目录，修复 mkdir/file_put_contents 权限报错）；GitHub 网络错误/限流/404 现在给出具体提示而非笼统“仓库名错误”，并提示改用自定义 zip 直链；站点根目录无写权限时会明确提示

---

## 更新日志 v2.3.1

- ✅ **RSS 2.0 规范修正**：统一 XML 转义（修复 `&amp;amp;` 双重转义）、CDATA 安全处理 `]]>`
- ✅ **新增 Atom 1.0 订阅源**：`/atom.php`（feed/entry 全要素 + 全文 `content`）
- ✅ **评论表情包**：可折叠表情面板；内置「颜文字 / Emoji」两组，插件可用 `qm_emotion_list` 过滤器追加字符与图片表情（`text` / `sticker`，sticker 以 `:code:` 渲染）
- ✅ 两条订阅源均加 `no-cache` 响应头，发布后即实时可抓

---

## 更新日志 v2.3

- ✅ **反垃圾评论插件**：`plugins/qingmo-antispam`，纯服务端校验（提交间隔、按 IP 限频、链接上限、关键词/邮箱/昵称黑名单、最短长度），命中给出具体原因
- ✅ **回复提醒**：点“回复”发楼中楼时，给被回复者发“有人回复了你的评论”邮件（复用评论者提醒开关）
- ✅ **评论归属地完善**：记录运营商与国家码，徽标显示「国旗 国家 省市 · 运营商」，悬停显示完整 IP
- ✅ **SEO 优化插件**：`plugins/qingmo-seo` —— canonical / Open Graph / Twitter Card / 文章 JSON-LD；设置页可生成 **sitemap.xml**
- ✅ **RSS 增强**：全文（content:encoded）+ 作者 / 分类 / 标签 / 更新时间 / atom self
- ✅ **页脚自定义**：站点设置可填页脚 HTML（统计代码、备案跳链等）
- ✅ **后台侧边栏布局**（仿 WordPress）：菜单从顶部挪到左侧并高亮当前页，窄屏自动折叠
- ✅ **评论体验**：邮箱必填（前后端双重校验）+ 昵称/邮箱/主页记忆（cookie 预填）

---

## 更新日志 v2.2

- ✅ **评论归属地插件**（轻墨版 Easy Location）：`plugins/comment-geo`，提交评论时记录 IP 并可在线查询归属地（ip-api.com，结果本地缓存 N 天、可关）；每条评论旁显示「来自 XX」
- ✅ **核心新增评论扩展点**（向后兼容）：`qm_comment_data`（写入前过滤器）与 `qm_comment_meta`（渲染动作）——反垃圾、归属地等评论插件都可挂载
- ✅ **在线更新**：后台「系统更新」可检查 GitHub 最新提交并一键拉取覆盖（自动排除 `data/`、`assets/uploads/`）；更新源可改为自定义 zip 直链
- ✅ 新增 `QM_VERSION` 版本常量，目前 **v2.2.0**

---

## 更新日志 v2.1.1

- ✅ **修复 PHP 8.2 兼容性**：`QmSmtp` 显式声明 `lastReply` 属性，消除“Creation of dynamic property”弃用警告（保存/发送 SMTP 邮件时不再出现 Deprecated 提示）
- ✅ 顺手加固：SMTP 连接失败后不再继续发送命令（返回明确错误）

---

## 更新日志 v2.1

- ✅ **插件设置页**：启用状态的插件若自带 `admin.php`，插件市场会提供「设置」入口并拥有独立设置页（表单/保存由插件负责）
- ✅ **页脚备案 / 侧栏公告支持后台填写**：备案号、公告内容不再需要改代码，直接在插件「设置」里填写
- ✅ **文章置顶插件**：`plugins/qingmo-sticky`，后台一键置顶/取消，置顶文章排到列表最前（核心新增 `qm_posts_list` / `qm_post_row_actions` / `qm_admin_posts_head` 三个扩展点）

---

## 更新日志 v2.0

> **全新界面设计** · **升级视觉体验，支持深色模式** · **性能优化** · **新增文章编辑器** · **评论系统重构** · **本地主题/插件市场**

v2.0 主要更新（均已实现并可用，状态以「✅」标注）：

- ✅ **全新界面设计 / 深色模式**：内置「轻现代」卡片风主题，浅/深色一键切换（导航栏 🌓，自动跟随系统偏好并记忆选择）
- ✅ **性能优化**：数据读取加入请求内缓存，同一页面反复读取的配置/分类/评论文件不再重复解析，页面加载更快、内存占用更低
- ✅ **文章编辑器**：正文支持 **Markdown（实时预览）/ 可视化所见即所得 / HTML 源码**三种模式，工具栏快捷排版，**图片拖拽/粘贴/选择上传**（自动存入 `assets/uploads/`，仅管理员可用）
- ✅ **评论系统重构**：**嵌套回复**（楼中楼，最多展示 3 层）+ **表情快捷插入** + **@提及提醒** + **新评论邮件通知**
- ✅ **邮件通知（SMTP）**：内置轻量 SMTP 客户端（SSL/STARTTLS/明文，AUTH LOGIN/PLAIN），后台配置后即可用于新评论通知、@提及提醒，并带「发送测试邮件」按钮；未配置 SMTP 时自动回退 PHP `mail()`
- ✅ **主题 / 插件市场（本地版，无需联网）**：后台「主题市场」一键启用主题并预览样式、「插件市场」启停本地插件
- ✅ **友链系统**：后台管理友链（增删改），前台友链页 + 侧边栏友链卡片
- ✅ **文章页脚**：标题 / 链接 / 作者 / 使用协议（后台可配置）

> 说明：按项目定位，v2.0 不提供联网下载的主题/插件在线市场；邮件通知依赖托管环境开启 PHP `mail()`，本地开发环境无法实际投递属正常现象。

---

## 目录

- [功能特性](#功能特性)
- [项目结构](#项目结构)
- [安装部署](#安装部署)
- [使用方法](#使用方法)
- [Nginx 配置](#nginx-配置)
- [美化指南](#美化指南)
- [注意事项](#注意事项)
- [作者](#作者)
- [License](#license)

---

## 功能特性

- **文章管理**：发布、编辑、删除文章
- **文章编辑器**：Markdown / 可视化富文本 / HTML 三模式，图片拖拽上传
- **分类系统**：文章分类管理
- **标签系统**：标签归类与检索
- **评论系统**：评论审核、嵌套回复、表情输入、@提及提醒
- **邮件通知**：SMTP 发送（新评论 / @提及 / 测试邮件）
- **文章搜索**：全文搜索
- **文章归档**：按时间归档
- **分页浏览**：列表分页
- **RSS 订阅**：自动生成 RSS 输出
- **后台仪表盘**：管理概览
- **密码修改**：后台安全设置
- **关于页面**：自定义内容
- **阅读计数**：文章浏览统计
- **文章页脚**：每篇文章底部显示标题/链接/作者/使用协议（后台可配置）
- **响应式布局**：适配移动端
- **CSRF 保护**：表单安全验证
- **主题系统**：文件夹式主题，后台一键切换
- **插件系统**：动作/过滤器钩子，后台启停插件

---

## 项目结构

```
轻墨/
├── index.php              # 前台入口（自动检测安装状态）
├── install.php            # 安装脚本（用完删）
├── rss.php                # RSS 2.0 订阅（实时生成）
├── atom.php               # Atom 1.0 订阅（实时生成）
├── includes/
│   ├── functions.php      # 核心函数（含主题/插件/钩子系统、Markdown 渲染）
│   ├── mailer.php         # 轻量 SMTP 客户端与统一发信
│   ├── emotions.php       # 评论表情包（默认组 + qm_emotion_list 过滤器）
│   ├── header.php         # 页面头部（含搜索框）
│   ├── footer.php         # 页面底部
│   └── db.php             # 占位
├── assets/
│   ├── style.css          # 全局样式（后台 + 默认主题回退）
│   └── uploads/           # 编辑器图片上传目录（自动生成）
├── themes/                # 主题目录（每个子目录一个主题）
│   ├── default/           # 默认主题（复古经典，使用 assets/style.css）
│   │   └── theme.php      # 主题元信息
│   ├── paper/             # 主题（简约淡雅，自带 style.css）
│   │   ├── theme.php
│   │   └── style.css
│   ├── modern/            # 主题（轻现代卡片，支持深色模式）
│   │   ├── theme.php      # dark_support=1 声明深色能力
│   │   └── style.css
│   └── sakura/            # 主题（樱笺·粉彩纸感，衬线标题 + 樱粉点缀）
│       ├── theme.php
│       └── style.css
├── plugins/               # 插件目录（每个子目录一个插件）
│   ├── footer-beian/      # 页脚备案信息（后台可填写文案，含 admin.php 设置页）
│   ├── post-copyright/    # 示例：文章版权尾巴（过滤器钩子）
│   ├── sidebar-notice/    # 侧栏公告（后台可填写内容，含 admin.php 设置页）
│   ├── qingmo-sticky/     # 文章置顶（后台一键置顶/取消）
│   ├── comment-geo/       # 评论归属地（轻墨版 Easy Location，记录 IP + 在线查归属地）
│   ├── qingmo-seo/        # SEO 优化（canonical/OG/Twitter/JSON-LD + 生成 sitemap.xml）
│   └── qingmo-antispam/   # 反垃圾评论（限频/黑名单/链接上限）
├── data/                  # 数据目录
│   ├── .htaccess          # 禁止外部访问
│   ├── config.php         # 站点设置
│   ├── users.php          # 管理员
│   ├── categories.php     # 分类
│   ├── comments.php       # 评论
│   ├── about.php          # 关于页面内容
│   ├── counter.php        # ID计数器
│   └── posts/             # 文章文件
└── admin/                 # 后台管理
    ├── index.php          # 路由
    ├── login.php          # 登录
    ├── dashboard.php      # 仪表盘
    ├── posts.php          # 文章管理
    ├── post-edit.php      # 写文章（Markdown/HTML 编辑器 + 图片上传）
    ├── upload.php         # 图片上传接口
    ├── categories.php     # 分类管理
    ├── comments.php       # 评论管理（含楼中楼回复）
    ├── themes.php         # 主题管理（切换主题）
    ├── plugins.php        # 插件管理（启停插件）
    ├── settings.php       # 站点设置
    ├── password.php       # 修改密码
    └── about-edit.php     # 编辑关于页面
```

---

## 安装部署

### 环境要求

- **PHP 8.0+**
- Web 服务器（Apache / Nginx / PHP 内置服务器）
- **不需要任何数据库**

### 安装步骤

1. 将压缩包解压后上传到网站目录
2. 设置数据目录权限：

```bash
chmod -R 755 data/
chmod -R 755 data/posts/
```

3. 浏览器访问博客首页，自动跳转到安装页面：

```
http://你的域名/
```

4. 按提示完成安装后，**删除 `install.php` 和 `gen-test.php`**

### 默认登录

- 后台：`http://你的域名/admin/`
- 账号：`admin`
- 密码：`admin`

---

## 使用方法

### 后台功能

| 功能 | 地址 |
|------|------|
| 仪表盘 | `admin/index.php` |
| 写文章 | `admin/index.php?page=post-edit` |
| 文章管理 | `admin/index.php?page=posts` |
| 分类管理 | `admin/index.php?page=categories` |
| 评论管理 | `admin/index.php?page=comments` |
| 友链管理 | `admin/index.php?page=links` |
| 主题市场 | `admin/index.php?page=themes` |
| 插件市场 | `admin/index.php?page=plugins` |
| 系统更新 | `admin/index.php?page=update` |
| 站点设置 | `admin/index.php?page=settings` |
| 关于页面 | `admin/index.php?page=about-edit` |
| 修改密码 | `admin/index.php?page=password` |
| 退出 | `admin/index.php?logout=1` |

### 主题系统

主题存放在站点根目录 `themes/` 下，**每个子文件夹就是一个主题**：

```text
themes/paper/
├── theme.php      # 主题元信息（返回数组：name/description/version/author）
└── style.css      # 主题样式（可选；缺省时前台回退到 assets/style.css）
```

后台「主题市场」页面可随时启用/切换主题，刷新前台即生效。系统内置 `default`（复古经典）、`paper`（简约淡雅）、`modern`（轻现代卡片，支持深色模式）与 `sakura`（樱笺·粉彩纸感）四个主题。

`modern` 主题深色模式：页面右上角 🌓 一键切换，首次访问自动跟随系统偏好（`prefers-color-scheme`），选择会记忆在浏览器中（localStorage）。主题 `theme.php` 中声明 `'dark_support' => 1` 即可启用深色能力，其余主题不受影响。

制作新主题：复制任意主题文件夹改名放入 `themes/`，修改 `style.css` 与 `theme.php` 即可。

### 插件系统

插件存放在站点根目录 `plugins/` 下，**每个子文件夹就是一个插件**：

```text
plugins/my-plugin/
├── plugin.json   # 元信息（JSON：name/description/version/author）
├── plugin.php    # 插件入口（include 后注册钩子）
└── admin.php     # 可选：后台「设置」页（启用后插件市场会出现「设置」入口）
```

`plugin.php` 示例：

```php
<?php
if (!defined('QM_BOOT')) { exit('Access denied'); }

// 过滤器：改写文章正文
add_filter('qm_post_content', function ($content, $post) {
    return $content . '<p>— 来自我的插件</p>';
}, 10, 2);
```

`admin.php` 负责输出设置表单并保存（页面已登录鉴权，表单记得 `csrf_field()`；保存的数据建议用 `load_data/save_data` 存到 `data/` 下自己的文件）。参考实现见 `plugins/footer-beian/admin.php`、`plugins/sidebar-notice/admin.php`。

后台「插件市场」页面可启用/停用插件，启用且带 `admin.php` 的插件会额外显示「设置」入口。系统内置以下钩子：

| 钩子 | 类型 | 说明 |
|------|------|------|
| `qm_head` | 动作 | 前台 `<head>` 内，可注入 CSS/JS |
| `qm_sidebar` | 动作 | 侧边栏小部件之后 |
| `qm_sidebar_links` | 动作 | 侧边栏「链接」列表内 |
| `qm_footer` | 动作 | 页脚区域 |
| `qm_post_content` | 过滤器 | 单篇文章正文（参数：内容、文章数组） |
| `qm_admin_footer` | 动作 | 后台页面底部 |

安装新主题/插件：把文件夹直接放进 `themes/` / `plugins/` 目录即可，无需改代码。

### RSS 订阅

地址：`http://你的域名/rss.php`

---

## Nginx 配置

```nginx
server {
    listen 80;
    server_name blog.example.com;
    root /var/www/qingmo;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
    }

    location ^~ /data/ {
        deny all;
    }
}
```

---

## 美化指南

所有样式修改都在 `assets/style.css` 中完成，改完刷新即生效。

### 背景设置

**纯色背景**

```css
body {
    background: #f0f0f0;
}
```

**图片平铺**

```css
body {
    background: #e0e0e0 url("bg.jpg") repeat;
}
```

**大图全屏**

```css
body {
    background-color: #333;
    background-image: url("bg.jpg");
    background-repeat: no-repeat;
    background-position: center center;
    background-size: cover;
    background-attachment: fixed;
}
```

图片放到 `assets/bg.jpg`。

### 内容区半透明（配合背景图）

```css
.container {
    background: rgba(255, 255, 255, 0.92);
}
```

### 配色方案

**经典蓝（默认）**

```css
a { color: #0000cc; }
a:hover { color: #cc0000; }
```

**复古绿**

```css
a { color: #006400; }
a:hover { color: #8b0000; }
```

**温暖棕**

```css
a { color: #8b4513; }
a:hover { color: #cd853f; }
```

**深夜模式**

```css
body {
    background: #1a1a1a;
    color: #d0d0d0;
}
.container {
    background: #2a2a2a;
    border: 1px solid #555;
}
.header h1 a { color: #e0e0e0; }
.post-meta { color: #888; }
.sidebar { border-left-color: #555; }
a { color: #7aa6da; }
a:hover { color: #ff6b6b; }
```

### 字体调整

**现代字体**

```css
body {
    font-family: "Microsoft YaHei", "PingFang SC", "Helvetica Neue", Arial, sans-serif;
}
```

**复古等宽**

```css
body {
    font-family: "Courier New", "SimSun", "宋体", monospace;
}
```

**字号调大**

```css
body {
    font-size: 15px;
    line-height: 1.8;
}
```

### 布局微调

**内容区加宽**

```css
.container {
    max-width: 900px;
}
```

**去掉边框（极简）**

```css
.container {
    border: none;
    box-shadow: 0 0 10px rgba(0,0,0,0.1);
}
```

### 装饰元素

**标题加横线**

```css
.header h1 {
    border-bottom: 3px double #999;
    padding-bottom: 8px;
}
```

**文章列表加小图标**

```css
.post-item h2::before {
    content: "▸ ";
    color: #999;
}
```

**评论区加引号**

```css
.comment-content::before {
    content: '"';
    color: #ccc;
    font-size: 24px;
}
```

### 响应式适配

```css
@media (max-width: 600px) {
    body {
        padding: 10px;
        font-size: 15px;
    }
    .main, .sidebar {
        float: none;
        width: 100%;
        border-left: none;
        padding-left: 0;
    }
}
```

### 推荐组合

```css
body {
    font-family: "Microsoft YaHei", "PingFang SC", sans-serif;
    font-size: 14px;
    line-height: 1.7;
    color: #333;
    background: #e8e8e8 url("bg.jpg") no-repeat center center fixed;
    background-size: cover;
    margin: 0;
    padding: 30px;
}
.container {
    max-width: 860px;
    margin: 0 auto;
    border: 1px solid #bbb;
    padding: 20px;
    background: rgba(255, 255, 255, 0.94);
}
a { color: #2e5c8a; }
a:hover { color: #d43f3a; }
```

---

## 注意事项

1. **安装后删除**：`install.php` 和 `gen-test.php`
2. **尽快修改默认密码**：后台 → 修改密码
3. **Nginx 安全配置**：确保配置 `location ^~ /data/ { deny all; }`

---

## 作者

**kina漫记** · [kina.ink](https://kina.ink)

---

## License

MIT License

---

*轻墨 — 轻量纯粹的写作空间*
