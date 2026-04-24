<?php

namespace Module\Car\API;

use App\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Module\Car\Models\CarApplication;
use Module\Car\Services\CarService;

class CarApplyController extends ApiController
{
    protected $carService;

    public function __construct(CarService $carService)
    {
        $this->carService = $carService;
    }

    /**
     * 申请用车
     */
    public function store(Request $request): JsonResponse
    {
        $validate = $request->validate([
            'car_type' => 'required|in:general,business,other',
            'reason' => 'required|string',
            'passenger_count' => 'required|integer|min:1',
            'use_time' => 'required|date',
            'remark' => 'nullable|string',
        ], [
            'car_type.required' => '请选择用车类型',
            'reason.required' => '请填写用车事由',
            'passenger_count.required' => '请填写用车人数',
            'use_time.required' => '请选择用车时间',
        ]);

        try {
            $result = $this->carService->apply($request);
            return $this->success($result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 我的申请列表
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $result = $this->carService->list($request);
            return $this->successPaginator($result['data'], $result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 申请详情
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        try {
            $user = $request->user();
            $application = CarApplication::where('uuid', $uuid)
                ->firstOrFail()
                ->load(['logs', 'taskLogs']);

            if ($application->user_uuid === $user->uuid) {
                $application->load('next');
            }

            return $this->success($application);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 删除申请
     */
    public function destroy(Request $request, string $uuid): JsonResponse
    {
        try {
            $user = $request->user();
            $application = CarApplication::where('uuid', $uuid)
                ->where('user_uuid', $user->uuid)
                ->where('status', 'applying')
                ->firstOrFail();

            $application->delete();

            return $this->success($application, '删除成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
