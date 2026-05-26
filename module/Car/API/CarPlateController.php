<?php

namespace Module\Car\API;

use App\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Module\Car\Models\CarPlate;
use App\Services\PermissionService;

class CarPlateController extends ApiController
{
    /**
     * 车牌列表
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $plates = CarPlate::orderBy('created_at', 'desc')->get();
            return $this->success($plates);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 添加车牌
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!PermissionService::userHasPermission($user->uuid, 'car_admin')) {
            return $this->error('无管理权限');
        }

        $validate = $request->validate([
            'plate_number' => 'required|string|unique:car_plates,plate_number',
            'description' => 'nullable|string',
        ], [
            'plate_number.required' => '请填写车牌号',
            'plate_number.unique' => '车牌号已存在',
        ]);

        try {
            $plate = CarPlate::create([
                'plate_number' => $validate['plate_number'],
                'description' => $validate['description'] ?? '',
                'status' => 0,
            ]);
            return $this->success($plate);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 更新车牌
     */
    public function update(Request $request, string $uuid): JsonResponse
    {
        $request->validate([
            'plate_number' => 'sometimes|required|string|unique:car_plates,plate_number,' . $uuid . ',uuid',
            'description' => 'nullable|string',
            'status' => 'sometimes|integer|in:0,1',
        ], [
            'plate_number.required' => '请填写车牌号',
            'plate_number.unique' => '车牌号已存在',
            'status.in' => '状态值无效',
        ]);

        $user = $request->user();

        /*if (!PermissionService::userHasPermission($user->uuid, 'car_admin')) {
            return $this->error('无管理权限');
        }*/

        try {
            $plate = CarPlate::where('uuid', $uuid)->firstOrFail();

            if ($request->has('plate_number')) {
                $plate->plate_number = $request->input('plate_number');
            }
            if ($request->has('description')) {
                $plate->description = $request->input('description');
            }
            if ($request->has('status')) {
                $plate->status = (int)$request->input('status');
            }
            $plate->save();

            return $this->success($plate);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 删除车牌
     */
    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();

        if (!PermissionService::userHasPermission($user->uuid, 'car_admin')) {
            return $this->error('无管理权限');
        }

        try {
            $plate = CarPlate::where('uuid', $uuid)->firstOrFail();
            $plate->delete();
            return $this->success($plate, '删除成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
