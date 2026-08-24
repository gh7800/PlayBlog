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
     * 可统计项配置：业务别名 → 可分组字段（人工维护，杜绝任意 SQL）。
     * 命中统计类问题后，按此配置执行 GROUP BY 聚合，并生成 ECharts option。
     */
    protected $statFields = [
        'car_applications' => [
            'aliases' => ['用车'],
            'fields'  => ['status' => '状态', 'applicant_name' => '申请人'],
        ],
        'documents' => [
            'aliases' => ['公文'],
            'fields'  => ['doc_type' => '类型', 'status' => '状态'],
        ],
        'notice_notices' => [
            'aliases' => ['通知'],
            'fields'  => ['notice_type' => '类型', 'status' => '状态'],
        ],
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

        // 1) 统计意图识别：命中则先取真实聚合数据，构造图表配置
        $chart = $this->buildChartIfStatQuestion($question);

        // 1.5) 导出意图识别：命中则在流里推 action 事件，前端渲染动作卡片
        $action = $this->detectExportAction($question);

        // 2) 从白名单业务表检索与问题相关的内容，注入系统提示词
        $dbCtx = $this->retrieveContext($question);
        if ($chart) {
            // 聚合结果也注入，让 AI 做文字总结（数量 + 占比）
            $dbCtx .= "\n\n【统计数据】" . json_encode($chart['rows'], JSON_UNESCAPED_UNICODE)
                . "\n请用自然语言总结这份统计数据，说明各项数量与占比，不要编造。";
        }
        $system = "你是企业移动办公系统的智能助理，只能依据下方【数据库内容】回答用户问题，"
            . "不知道或内容里没有的就如实说不知道，不要编造。\n【数据库内容】\n" . $dbCtx;
        if ($action) {
            $system .= "\n\n【注意】本次回复下方会展示一个「" . $action['label']
                . "」按钮卡片，请在回复末尾自然地引导用户点击该按钮完成导出，不要声称自己已经导出文件。";
        }

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
        return response()->stream(function () use ($client, $apiKey, $baseUrl, $payload, $chart, $action) {
            try {
                // 0) 快捷动作：先把动作卡片配置一次性发给前端（前端查注册表渲染按钮）
                if ($action) {
                    echo "data: " . json_encode(['action' => $action], JSON_UNESCAPED_UNICODE) . "\n\n";
                    if (ob_get_level() > 0) { ob_flush(); }
                    flush();
                }
                // 0.5) 统计类问题：先把图表配置一次性发给前端（数据真实来自 GROUP BY）
                if ($chart) {
                    echo "data: " . json_encode(['chart' => $chart['option']], JSON_UNESCAPED_UNICODE) . "\n\n";
                    if (ob_get_level() > 0) { ob_flush(); }
                    flush();
                }
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
     * 可触发的快捷动作配置：后端只发 key，前端查自己的注册表决定调用哪个接口。
     * 这样避免"后端发什么前端就执行什么"的注入风险，实际执行权在前端注册表。
     */
    protected $exportActions = [
        'exportCarApply'       => '导出全部用车数据',
        'exportCarApproveTodo' => '导出待审批用车',
        'exportCarApproveDone' => '导出已审批用车',
    ];

    /**
     * 导出意图识别：问题含"导出/下载"且涉及用车/审批时，
     * 返回 { key, label } 供前端渲染动作卡片（点击后调用 /api/car/* 导出接口）。
     * 未命中返回 null，走普通问答流程，与 chart 事件的降级策略一致。
     */
    protected function detectExportAction(string $q): ?array
    {
        if (preg_match('/(导出|下载)/u', $q) !== 1) {
            return null;
        }

        if (preg_match('/(待审|待处理|未审)/u', $q) === 1) {
            return ['key' => 'exportCarApproveTodo', 'label' => $this->exportActions['exportCarApproveTodo']];
        }
        if (preg_match('/(已审|审批完|办结)/u', $q) === 1) {
            return ['key' => 'exportCarApproveDone', 'label' => $this->exportActions['exportCarApproveDone']];
        }
        if (preg_match('/(用车|车辆|派车|审批)/u', $q) === 1) {
            return ['key' => 'exportCarApply', 'label' => $this->exportActions['exportCarApply']];
        }
        return null;
    }

    /**
     * 统计意图识别：命中"分布/占比/多少条"等词，且能从问题中定位业务+分组字段时，
     * 执行真实 GROUP BY 聚合，返回 ECharts option 与原始行数据（供提示词注入）。
     * 未命中或执行失败返回 null（走普通问答，不影响原流程）。
     * 图表类型：问题含"柱状/条形/bar"出柱状图，否则默认饼图。
     */
    protected function buildChartIfStatQuestion(string $q): ?array
    {
        if (!preg_match('/(分布|占比|统计|比例|多少条|有几个|饼图|柱状|条形)/u', $q)) {
            return null;
        }

        foreach ($this->statFields as $table => $cfg) {
            foreach ($cfg['aliases'] as $alias) {
                if (mb_strpos($q, $alias) === false) {
                    continue;
                }
                foreach ($cfg['fields'] as $col => $label) {
                    if (mb_strpos($q, $label) === false) {
                        continue;
                    }
                    try {
                        $rows = DB::connection('app')->table($table)
                            ->select("{$col} as name", DB::raw('COUNT(*) as value'))
                            ->groupBy($col)
                            ->get();
                    } catch (\Throwable $e) {
                        \Log::error("AiController 统计表 {$table} 字段 {$col} 失败: " . $e->getMessage());
                        return null;
                    }
                    if ($rows->isEmpty()) {
                        return null;
                    }

                    $isBar = preg_match('/(柱状|条形|bar)/u', $q) === 1;
                    $option = [
                        'title'   => ['text' => "{$alias}按{$label}分布", 'left' => 'center'],
                        'tooltip' => ['trigger' => $isBar ? 'axis' : 'item'],
                    ];
                    if ($isBar) {
                        $option['xAxis']  = ['type' => 'category', 'data' => $rows->pluck('name')->all()];
                        $option['yAxis']  = ['type' => 'value'];
                        $option['series'] = [['type' => 'bar', 'barWidth' => 40, 'data' => $rows->pluck('value')->all()]];
                    } else {
                        $option['legend'] = ['bottom' => 0];
                        $option['series'] = [['type' => 'pie', 'radius' => '60%', 'data' => $rows->map(fn($r) => ['name' => (string) $r->name, 'value' => (int) $r->value])->values()->all()]];
                    }

                    return [
                        'rows'   => $rows->map(fn($r) => ['name' => (string) $r->name, 'value' => (int) $r->value])->values()->all(),
                        'option' => $option,
                    ];
                }
            }
        }
        return null;
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
                // 注入字段清单，让模型能回答“某表有哪些字段 / 字段含义”
                try {
                    $colList = Schema::connection('app')->getColumnListing($name);
                    $colStr  = implode(',', $colList);
                } catch (\Throwable $e) {
                    $colStr = '?';
                }
                $counts[] = "{$name}({$n}) 字段:{$colStr}";
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
     * 设计要点（修复“查不出数据”问题）：
     *  1) 去掉“整句兜底”——中文整句永远比字段值长，拿去 LIKE 会 0 命中。
     *  2) 识别“X等于Y / X是Y / X为Y / X叫Y”结构，把 Y 作为强关键词（值部分）。
     *  3) 中文无空格分词，改用 2-gram 滑窗补强短语召回。
     *  4) 过滤常见停用词，避免把“的/了/什么”之类拿去 LIKE。
     */
    protected function extractKeywords(string $q): array
    {
        $q = trim($q);
        if ($q === '') {
            return [];
        }

        $stop = ['的', '了', '吗', '呢', '是', '有', '在', '和', '与', '及', '各', '表', '数据',
                 '多少', '查询', '请问', '告诉', '我', '你', '什么', '哪些', '怎么', '如何',
                 '里面', '当前', '现在', '一下', '一个', '这条', '那条', '查下', '这个', '那个'];

        $keywords = [];

        // 1) 结构化提取：“X等于Y / X是Y / X为Y / X叫Y” → 取 Y 作为强关键词
        if (preg_match('/(?:等于|是|为|叫)(.+)$/u', $q, $m)) {
            $v = preg_replace('/[的这个那条那了]+$/u', '', trim($m[1]));
            if ($v !== '' && mb_strlen($v) >= 2) {
                $keywords[] = $v;
            }
        }

        // 2) 通用切词：按标点/空白切分，过滤停用词与过长的整句（>12 字视为整句丢弃）
        $parts = preg_split('/[\s,，;；、。！？!?]+/u', $q);
        foreach ($parts as $t) {
            $t = trim($t);
            $len = mb_strlen($t);
            if ($t === '' || $len < 2 || $len > 12 || in_array($t, $stop, true)) {
                continue;
            }
            $keywords[] = $t;
        }

        // 3) 2-gram 滑窗补强中文短语召回（限前 40 个，避免 SQL 过大）
        $chars = preg_split('//u', $q, -1, PREG_SPLIT_NO_EMPTY);
        $n = count($chars);
        for ($i = 0; $i < $n - 1 && $i < 40; $i++) {
            $bg = $chars[$i] . $chars[$i + 1];
            if (mb_strlen($bg) >= 2 && !in_array($bg, $stop, true)) {
                $keywords[] = $bg;
            }
        }

        return array_values(array_unique(array_filter($keywords)));
    }
}
