<?php

namespace Module\Car\API;

use App\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Module\Car\Models\CarPlate;
use Module\Car\Services\CarService;

class CarApproveController extends ApiController
{
    protected $carService;

    public function __construct(CarService $carService)
    {
        $this->carService = $carService;
    }

    /**
     * 审批
     */
    public function approve(Request $request): JsonResponse
    {
        $validate = $request->validate([
            'uuid' => 'required|uuid',
            'action' => 'required|in:agree,reject',
            'plate_id' => 'nullable|uuid',
            'reply' => 'nullable|string',
        ], [
            'uuid.required' => '请选择要审批的申请',
            'action.required' => '请选择审批操作',
        ]);

        try {
            $result = $this->carService->approve($request);
            return $this->success($result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 待处理列表
     */
    public function todo(Request $request): JsonResponse
    {
        try {
            $paginator = $this->carService->todoList($request);
            return $this->successPaginator($paginator->items(), $paginator);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 已处理列表
     */
    public function processed(Request $request): JsonResponse
    {
        try {
            $result = $this->carService->processedList($request);
            return $this->success($result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 获取可用车牌列表
     */
    public function plates(Request $request): JsonResponse
    {
        $plates = CarPlate::where('status', 0)->get();
        return $this->success($plates);
    }

    /**
     * 里程异常列表
     */
    public function mileageAbnormal(Request $request): JsonResponse
    {
        try {
            $paginator = $this->carService->mileageAbnormalList($request);
            return $this->successPaginator($paginator->items(), $paginator);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
