# Starter Web Application

A comprehensive PHP-based web application featuring user authentication, role-based access control, and AI-powered chat functionality.

## Features

- **User Authentication System**: Complete sign-up, sign-in, email verification, and password recovery
- **Role-Based Access Control**: Support for three user roles (child, parent, admin)
- **AI Chat Integration**: LLM-powered conversational interface
- **Admin Management**: User and system administration capabilities
- **Database Migrations**: Structured database schema management

## System Requirements

- **PHP**: Version 7.4 or higher
- **MySQL**: Version 5.7 or higher
- **Composer**: Latest version
- **Web Server**: PHP built-in server or Apache/Nginx

## Installation Guide

### Step 1: Database Configuration

#### 1.1 Create MySQL Database

First, Run mysql: sudo systemctl start mysql

And create a new database in MySQL:
```sql
CREATE DATABASE starter CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

#### 1.2 Configure Database Connection

Edit the `.env` file in the project root directory and configure your database settings:

```env
# Database Settings
DB_HOST=localhost
DB_PORT=3306
DB_NAME=starter
DB_USERNAME=root
DB_PASS=your_database_password
```

**Configuration Parameters:**
- `DB_HOST`: Database server address (default: localhost)
- `DB_PORT`: MySQL port (default: 3306)
- `DB_NAME`: Database name (default: starter)
- `DB_USERNAME`: MySQL username
- `DB_PASS`: MySQL password (leave empty if no password is set)

### Step 2: AI Chat Configuration

The application includes an AI-powered chat feature that uses a Large Language Model (LLM) API. Configure the API settings in your `.env` file:

```env
# LLM API Settings
LLM_API_KEY="your-api-key-here"
LLM_API_URL="https://api.deepseek.com/v1/chat/completions"
```

**Configuration Parameters:**
- `LLM_API_KEY`: Your API key for the LLM service
- `LLM_API_URL`: The endpoint URL for the LLM API

**Note**: The current configuration uses DeepSeek API. You can replace these values with any OpenAI-compatible API endpoint (e.g., OpenAI, Azure OpenAI, or other LLM providers).

### Step 2.1: Knowledge Base Storage Location

The knowledge base service stores data locally under the project root in the following directories:

- `storage/knowledge/uploads/`: Stores the original uploaded files
- `storage/knowledge/chroma_db/`: Stores ChromaDB persistent data, including vector indexes and metadata

If the project is deployed at `/var/www/html/AIChatforChildren`, the corresponding absolute paths are:

- `/var/www/html/AIChatforChildren/storage/knowledge/uploads/`
- `/var/www/html/AIChatforChildren/storage/knowledge/chroma_db/`

These directories are created automatically when the ChromaDB service starts.

### Step 3: Run Database Migrations

Execute the database migrations to create all necessary tables:

```bash
php database/migrate.php
```

This command will create the following database structure:
- **users** table: Stores user accounts with authentication and verification data
- **conversations** table: Stores chat conversation metadata for each user
- **messages** table: Stores individual messages within conversations

### Database Schema

#### Users Table
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

#### Conversations Table
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

#### Messages Table
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

**Optional - Rollback Migrations:**

If you need to rollback (drop) all tables, run:

```bash
php database/migrate.php --down
```

## Running the Application

### Option 1: Using Apache with .htaccess (Production Ready)

This is the recommended method for production deployment. The application uses `.htaccess` for URL rewriting and routing.

#### Prerequisites

1. **Apache Web Server** with `mod_rewrite` enabled
2. Project deployed to web server directory (e.g., `/var/www/html/`)

#### Step-by-Step Deployment Guide

##### 1. Install Dependencies

```bash
cd /var/www/html/AIChatforChildren
composer install --no-dev --optimize-autoloader
```

##### 2. Configure Base URL

The application uses `.htaccess` for URL rewriting. You must configure the `base_url` correctly based on your deployment path.

**Edit `config/config.php`:**

```php
<?php

return [
    /**
     * Application Base URL
     * - For root directory: '/'
     * - For subdirectory: '/subdirectory/'
     */
    'base_url' => '/AIChatforChildren/',  // Modify according to your deployment path

    'auth' => [
        'require_verification' => false,  // Email verification toggle
    ]
];
```

**Configuration Examples:**

| Deployment Location | base_url Setting | Access URL |
|---------------------|------------------|------------|
| Root directory | `'/'` | `http://yourdomain.com/` |
| Subdirectory | `'/myapp/'` | `http://yourdomain.com/myapp/` |
| This project | `'/AIChatforChildren/'` | `http://localhost/AIChatforChildren/` |

##### 3. Verify .htaccess Configuration

Ensure the `RewriteBase` in `.htaccess` matches your `base_url`:

```apache
<IfModule mod_rewrite.c>
RewriteEngine On

# Set rewrite base path (must match base_url in config.php)
RewriteBase /AIChatforChildren/

# Allow direct access to static files
RewriteCond %{REQUEST_URI} \.(css|js|jpg|jpeg|png|gif|svg|ico|woff|woff2|ttf|eot)$ [NC]
RewriteRule ^ - [L]

# Don't rewrite if file or directory exists
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d

# Redirect all requests to index.php
RewriteRule ^(.*)$ index.php [QSA,L]

</IfModule>
```

