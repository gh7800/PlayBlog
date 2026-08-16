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
        $model   = env('DEEPSEEK_MODEL', 'deepseek-v4-flash');

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
                            if (ob_get_level() > 0) { ob_flush(); }
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
                            if (ob_get_level() > 0) { ob_flush(); }
                            flush();
                        }
                    }
                }
            } catch (\Throwable $e) {
                echo "data: " . json_encode(['error' => $e->getMessage()]) . "\n\n";
                if (ob_get_level() > 0) { ob_flush(); }
                flush();
            }
        }, 200, $headers);
    }

    /**
     * 构建数据库上下文，注入系统提示词。
     * 设计要点：
     *  1) 始终注入“业务表清单 + 各表数据量”总览，让模型能回答“有哪些表 / 各表多少条数据”。
     *  2) 再把问题中的关键词拿到白名单表里做 LIKE 检索，注入命中的样例记录。
     *  3) 任何一步失败都记日志（\Log::error），不再静默吞掉，便于排查。
     *  4) 仅白名单表、只读、参数化查询。
     */
    protected function retrieveContext(string $q): string
    {
        $conn   = DB::connection('app');
        $pieces = [];

        // —— A) 表清单 + 数据量总览（始终注入）——
        try {
            $tables = $conn->select('SHOW TABLES');
            $counts = [];
            foreach ($tables as $t) {
                $name = array_values((array) $t)[0] ?? null;
                if ($name === null || !in_array($name, $this->allowedTables, true)) {
                    continue;
                }
                try {
                    $cnt = $conn->select("SELECT COUNT(*) AS c FROM `{$name}`");
                    $n   = $cnt[0]->c ?? 0;
                } catch (\Throwable $e) {
                    $n = '?';
                    \Log::error("AiController 统计表 {$name} 行数失败: " . $e->getMessage());
                }
                $counts[] = "{$name}({$n})";
            }
            if ($counts) {
                $pieces[] = "【业务表及数据量】\n" . implode('，', $counts);
            }
        } catch (\Throwable $e) {
            \Log::error('AiController 获取表清单失败: ' . $e->getMessage());
        }

        // —— B) 关键词检索：把问题拆词后到白名单表做 LIKE，注入命中样例 ——
        $keywords = $this->extractKeywords($q);
        if ($keywords) {
            foreach ($this->allowedTables as $table) {
                try {
                    $cols = Schema::connection('app')->getColumnListing($table);
                } catch (\Throwable $e) {
                    continue;
                }
                if (empty($cols)) {
                    continue;
                }
                $wheres = [];
                $params = [];
                foreach ($cols as $c) {
                    foreach ($keywords as $k) {
                        $wheres[] = "`{$c}` LIKE ?";
                        $params[] = '%' . $k . '%';
                    }
                }
                $sql = "SELECT * FROM `{$table}` WHERE (" . implode(' OR ', $wheres) . ") LIMIT 5";
                try {
                    $rows = $conn->select($sql, $params);
                } catch (\Throwable $e) {
                    \Log::error("AiController 检索表 {$table} 失败: " . $e->getMessage());
                    continue;
                }
                if (!empty($rows)) {
                    $pieces[] = "【表 {$table} 相关记录】\n" . json_encode($rows, JSON_UNESCAPED_UNICODE);
                }
            }
        }

        if (!$pieces) {
            return '（数据库当前没有与问题匹配的业务数据）';
        }
        return implode("\n\n", $pieces);
    }

    /**
     * 从用户问题中提取检索关键词。
     * 中文按整句兜底（中文无空格分词），同时按非字母数字切分保留有意义的词（≥2字），
     * 过滤常见停用词，避免把“的/了/什么”之类拿去 LIKE。
     */
    protected function extractKeywords(string $q): array
    {
        $q = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $q);
        $q = trim($q);
        if ($q === '') {
            return [];
        }
        $stop = ['的', '了', '吗', '呢', '是', '有', '在', '和', '与', '及', '各', '表', '数据',
                 '多少', '查询', '请问', '告诉', '我', '你', '什么', '哪些', '怎么', '如何',
                 '里面', '当前', '现在', '一下', '一个', '这条', '那条'];
        $tokens = array_filter(
            explode(' ', $q),
            fn($t) => mb_strlen($t) >= 2 && !in_array($t, $stop, true)
        );
        $tokens[] = $q; // 整句兜底，提升中文召回
        return array_values(array_unique($tokens));
    }
}
