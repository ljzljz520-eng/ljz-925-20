<?php

namespace App;

/**
 * Linux 部署环境自检类
 *
 * 检查 PHP 版本、SQLite3 扩展、数据库文件权限、
 * 日志目录可写性、伪静态配置以及默认账号安全等项目，
 * 对不满足要求的项目给出可执行的修复建议。
 *
 * 注意：本类输出的信息仅允许管理员查看（需经过管理接口鉴权）。
 */
class SystemChecker
{
    /** 最低支持的 PHP 版本 */
    private const MIN_PHP_VERSION = '8.0.0';

    /**
     * 执行全部部署自检
     *
     * @return array{summary:array, environment:array, checks:array}
     */
    public static function runAll(): array
    {
        $checks = array_merge(
            self::checkPhpVersion(),
            self::checkRequiredExtensions(),
            self::checkFunctionsAvailable(),
            self::checkDatabase(),
            self::checkLogDirectory(),
            self::checkRewriteConfig(),
            self::checkDefaultAccount(),
            self::checkServerTime()
        );

        $total = count($checks);
        $passCount = 0;
        $warnCount = 0;
        $failCount = 0;

        foreach ($checks as $check) {
            if ($check['status'] === 'pass') {
                $passCount++;
            } elseif ($check['status'] === 'warn') {
                $warnCount++;
            } else {
                $failCount++;
            }
        }

        $overall = 'pass';
        if ($failCount > 0) {
            $overall = 'fail';
        } elseif ($warnCount > 0) {
            $overall = 'warn';
        }

        return [
            'summary' => [
                'overall' => $overall,
                'total' => $total,
                'pass' => $passCount,
                'warn' => $warnCount,
                'fail' => $failCount,
                'checked_at' => date('Y-m-d H:i:s'),
            ],
            'environment' => self::getEnvironment(),
            'checks' => $checks,
        ];
    }

    /**
     * 伪静态运行时探测探针返回的数据（公开接口，不含敏感信息）
     *
     * @return array
     */
    public static function getProbe(): array
    {
        return [
            'rewrite_probe' => 'ok',
            'time' => time(),
        ];
    }

    /**
     * 应急自检令牌的签名密钥
     * 优先使用环境变量，否则基于脚本路径与主机名派生（不入库、不外泄）。
     */
    public static function emergencySecret(): string
    {
        $env = getenv('DEPLOY_CHECK_SECRET');
        if ($env) {
            return $env;
        }
        return hash('sha256', dirname(__DIR__) . '/public/deploy-check.php|' . php_uname('n') . '|deploy-selfcheck-v1');
    }

    /**
     * 签发一个应急自检令牌（默认 5 分钟有效）
     */
    public static function issueEmergencyToken(int $ttl = 300): array
    {
        $expires = time() + $ttl;
        $token = $expires . '.' . hash_hmac('sha256', (string) $expires, self::emergencySecret());
        return [
            'token' => $token,
            'expires_at' => date('Y-m-d H:i:s', $expires),
            'ttl' => $ttl,
            'url' => '/api/deploy/self-check?token=' . $token,
        ];
    }

