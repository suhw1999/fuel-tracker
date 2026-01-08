<?php
require_once dirname(__DIR__) . '/config.php';

class AuthHelper {
    /**
     * 验证 POST 请求
     */
    public static function validatePostRequest(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            self::sendError('仅允许POST请求', 405);
        }
    }

    /**
     * 验证 CSRF Token
     */
    public static function validateCSRF(): void {
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            self::sendError('CSRF验证失败', 403);
        }
    }

    /**
     * 验证登录状态
     */
    public static function validateLogin(): void {
        if (!isset($_SESSION['fuel_authenticated'])) {
            self::sendError('未授权访问', 401);
        }
    }

    /**
     * 完整的请求验证（POST + CSRF + 登录）
     * @param bool $requirePost 是否要求 POST 请求
     */
    public static function validateRequest(bool $requirePost = true): void {
        if ($requirePost) {
            self::validatePostRequest();
        }
        self::validateCSRF();
        self::validateLogin();
    }

    /**
     * 发送 JSON 错误响应并终止
     * @param string $message 错误消息
     * @param int $code HTTP 状态码
     */
    private static function sendError(string $message, int $code = 400): void {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }
}