**Important:** `RewriteBase` and `base_url` must be identical!

##### 4. Enable Apache mod_rewrite

```bash
# Ubuntu/Debian
sudo a2enmod rewrite
sudo systemctl restart apache2

# CentOS/RHEL
# mod_rewrite is usually enabled by default
# Verify in httpd.conf: LoadModule rewrite_module modules/mod_rewrite.so
sudo systemctl restart httpd
```

##### 5. Configure Apache Virtual Host (Recommended)

Edit your Apache configuration file (e.g., `/etc/apache2/sites-available/000-default.conf`):

```apache
<VirtualHost *:80>
    ServerName localhost
    DocumentRoot /var/www/html

    # Allow .htaccess overrides
    <Directory /var/www/html/AIChatforChildren>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
```

**Note:** `AllowOverride All` is required for `.htaccess` to work!

##### 6. Set File Permissions

```bash
# Set proper permissions
chmod -R 755 /var/www/html/AIChatforChildren
chown -R www-data:www-data /var/www/html/AIChatforChildren

# Secure .env file
chmod 600 /var/www/html/AIChatforChildren/.env
```

##### 7. Start Apache Services

```bash
# Start Apache
sudo systemctl start apache2

# Start MySQL
sudo systemctl start mysql

# Enable auto-start on boot
sudo systemctl enable apache2
sudo systemctl enable mysql
```

##### 8. Create Administrator Account

First-time setup requires creating an admin account. Choose one method:

**Method A: Direct Database Insert**

```sql
INSERT INTO users (name, email, password, role, verification_status, created_at)
VALUES (
    'Admin',
    'admin@example.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: password
    'admin',
    'verified',
    NOW()
);
```

**Method B: Register Then Modify Role**

1. Register a new account via the sign-up page
2. Update the role in database:

```sql
UPDATE users SET role = 'admin', verification_status = 'verified' WHERE email = 'your@email.com';
```

##### 9. Access the Application

Open your browser and navigate to:

```
http://localhost/AIChatforChildren/
```

The system will automatically redirect to the sign-in page:

```
http://localhost/AIChatforChildren/sign-in
```

**Default Login (if using Method A):**
- Email: `admin@example.com`
- Password: `password`

**⚠️ Important:** Change the password immediately after first login!

#### How .htaccess Routing Works

The `.htaccess` file enables clean URLs by rewriting all requests through `index.php`:

```apache
RewriteEngine On                    # Enable URL rewriting
RewriteBase /AIChatforChildren/     # Set base path

# 1. Static files bypass routing
RewriteCond %{REQUEST_URI} \.(css|js|jpg|jpeg|png|gif|svg|ico|woff|woff2|ttf|eot)$ [NC]
RewriteRule ^ - [L]

# 2. Existing files/directories accessed directly
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d

# 3. All other requests forwarded to index.php
RewriteRule ^(.*)$ index.php [QSA,L]
```

**URL Routing Examples:**

| User Visits | Processing Flow |
|------------|-----------------|
| `/AIChatforChildren/` | → `index.php` → Router → Redirect to `/sign-in` |
| `/AIChatforChildren/sign-in` | → `index.php` → Router → `pages/auth/signin.php` |
| `/AIChatforChildren/admin/users` | → `index.php` → Router → Middleware → `pages/admin/users.php` |
| `/AIChatforChildren/assets/css/admin.css` | → Direct file access, bypasses PHP |

#### Troubleshooting

**Problem: All pages show 404**

**Solution:** Enable Apache `mod_rewrite` module

```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

**Problem: .htaccess not working**

**Solution:** Ensure `AllowOverride All` is set in Apache configuration

```apache
<Directory /var/www/html>
    AllowOverride All
