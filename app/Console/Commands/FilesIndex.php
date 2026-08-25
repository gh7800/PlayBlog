<?php

namespace App\Console\Commands;

use App\Models\FileContent;
use App\Models\FileModel;
use App\Services\FileTextExtractor;
use Illuminate\Console\Command;

/**
 * 存量文件补建文本索引（两阶段架构第一阶段的一次性补录）。
 *
 * 用法：
 *   php artisan files:index              # 只处理还没有索引记录的文件
 *   php artisan files:index --all        # 强制重建全部索引（覆盖旧记录）
 *   php artisan files:index --batch=10 --sleep=2   # 调小批次、拉长间隔（低配服务器防OOM）
 *
 * 设计要点（2C2G 约束）：
 *  1) 分批处理（默认 20 条），每批之间 sleep（默认 1 秒），给 PHP-FPM / MySQL 留喘息空间。
 *  2) 物理文件缺失只标记 failed，不中断整批。
 *  3) SoftDeletes 默认作用域生效，已删除文件不会被处理。
 */
class FilesIndex extends Command
{
    protected $signature = 'files:index
                            {--all : 重建全部索引（含已解析过的文件）}
                            {--batch=20 : 每批处理条数}
                            {--sleep=1 : 每批之间的间隔秒数}';

    protected $description = '为存量文件提取文本并写入 file_contents 索引表';

    public function handle(FileTextExtractor $extractor)
    {
        $batch = max(1, (int) $this->option('batch'));
        $sleep = max(0, (int) $this->option('sleep'));
        $all   = (bool) $this->option('all');

        $total    = 0;   // 实际处理数
        $parsed   = 0;
        $unparse  = 0;
        $failed   = 0;
        $skipped  = 0;   // 已有索引记录被跳过（仅双保险逻辑会命中）
        $batchNo  = 0;

        // 基础查询：默认只取"还没有索引记录"的文件
        $query = FileModel::query();
        if (!$all) {
            $query->whereNotIn('id', FileContent::query()->select('file_id'));
        }

        $query->orderBy('id')->chunkById($batch, function ($files) use (
            $extractor, $all, $sleep, &$total, &$parsed, &$unparse, &$failed, &$skipped, &$batchNo
        ) {
            $batchNo++;
            foreach ($files as $file) {
                $total++;

                // 非 --all 模式下理论上不会命中已索引文件，双保险再查一次
                if (!$all && FileContent::where('file_id', $file->id)->exists()) {
                    $skipped++;
                    continue;
                }

                $extractor->index($file);

                $status = FileContent::where('file_id', $file->id)->value('status');
                if ($status === 'parsed') {
                    $parsed++;
                } elseif ($status === 'unparseable') {
                    $unparse++;
                } else {
                    $failed++;
                }
                $this->line("[{$file->id}] {$file->title} → {$status}");
            }

            if ($sleep > 0) {
                sleep($sleep);
            }
        });

        $this->info("处理完成：共 {$total} 个文件，解析成功 {$parsed}，格式不支持 {$unparse}，失败 {$failed}，跳过 {$skipped}");
        return 0;
    }
}
