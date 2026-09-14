# 卡密访问控制系统

基于卡密认证的访问控制系统，包含用户前端、管理后台和后端API服务。

## 原始需求

> 前端1.帮我把这些代码加密，防止他人审查元素查看代码，防止查看网页源代码，全部加密成乱码2.需要在index.html 实现输入卡密才能载入到index.html页面，卡密需要后端生成
> 3.index.html需要实时检测卡密是否异常，异常则不能访问，另外不允许没卡密直接访问数据。4.前端输入卡密的UI需要美化适配手机端。
> 后端1.访问后端/admin 登录账号：admin 登录密码：daniaoge 2.后端可以批量生成卡密，自定义卡密前缀，可以批量删除卡密，可以批量导出卡密3.后端可以批量查看生成后的卡密数量，使用数量，过期数量，生成时间，使用时间4.后端生成的卡密限制12个字符5.后端如果点击该封卡，或者删除该卡密，那么前端就无法用卡密访问，会自动无法使用直接退出前端到输入卡密页面6.后端需要美化UI 布局整齐
> 另外增加
> 卡密有效期设置
> 详细的使用日志
> 卡密使用统计表
> 自动封禁过期卡密
> 自定义卡密生成规则
> 所有数据加密安全化做到最安全
> 依赖就用sqlite3
> 我这个是php项目
> 我需要的是直接上传liux服务器就可以使用
> 切记我不要求速度，我要求质量，一定要确定能直接使用。

## 技术栈

- **用户前端**: PHP（`php-frontend`）+ 原生 JavaScript + Tailwind 风格 CSS
- **管理后台**: PHP（`php-admin`）+ 原生 JavaScript + Tailwind 风格 CSS
- **后端**: PHP 8.3 + SQLite3
- **Web Server**: Nginx（统一容器内，监听 3009）
- **部署**: Docker Compose（根目录 `docker-compose.yml`）
- **HTTP 客户端**: fetch（浏览器原生）

### 技术选型说明

#### 前端技术栈（PHP 版本）

- **PHP 路由 + HTML 模板**: 无需 Node.js 构建，部署更直接
- **原生 JavaScript**: 使用 `localStorage + fetch`，无需额外前端构建
- **Tailwind 风格 CSS**: `php-frontend/assets/css/styles.css` + `php-admin/assets/css/admin-styles.css`

#### 后端技术栈

- **PHP 8.3**: 最新稳定版本，支持类型声明、枚举、只读属性
- **SQLite3**: 轻量级嵌入式数据库，零配置，单文件存储
- **PDO**: PHP 数据对象，支持预处理语句，防止 SQL 注入
- **bcrypt**: 密码哈希算法，自动加盐，抗彩虹表攻击
- **HMAC-SHA256**: 消息认证码，用于卡密哈希

#### 部署技术栈

- **Docker**: 容器化部署，环境一致性
- **Docker Compose**: 推荐统一容器（前端 + 管理后台 + 后端）
- **Nginx**: 高性能 Web 服务器，反向代理，静态文件服务
- **Supervisor**: 进程管理（Nginx + PHP-FPM）

### 核心技术特性

#### 1. 认证机制

```
用户输入卡密 → HMAC-SHA256 哈希 → 数据库匹配 → 生成 Token → 返回前端
前端保存 Token → 每次请求携带 → 后端验证 → 检查有效期 → 返回结果
心跳检测（30秒） → 验证 Token → 检查卡密状态 → 封禁/删除自动退出
```

#### 2. 数据加密

- **卡密**: `HMAC-SHA256(key, server_secret)` - 不可逆
- **密码**: `bcrypt(password, cost=10)` - 自动加盐
- **Token**: `SHA256(random_bytes(32))` - 64字符
- **IP地址**: `SHA256(ip)` - 隐私保护
- **User Agent**: `SHA256(ua)` - 隐私保护

#### 3. 防调试保护

- 禁用右键菜单
- 禁用 F12 快捷键
- 禁用 Ctrl+Shift+I（开发者工具）
- 禁用 Ctrl+U（查看源代码）
- 禁用 Ctrl+S（保存页面）
- 防止 iframe 嵌入

#### 5. 缓存控制

```nginx
# 完全禁用缓存（开发模式）
add_header Cache-Control "no-store, no-cache, must-revalidate" always;
add_header Pragma "no-cache" always;
add_header Expires "0" always;
etag off;
if_modified_since off;
```

#### 6. Docker 构建策略（unified）

- 后端依赖在镜像构建期执行 `composer install --no-dev`
- 前端/管理后台直接复制 PHP/静态资源，无需 Node.js 构建

## 功能特性

### 用户前端

- ✅ **路由预验证机制** - `/` 根据 localStorage token 自动跳转到 `/gate` 或 `/home`
- ✅ **30秒心跳检测** - 实时检测卡密状态
- ✅ **防调试保护** - 禁用F12、右键、查看源代码
- ✅ **移动端适配** - 响应式设计
- ✅ Token认证机制（12小时有效期）
- ✅ 卡密实时封禁/删除生效
- ✅ 话术生成功能
- ✅ 用户昵称管理

### 管理后台

- ✅ **管理员登录** - 初始账号 `admin / daniaoge`（仅用于首次登录，上线前必须修改，见「默认账号处理」）
- ✅ **批量生成卡密** - 自定义前缀、1-1000个、1-3650天
- ✅ **批量封禁/解封/删除** - 实时生效
- ✅ **导出卡密** - Excel（.xlsx，支持导出全部/导出选中）
- ✅ **数据统计** - 总数、使用数、过期数、今日数据等
- ✅ **详细日志** - 使用日志、管理员操作日志

### 后端API

- ✅ **卡密验证** - HMAC-SHA256加密存储
- ✅ **Token管理** - 12小时有效期
- ✅ **自动封禁过期卡密** - 验证时自动封禁
- ✅ **防爆破机制** - 5分钟内最多10次尝试
- ✅ **完整审计日志** - 记录所有操作
- ✅ 所有敏感信息哈希存储

