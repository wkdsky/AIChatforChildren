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

### Step 3: Run Database Migrations

Execute the database migrations to create all necessary tables:

```bash
php database/migrate.php
```

This command will create the following database structure:
- **users** table: Stores user accounts with authentication and verification data

**Optional - Rollback Migrations:**

If you need to rollback (drop) all tables, run:

```bash
php database/migrate.php --down
```

## Running the Application

### Main Application

To start the main web application, run the following command in your terminal:

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

### Admin Management Interface

To access the administrator account management interface (for creating, editing, and deleting admin accounts), run:

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



