<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiController;
use App\Services\OrganizationService;
use Illuminate\Http\JsonResponse;

class OrganizationController extends ApiController
{
    public function tree(): JsonResponse
    {
        try {
            $tree = OrganizationService::getOrganizationTree();
            return $this->success($tree);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}