## 快速开始

### 前置要求

- Docker
- Docker Compose

### 部署步骤

1. **克隆项目**

```bash
cd card-code
```

1. **启动服务**

```bash
docker-compose up -d --build
```

1. **访问系统**

- **用户前端**: http://localhost:3009/
- **管理后台**: http://localhost:3009/admin/login
- **默认管理员**: admin / daniaoge ⚠️ 仅限首次登录，正式使用前请立即改密并删除 TEST 测试卡密
- **测试卡密**: TEST00000001 ~ TEST00000010（详见TEST-KEYS.md）

1. **查看日志**

```bash
docker-compose logs -f unified-app
```

1. **停止服务**

```bash
docker-compose down
```

## Linux 服务器直接部署（上传即用）

除 Docker 外，本系统所有页面均为「免构建 PHP」，把代码上传到装有 Nginx/Apache + PHP-FPM 的 Linux 服务器（或宝塔 / 1Panel 等面板）即可运行。

### 1. 环境要求

| 组件 | 要求 | 说明 |
| --- | --- | --- |
| PHP | >= 8.0（推荐 8.2 / 8.3） | 需启用 FPM |
| 扩展 | `pdo_sqlite`（必需）、`sqlite3`（建议） | 系统通过 PDO 访问 SQLite |
| 函数 | `random_int` / `password_hash` / `hash_hmac` | PHP 内置，勿在 `disable_functions` 禁用 |
| Web 服务器 | Nginx 或 Apache | 需配置伪静态（URL Rewrite） |
| Composer | 仅首次部署需要 | 安装后端依赖（`vendor/` 已随仓库提供时可跳过） |

验证命令：

```bash
php -v                 # 查看 PHP 版本
php -m | grep -i sqlite  # 应能看到 pdo_sqlite、sqlite3
```

如缺少扩展：

```bash
# Debian / Ubuntu
sudo apt install -y php-sqlite3
sudo systemctl restart php8.3-fpm   # 按实际版本调整

# CentOS / RHEL
sudo yum install -y php-pdo php-sqlite3
sudo systemctl restart php-fpm

# 宝塔 / 1Panel：软件商店 → 对应 PHP → 安装扩展 → 打开 sqlite3 / pdo_sqlite
```

### 2. 上传后的目录结构与权限

站点根目录建议直接指向 `php-frontend/public`；`includes`、`assets` 放在其上一级。统一容器布局如下（自行部署时保持同样的相对关系即可）：

```text
/var/www/html/            # 站点根目录 = php-frontend/public
├── index.php  api.php  .htaccess
├── assets/
└── admin/                # = php-admin/public（管理后台，地址 /admin）
/var/www/includes/        # = php-frontend/includes
/var/www/admin-includes/  # = php-admin/includes
/app/backend/             # 后端 API（含 data/ 数据库目录、vendor/）
```

**必须保证 PHP-FPM 运行用户（Debian/Ubuntu 多为 `www-data`，CentOS 多为 `nginx`/`apache`）对以下目录可写：**

```bash
# 以下路径请按实际部署位置替换
WWW_USER=www-data

# 1) 数据库目录（最关键：SQLite 的 app.db 及 WAL/SHM 文件都在这里创建）
sudo mkdir -p /app/backend/data
sudo chown -R $WWW_USER:$WWW_USER /app/backend/data
sudo chmod 755 /app/backend/data

# 已存在数据库文件时
sudo chown $WWW_USER:$WWW_USER /app/backend/data/app.db*
sudo chmod 644 /app/backend/data/app.db

# 2) PHP / Web 日志目录
sudo mkdir -p /var/log/php
sudo chown -R $WWW_USER:$WWW_USER /var/log/php
sudo chmod 755 /var/log/php

# 3) 代码目录可读即可（不要给 777）
sudo chown -R $WWW_USER:$WWW_USER /var/www /app/backend
sudo find /var/www /app/backend -type d -exec chmod 755 {} \;
sudo find /var/www /app/backend -type f -exec chmod 644 {} \;
```

> 注意：SQLite 启用了 WAL 模式，除 `app.db` 外还会生成 `app.db-wal`、`app.db-shm`，因此**可写权限要给到 data 目录本身**，只给文件可写是不够的。请勿将数据库目录设置为 `777`。

Docker 部署时数据目录通过卷挂载，宿主机同样要可写：

```bash
mkdir -p ./data && chmod 755 ./data
```

### 3. 伪静态（URL Rewrite）配置

前端、后台、API 都依赖「把不存在的路径转发到 PHP 入口」，伪静态未生效会出现：页面刷新 404、`/api/...` 请求 404、无法登录。

**Nginx**（在站点 `server{}` 内添加；宝塔：站点设置 → 伪静态，粘贴即可）：

```nginx
root /var/www/html;
index index.php;

location /admin/ {
    try_files $uri /admin/index.php?$query_string;
}
location = /admin {
    return 301 /admin/;
}
location /assets/ {
    alias /var/www/html/assets/;
}
location /api/admin/ {
    rewrite ^/api/admin/(.*)$ /admin/api.php last;
}
location /api/ {
    rewrite ^/api/(.*)$ /api.php last;
}
location ~ \.php$ {
    fastcgi_pass 127.0.0.1:9000;          # 按实际 PHP-FPM 监听地址调整
    fastcgi_index index.php;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_param HTTP_AUTHORIZATION $http_authorization;
    include fastcgi_params;
}
location / {
    try_files $uri /index.php?$query_string;
}
```

```bash
sudo nginx -t && sudo systemctl reload nginx
```

**Apache**：代码包 `public/.htaccess` 已自带重写规则，需启用 `mod_rewrite` 并允许覆盖：

```bash
sudo a2enmod rewrite && sudo systemctl restart apache2
# 并在站点配置中设置 AllowOverride All
```

### 4. 默认账号处理（上线必做）

系统首次初始化会写入种子数据，其中包含**默认管理员与测试卡密，仅限测试，正式使用前必须处理**：

