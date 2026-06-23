# 轻墨 (Qingmo)

> 一个轻量纯粹的纯文件博客系统。无数据库、无框架，解压即用。

[![Version](https://img.shields.io/badge/version-1.0.0-blue)](https://github.com/yourname/qingmo)
[![License](https://img.shields.io/badge/license-MIT-green)](https://github.com/yourname/qingmo/blob/main/LICENSE)

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
- **分类系统**：文章分类管理
- **标签系统**：标签归类与检索
- **评论系统**：支持评论审核
- **文章搜索**：全文搜索
- **文章归档**：按时间归档
- **分页浏览**：列表分页
- **RSS 订阅**：自动生成 RSS 输出
- **后台仪表盘**：管理概览
- **密码修改**：后台安全设置
- **关于页面**：自定义内容
- **阅读计数**：文章浏览统计
- **响应式布局**：适配移动端
- **CSRF 保护**：表单安全验证

---

## 项目结构

```
轻墨/
├── index.php              # 前台入口（自动检测安装状态）
├── install.php            # 安装脚本（用完删）
├── rss.php                # RSS 订阅
├── includes/
│   ├── functions.php      # 核心函数
│   ├── header.php         # 页面头部（含搜索框）
│   ├── footer.php         # 页面底部
│   └── db.php             # 占位
├── assets/
│   └── style.css          # 全局样式
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
    ├── post-edit.php      # 写文章
    ├── categories.php     # 分类管理
    ├── comments.php       # 评论管理
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
| 站点设置 | `admin/index.php?page=settings` |
| 关于页面 | `admin/index.php?page=about-edit` |
| 修改密码 | `admin/index.php?page=password` |
| 退出 | `admin/index.php?logout=1` |

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