    /**
     * 校验应急自检令牌
     */
    public static function verifyEmergencyToken(string $token): bool
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2 || !ctype_digit($parts[0])) {
            return false;
        }
        [$expires, $sign] = $parts;
        if ((int) $expires < time()) {
            return false;
        }
        $expected = hash_hmac('sha256', $expires, self::emergencySecret());
        return hash_equals($expected, $sign);
    }

    /* -----------------------------------------------------------------
     | 环境信息
     | ----------------------------------------------------------------- */

    private static function getEnvironment(): array
    {
        return [
            'os' => PHP_OS,
            'os_family' => stripos(PHP_OS, 'Linux') !== false ? 'linux' : 'other',
            'hostname' => function_exists('gethostname') ? (gethostname() ?: 'unknown') : 'unknown',
            'php_version' => PHP_VERSION,
            'php_sapi' => PHP_SAPI,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
            'running_user' => self::getRunningUser(),
            'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'unknown',
            'db_path' => self::resolveDbPath(),
        ];
    }

    private static function getRunningUser(): string
    {
        $user = '';
        if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
            $info = @posix_getpwuid(posix_geteuid());
            $user = $info['name'] ?? '';
        }
        if (!$user && getenv('USER')) {
            $user = getenv('USER');
        }
        return $user ?: 'unknown';
    }

    private static function resolveDbPath(): string
    {
        $envPath = getenv('DB_PATH');
        if ($envPath && file_exists(dirname($envPath))) {
            return $envPath;
        }
        return dirname(__DIR__) . '/data/app.db';
    }

    /* -----------------------------------------------------------------
     | 检查项：PHP 版本
     | ----------------------------------------------------------------- */

    private static function checkPhpVersion(): array
    {
        $current = PHP_VERSION;
        $ok = version_compare($current, self::MIN_PHP_VERSION, '>=');

        return [[
            'key' => 'php_version',
            'group' => '运行环境',
            'name' => 'PHP 版本',
            'status' => $ok ? 'pass' : 'fail',
            'actual' => $current,
            'expected' => '>= ' . self::MIN_PHP_VERSION,
            'message' => $ok
                ? '当前 PHP 版本满足系统要求'
                : 'PHP 版本过低，系统需要 PHP ' . self::MIN_PHP_VERSION . ' 及以上版本',
            'fix' => $ok ? '' : implode("\n", [
                '请升级 PHP（以 Debian/Ubuntu 为例）：',
                '  sudo apt update',
                '  sudo apt install -y software-properties-common',
                '  sudo add-apt-repository ppa:ondrej/php',
                '  sudo apt install -y php8.3 php8.3-fpm php8.3-sqlite3',
                '然后重启 Web 服务：',
                '  sudo systemctl restart php8.3-fpm nginx',
                '若使用面板（宝塔/1Panel 等），请在「PHP 版本管理」中切换到 8.0 以上。',
            ]),
        ]];
    }

    /* -----------------------------------------------------------------
     | 检查项：必需扩展（sqlite3 / pdo_sqlite）
     | ----------------------------------------------------------------- */

    private static function checkRequiredExtensions(): array
    {
        $checks = [];

        // 系统通过 PDO + sqlite 驱动访问数据库
        $pdoSqliteLoaded = extension_loaded('pdo_sqlite');
        $pdoDrivers = class_exists(\PDO::class) ? \PDO::getAvailableDrivers() : [];

        $checks[] = [
            'key' => 'ext_pdo_sqlite',
            'group' => '运行环境',
            'name' => 'PDO SQLite 扩展 (pdo_sqlite)',
            'status' => ($pdoSqliteLoaded && in_array('sqlite', $pdoDrivers, true)) ? 'pass' : 'fail',
            'actual' => $pdoSqliteLoaded ? '已安装' : '未安装',
            'expected' => '已安装 pdo_sqlite 驱动',
            'message' => ($pdoSqliteLoaded && in_array('sqlite', $pdoDrivers, true))
                ? 'PDO SQLite 驱动可用，数据库连接正常依赖已满足'
                : '缺少 pdo_sqlite 扩展，系统将无法连接 SQLite 数据库',
            'fix' => ($pdoSqliteLoaded && in_array('sqlite', $pdoDrivers, true)) ? '' : implode("\n", [
                '请为当前 PHP 安装 pdo_sqlite 扩展：',
                '  Debian/Ubuntu: sudo apt install -y php-sqlite3',
                '  CentOS/RHEL  : sudo yum install -y php-pdo php-sqlite3',
                '  宝塔面板     : 软件商店 → 对应 PHP → 安装扩展 → 打开 sqlite3 / pdo_sqlite',
                '安装后重启 PHP-FPM：',
                '  sudo systemctl restart php-fpm   # 或 sudo systemctl restart php8.3-fpm',
            ]),
        ];

        // sqlite3 扩展（需求中明确要求检查）
        $sqlite3Loaded = extension_loaded('sqlite3');
        $checks[] = [
            'key' => 'ext_sqlite3',
            'group' => '运行环境',
            'name' => 'SQLite3 扩展 (sqlite3)',
            'status' => $sqlite3Loaded ? 'pass' : 'warn',
            'actual' => $sqlite3Loaded ? '已安装' : '未安装',
            'expected' => '已安装 sqlite3 扩展',
            'message' => $sqlite3Loaded
                ? 'sqlite3 扩展可用'
                : '未检测到 sqlite3 扩展（当前系统数据库访问走 pdo_sqlite，可正常运行，但建议一并安装以便命令行排障）',
            'fix' => $sqlite3Loaded ? '' : implode("\n", [
                '建议安装 sqlite3 扩展：',
                '  Debian/Ubuntu: sudo apt install -y php-sqlite3',
                '  CentOS/RHEL  : sudo yum install -y php-sqlite3',
                '安装后重启 PHP-FPM 与 Nginx。',
            ]),
        ];

        return $checks;
    }

    /* -----------------------------------------------------------------
     | 检查项：必需函数是否被禁用（exec 等不做强依赖，这里仅提示）
     | ----------------------------------------------------------------- */

    private static function checkFunctionsAvailable(): array
    {
        $required = ['random_int', 'password_hash', 'password_verify', 'hash_hmac'];
        $disabled = array_filter($required, static fn($fn) => !function_exists($fn));

        return [[
            'key' => 'required_functions',
            'group' => '运行环境',
            'name' => '核心加密函数可用性',
            'status' => empty($disabled) ? 'pass' : 'fail',
            'actual' => empty($disabled) ? '全部可用' : '缺失：' . implode('、', $disabled),
            'expected' => 'random_int/password_hash/hash_hmac 等函数可用',
            'message' => empty($disabled)
                ? '密码哈希、随机数、HMAC 相关函数均可正常使用'
                : '部分核心函数被禁用，登录、卡密生成等功能将不可用',
            'fix' => empty($disabled) ? '' : implode("\n", [
                '这些函数属于 PHP 内置函数，通常无需单独安装。',
                '若被 disable_functions 禁用，请编辑 php.ini：',
                '  # 查找配置文件',
                '  php --ini',
                '  # 从 disable_functions 中移除上述函数后重启 PHP-FPM',
                '  sudo systemctl restart php-fpm',
            ]),
        ]];
    }

    /* -----------------------------------------------------------------
     | 检查项：数据库文件与目录权限
     | ----------------------------------------------------------------- */

    private static function checkDatabase(): array
    {
        $checks = [];
        $dbPath = self::resolveDbPath();
        $dbDir = dirname($dbPath);
        $user = self::getRunningUser();
        $dataDirDefault = dirname(__DIR__) . '/data';

        // 1) data 目录
        $dirExists = is_dir($dbDir);
        $dirWritable = $dirExists && is_writable($dbDir);

        $checks[] = [
            'key' => 'db_dir',
            'group' => '数据库权限',
            'name' => '数据库目录',
            'status' => $dirWritable ? 'pass' : 'fail',
            'actual' => $dirExists
                ? ($dirWritable ? '存在且可写' : '存在但不可写')
                : '目录不存在',
            'expected' => '目录存在，且 PHP 运行用户（' . $user . '）可读写',
            'message' => $dirWritable
                ? '数据库目录可写：' . $dbDir
                : '数据库目录不可用，SQLite 将无法创建/写入数据库及 WAL 日志文件',
            'fix' => $dirWritable ? '' : implode("\n", [
                '在服务器执行（将路径替换为实际 data 目录，默认：' . $dataDirDefault . '）：',
                '  sudo mkdir -p ' . $dbDir,
                '  sudo chown -R ' . $user . ':' . $user . ' ' . $dbDir,
                '  sudo chmod 755 ' . $dbDir,
                '若使用 Docker 部署，请确认已挂载数据卷且宿主机目录可写：',
                '  mkdir -p ./data && chmod 755 ./data',
            ]),
        ];

        // 2) 数据库文件
        if (!$dirExists) {
            $checks[] = [
                'key' => 'db_file',
                'group' => '数据库权限',
                'name' => 'SQLite 数据库文件',
                'status' => 'warn',
                'actual' => '尚未创建',
                'expected' => $dbPath,
                'message' => '数据库文件尚不存在，系统首次访问 API 时会自动初始化创建（前提是目录可写）',
                'fix' => '',
            ];
        } else {
            $fileExists = file_exists($dbPath);
            if (!$fileExists) {
                $checks[] = [
                    'key' => 'db_file',
                    'group' => '数据库权限',
                    'name' => 'SQLite 数据库文件',
                    'status' => 'warn',
                    'actual' => '文件不存在',
                    'expected' => $dbPath,
                    'message' => '数据库文件尚未创建，首次访问 API 将自动初始化（目录已可写）',
                    'fix' => '',
                ];
            } else {
                $readable = is_readable($dbPath);
                $writable = is_writable($dbPath);
                $perms = substr(sprintf('%o', fileperms($dbPath)), -4);
                $status = ($readable && $writable) ? 'pass' : 'fail';

                $checks[] = [
                    'key' => 'db_file',
                    'group' => '数据库权限',
                    'name' => 'SQLite 数据库文件',
                    'status' => $status,
                    'actual' => '权限 ' . $perms . '，' . ($readable ? '可读' : '不可读') . '/' . ($writable ? '可写' : '不可写'),
                    'expected' => 'PHP 运行用户（' . $user . '）可读写（建议 640/644，属主为运行用户）',
                    'message' => ($readable && $writable)
                        ? '数据库文件可正常读写：' . $dbPath
                        : '数据库文件权限不足，登录与卡密验证都会失败',
                    'fix' => ($readable && $writable) ? '' : implode("\n", [
                        '修正数据库文件属主与权限：',
                        '  sudo chown ' . $user . ':' . $user . ' ' . $dbPath,
                        '  sudo chmod 644 ' . $dbPath,
                        '同时确保同目录下的 WAL/SHM 文件属主一致（如存在）：',
                        '  sudo chown ' . $user . ':' . $user . ' ' . $dbDir . '/app.db-wal ' . $dbDir . '/app.db-shm 2>/dev/null',
                        '  sudo chmod 644 ' . $dbDir . '/app.db-wal ' . $dbDir . '/app.db-shm 2>/dev/null',
                    ]),
                ];
            }
        }

        // 3) 数据库实际连通性（尝试创建 PDO 连接并执行简单查询）
        $dbStatus = 'pass';
        $dbMessage = 'PDO 可正常连接 SQLite 数据库';
        $dbFix = '';
        $dbActual = '连接正常';
        try {
            $pdo = Database::getInstance();
            $pdo->query('SELECT 1');
        } catch (\Throwable $e) {
            $dbStatus = 'fail';
            $dbMessage = '无法连接数据库：' . $e->getMessage();
            $dbActual = '连接失败';
            $dbFix = implode("\n", [
                '请按顺序排查：',
                '  1. 确认已安装 pdo_sqlite 扩展；',
                '  2. 确认 data 目录与 app.db 文件属主为 PHP 运行用户（' . $user . '）且可写；',
                '  3. 若磁盘已满或文件损坏，先备份后删除 app.db 让系统重新初始化（会清空数据，谨慎操作）。',
            ]);
        }

        $checks[] = [
            'key' => 'db_connection',
            'group' => '数据库权限',
            'name' => '数据库连通性',
            'status' => $dbStatus,
            'actual' => $dbActual,
            'expected' => '能够建立 PDO SQLite 连接并执行查询',
            'message' => $dbMessage,
            'fix' => $dbFix,
        ];

        return $checks;
    }

    /* -----------------------------------------------------------------
     | 检查项：日志目录
     | ----------------------------------------------------------------- */

    private static function checkLogDirectory(): array
    {
        $checks = [];
        $user = self::getRunningUser();

        // PHP 错误日志目标（error_log 配置）
        $errorLogTarget = ini_get('error_log');
        if ($errorLogTarget) {
            $logDir = dirname($errorLogTarget);
            $writable = self::isDirWritableForPhp($logDir);
            $checks[] = [
                'key' => 'php_error_log',
                'group' => '日志目录',
                'name' => 'PHP 错误日志目录',
                'status' => $writable ? 'pass' : 'fail',
                'actual' => $logDir . '（' . ($writable ? '可写' : '不可写') . '）',
                'expected' => 'PHP 运行用户（' . $user . '）可写：' . $errorLogTarget,
                'message' => $writable
                    ? 'PHP 错误日志可正常写入'
                    : 'PHP 错误日志目录不可写，运行期错误将无法落盘，不利于排查问题',
                'fix' => $writable ? '' : implode("\n", [
                    '创建日志目录并授权：',
                    '  sudo mkdir -p ' . $logDir,
                    '  sudo chown -R ' . $user . ':' . $user . ' ' . $logDir,
                    '  sudo chmod 755 ' . $logDir,
                ]),
            ];
        } else {
            $checks[] = [
                'key' => 'php_error_log',
                'group' => '日志目录',
                'name' => 'PHP 错误日志目录',
                'status' => 'warn',
                'actual' => '未配置 error_log（默认输出到 Web 服务器日志/SAPI）',
                'expected' => '建议显式指定可写的错误日志文件',
                'message' => '未在 php.ini 中配置 error_log，错误信息将交由 Nginx/Apache 或容器接管',
                'fix' => implode("\n", [
                    '如需独立的 PHP 错误日志，编辑 php.ini：',
                    '  error_log = /var/log/php/php-error.log',
                    '并创建目录：',
                    '  sudo mkdir -p /var/log/php',
                    '  sudo chown -R ' . $user . ':' . $user . ' /var/log/php',
                ]),
            ];
        }

        // Web 服务器日志目录（Linux 常见路径，存在才检查）
        foreach (['/var/log/nginx', '/var/log/apache2', '/var/log/httpd'] as $candidate) {
            if (is_dir($candidate)) {
                $writable = self::isDirWritableForPhp($candidate);
                $checks[] = [
                    'key' => 'webserver_log_dir',
                    'group' => '日志目录',
                    'name' => 'Web 服务器日志目录',
                    'status' => $writable ? 'pass' : 'warn',
                    'actual' => $candidate . '（' . ($writable ? '可写' : '不可写') . '）',
                    'expected' => 'Web 服务进程对该目录可写',
                    'message' => $writable
                        ? 'Web 服务器日志目录可写'
                        : '当前 PHP 用户对 ' . $candidate . ' 不可写；多数发行版由 www-data/apache/nginx 账户写入，通常无需处理',
                    'fix' => $writable ? '' : implode("\n", [
                        '如确实需要修复（以 Nginx 为例）：',
                        '  sudo mkdir -p ' . $candidate,
                        '  sudo chown -R www-data:adm ' . $candidate,
                        '  sudo chmod 755 ' . $candidate,
                        '宝塔/1Panel 用户请在面板中查看默认日志目录。',
                    ]),
                ];
                break;
            }
        }

        return $checks;
    }

    /**
     * 判断目录对当前 PHP 运行用户是否可写：
     * 优先真实写入探测，兜底使用 is_writable。
     */
    private static function isDirWritableForPhp(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }
        $probe = rtrim($dir, '/') . '/.deploy_check_' . bin2hex(random_bytes(4));
        $written = @file_put_contents($probe, '1');
        if ($written !== false) {
            @unlink($probe);
            return true;
        }
        return is_writable($dir);
    }

    /* -----------------------------------------------------------------
     | 检查项：伪静态（URL 重写）配置
     | 服务端只能检查“是否具备配置”，实际是否生效由前端 JS 探针补充。
     | ----------------------------------------------------------------- */

    private static function checkRewriteConfig(): array
    {
        $software = $_SERVER['SERVER_SOFTWARE'] ?? '';
        $isNginx = stripos($software, 'nginx') !== false;
        $isApache = stripos($software, 'apache') !== false;

        // 定位站点根目录（尝试几种常见部署布局）
        $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
        $htaccessCandidates = array_values(array_filter([
            $docRoot . '/.htaccess',
            $docRoot . '/admin/.htaccess',
            dirname(__DIR__, 2) . '/php-frontend/public/.htaccess',
            dirname(__DIR__, 2) . '/php-admin/public/.htaccess',
        ], 'is_file'));

        if ($isNginx) {
            $nginxOk = self::detectNginxRewrite();
            return [[
                'key' => 'rewrite_config',
                'group' => '伪静态',
                'name' => 'Nginx 伪静态（URL Rewrite）',
                'status' => $nginxOk ? 'pass' : 'warn',
                'actual' => $nginxOk ? '检测到 try_files/rewrite 转发到 PHP 入口' : '未能自动确认 Nginx 重写规则',
                'expected' => '站点需将不存在的路径转发到 index.php，并将 /api/* 转发到 api.php',
                'message' => $nginxOk
                    ? 'Nginx 伪静态规则已配置（最终生效结果以页面“路由实测”为准）'
                    : '未自动确认 Nginx 重写规则。若页面刷新 404 或 /api 请求 404，即为伪静态未生效',
                'fix' => $nginxOk ? '' : implode("\n", [
                    '在站点 server{} 配置中加入（宝塔：站点设置 → 伪静态，粘贴以下内容）：',
                    '  location /admin/ { try_files $uri /admin/index.php?$query_string; }',
                    '  location /api/admin/ { rewrite ^/api/admin/(.*)$ /admin/api.php last; }',
                    '  location /api/ { rewrite ^/api/(.*)$ /api.php last; }',
                    '  location / { try_files $uri /index.php?$query_string; }',
                    '并重载配置：sudo nginx -t && sudo systemctl reload nginx',
                ]),
            ]];
        }

        if ($isApache) {
            $htaccessOk = !empty($htaccessCandidates);
            $modRewrite = function_exists('apache_get_modules') ? in_array('mod_rewrite', apache_get_modules(), true) : null;
            $status = 'pass';
            $actual = [];
            if ($modRewrite === false) {
                $status = 'fail';
                $actual[] = 'mod_rewrite 未启用';
            } elseif ($modRewrite === true) {
                $actual[] = 'mod_rewrite 已启用';
            }
            $actual[] = $htaccessOk ? '.htaccess 存在' : '.htaccess 缺失';
            if (!$htaccessOk) {
                $status = 'fail';
            }

            return [[
                'key' => 'rewrite_config',
                'group' => '伪静态',
                'name' => 'Apache 伪静态（mod_rewrite / .htaccess）',
                'status' => $status,
                'actual' => implode('；', $actual),
                'expected' => '已启用 mod_rewrite 且站点目录存在 .htaccess',
                'message' => $status === 'pass'
                    ? 'Apache 伪静态配置具备（最终生效结果以页面“路由实测”为准）'
                    : 'Apache 伪静态配置不完整，刷新页面或访问 /api 可能 404',
                'fix' => $status === 'pass' ? '' : implode("\n", [
                    '1) 启用 mod_rewrite：',
                    '   sudo a2enmod rewrite && sudo systemctl restart apache2',
                    '2) 确认站点目录允许 .htaccess 覆盖（apache2.conf / 站点配置中）：',
                    '   <Directory /var/www/html>',
                    '       AllowOverride All',
                    '   </Directory>',
                    '3) 确认上传的代码包内 public 目录下的 .htaccess 已一并上传，',
                    '   且站点根目录直接指向 public 目录。',
                ]),
            ]];
        }

        // 其它/无法识别的 Web 服务器
        return [[
            'key' => 'rewrite_config',
            'group' => '伪静态',
            'name' => '伪静态（URL Rewrite）',
            'status' => 'warn',
            'actual' => '无法识别 Web 服务器（' . ($software ?: 'unknown') . '）',
            'expected' => '需将不存在的路径重写到 PHP 入口（index.php / api.php）',
            'message' => '服务端无法自动判断伪静态是否生效，请以页面“路由实测”结果为准',
            'fix' => implode("\n", [
                'Nginx 请配置 try_files/rewrite；Apache 请启用 mod_rewrite 并保留 .htaccess。',
                '配置完成后可点击本页“重新检测”查看路由实测结果。',
            ]),
        ]];
    }

    private static function detectNginxRewrite(): bool
    {
        // 运行在容器/统一镜像内时，配置路径固定
        $candidateFiles = [
            '/etc/nginx/sites-available/default',
            '/etc/nginx/conf.d/default.conf',
            '/etc/nginx/nginx.conf',
        ];

        foreach ($candidateFiles as $file) {
            if (!is_readable($file)) {
                continue;
            }
            $content = @file_get_contents($file);
            if ($content === false) {
                continue;
            }
            if ((strpos($content, 'try_files') !== false || strpos($content, 'rewrite') !== false)
                && strpos($content, '.php') !== false) {
                return true;
            }
        }

        // 宿主机面板部署时通常读不到 Nginx 配置；
        // 若项目自带 .htaccess，至少说明代码包完整，交给前端探针实测
        $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
        if ($docRoot && is_file($docRoot . '/.htaccess')) {
            return false;
        }

        return false;
    }

    /* -----------------------------------------------------------------
     | 检查项：默认管理员账号安全
     | ----------------------------------------------------------------- */

    private static function checkDefaultAccount(): array
    {
        $status = 'warn';
        $actual = '无法读取管理员信息';
        $message = '';
        $fix = '';

        try {
            $admin = Database::queryOne(
                'SELECT username FROM admin_user WHERE username = ? LIMIT 1',
                ['admin']
            );

            if ($admin) {
                $stillDefault = self::isAdminPasswordStillDefault();

                if ($stillDefault) {
                    $status = 'fail';
                    $actual = '存在默认管理员账号 admin，且仍使用初始密码';
                    $message = '检测到默认账号仍为初始密码，任何人都能登录后台，存在严重安全风险';
                    $dbPath = self::resolveDbPath();
                    $fix = <<<FIX
请立即处理（任选其一）：
  1. 登录管理后台后尽快修改管理员密码（推荐）；
  2. 通过 SQL 修改密码：先在服务器生成新密码的哈希值
       php -r "echo password_hash('你的新密码', PASSWORD_DEFAULT);"
     然后更新数据库（将 <HASH> 替换为上面生成的哈希）：
       sqlite3 {$dbPath} "UPDATE admin_user SET password_hash='<HASH>', updated_at=datetime('now','localtime') WHERE username='admin';"
  3. 如不需要默认账号，可在确认已存在其它管理员账号后删除该账号。
另外：正式对外使用前请删除种子测试卡密（TEST 前缀）。
FIX;
                } else {
                    $status = 'warn';
                    $actual = '存在默认用户名 admin（密码可能已修改）';
                    $message = '已检测到默认用户名，建议改为不易猜测的用户名以降低爆破风险';
                    $fix = implode("\n", [
                        '建议操作：',
                        '  1. 新建一个非 admin 的管理员账号；',
                        '  2. 使用新账号登录并停用/删除默认 admin 账号；',
                        '  3. 同时为后台登录路径增加 IP 白名单或 HTTPS。',
                    ]);
                }
            } else {
                $status = 'pass';
                $actual = '不存在默认用户名 admin';
                $message = '默认管理员账号已处理';
            }
        } catch (\Throwable $e) {
            $status = 'warn';
            $message = '无法校验默认账号（数据库尚未就绪）：' . $e->getMessage();
        }

        return [[
            'key' => 'default_admin',
            'group' => '安全配置',
            'name' => '默认管理员账号',
            'status' => $status,
            'actual' => $actual,
            'expected' => '不使用默认账号名/初始密码',
            'message' => $message,
            'fix' => $fix,
        ]];
    }

    /**
     * 判断 admin 账号的密码是否仍是种子初始密码
     */
    private static function isAdminPasswordStillDefault(): bool
    {
        try {
            $row = Database::queryOne(
                'SELECT password_hash FROM admin_user WHERE username = ? LIMIT 1',
                ['admin']
            );
            if (!$row || empty($row['password_hash'])) {
                return false;
            }
            return password_verify('daniaoge', $row['password_hash']);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /* -----------------------------------------------------------------
     | 检查项：服务器时间（影响过期判断/Token）
     | ----------------------------------------------------------------- */

    private static function checkServerTime(): array
    {
        $tz = date_default_timezone_get();
        $offsetOk = true;
        $message = '时区：' . $tz . '，服务器时间：' . date('Y-m-d H:i:s');
        $fix = '';

        if ($tz !== 'Asia/Shanghai') {
            return [[
                'key' => 'server_time',
                'group' => '运行环境',
                'name' => '服务器时间与时区',
                'status' => 'warn',
                'actual' => $tz . ' / ' . date('Y-m-d H:i:s'),
                'expected' => 'Asia/Shanghai（中国时区）',
                'message' => '当前时区不是 Asia/Shanghai，卡密过期时间可能与预期相差数小时',
                'fix' => implode("\n", [
                    '方案一（推荐）：在 php.ini 中设置',
                    '  date.timezone = Asia/Shanghai',
                    '方案二：服务器系统时区',
                    '  sudo timedatectl set-timezone Asia/Shanghai',
                    '修改后重启 PHP-FPM / Web 服务。',
                ]),
            ]];
        }

        return [[
            'key' => 'server_time',
            'group' => '运行环境',
            'name' => '服务器时间与时区',
            'status' => 'pass',
            'actual' => $tz . ' / ' . date('Y-m-d H:i:s'),
            'expected' => 'Asia/Shanghai（中国时区）',
            'message' => $message,
            'fix' => '',
        ]];
    }
}