- 默认管理员：`admin` / `daniaoge`
- 测试卡密：`TEST` 前缀（TEST00000001 ~ TEST00000020）

请按以下顺序处理：

1. 用默认账号登录后台后，**立即修改管理员密码**（建议同时新建一个非 `admin` 的管理员并停用默认账号）；
2. 在「卡密管理」中删除全部 `TEST` 前缀的测试卡密；
3. 自检页会自动检测「默认管理员账号 / 是否仍为初始密码」，未处理会报红；
4. 生产环境务必配置 HTTPS，并建议为 `/admin` 路径增加 IP 白名单。

### 5. 部署自检（后台「🩺 部署自检」页面）

登录管理后台，侧边栏进入 **部署自检**（地址 `/admin/syscheck`），系统会在服务器端检测并对每项给出可直接复制的修复命令：

- **PHP 版本**：是否 >= 8.0；
- **SQLite3 / pdo_sqlite 扩展**：缺失时给出各发行版安装命令；
- **数据库文件权限**：data 目录、`app.db` 文件是否存在、属主与读写权限、PDO 实际连通性；
- **日志目录**：PHP `error_log` 目标目录与 Web 服务器日志目录是否可写；
- **伪静态配置**：服务端检查 Nginx/Apache 重写配置，浏览器端再用探针**实测** URL Rewrite 是否真正生效；
- **默认管理员账号**：是否仍是 `admin` / 初始密码；
- 另含核心加密函数可用性、服务器时区等检查。

安全说明：

- 自检信息包含路径、版本、权限等，**仅登录管理员可见**（接口 `GET /api/admin/system-check` 有 Token + Session 双重鉴权，未登录返回 4001）；
- **应急入口**：若数据库权限异常导致无法登录后台，可在服务器本机执行下面命令，生成一个 **5 分钟有效**的临时自检链接（不依赖数据库、不经过登录）：

```bash
php /app/backend/public/deploy-check.php issue
# 输出形如：/api/deploy/self-check?token=<过期时间>.<HMAC签名>
# 拼上域名在浏览器打开即可；令牌过期需重新生成，切勿外泄
```

## 系统架构

### 整体架构图

```text
┌─────────────────────────────────────────────────────────────┐
│                         用户浏览器                            │
│  ┌──────────────────┐              ┌──────────────────┐     │
│  │   用户前端        │              │   管理后台        │     │
│  │ (PHP + JS + CSS) │              │ (PHP + JS + CSS) │     │
│  └────────┬─────────┘              └────────┬─────────┘     │
└───────────┼──────────────────────────────────┼──────────────┘
            │                                  │
            │ HTTP/HTTPS                       │ HTTP/HTTPS
            │                                  │
┌───────────┼──────────────────────────────────┼──────────────┐
│           ▼                                  ▼               │
│  ┌─────────────────────────────────────────────────────┐    │
│  │                  Nginx (反向代理)                     │    │
│  │  - 静态文件服务 (前端/管理后台)                        │    │
│  │  - API 路由转发 (/api/* → PHP-FPM)                   │    │
│  │  - 管理后台路由 (/admin/* → PHP-FPM)                  │    │
│  └────────────────────┬────────────────────────────────┘    │
│                       │ FastCGI                              │
│                       ▼                                      │
│  ┌─────────────────────────────────────────────────────┐    │
│  │              PHP-FPM (后端应用)                       │    │
│  │  ┌──────────────────────────────────────────────┐   │    │
│  │  │  核心类库 (src/)                              │   │    │
│  │  │  - Database.php    (数据库连接)              │   │    │
│  │  │  - Auth.php        (认证核心)                │   │    │
│  │  │  - TokenManager.php (Token管理)              │   │    │
│  │  │  - KeyManager.php  (卡密管理)                │   │    │
│  │  │  - Logger.php      (日志记录)                │   │    │
│  │  └──────────────────────────────────────────────┘   │    │
│  │  ┌──────────────────────────────────────────────┐   │    │
│  │  │  路由层 (routes/)                             │   │    │
│  │  │  - auth.php        (认证路由)                │   │    │
│  │  │  - api.php         (业务路由)                │   │    │
│  │  └──────────────────────────────────────────────┘   │    │
│  │  ┌──────────────────────────────────────────────┐   │    │
│  │  │  管理后台 (admin/)                            │   │    │
│  │  │  - login.php       (登录页)                  │   │    │
│  │  │  - dashboard.php   (仪表盘)                  │   │    │
│  │  │  - keys.php        (卡密管理)                │   │    │
│  │  │  - logs.php        (日志查看)                │   │    │
│  │  └──────────────────────────────────────────────┘   │    │
│  └────────────────────┬────────────────────────────────┘    │
│                       │                                      │
│                       ▼                                      │
│  ┌─────────────────────────────────────────────────────┐    │
│  │              SQLite3 数据库                           │    │
│  │  - admin_user        (管理员表)                       │    │
│  │  - license_key       (卡密表)                         │    │
│  │  - key_batch         (卡密批次表)                     │    │
│  │  - access_token      (访问令牌表)                     │    │
│  │  - key_usage_log     (卡密使用日志)                   │    │
│  │  - admin_op_log      (管理员操作日志)                 │    │
│  │  - system_config     (系统配置表)                     │    │
│  │  - problem_types     (问题类型表)                     │    │
│  │  - templates         (模板表)                         │    │
│  └─────────────────────────────────────────────────────┘    │
│                                                              │
│                    Docker Compose 容器编排                    │
└──────────────────────────────────────────────────────────────┘
```

### 数据流向

#### 1. 用户认证流程

```text
用户输入卡密
    ↓
前端验证格式 (12字符)
    ↓
POST /api/auth/verify-key
    ↓
后端计算 key_hash = HMAC-SHA256(key, server_secret)
    ↓
查询数据库匹配 key_hash
    ↓
检查状态 (active/banned/deleted)
    ↓
检查过期时间
    ↓
生成 token (随机64字符)
    ↓
存储 token_hash 到数据库
    ↓
返回 token 给前端
    ↓
前端保存到 localStorage
    ↓
跳转到 Home 页面
```

