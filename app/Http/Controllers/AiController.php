<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AI 助理：流式（SSE）对接 DeepSeek，并基于 app_db 业务表内容作答。
 * 安全：API Key 仅取自服务端环境变量；数据库只读、白名单表、参数化查询。
 */
class AiController extends ApiController
{
    /**
     * 业务表白名单（排除 user / personal_access_tokens 等敏感表）。
     * 仅这些表可被检索并注入提示词。
     */
    protected $allowedTables = [
        'companies', 'departments', 'documents', 'document_models', 'next',
        'approval_logs', 'document_files', 'document_task_logs',
        'notice_notices', 'notice_receivers', 'notice_files',
        'car_applications', 'car_plates', 'car_approval_chains', 'car_approval_nodes',
        'files',
    ];

    /**
     * POST /api/ai/chat
     * 请求体：{ "message": "用户问题" }
     * 响应：text/event-stream，逐行 data: {"token":"..."}，结束 data: [DONE]
     */
    public function chat()
    {
        $question = trim(request()->input('message', ''));
        if ($question === '') {
            return $this->error('请输入问题');
        }

        // 1) 从白名单业务表检索与问题相关的内容，注入系统提示词
        $dbCtx = $this->retrieveContext($question);
        $system = "你是企业移动办公系统的智能助理，只能依据下方【数据库内容】回答用户问题，"
            . "不知道或内容里没有的就如实说不知道，不要编造。\n【数据库内容】\n" . $dbCtx;

        // 2) 组装 DeepSeek 请求（OpenAI 兼容协议）
        $apiKey  = env('DEEPSEEK_API_KEY');
        $baseUrl = rtrim(env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com/v1'), '/');
        $model   = env('DEEPSEEK_MODEL', 'deepseek-v4-frash');

        $payload = [
            'model'    => $model,
            'stream'   => true,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user',   'content' => $question],
            ],
        ];

        $headers = [
            'Content-Type'       => 'text/event-stream',
            'Cache-Control'      => 'no-cache',
            'X-Accel-Buffering'  => 'no', // 关键：让 nginx 不要缓冲 SSE
            'Connection'         => 'keep-alive',
        ];

        $client = new Client();

        // 3) 以 SSE 流式把 DeepSeek 的 token 透传给前端
        return response()->stream(function () use ($client, $apiKey, $baseUrl, $payload) {
            try {
                $response = $client->post($baseUrl . '/chat/completions', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $apiKey,
                        'Content-Type'  => 'application/json',
                    ],
                    'json'    => $payload,
                    'stream'  => true,
                    'timeout' => 120,
                ]);

                $body = $response->getBody();
                $buffer = '';
                while (!$body->eof()) {
                    $chunk = $body->read(8192);
                    if ($chunk === false || $chunk === '') {
                        continue;
                    }
                    $buffer .= $chunk;
                    while (($pos = strpos($buffer, "\n")) !== false) {
                        $line  = trim(substr($buffer, 0, $pos));
                        $buffer = substr($buffer, $pos + 1);
                        if ($line === '' || substr($line, 0, 5) !== 'data:') {
                            continue;
                        }
                        $data = trim(substr($line, 5));
                        if ($data === '[DONE]') {
                            echo "data: [DONE]\n\n";
                            ob_flush();
                            flush();
                            continue;
                        }
                        $json = json_decode($data, true);
                        if (!is_array($json)) {
                            continue;
                        }
                        $token = $json['choices'][0]['delta']['content'] ?? '';
                        if ($token !== '') {
                            echo "data: " . json_encode(['token' => $token], JSON_UNESCAPED_UNICODE) . "\n\n";
                            ob_flush();
                            flush();
                        }
                    }
                }
            } catch (\Throwable $e) {
                echo "data: " . json_encode(['error' => $e->getMessage()]) . "\n\n";
                ob_flush();
                flush();
            }
        }, 200, $headers);
    }

    /**
     * 从白名单业务表中检索与问题相关的行（只读、参数化、限行）。
     * 表不存在或无匹配则静默跳过，绝不抛错中断对话。
     */
    protected function retrieveContext(string $q): string
    {
        $conn = DB::connection('app');
        $like = '%' . $q . '%';
        $pieces = [];

        foreach ($this->allowedTables as $table) {
            try {
                $cols = Schema::connection('app')->getColumnListing($table);
            } catch (\Throwable $e) {
                continue; // 表不存在则跳过
            }
            if (empty($cols)) {
                continue;
            }

            $wheres = array_map(fn($c) => "`{$c}` LIKE ?", $cols);
            $sql    = "SELECT * FROM `{$table}` WHERE " . implode(' OR ', $wheres) . " LIMIT 5";
            $params = array_fill(0, count($cols), $like);

            try {
                $rows = $conn->select($sql, $params);
            } catch (\Throwable $e) {
                continue;
            }

            if (!empty($rows)) {
                $pieces[] = "【表 {$table}】\n" . json_encode($rows, JSON_UNESCAPED_UNICODE);
            }
        }

        return $pieces ? implode("\n\n", $pieces) : '（数据库中未找到与问题相关的业务数据）';
    }
}
