<?php

namespace Module\Car\API;

use App\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Module\Car\Models\CarPlate;
use Module\Car\Services\CarService;
use Module\Car\Services\PermissionService;

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
        $user = $request->user();

        // 检查权限
        if (!PermissionService::userHasPermission($user->uuid, 'car_approve')) {
            return $this->error('无审批权限');
        }

        $validate = $request->validate([
            'uuid' => 'required|uuid',
            'action' => 'required|in:agree,reject',
            'plate_id' => 'required_if:action,agree|uuid',
            'reply' => 'nullable|string',
        ], [
            'uuid.required' => '请选择要审批的申请',
            'action.required' => '请选择审批操作',
            'plate_id.required_if' => '同意时必须选择车牌',
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
            $result = $this->carService->todoList($request);
            return $this->success($result);
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
}
