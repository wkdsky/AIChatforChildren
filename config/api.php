<?php
// API配置管理
class APIConfig {
    public static function getDeepSeekConfig() {
        return [
            'api_key' => $_ENV['LLM_API_KEY'] ?? '',
            'api_url' => $_ENV['LLM_API_URL'] ?? 'https://api.deepseek.com/chat/completions',
            'model' => 'deepseek-chat',
            'max_tokens' => 2000,
            'temperature' => 0.7,
            'top_p' => 0.9,
            'frequency_penalty' => 0.5,
            'presence_penalty' => 0.3,
            'timeout' => 60,
            'max_retries' => 3,
            'retry_delay' => 1000, // 基础重试延迟(ms)
            'max_retry_delay' => 5000 // 最大重试延迟(ms)
        ];
    }

    public static function validateConfig() {
        $config = self::getDeepSeekConfig();
        $errors = [];

        if (empty($config['api_key'])) {
            $errors[] = 'API密钥未设置';
        } elseif (!str_starts_with($config['api_key'], 'sk-')) {
            $errors[] = 'API密钥格式无效';
        }

        if (empty($config['api_url'])) {
            $errors[] = 'API URL未设置';
        }

        return $errors;
    }
}
?>