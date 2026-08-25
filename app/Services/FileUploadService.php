<?php

namespace App\Services;

use App\Models\FileModel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class FileUploadService
{
    /**
     * 处理单个文件上传
     * @throws \Exception
     */
    public function uploadSingle(UploadedFile $file, string $dir = 'uploads')
    {
        if (!$file->isValid()) {
            throw new \Exception("文件 {$file->getClientOriginalName()} 无效");
        }

        // 存储文件
        $path = $file->store($dir, 'public');

        $model = [
            'title' => $file->getClientOriginalName(),
            'file_name' => $file->getClientMimeType(),
            'file_size'          => $file->getSize(),
            'file_path'          => $path,
            //'file_url'           => env('APP_URL').Storage::url($path),
        ];

        $model = FileModel::create($model);

        // 上传成功后同步提取文本写入 file_contents 索引表（两阶段架构：上传建索引）
        // 提取器内部吞掉所有异常（记 status=failed），这里再兜一层，绝不影响上传主流程
        try {
            app(FileTextExtractor::class)->index($model);
        } catch (\Throwable $e) {
            \Log::error("文件文本索引失败 file_id={$model->id}: " . $e->getMessage());
        }

        return $model;
    }

    /**
     * 处理多个文件上传
     */
    public function uploadMultiple(array $files, string $dir = 'uploads'): array
    {
        $result = [];
        foreach ($files as $file) {
            $result[] = $this->uploadSingle($file, $dir);
        }
        return $result;
    }
}
