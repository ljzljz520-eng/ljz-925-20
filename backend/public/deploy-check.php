<?php

/**
 * 部署自检独立入口（应急诊断）
 *
 * 设计目的：
 *   当数据库文件权限/目录异常、pdo_sqlite 缺失等导致后台无法登录时，
 *   管理员仍可通过本入口查看部署自检结果以定位问题。
 *
 * 安全保障：
 *   1. 不加载 composer、不连接数据库，只读取服务器环境信息；
 *   2. 必须携带正确的签名 token，token 只能在服务器本机生成；
 *   3. token 5 分钟内有效，且不持久化、不回传任何敏感数据。
 *
 * 使用方法（在服务器上执行）：
 *   php /path/to/backend/public/deploy-check.php issue
 *   # 将输出的完整链接复制到管理员浏览器中打开
 */

error_reporting(E_ERROR | E_PARSE);
date_default_timezone_set('Asia/Shanghai');
header('Content-Type: application/json; charset=utf-8');

// 直接加载自检类（不经过 composer autoload）
require __DIR__ . '/../src/SystemChecker.php';

use App\SystemChecker;

// CLI 签发模式：php deploy-check.php issue
if (PHP_SAPI === 'cli') {
    $action = $argv[1] ?? '';
    if ($action === 'issue') {
        $issued = SystemChecker::issueEmergencyToken();
        fwrite(STDOUT, "自检访问令牌（5 分钟内有效）：\n");
        fwrite(STDOUT, $issued['token'] . "\n\n");
        fwrite(STDOUT, "在浏览器访问（替换为你的域名/端口）：\n");
        fwrite(STDOUT, $issued['url'] . "\n");
        exit(0);
    }
    fwrite(STDERR, "用法: php deploy-check.php issue\n");
    exit(1);
}

// Web 请求：校验 token
$token = $_GET['token'] ?? '';
if (!SystemChecker::verifyEmergencyToken($token)) {
    http_response_code(403);
    echo json_encode(['code' => 403, 'message' => '自检令牌无效或已过期，请在服务器上重新生成', 'data' => null], JSON_UNESCAPED_UNICODE);
    exit;
}

// 鉴权通过：执行环境层自检（数据库不可用时也尽量给出结果）
try {
    $report = SystemChecker::runAll();
    echo json_encode(['code' => 0, 'message' => '自检完成（应急入口）', 'data' => $report], JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'code' => 5000,
        'message' => '自检过程发生错误：' . $e->getMessage(),
        'data' => null,
    ], JSON_UNESCAPED_UNICODE);
}