#### 2. 心跳检测流程

```text
前端每30秒执行
    ↓
GET /api/auth/ping (携带 token)
    ↓
后端验证 token_hash
    ↓
检查 token 是否过期
    ↓
检查关联卡密状态
    ↓
返回 {valid: true/false, key_status: 'active/banned/deleted'}
    ↓
前端判断结果
    ↓
如果 invalid 或 banned/deleted → 清除 token → 跳转到 Gate 页面
```

#### 3. 卡密管理流程

```text
管理员登录后台
    ↓
批量生成卡密 (前缀 + 数量 + 有效期)
    ↓
后端生成随机卡密 (12字符)
    ↓
计算 key_hash 存储到数据库
    ↓
返回明文卡密 (仅此时可见)
    ↓
管理员导出 CSV
    ↓
---
管理员封禁卡密
    ↓
更新数据库 status = 'banned'
    ↓
前端心跳检测到 banned
    ↓
自动退出到 Gate 页面
```

## 项目结构

```text
card-code/
├── backend/                      # PHP 后端（API）
│   ├── composer.json
│   ├── migrations/               # init.sql / seed.sql
│   ├── public/                   # index.php（API 统一入口）
│   ├── routes/                   # api.php / auth.php / admin.php
│   └── src/                      # Database/Auth/KeyManager/Logger...
│
├── php-frontend/                 # 用户前端（PHP + JS + CSS）
│   ├── public/                   # index.php / api.php / .htaccess
│   ├── includes/                 # gate.php / home.php / config.php
│   └── assets/                   # css/styles.css + js/*
│
├── php-admin/                    # 管理后台（PHP + JS + CSS）
│   ├── public/                   # index.php / api.php / .htaccess
│   ├── includes/                 # login/dashboard/keys/logs + config.php
│   └── assets/                   # css/admin-styles.css + js/*
│
│
├── docker/                       # Nginx/Supervisor 等配置
│   ├── nginx-unified.conf
│   └── supervisord-unified.conf
│
├── data/                         # 数据持久化（SQLite）
│   └── app.db
│
├── Dockerfile.unified            # 统一容器镜像（Nginx + PHP-FPM + Backend + PHP Frontend/Admin）
├── docker-compose.yml            # 推荐入口（统一容器，端口 3009）
└── README.md
```

## 数据库设计

### 表结构

#### 1. admin_user (管理员表)

```sql
CREATE TABLE admin_user (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,  -- bcrypt加密
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);
```

#### 2. license_key (卡密表)

```sql
CREATE TABLE license_key (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    batch_id INTEGER,
    key_hash TEXT NOT NULL UNIQUE,  -- HMAC-SHA256(key, server_secret)
    key_encrypted TEXT,             -- AES-256-CBC(base64)，用于管理后台展示明文
    status TEXT NOT NULL DEFAULT 'active',  -- active/banned/deleted
    expire_at TEXT NOT NULL,
    created_at TEXT NOT NULL,
    first_used_at TEXT,
    last_used_at TEXT,
    nickname TEXT,
    FOREIGN KEY (batch_id) REFERENCES key_batch(id)
);
CREATE INDEX idx_key_hash ON license_key(key_hash);
CREATE INDEX idx_status ON license_key(status);
```

#### 3. key_batch (卡密批次表)

```sql
CREATE TABLE key_batch (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    prefix TEXT,
    count INTEGER NOT NULL,
    expire_days INTEGER NOT NULL,
    created_by INTEGER NOT NULL,
    created_at TEXT NOT NULL,
    FOREIGN KEY (created_by) REFERENCES admin_user(id)
);
```

#### 4. access_token (访问令牌表)

```sql
CREATE TABLE access_token (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    token_hash TEXT NOT NULL UNIQUE,  -- SHA256(token)
    key_id INTEGER NOT NULL,
    expire_at TEXT NOT NULL,
    created_at TEXT NOT NULL,
    last_seen_at TEXT NOT NULL,
    ip_hash TEXT,  -- SHA256(ip)
    ua_hash TEXT,  -- SHA256(user_agent)
    FOREIGN KEY (key_id) REFERENCES license_key(id)
);
CREATE INDEX idx_token_hash ON access_token(token_hash);
CREATE INDEX idx_expire_at ON access_token(expire_at);
```

#### 5. key_usage_log (卡密使用日志)

```sql
CREATE TABLE key_usage_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    key_id INTEGER,
    action TEXT NOT NULL,  -- verify/ping/access
    ip TEXT NOT NULL,
    user_agent TEXT,
    result TEXT NOT NULL,  -- success/failed
    reason TEXT,
    created_at TEXT NOT NULL,
    FOREIGN KEY (key_id) REFERENCES license_key(id)
);
CREATE INDEX idx_created_at ON key_usage_log(created_at);
```

#### 6. admin_op_log (管理员操作日志)

```sql
CREATE TABLE admin_op_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    admin_id INTEGER NOT NULL,
    action TEXT NOT NULL,  -- generate/ban/unban/delete/export
    details TEXT,
    ip TEXT NOT NULL,
    created_at TEXT NOT NULL,
    FOREIGN KEY (admin_id) REFERENCES admin_user(id)
);
CREATE INDEX idx_admin_created_at ON admin_op_log(created_at);
```

#### 7. system_config (系统配置表)

```sql
CREATE TABLE system_config (
    key TEXT PRIMARY KEY,
    value TEXT NOT NULL,
    updated_at TEXT NOT NULL
);
```

**关键配置：**

- `server_secret`: HMAC密钥（用于卡密哈希）
- `token_expire_hours`: Token有效期（默认12小时）
- `heartbeat_interval`: 心跳间隔（默认30秒）
- `max_attempts`: 最大尝试次数（默认10次/5分钟）

#### 8. problem_types (问题类型表)

```sql
CREATE TABLE problem_types (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    description TEXT,
    sort_order INTEGER DEFAULT 0,
    created_at TEXT NOT NULL
);
```

