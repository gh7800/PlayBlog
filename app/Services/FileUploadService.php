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
            //'mime_type'     => $file->getClientMimeType(),
            'file_size'          => $file->getSize(),
            'file_path'          => $path,
            'file_url'           => Storage::url($path),
        ];

        return FileModel::on('mysql_file')->create($model);
    }

    /**
     * 处理多个文件上传
     */
    public function uploadMultiple(array $files, string $dir = 'uploads'): array
    {
        $result = [];
        foreach ($files as $file) {
            $result[] = $this->uploadSingle($file, $dir)->toArray();
        }
        return $result;
    }
}
