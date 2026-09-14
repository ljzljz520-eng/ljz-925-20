<?php

/**
 * API入口文件
 * 统一处理所有API请求
 */

// 错误报告设置
error_reporting(E_ALL);
ini_set('display_errors', '0');

// 设置时区
date_default_timezone_set('Asia/Shanghai');

// 加载Composer autoload
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
}

// 自动加载类
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/../src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

use App\Database;
use App\Response;

// CORS设置
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// 处理OPTIONS预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 设置响应头
header('Content-Type: application/json; charset=utf-8');

// 获取请求路径与方法（提前解析，便于在数据库初始化前处理运维探针）
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);
$path = trim($path, '/');
$method = $_SERVER['REQUEST_METHOD'];

try {
    // 伪静态运行时探测探针：在数据库初始化之前处理，
    // 这样即使数据库/扩展异常，只要伪静态生效就能探测到。
    // 公开访问，仅返回固定标记，不泄露任何敏感信息。
    if ($path === 'api/deploy/rewrite-probe') {
        Response::jsonSuccess(\App\SystemChecker::getProbe(), 'ok');
    }

    // 应急部署自检入口：不依赖数据库登录，使用服务器本机签发的限时令牌鉴权
    if ($path === 'api/deploy/self-check') {
        require __DIR__ . '/deploy-check.php';
        exit;
    }

    // 初始化数据库
    Database::initialize();

    // 定期清理过期数据（10%概率）
    if (rand(1, 10) === 1) {
        Database::cleanup();
    }

    // 路由分发
    if (strpos($path, 'api/admin/') === 0) {
        // 管理后台路由
        require __DIR__ . '/../routes/admin.php';
    } elseif (strpos($path, 'api/auth/') === 0) {
        // 认证路由
        require __DIR__ . '/../routes/auth.php';
    } elseif (strpos($path, 'api/') === 0) {
        // 业务路由
        require __DIR__ . '/../routes/api.php';
    } else {
        // 404
        Response::jsonError(404, '接口不存在');
    }

} catch (Exception $e) {
    // 统一异常处理
    error_log('API Error: ' . $e->getMessage());
    Response::jsonError(5000, '服务器内部错误');
}
