<?php

namespace Module\Document\api;

use App\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Module\Document\Flow\DocumentService;
use Module\Document\Models\Document;

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

            return $this->successPaginator($paginator->items(),$paginator);
        }catch (\Exception $exception){
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
            'user_uuid' => $user->uuid
        ];

        try {
            $result = Document::create($data)->refresh();
//            $result->status = 'new';
//            $result->status_title = '未申请';

            $result->Next()->create([
                'text' => '新建',
                'step' => '1'
            ]);

            $result->addLogs()->create([
                'user_name' => $user->real_name,
                'user_uuid' => $user->uuid,
                'status' => 'new',
                'status_title' => '未申请',
                'step' => '1'
            ]);

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
    public function approve($uuid,DocumentService $service)
    {
        return $service->approve($uuid);
    }

    /**
     * 驳回
     */
    public function reject(Request $request){

    }
}
