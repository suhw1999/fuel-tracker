<?php

/**
 * API 统一响应类
 * 提供标准化的 JSON 响应格式
 */
class ApiResponse {
    /**
     * 发送成功响应
     * @param array $data 响应数据
     * @param string $message 成功消息
     */
    public static function success(array $data = [], string $message = ''): void {
        header('Content-Type: application/json; charset=utf-8');
        $response = ['success' => true];
        if ($message) {
            $response['message'] = $message;
        }
        echo json_encode(array_merge($response, $data));
        exit;
    }

    /**
     * 发送错误响应
     * @param string $message 错误消息
     * @param int $code HTTP 状态码
     */
    public static function error(string $message, int $code = 400): void {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => $message
        ]);
        exit;
    }

    /**
     * 发送 JSON 响应（不添加 success 字段）
     * @param array $data 响应数据
     */
    public static function json(array $data): void {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }
}
