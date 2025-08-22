<?php

namespace Module\Document\api;

use App\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Module\Document\Flow\DocumentService;
use Module\Document\Models\Document;
use Module\Document\Models\DocumentTaskLog;

class DocumentController extends ApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 15);
            $page = $request->input('page', 1);
            $paginator = Document::paginate($perPage, ['*'], 'page', $page);

            return $this->successPaginator($paginator->items(), $paginator);
        } catch (\Exception $exception) {
            return $this->error($exception->getMessage());
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = [
            'title' => $request->input('title'),
            'content' => $request->input('content'),
            'code' => $request->input('code'),
            'status' => 'new',
            'status_title' => '未申请',
            'user_name' => $user->real_name,
            'user_uuid' => $user->uuid,
            'step' => 1
        ];

        try {
            $result = Document::create($data)->refresh();

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

            /*$result->taskLogs()->create([
                'user_name' => $user->real_name,
                'user_uuid' => $user->uuid,
                'status' => 'new',
                'status_title' => '待申请'
            ]);*/

            $result->load(['next', 'logs']);

            return $this->success($result);

        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        //
    }

    /**
     * 审批流程
     */
    public function approval(Request $request, DocumentService $service): Document
    {
        return $service->approval($request);
    }

    /**
     * 待处理
     */
    public function todo(Request $request)
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

}
