# Starter Web 应用

这是一个基于 PHP 的综合型 Web 应用，包含用户认证、基于角色的访问控制，以及 AI 驱动的聊天功能。

## 功能特性

- **用户认证系统**：完整的注册、登录、邮箱验证与密码找回流程
- **基于角色的访问控制**：支持三种用户角色（child、parent、admin）
- **AI 聊天集成**：基于大语言模型的对话式交互
- **后台管理**：提供用户和系统管理能力
- **数据库迁移**：结构化的数据库表管理方式

## 系统要求

- **PHP**：7.4 或更高版本
- **MySQL**：5.7 或更高版本
- **Composer**：最新版本
- **Web 服务器**：PHP 内置服务器，或 Apache/Nginx

## 安装指南

### 第 1 步：配置数据库

#### 1.1 创建 MySQL 数据库

首先启动 MySQL：

```bash
sudo systemctl start mysql
```

然后在 MySQL 中创建数据库：

```sql
CREATE DATABASE starter CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

#### 1.2 配置数据库连接

编辑项目根目录下的 `.env` 文件，并填写数据库配置：

```env
# Database Settings
DB_HOST=localhost
DB_PORT=3306
DB_NAME=starter
DB_USERNAME=root
DB_PASS=your_database_password
```

**配置项说明：**
- `DB_HOST`：数据库服务器地址，默认 `localhost`
- `DB_PORT`：MySQL 端口，默认 `3306`
- `DB_NAME`：数据库名称，默认 `starter`
- `DB_USERNAME`：MySQL 用户名
- `DB_PASS`：MySQL 密码，如果未设置密码可留空

### 第 2 步：配置 AI 聊天

应用包含一个基于大语言模型（LLM）的 AI 聊天功能。请在 `.env` 文件中配置 API 参数：

```env
# LLM API Settings
LLM_API_KEY="your-api-key-here"
LLM_API_URL="https://api.deepseek.com/v1/chat/completions"
```

**配置项说明：**
- `LLM_API_KEY`：LLM 服务的 API Key
- `LLM_API_URL`：LLM API 的请求地址

**说明**：当前默认使用 DeepSeek API。你也可以替换为任何兼容 OpenAI 接口格式的服务，例如 OpenAI、Azure OpenAI 或其他 LLM 提供商。

### 第 2.1 步：知识库存储位置

知识库服务会将数据存储在项目根目录下的以下位置：

- `storage/knowledge/uploads/`：保存原始上传文件
- `storage/knowledge/chroma_db/`：保存 ChromaDB 持久化数据，包括向量索引和元数据

如果项目部署在 `/var/www/html/AIChatforChildren`，对应的绝对路径如下：

- `/var/www/html/AIChatforChildren/storage/knowledge/uploads/`
- `/var/www/html/AIChatforChildren/storage/knowledge/chroma_db/`

这些目录会在 ChromaDB 服务启动时自动创建。

### 第 3 步：执行数据库迁移

运行以下命令创建所需的数据表：

```bash
php database/migrate.php
```

该命令会创建以下数据库结构：
- **users** 表：存储用户账号、认证和验证相关数据
- **conversations** 表：存储每个用户的聊天会话元数据
- **messages** 表：存储会话中的具体消息内容

### 数据库结构

#### Users 表

```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('child', 'parent', 'admin') DEFAULT 'child' NOT NULL,
    verification_code INT NULL,
    verification_status ENUM('pending', 'verified') DEFAULT 'pending',
    verification_requested_at TIMESTAMP NULL,
    request_attempts INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

#### Conversations 表

