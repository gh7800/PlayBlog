<?php

namespace Module\Car\API;

use App\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Module\Car\Services\CarService;

class CarEndController extends ApiController
{
    protected $carService;

    public function __construct(CarService $carService)
    {
        $this->carService = $carService;
    }

    /**
     * 结束用车
     */
    public function end(Request $request, string $uuid): JsonResponse
    {
        $validate = $request->validate([
            'start_km' => 'required|numeric|min:0',
            'end_km' => 'required|numeric|gt:start_km',
        ], [
            'start_km.required' => '请填写开始公里数',
            'end_km.required' => '请填写结束公里数',
            'end_km.gt' => '结束公里数必须大于开始公里数',
        ]);

        try {
            $result = $this->carService->end($request, $uuid);
            return $this->success($result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