#### 9. templates (模板表)

```sql
CREATE TABLE templates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    problem_type_id INTEGER NOT NULL,
    template_type TEXT NOT NULL,  -- SLOGAN/APPLICATION
    title TEXT NOT NULL,
    content TEXT NOT NULL,
    created_at TEXT NOT NULL,
    FOREIGN KEY (problem_type_id) REFERENCES problem_types(id)
);
CREATE INDEX idx_problem_type ON templates(problem_type_id);
```

### 数据加密策略

| 数据类型   | 加密方式    | 存储格式      | 可逆性    |
| ---------- | ----------- | ------------- | --------- |
| 卡密       | HMAC-SHA256 | key_hash      | ❌ 不可逆 |
| 管理员密码 | bcrypt      | password_hash | ❌ 不可逆 |
| Token      | SHA256      | token_hash    | ❌ 不可逆 |
| IP地址     | SHA256      | ip_hash       | ❌ 不可逆 |
| User Agent | SHA256      | ua_hash       | ❌ 不可逆 |

**安全原则：**

- 所有敏感信息只存储哈希值
- 卡密明文仅在生成时返回一次
- Token明文仅在验证成功时返回一次
- 数据库中无法反推原始数据

## 数据库初始化

系统首次启动时会自动执行数据库初始化：

1. 创建所有表结构（init.sql）
2. 插入种子数据（seed.sql）
   - 默认管理员账号
   - 系统配置
   - 10种问题类型
   - 50个模板数据
   - 20个测试卡密

## API接口

### 统一响应格式

所有API接口统一返回HTTP 200，通过响应体中的code字段区分成功和失败：

```json
{
	"code": 0, // 0=成功，其他=错误码
	"message": "操作成功", // 中文提示信息
	"data": {} // 业务数据
}
```

**错误码规范：**

- **1xxx**: 认证相关错误
  - 1001: 卡密无效或已失效
  - 1002: 登录已过期，请重新验证
  - 1003: 卡密已被封禁或删除
  - 1004: 尝试次数过多，请稍后再试
- **2xxx**: 业务相关错误
  - 2001: 生成失败，请重试
  - 2002: 参数错误
  - 2003: 数据不存在
- **5xxx**: 系统相关错误
  - 5000: 服务器内部错误

### 认证接口

#### POST /api/auth/verify-key

验证卡密并返回Token

**请求：**

```json
{
	"key": "TEST00000001"
}
```

**响应：**

```json
{
	"code": 0,
	"message": "验证成功",
	"data": {
		"token": "538dc863582b91057c688bd754a8b99ed7a3c10c1ee96cc02fc24bd38d1c0877",
		"expire_at": "2026-01-28 23:38:22"
	}
}
```

**错误响应：**

```json
{
	"code": 1001,
	"message": "卡密无效或已失效",
	"data": null
}
```

#### GET /api/auth/ping

心跳检测，验证Token和卡密状态

**请求头：**

```
Authorization: Bearer <token>
```

**响应：**

```json
{
	"code": 0,
	"message": "检测成功",
	"data": {
		"valid": true,
		"key_status": "active"
	}
}
```

#### POST /api/auth/logout

登出，撤销Token

**请求头：**

```
Authorization: Bearer <token>
```

**响应：**

```json
{
	"code": 0,
	"message": "已退出登录",
	"data": null
}
```

### 业务接口

#### GET /api/problem-types

获取问题类型列表

**响应：**

```json
{
	"code": 0,
	"message": "获取成功",
	"data": [
		{
			"id": 1,
			"name": "虚假宣传",
			"description": "商家虚假宣传相关问题"
		}
	]
}
```

#### GET /api/templates

获取模板列表

**请求参数：**

- `problemTypeId`: 问题类型ID（必填）
- `templateType`: 模板类型（可选，SLOGAN/APPLICATION）

**响应：**

```json
{
	"code": 0,
	"message": "获取成功",
	"data": [
		{
			"id": 1,
			"title": "虚假宣传话术模板1",
			"content": "模板内容..."
		}
	]
}
```

#### POST /api/generate/slogan

生成话术

**请求：**

```json
{
	"problemTypeId": 1,
	"customInput": "用户自定义输入"
}
```

**响应：**

```json
{
	"code": 0,
	"message": "生成成功",
	"data": {
		"content": "生成的话术内容..."
	}
}
```

#### POST /api/user/update-nickname

更新用户昵称

**请求：**

```json
{
	"nickname": "新昵称"
}
```

**响应：**

```json
{
	"code": 0,
	"message": "更新成功",
	"data": null
}
```

#### GET /api/user/info

获取用户信息

**响应：**

```json
{
	"code": 0,
	"message": "获取成功",
	"data": {
		"nickname": "用户昵称",
		"expire_at": "2026-01-28 23:38:22"
	}
}
```

## 技术实现细节

### 前端技术栈


#### PHP + 原生 JavaScript

- **PHP 路由入口**: 使用 `index.php` 和 `.htaccess` 处理页面访问
- **原生 JavaScript**: 使用 `fetch + localStorage` 管理认证和交互
- **自定义样式**: 使用 `styles.css` 和 `admin-styles.css` 提供界面样式
- **免构建部署**: 无需 Node.js 构建即可直接部署到服务器

#### 防调试保护 (index.html)

```javascript
// 禁用右键菜单
document.addEventListener('contextmenu', (e) => e.preventDefault())

// 禁用F12、Ctrl+Shift+I、Ctrl+U、Ctrl+S
document.addEventListener('keydown', (e) => {
	if (
		e.key === 'F12' ||
		(e.ctrlKey && e.shiftKey && e.key === 'I') ||
		(e.ctrlKey && e.key === 'u') ||
		(e.ctrlKey && e.key === 's')
	) {
		e.preventDefault()
	}
})

// 检测开发者工具
const devtools = /./
devtools.toString = function () {
	this.opened = true
}
setInterval(() => {
	console.log('%c', devtools)
	if (devtools.opened) {
		document.body.innerHTML = '<h1>检测到调试工具，页面已锁定</h1>'
	}
}, 1000)

// 防止iframe嵌入
if (window.self !== window.top) {
	window.top.location = window.self.location
}
```