```sql
CREATE TABLE conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL DEFAULT 'New Chat',
    auto_renamed TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_updated_at (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Messages 表

```sql
CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    role ENUM('user', 'assistant', 'system') NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    INDEX idx_conversation_id (conversation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**可选：回滚迁移**

如果需要回滚并删除所有表，执行：

```bash
php database/migrate.php --down
```

## 运行应用

### 方式一：使用 Apache + .htaccess（生产环境推荐）

这是生产部署推荐的方式。应用通过 `.htaccess` 实现 URL 重写和路由分发。

#### 前置条件

1. 已安装并启用 `mod_rewrite` 的 **Apache Web 服务器**
2. 项目已部署到 Web 服务器目录，例如 `/var/www/html/`

#### 分步部署指南

##### 1. 安装依赖

```bash
cd /var/www/html/AIChatforChildren
composer install --no-dev --optimize-autoloader
```

##### 2. 配置基础 URL

应用通过 `.htaccess` 进行 URL 重写，因此必须根据你的部署路径正确配置 `base_url`。

**编辑 `config/config.php`：**

```php
<?php

return [
    /**
     * 应用基础 URL
     * - 部署在站点根目录时：'/'
     * - 部署在子目录时：'/subdirectory/'
     */
    'base_url' => '/AIChatforChildren/',  // 按你的部署路径修改

    'auth' => [
        'require_verification' => false,  // 是否启用邮箱验证
    ]
];
```

**配置示例：**

| 部署位置 | base_url 配置 | 访问地址 |
|---------------------|------------------|------------|
| 根目录 | `'/'` | `http://yourdomain.com/` |
| 子目录 | `'/myapp/'` | `http://yourdomain.com/myapp/` |
| 当前项目 | `'/AIChatforChildren/'` | `http://localhost/AIChatforChildren/` |

##### 3. 检查 .htaccess 配置

确保 `.htaccess` 中的 `RewriteBase` 与 `base_url` 保持一致：

```apache
<IfModule mod_rewrite.c>
RewriteEngine On

# 设置重写基础路径（必须与 config.php 中的 base_url 一致）
RewriteBase /AIChatforChildren/

# 允许静态资源直接访问
RewriteCond %{REQUEST_URI} \.(css|js|jpg|jpeg|png|gif|svg|ico|woff|woff2|ttf|eot)$ [NC]
RewriteRule ^ - [L]

# 如果请求的不是现有文件或目录，则继续重写
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d

# 其余请求统一转发到 index.php
RewriteRule ^(.*)$ index.php [QSA,L]

</IfModule>
```

**重要**：`RewriteBase` 和 `base_url` 必须完全一致。

##### 4. 启用 Apache mod_rewrite

```bash
# Ubuntu/Debian
sudo a2enmod rewrite
sudo systemctl restart apache2

# CentOS/RHEL
# mod_rewrite 通常默认启用
# 可在 httpd.conf 中检查：LoadModule rewrite_module modules/mod_rewrite.so
sudo systemctl restart httpd
```

##### 5. 配置 Apache Virtual Host（推荐）

编辑 Apache 配置文件，例如 `/etc/apache2/sites-available/000-default.conf`：

```apache
<VirtualHost *:80>
    ServerName localhost
    DocumentRoot /var/www/html

    # 允许 .htaccess 生效
    <Directory /var/www/html/AIChatforChildren>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
```

**说明**：必须设置 `AllowOverride All`，否则 `.htaccess` 不会生效。

##### 6. 设置文件权限

```bash
# 设置目录权限
chmod -R 755 /var/www/html/AIChatforChildren
chown -R www-data:www-data /var/www/html/AIChatforChildren

# 保护 .env 文件
chmod 600 /var/www/html/AIChatforChildren/.env
```

##### 7. 启动 Apache 服务

```bash
# 启动 Apache
sudo systemctl start apache2

# 启动 MySQL
sudo systemctl start mysql

# 开机自动启动
sudo systemctl enable apache2
sudo systemctl enable mysql
```

##### 8. 创建管理员账号

首次部署需要创建一个管理员账号。可以任选以下方式。

**方式 A：直接写入数据库**

```sql
INSERT INTO users (name, email, password, role, verification_status, created_at)
VALUES (
    'Admin',
    'admin@example.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- 密码：password
    'admin',
    'verified',
    NOW()
);
```

**方式 B：先注册再修改角色**

1. 先通过注册页面创建一个普通账号
2. 然后在数据库中修改角色：

```sql
UPDATE users SET role = 'admin', verification_status = 'verified' WHERE email = 'your@email.com';
```

##### 9. 访问应用

打开浏览器，访问：

```text
http://localhost/AIChatforChildren/
```

系统会自动跳转到登录页：

```text
http://localhost/AIChatforChildren/sign-in
```

**默认登录信息（如果使用方式 A）：**
- 邮箱：`admin@example.com`
- 密码：`password`

**⚠️ 重要**：首次登录后请立即修改密码。

#### .htaccess 路由工作原理

`.htaccess` 通过把所有请求重写到 `index.php` 来实现整洁 URL：

```apache
RewriteEngine On                    # 启用 URL 重写
RewriteBase /AIChatforChildren/     # 设置基础路径

# 1. 静态资源绕过路由
RewriteCond %{REQUEST_URI} \.(css|js|jpg|jpeg|png|gif|svg|ico|woff|woff2|ttf|eot)$ [NC]
RewriteRule ^ - [L]

# 2. 现有文件或目录直接访问
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d

# 3. 其余请求全部转发到 index.php
RewriteRule ^(.*)$ index.php [QSA,L]
```

**URL 路由示例：**

| 用户访问地址 | 处理流程 |
|------------|-----------------|
| `/AIChatforChildren/` | → `index.php` → Router → 跳转到 `/sign-in` |
| `/AIChatforChildren/sign-in` | → `index.php` → Router → `pages/auth/signin.php` |
| `/AIChatforChildren/admin/users` | → `index.php` → Router → Middleware → `pages/admin/users.php` |
| `/AIChatforChildren/assets/css/admin.css` | → 直接访问静态文件，绕过 PHP |

#### 常见问题排查

**问题：所有页面都显示 404**

**解决方法**：启用 Apache `mod_rewrite` 模块

```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

**问题：.htaccess 不生效**

**解决方法**：确认 Apache 配置里已设置 `AllowOverride All`

```apache
<Directory /var/www/html>
    AllowOverride All
</Directory>
```

**问题：CSS/JS 资源无法加载**

**解决方法**：检查 `config/config.php` 中的 `base_url` 是否和部署路径一致

**问题：修改部署路径后，页面链接失效**

**解决方法**：同时更新以下两个位置：

1. `.htaccess` 中的 `RewriteBase`
2. `config/config.php` 中的 `base_url`

这两个值必须完全一致。

---

### 方式二：使用 PHP 内置服务器（仅开发环境）

如果只是快速本地开发测试，可以使用 PHP 自带的内置服务器。

#### 主应用

```bash
php -S localhost:8080 -t .
```

然后在浏览器中访问：

```text
http://localhost:8080
```

此方式下可以使用以下功能：
- 用户注册与登录
- 邮箱验证系统
- 密码找回
- 基于角色的控制台页面
- AI 聊天界面

#### 管理员管理界面

如果你要使用管理员账号管理页面，可以运行：

```bash
php -S localhost:8080 admin_management.php
```

然后在浏览器中访问：

```text
http://localhost:8080
```

**管理员管理功能：**
- 创建新的管理员账号
- 编辑已有管理员资料
- 删除管理员账号
- 管理用户角色与权限

**⚠️ 注意**：PHP 内置服务器只适合开发环境。生产环境请使用 Apache + .htaccess。

## 项目结构

```text
starter/
├── app/
│   ├── controllers/     # 应用控制器
│   └── models/          # 数据模型
├── config/              # 配置文件
├── core/                # 框架核心类
├── database/
│   ├── migrations/      # 数据库迁移文件
│   └── migrate.php      # 迁移执行入口
├── pages/               # 视图模板
│   ├── admin/           # 管理后台页面
│   ├── auth/            # 认证相关页面
│   ├── child/           # child 角色页面
│   ├── parent/          # parent 角色页面
│   └── home.php         # 基础入口页面
├── utils/               # 工具函数
├── vendor/              # Composer 依赖
├── .env                 # 环境配置
├── index.php            # 主应用入口
├── admin_management.php # 管理员管理入口
└── composer.json        # Composer 配置
```