</Directory>
```

**Problem: CSS/JS files not loading**

**Solution:** Verify `base_url` in `config/config.php` matches your deployment path

**Problem: After changing deployment path, links break**

**Solution:** Update both locations:
1. `.htaccess` → `RewriteBase`
2. `config/config.php` → `base_url`

They must be identical!

---

### Option 2: Using PHP Built-in Server (Development Only)

For quick development testing, you can use PHP's built-in server:

#### Main Application

```bash
php -S localhost:8080 -t .
```

Then open your browser and navigate to:
```
http://localhost:8080
```

The application will be accessible with the following features:
- User registration and login
- Email verification system
- Password recovery
- Role-based dashboards
- AI chat interface

#### Admin Management Interface

To access the administrator account management interface:

```bash
php -S localhost:8080 admin_management.php
```

Then open your browser and navigate to:
```
http://localhost:8080
```

**Admin Management Features:**
- Create new administrator accounts
- Edit existing administrator profiles
- Delete administrator accounts
- Manage user roles and permissions

**⚠️ Note:** PHP built-in server is suitable for development only. Use Apache + .htaccess for production.

## Project Structure

```
starter/
├── app/
│   ├── controllers/     # Application controllers
│   └── models/          # Data models
├── config/              # Configuration files
├── core/                # Core framework classes
├── database/
│   ├── migrations/      # Database migration files
│   └── migrate.php      # Migration runner
├── pages/               # View templates
│   ├── admin/           # Admin panel pages
│   ├── auth/            # Authentication pages
│   ├── child/           # Child role pages
│   ├── parent/          # Parent role pages
│   └── home.php         # Base entry pages        
├── utils/               # Utility functions
├── vendor/              # Composer dependencies
├── .env                 # Environment configuration
├── index.php            # Main application entry point
├── admin_management.php # Admin management entry point
└── composer.json        # Composer configuration
```

run chromaDB：
cd ./services/chroma
python3 main.py

visit router:
http://localhost:81/AIChatforChildren/sign-in


UNICEF《Guidance on AI and Children 3.0》
这份最适合放在你的“产品规则库/安全边界库”里，用来定义儿童友好 AI 的原则，比如安全、监督、隐私、透明、年龄适配、风险评估等。UNICEF 页面还提供了 完整版 PDF、checklist 和 poster 下载。
UNICEF《Policy Guidance on AI for Children 2.0》
这是前一版的系统性框架，适合和 3.0 配套使用。3.0 更新，2.0 更像“基础理论版”，做知识库时可以把两者结合，前者做规则，后者做解释。
NSPCC《Viewing Generative AI and children’s safety in the round》
这份很适合放入“风险场景库”。它聚焦生成式 AI 对儿童的具体风险，并总结了 27 类解决思路，还强调儿童安全要进入产品设计和治理流程。对你做“高风险话题拦截、异常依赖预警、求助转介规则”很有参考价值。
Common Sense Media《Talk, Trust, and Trade-Offs》
这份和“AI 伙伴/AI 陪伴”最贴近，直接讨论青少年使用 AI companions 的情况，并建议家庭协议、风险沟通，以及明确 AI 不能替代心理健康支持。做儿童陪伴产品时，这份特别适合抽出“家长须知”“使用边界”“防依赖提示语”。
UNICEF《Child Safety Online》
适合做“在线安全规则库”，涵盖网络风险、儿童保护、平台责任等内容。你的产品如果涉及开放聊天、图片、语音、社交化元素，这份很值得纳入。
CDC《Milestone Checklists by Age》
这是做“年龄分层对话知识库”非常实用的一组资料。它按月龄/年龄给出发展里程碑，而且页面明确提供各年龄段下载项；CDC 还提供 中文等多语言版本。你可以按 2 月、4 月、1 岁、2 岁、3 岁、4 岁、5 岁拆成不同知识分片。
CDC《Milestone Moments Booklet》
如果你想要一份更像“总览手册”的资料，这份比单张 checklist 更完整，适合做“年龄能力画像库”，帮助模型判断某年龄段更适合什么样的语言难度、互动方式和游戏建议。
WHO/UNICEF《Care for Child Development Participant Manual》
这份很适合做“陪伴对话内容库”。它核心是 游戏、沟通、回应式照护，非常贴近儿童陪伴场景，可以抽取成“陪聊脚本、亲子活动建议、互动任务模板”。
UNICEF《Learning through Play》
适合做“教育型陪伴知识库”。如果你的 AI 不只是聊天，还要会讲故事、做小游戏、引导观察、鼓励表达，这份关于 play-based learning 的材料很合适。
UNICEF《Caring for the Caregiver Overview Guide》
这份不是直接给孩子的，而是很适合做“家长支持库/监护人沟通库”。儿童陪伴软件如果设计得更完整，通常还需要输出给家长的解释、建议、观察提示，这份正好补这块。


UNICEF 中国《儿童网络安全家长小贴士》
中文 PDF，适合直接做“家长端 FAQ”或“监护提示库”。
ITU《保护在线儿童儿童指南》中文版
中文 PDF，适合做“儿童上网安全基础知识库”，尤其是安全提醒、陌生人风险、行为规范这类模块。
UNICEF 中国《数字时代的儿童》
这份更偏宏观背景，适合做“产品立项/需求分析/风险说明”的参考底稿，不如前两份那么直接面向对话，但对产品定位很有帮助。

#database settings

DB_HOST=localhost
DB_PORT=3306
DB_NAME=starter
DB_USERNAME=root
DB_PASS=
DB_SOCKET=/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock

# LLM API settings
LLM_API_KEY="sk-cfa070aac29141ac889edf44f8c84b21"
LLM_API_URL="https://api.deepseek.com/v1/chat/completions"

# ChromaDB Knowledge Base Service settings
CHROMA_PYTHON_PATH="python3"
CHROMA_SERVICE_HOST="127.0.0.1"
CHROMA_SERVICE_PORT="4001"
CHROMA_MAX_FILE_SIZE="20"