#### 预验证机制 (index.html)

```javascript
// 在进入业务页面前验证卡密
const token = localStorage.getItem('token')
if (!token) {
	// 显示卡密输入界面
	showGateUI()
} else {
	// 验证token有效性
	fetch('/api/auth/ping', {
		headers: { Authorization: `Bearer ${token}` },
	})
		.then((res) => res.json())
		.then((data) => {
			if (data.code === 0 && data.data.valid) {
				// 进入主页
				window.location.href = '/home'
			} else {
				// 清除token，显示卡密输入界面
				localStorage.clear()
				showGateUI()
			}
		})
}
```

#### 心跳检测 (home.js)

```javascript
const interval = setInterval(async () => {
	try {
		const result = await API.get('/auth/ping')
		if (!result.valid || result.key_status !== 'active') {
			localStorage.removeItem('token')
			localStorage.removeItem('token_expire_at')
			Toast.show('登录已失效，请重新验证', 'warning')
			window.location.href = '/gate'
		}
	} catch (error) {
		console.error('心跳检测失败:', error)
	}
}, 30000)
```

### 后端技术栈

#### PHP 8.2 + SQLite3

- **PHP 8.2**: 最新稳定版本，支持类型声明和属性
- **SQLite3**: 轻量级数据库，无需额外配置
- **PDO**: PHP数据对象，支持预处理语句
- **bcrypt**: 密码哈希算法
- **HMAC-SHA256**: 卡密哈希算法

#### 核心类库设计

**Database.php (单例模式)**

```php
class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        $dbPath = getenv('DB_PATH') ?: './data/app.db';
        $this->pdo = new PDO("sqlite:$dbPath");
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('PRAGMA foreign_keys = ON');
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function query($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
}
```

**Auth.php (认证核心)**

```php
class Auth {
    public static function verifyKey($keyPlain) {
        // 1. 输入验证
        if (empty($keyPlain) || strlen($keyPlain) !== 12) {
            return Response::error(1001, '卡密格式不正确');
        }

        // 2. 计算key_hash
        $serverSecret = Config::get('server_secret');
        $keyHash = hash_hmac('sha256', $keyPlain, $serverSecret);

        // 3. 查询数据库
        $db = Database::getInstance();
        $stmt = $db->query(
            'SELECT * FROM license_key WHERE key_hash = ?',
            [$keyHash]
        );
        $key = $stmt->fetch(PDO::FETCH_ASSOC);

        // 4. 检查卡密
        if (!$key) {
            Logger::log('verify', null, 'failed', '卡密不存在');
            return Response::error(1001, '卡密无效或已失效');
        }

        if ($key['status'] !== 'active') {
            Logger::log('verify', $key['id'], 'failed', '卡密已被封禁或删除');
            return Response::error(1003, '卡密已被封禁或删除');
        }

        if (strtotime($key['expire_at']) < time()) {
            Logger::log('verify', $key['id'], 'failed', '卡密已过期');
            return Response::error(1001, '卡密已过期');
        }

        // 5. 生成token
        $token = TokenManager::generate($key['id']);
        $expireAt = date('Y-m-d H:i:s', time() + 12 * 3600);

        // 6. 更新使用时间
        $db->query(
            'UPDATE license_key SET first_used_at = COALESCE(first_used_at, ?), last_used_at = ? WHERE id = ?',
            [date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), $key['id']]
        );

        // 7. 记录日志
        Logger::log('verify', $key['id'], 'success', '验证成功');

        return Response::success([
            'token' => $token,
            'expire_at' => $expireAt
        ]);
    }
}
```

**TokenManager.php (Token管理)**

```php
class TokenManager {
    public static function generate($keyId) {
        // 生成随机token (64字符)
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);

        // 存储到数据库
        $db = Database::getInstance();
        $expireAt = date('Y-m-d H:i:s', time() + 12 * 3600);
        $db->query(
            'INSERT INTO access_token (token_hash, key_id, expire_at, created_at, last_seen_at) VALUES (?, ?, ?, ?, ?)',
            [$tokenHash, $keyId, $expireAt, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]
        );

        return $token;
    }

    public static function validate($token) {
        $tokenHash = hash('sha256', $token);

        $db = Database::getInstance();
        $stmt = $db->query(
            'SELECT * FROM access_token WHERE token_hash = ?',
            [$tokenHash]
        );
        $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$tokenData) {
            return false;
        }

        if (strtotime($tokenData['expire_at']) < time()) {
            return false;
        }

        // 更新最后访问时间
        $db->query(
            'UPDATE access_token SET last_seen_at = ? WHERE token_hash = ?',
            [date('Y-m-d H:i:s'), $tokenHash]
        );

        return $tokenData['key_id'];
    }
}
```

**KeyManager.php (卡密管理)**

```php
class KeyManager {
    public static function generateKeys($prefix, $count, $expireDays, $adminId) {
        $keys = [];
        $serverSecret = Config::get('server_secret');
        $db = Database::getInstance();

        // 创建批次
        $db->query(
            'INSERT INTO key_batch (prefix, count, expire_days, created_by, created_at) VALUES (?, ?, ?, ?, ?)',
            [$prefix, $count, $expireDays, $adminId, date('Y-m-d H:i:s')]
        );
        $batchId = $db->lastInsertId();

        // 生成卡密
        for ($i = 0; $i < $count; $i++) {
            $key = self::generateKey($prefix, 12);
            $keyHash = hash_hmac('sha256', $key, $serverSecret);
            $expireAt = date('Y-m-d H:i:s', time() + $expireDays * 86400);

            $db->query(
                'INSERT INTO license_key (batch_id, key_hash, status, expire_at, created_at) VALUES (?, ?, ?, ?, ?)',
                [$batchId, $keyHash, 'active', $expireAt, date('Y-m-d H:i:s')]
            );

            $keys[] = $key;
        }

        // 记录操作日志
        Logger::logAdmin($adminId, 'generate', "生成{$count}个卡密，前缀:{$prefix}");

        return $keys;
    }

    private static function generateKey($prefix, $length) {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $prefixLen = strlen($prefix);
        $randomLen = $length - $prefixLen;

        $random = '';
        for ($i = 0; $i < $randomLen; $i++) {
            $random .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $prefix . $random;
    }
}
```

