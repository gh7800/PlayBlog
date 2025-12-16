<?php

namespace Module\Document\api;

use App\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Module\Document\Flow\DocumentService;
use Module\Document\Models\Document;

class DocumentController extends ApiController
{
    /**
     * Display a listing of the resource.获取列表
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $page = $request->input('page', 1);
            $perPage = $request->input('per_page', 15);
            $keyword = $request->input('keyword');

            $query = Document::query();
            if ($keyword) {
                $query->where(function ($q) use ($keyword) {
                    $columns = Schema::getColumnListing((new Document())->getTable());
                    // 排除不需要查询的列
                    $excludeColumns = ['id', 'created_at', 'updated_at', 'deleted_at'];
                    foreach ($columns as $column) {
                        if (!in_array($column, $excludeColumns)) {
                            $q->orWhere($column, 'like', "%{$keyword}%");
                        }
                    }
                });
            }

            $paginator = $query-> paginate($perPage, ['*'], 'page', $page);

            return $this->successPaginator($paginator->items(), $paginator);
        } catch (\Exception $exception) {
            return $this->error($exception->getMessage());
        }
    }

    /**
     * Show the form for creating a new resource.创建表单
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.保存数据
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validate = $request->validate([
            'title' => 'required|string',
        ], [
            'title.required' => '请输入标题'
        ]);

        $files = $request->input('files');

        $data = [
            'title' => $validate['title'],
            'content' => $request->input('content'),
            'code' => $request->input('code'),
            'status' => DocumentStatus::NEW,
            'status_title' => DocumentStatus::getStatusTitle(DocumentStatus::NEW),
            'user_name' => $user->real_name,
            'user_uuid' => $user->uuid,
            'step' => 1
        ];

        $result = Document::query()->create($data)->refresh();

        $result->next()->create([
            'text' => '提交申请',
            'step' => '2'
        ]);

        $result->logs()->create([
            'user_name' => $user->real_name,
            'user_uuid' => $user->uuid,
            'status' => 'new',
            'status_title' => '未申请',
            'step' => '1'
        ]);

        // 遍历数组，逐个创建文件记录
        if (is_array($files)) {
            $result->files()->createMany($files);
        }

        $result->load(['next', 'logs', 'files']);

        return $this->success($result);
    }

    /**
     * Display the specified resource.获取单条数据
     */
    public function show(Request $request,$id)
    {
        $user = $request->user();
        try {
            $document = Document::where('uuid', $id)
                ->firstOrFail()
                ->load(['files','logs','taskLogs']);

            if ($document->taskLogs->contains('user_uuid', $user->uuid)) {
                $document->load('next'); // 满足条件再加载 next
            }
            return $this->success($document);
        } catch (\Exception $exception) {
            return $this->error($exception->getMessage());
        }
    }

    /**
     * Show the form for editing the specified resource.显示表单
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage. 更新数据
     */
    public function update(Request $request, $id)
    {
        try {
            $document = Document::where('uuid', $id)->firstOrFail();
            $document->title = $request->input('title');
            $document->save();
            return $this->success($document, '成功');
        } catch (\Exception $exception) {
            return $this->error($exception->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.删除数据
     */
    public function destroy($id): JsonResponse
    {
        $document = Document::where('uuid', $id)->firstOrFail();
        $document->delete();
        return $this->success($document);
    }

    /**
     * 待处理
     */
    public function todo(Request $request): JsonResponse
    {
        $user_uuid = $request->user()->uuid;
        $result = Document::whereHas('taskLogs', function ($query) use ($user_uuid) {
            $query->where('user_uuid', $user_uuid);
        })
            ->with(['taskLogs'])
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->success($result);
    }

    /**
     * 已处理
     */
    public function processed(Request $request): JsonResponse
    {
        $user_uuid = $request->user()->uuid;
        $result = Document::whereHas('logs', function ($query) use ($user_uuid) {
            $query->where('user_uuid', $user_uuid);
        })
            ->with(['taskLogs'])
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->success($result);
    }

}
