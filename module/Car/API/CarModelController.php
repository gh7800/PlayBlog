<?php

namespace Module\Car\API;

use App\Http\Controllers\ApiController;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Module\Car\Models\CarModel;

class CarModelController extends ApiController
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $data = [
            'title' => $request->input('title'),
            'content' => $request->input('content'),
        ];
        logger('999999');

        try {
            CarModel::create($data);
            return $this->success($data);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     *
     * @param CarModel $carModel
     * @return Response
     */
    public function show(CarModel $carModel)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param CarModel $carModel
     * @return Response
     */
    public function edit(CarModel $carModel)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param CarModel $carModel
     * @return Response
     */
    public function update(Request $request, CarModel $carModel)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param CarModel $carModel
     * @return Response
     */
    public function destroy(CarModel $carModel)
    {
        //
    }
}