### Docker 部署（推荐：统一容器）

#### docker-compose.yml（根目录）

```yaml
services:
  unified-app:
    build:
      context: .
      dockerfile: Dockerfile.unified
    container_name: card-code-unified
    ports:
      - '3009:3009'
    volumes:
      - ./data:/app/backend/data
    environment:
      - DB_PATH=/app/backend/data/app.db
      - PYTHONUNBUFFERED=1
    restart: unless-stopped
```

#### Dockerfile.unified（要点）

- 基础镜像：`php:8.2-fpm`
- Nginx 监听：3009（配置：`docker/nginx-unified.conf`）
- Supervisor 管理：Nginx + PHP-FPM（配置：`docker/supervisord-unified.conf`）
- 后端：复制 `backend/` 并执行 `composer install --no-dev`
- 用户前端：复制 `php-frontend/` 到 `/var/www/html` 和 `/var/www/includes`
- 管理后台：复制 `php-admin/` 到 `/var/www/html/admin` 和 `/var/www/admin-includes`

### 业务接口

- `GET /api/problem-types` - 获取问题类型
- `GET /api/templates` - 获取模板列表
- `POST /api/generate/slogan` - 生成话术
- `POST /api/user/update-nickname` - 更新昵称
- `GET /api/user/info` - 获取用户信息

## 性能优化

### 前端性能

#### 1. 构建优化

- **免构建交付**: 页面和脚本可直接由 Nginx + PHP-FPM 提供
- **静态资源直出**: 样式和脚本无需额外打包步骤
- **压缩混淆**: 生产环境自动压缩和混淆
- **按需请求 API**: 页面只在需要时请求认证和业务接口

#### 2. 运行时优化

- **轻量运行时**: 页面逻辑基于原生 JavaScript，依赖更少
- **心跳检测**: 仅保留必要的 30 秒状态校验
- **防抖节流**: 表单输入、搜索等场景
- **渐进增强**: 表单、提示、弹窗均按页面场景独立初始化

#### 3. 网络优化

- **统一请求封装**: 统一处理请求、认证头和响应错误
- **请求取消**: 避免重复请求
- **错误重试**: 网络错误自动重试
- **缓存策略**: localStorage 缓存 token

### 后端性能

#### 1. 数据库优化

```sql
-- 关键索引
CREATE INDEX idx_key_hash ON license_key(key_hash);
CREATE INDEX idx_token_hash ON access_token(token_hash);
CREATE INDEX idx_expire_at ON access_token(expire_at);
CREATE INDEX idx_status ON license_key(status);
```

#### 2. 查询优化

- **预处理语句**: 防止 SQL 注入，提高性能
- **单例模式**: 数据库连接复用
- **批量操作**: 批量生成卡密时使用事务
- **索引覆盖**: 查询字段都在索引中

#### 3. 缓存策略

- **配置缓存**: 系统配置缓存在内存
- **Token 验证**: 减少数据库查询
- **日志异步**: 日志记录不阻塞主流程

### Nginx 优化

```nginx
# Gzip 压缩
gzip on;
gzip_vary on;
gzip_min_length 1024;
gzip_types text/plain text/css text/javascript application/javascript application/json;

# 连接优化
keepalive_timeout 65;
keepalive_requests 100;

# 缓存策略（生产环境）
location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg)$ {
    expires 1y;
    add_header Cache-Control "public, immutable";
}
```

### Docker 优化

#### 1. 镜像优化

- **统一容器**: Nginx + PHP-FPM + 后端 + PHP 前端/管理后台合并部署
- **Composer 生产依赖**: 构建期 `composer install --no-dev`
- **无 Node.js 构建**: PHP 前端/管理后台直接复制文件即可

#### 2. 资源限制

```yaml
services:
  unified-app:
    deploy:
      resources:
        limits:
          cpus: '0.5'
          memory: 512M
        reservations:
          cpus: '0.25'
          memory: 256M
```

## 最佳实践

### 开发流程

#### 1. 本地开发

```bash
# 推荐：直接用统一容器开发（无需 Node.js）
docker-compose up -d --build

# 后端开发
cd backend
php -S localhost:8000 -t public
```

#### 2. 代码规范

- **ESLint**: JavaScript 代码检查
- **Prettier**: 代码格式化
- **PHP CS Fixer**: PHP 代码规范
- **Git Hooks**: 提交前自动检查

#### 3. 测试策略

- **单元测试**: 核心业务逻辑
- **集成测试**: API 接口测试
- **E2E 测试**: 关键用户流程
- **安全测试**: SQL 注入、XSS 等

### 部署流程

#### 1. 开发环境

```bash
# 完全重新部署
docker-compose down
docker-compose up -d --build

# 查看日志
docker-compose logs -f unified-app

# 进入容器调试
docker-compose exec unified-app sh
```

#### 2. 生产环境

```bash
# 1. 备份数据库
cp data/app.db data/app.db.backup

# 2. 拉取最新代码
git pull origin main

# 3. 构建镜像
docker-compose build --no-cache

# 4. 滚动更新
docker-compose up -d

# 5. 验证部署
curl -I http://localhost:3009/
docker-compose ps
```

#### 3. 监控和日志

```bash
# 查看容器状态
docker-compose ps

# 查看资源使用
docker stats

# 查看日志
docker-compose logs -f unified-app

# 导出日志
docker-compose logs > logs.txt
```

### 安全加固

#### 1. 生产环境配置

