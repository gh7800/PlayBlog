<?php

namespace App\Http\Controllers;

use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class FileController extends ApiController
{
    protected FileUploadService $uploader;

    public function __construct(FileUploadService $uploader)
    {
        $this->uploader = $uploader;
    }

    /**
     * 上传单个或多个文件
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'files'   => 'required',
            'files.*' => 'file|max:102400', // 限制 100M
        ]);

        $files = $request->file('files');

        try {
            if (is_array($files)) {
                $result = $this->uploader->uploadMultiple($files);
            } else {
                $result = $this->uploader->uploadSingle($files);
            }
            return $this->success($result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
