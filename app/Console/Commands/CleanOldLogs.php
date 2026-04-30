<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CleanOldLogs extends Command
{
    protected $signature = 'logs:clean {--days=7 : 日志保留天数}';
    protected $description = '清理旧日志文件中指定天数前的记录';

    public function handle()
    {
        $days = (int) $this->option('days');
        $logFile = storage_path('logs/laravel.log');

        if (!File::exists($logFile)) {
            $this->info('日志文件不存在');
            return;
        }

        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false || empty($lines)) {
            $this->info('日志文件为空');
            return;
        }

        $cutoff = now()->subDays($days)->timestamp;
        $kept = [];
        $removed = 0;

        foreach ($lines as $line) {
            // 匹配时间戳格式：[2026-04-30 12:00:00]
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
                $timestamp = strtotime($matches[1]);
                if ($timestamp !== false && $timestamp >= $cutoff) {
                    $kept[] = $line;
                } else {
                    $removed++;
                }
            } else {
                // 无法解析时间戳的行默认保留
                $kept[] = $line;
            }
        }

        if ($removed === 0) {
            $this->info("没有 {$days} 天前的日志记录");
            return;
        }

        File::put($logFile, implode("\n", $kept) . "\n");
        $this->info("已删除 {$removed} 条 {$days} 天前的日志记录，保留 " . count($kept) . " 条");
    }
}