- ✅ 修改默认管理员密码（`admin / daniaoge`），建议同时更换默认用户名
- ✅ 删除全部 TEST 前缀的种子测试卡密
- ✅ 上线后在后台「🩺 部署自检」页面确认全部检查通过
- ✅ 配置 HTTPS（Let's Encrypt）
- ✅ 限制管理后台访问 IP
- ✅ 启用防火墙（UFW/iptables）
- ✅ 定期更新依赖
- ✅ 定期备份数据库

#### 2. Nginx 安全配置

```nginx
# 隐藏版本信息
server_tokens off;

# 限制请求大小
client_max_body_size 10M;

# 限制请求速率
limit_req_zone $binary_remote_addr zone=api:10m rate=10r/s;
limit_req zone=api burst=20 nodelay;

# SSL 配置（生产环境）
ssl_protocols TLSv1.2 TLSv1.3;
ssl_ciphers HIGH:!aNULL:!MD5;
ssl_prefer_server_ciphers on;
```

#### 3. 数据库安全

```bash
# 设置目录/文件属主与权限（www-data 替换为实际 PHP-FPM 运行用户）
chown -R www-data:www-data data
chmod 755 data
chmod 644 data/app.db
# 注意：SQLite 使用 WAL 模式，data 目录本身必须对 PHP 运行用户可写
# （app.db-wal / app.db-shm 也在该目录创建），切勿仅给文件授权

# 定期备份
0 2 * * * cp /path/to/data/app.db /path/to/backup/app.db.$(date +\%Y\%m\%d)

# 清理过期日志（保留30天）
DELETE FROM key_usage_log WHERE created_at < datetime('now', '-30 days');
```

### 故障排查

#### 1. 常见问题

| 问题         | 原因             | 解决方案                                         |
| ------------ | ---------------- | ------------------------------------------------ |
| 端口被占用   | 3009端口已被使用 | 修改 docker-compose.yml 端口映射                 |
| 数据库锁定   | SQLite 并发写入  | 检查是否有长时间运行的事务                       |
| 前端白屏     | JS 加载失败      | 查看 unified-app 内的 Nginx 日志，清除浏览器缓存 |
| API 500 错误 | PHP 错误         | 查看 unified-app 容器日志（Nginx/PHP-FPM）       |
| Token 失效   | 时间不同步       | 检查服务器时间，同步 NTP                         |

#### 2. 调试技巧

```bash
# 查看详细错误
docker-compose logs unified-app | grep -i error

# 进入容器调试
docker-compose exec unified-app sh
php -v
php -m  # 查看已安装的扩展

# 测试 API
curl -X POST http://localhost:3009/api/auth/verify-key \
  -H "Content-Type: application/json" \
  -d '{"key":"TEST00000001"}'

# 查看数据库（推荐在宿主机执行）
sqlite3 data/app.db
.tables
SELECT * FROM license_key LIMIT 5;
```

## 安全特性

### 前端安全

1. **代码混淆**
   - JavaScript代码高强度混淆
   - 字符串加密（Base64编码）
   - 控制流平坦化
   - 死代码注入
   - 自我防御机制

2. **防调试保护**
   - 禁用右键菜单
   - 禁用F12开发者工具
   - 禁用Ctrl+U查看源代码
   - 禁用Ctrl+S保存页面
   - 检测开发者工具打开（自动锁定页面）
   - 防止iframe嵌入
   - 定期反调试检测

3. **预验证机制**
   - 在进入业务页面前验证卡密
   - 验证通过后才加载应用代码
   - 实时心跳检测（30秒）
   - Token失效自动退出

### 后端安全

1. **数据加密**
   - 卡密：HMAC-SHA256存储
   - 密码：bcrypt加密
   - Token：SHA256存储
   - IP地址：SHA256哈希

2. **访问控制**
   - Token验证中间件
   - 防爆破机制（5分钟10次）
   - 自动封禁过期卡密
   - Session管理（管理后台）

3. **审计日志**
   - 完整的使用日志
   - 管理员操作日志
   - IP和UA记录
   - 时间戳记录

4. **输入验证**
   - SQL预处理语句防注入
   - 表单验证（前后端双重验证）
   - 中文错误信息
   - 统一响应格式

## 开发模式

### 后端开发

```bash
cd backend
php -S localhost:8000 -t public
```

### 前端开发

```bash
docker-compose up -d --build
```

访问: <http://localhost:3009>

## 环境变量

### 后端

- `DB_PATH` - 数据库文件路径（默认: ./data/app.db）

## 验收标准

- ✅ Docker一键启动
- ✅ 无卡密无法访问业务页面
- ✅ 有效卡密可正常访问
- ✅ 封禁卡密30秒内自动退出
- ✅ 数据库无明文敏感信息
- ✅ 所有接口返回HTTP 200
- ✅ 错误信息为中文
- ✅ 无原生alert/confirm
- ✅ 所有表单具备验证
- ✅ 时间格式统一（YYYY-MM-DD HH:mm:ss）

## 故障排查

### 数据库权限问题

SQLite 启用 WAL 模式，data 目录及 app.db 都要对 PHP-FPM 运行用户可写（Debian/Ubuntu 为 www-data）：

```bash
sudo chown -R www-data:www-data data
sudo chmod 755 data
sudo chmod 644 data/app.db
# 如已生成 WAL/SHM 文件，属主一并修正
sudo chown www-data:www-data data/app.db-wal data/app.db-shm 2>/dev/null
```

也可以登录后台「🩺 部署自检」页面，按数据库权限、伪静态等项目给出的修复命令逐项处理；
后台无法登录时，在服务器执行 `php backend/public/deploy-check.php issue` 生成临时自检链接。

### 伪静态 / 扩展缺失

- 页面刷新或访问 `/api/...` 出现 404：Nginx/Apache 伪静态未生效，见上文「Linux 服务器直接部署 → 伪静态配置」。
- 提示找不到 SQLite 驱动：安装 `pdo_sqlite`（建议同时安装 `sqlite3`）并重启 PHP-FPM。

### 查看容器日志

```bash
docker-compose logs unified-app
```

### 重新构建

```bash
docker-compose down
docker-compose build --no-cache
docker-compose up -d
```
