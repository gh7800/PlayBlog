<?php

namespace Module\Document\api;

use App\Http\Controllers\ApiController;
use App\Models\BlogUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Module\Document\DocumentStatus;
use Module\Document\Flow\DocumentService;
use Module\Document\Models\Document;

class DocumentController extends ApiController
{
    /**
     * 获取列表（收文管理-全部 / 我的请示）
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $page = $request->input('page', 1);
            $perPage = $request->input('per_page', config('pagination.per_page'));
            $keyword = $request->input('keyword');
            $type = $request->input('type');
            $mine = $request->input('mine', 0); // 1=我的请示

            $query = Document::query()
                ->with(['applicant.company', 'applicant.department']);

            // 我的请示：只看自己发起的
            if ($mine) {
                $query->where('user_uuid', $user->uuid);
            }

            // 关键字模糊查询：标题 / 内容 / 备注 / 类型 / 申请人
            if ($keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->where('title', 'like', "%{$keyword}%")
                        ->orWhere('content', 'like', "%{$keyword}%")
                        ->orWhere('remark', 'like', "%{$keyword}%")
                        ->orWhere('user_name', 'like', "%{$keyword}%")
                        ->orWhere('type', 'like', "%{$keyword}%");
                });
            }

            // 类型筛选
            if ($type) {
                $query->where('type', $type);
            }

            $paginator = $query->orderBy('created_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);

            return $this->successPaginator($this->decorateApplicant($paginator->items()), $paginator);
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
     * Store a newly created resource in storage. 新增请示
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validate = $request->validate([
            'title' => 'required|string',
            'type' => 'required|in:zongbanhui,dangweihui,dongshihui',
        ], [
            'title.required' => '请输入标题',
            'type.required' => '请选择请示类型',
            'type.in' => '请示类型不正确',
        ]);

        $files = $request->input('files');

        $data = [
            'title' => $validate['title'],
            'type' => $validate['type'],
            'content' => $request->input('content'),
            'remark' => $request->input('remark'),
            'code' => $request->input('code'),
            'status' => DocumentStatus::NEW,
            'status_title' => DocumentStatus::getStatusTitle(DocumentStatus::NEW),
            'user_name' => $user->real_name,
            'user_uuid' => $user->uuid,
            'step' => 1
        ];

        $result = Document::query()->create($data)->refresh();

        // 操作记录（logs）：提交申请
        $result->logs()->create([
            'user_name' => $user->real_name,
            'user_uuid' => $user->uuid,
            'status' => DocumentStatus::NEW,
            'status_title' => '提交申请',
            'result' => 1,
            'step' => 1,
        ]);

        // 下一步操作：同意 / 驳回
        $result->next()->createMany([
            ['step' => 1, 'text' => '驳回'],
            ['step' => 2, 'text' => '同意'],
        ]);

        // 创建待办：行政办公室主任审批（权限组 role_director）
        $service = new DocumentService();
        $approverUuids = $service->getStepApprovers(2);
        foreach ($approverUuids as $uuid) {
            $approver = BlogUser::where('uuid', $uuid)->first();
            if (!$approver) {
                continue;
            }
            $result->taskLogs()->create([
                'user_uuid' => $approver->uuid,
                'user_name' => $approver->real_name ?? '',
                'status' => DocumentStatus::PENDING,
                'status_title' => DocumentStatus::getStatusTaskTitle(DocumentStatus::PENDING),
            ]);
        }

        // 遍历数组，逐个创建文件记录
        if (is_array($files)) {
            $result->files()->createMany($files);
        }

        $result->load(['next', 'logs', 'files', 'taskLogs']);

        return $this->success($result);
    }

    /**
     * Display the specified resource. 请示详情（含添加人的单位、部门）
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();
        try {
            $document = Document::where('uuid', $id)
                ->firstOrFail()
                ->load(['files', 'logs', 'taskLogs', 'next', 'applicant.company', 'applicant.department']);

            if ($document->taskLogs->contains('user_uuid', $user->uuid)) {
                $document->load('next'); // 满足条件再加载 next
            }

            // 补充申请人单位、部门
            $applicant = $document->applicant;
            $document->user_company = $applicant && $applicant->company ? $applicant->company->name : '';
            $document->user_department = $applicant && $applicant->department ? $applicant->department->name : '';

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
     * 待处理（收文管理-待处理：当前用户是审批人）
     */
    public function todo(Request $request): JsonResponse
    {
        $user_uuid = $request->user()->uuid;
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', config('pagination.per_page'));
        $keyword = $request->input('keyword');

        $query = Document::whereHas('taskLogs', function ($query) use ($user_uuid) {
            $query->where('user_uuid', $user_uuid);
        })
            ->with(['taskLogs', 'applicant.company', 'applicant.department']);

        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('title', 'like', "%{$keyword}%")
                    ->orWhere('content', 'like', "%{$keyword}%")
                    ->orWhere('remark', 'like', "%{$keyword}%")
                    ->orWhere('user_name', 'like', "%{$keyword}%");
            });
        }

        $paginator = $query->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return $this->successPaginator($this->decorateApplicant($paginator->items()), $paginator);
    }

    /**
     * 已处理（收文管理-已处理：当前用户审批过的，不含自己提交的记录）
     */
    public function processed(Request $request): JsonResponse
    {
        $user_uuid = $request->user()->uuid;
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', config('pagination.per_page'));
        $keyword = $request->input('keyword');

        // 只统计审批操作（status != new，new 是申请人提交请示时写入的日志）
        $query = Document::whereHas('logs', function ($query) use ($user_uuid) {
            $query->where('user_uuid', $user_uuid)
                ->where('status', '!=', DocumentStatus::NEW);
        })
            ->with(['taskLogs', 'logs', 'applicant.company', 'applicant.department']);

        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('title', 'like', "%{$keyword}%")
                    ->orWhere('content', 'like', "%{$keyword}%")
                    ->orWhere('remark', 'like', "%{$keyword}%")
                    ->orWhere('user_name', 'like', "%{$keyword}%");
            });
        }

        $paginator = $query->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return $this->successPaginator($this->decorateApplicant($paginator->items()), $paginator);
    }

    /**
     * 我的请示：查看我申请的请示
     */
    public function mine(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $page = $request->input('page', 1);
            $perPage = $request->input('per_page', config('pagination.per_page'));
            $keyword = $request->input('keyword');

            $query = Document::query()
                ->where('user_uuid', $user->uuid)
                ->with(['applicant.company', 'applicant.department']);

            if ($keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->where('title', 'like', "%{$keyword}%")
                        ->orWhere('content', 'like', "%{$keyword}%")
                        ->orWhere('remark', 'like', "%{$keyword}%");
                });
            }

            $paginator = $query->orderBy('created_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);

            return $this->successPaginator($this->decorateApplicant($paginator->items()), $paginator);
        } catch (\Exception $exception) {
            return $this->error($exception->getMessage());
        }
    }

    /**
     * 为列表条目附加申请人单位、部门（列表接口与详情保持一致）
     * @param array|\Illuminate\Support\Collection $documents
     * @return mixed
     */
    private function decorateApplicant($documents)
    {
        foreach ($documents as $document) {
            $applicant = $document->applicant;
            $document->user_company = $applicant && $applicant->company ? $applicant->company->name : '';
            $document->user_department = $applicant && $applicant->department ? $applicant->department->name : '';
        }
        return $documents;
    }
}
