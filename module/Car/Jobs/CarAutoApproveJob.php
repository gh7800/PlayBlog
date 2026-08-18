<?php

namespace Module\Car\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Module\Car\Models\CarApplication;
use Module\Car\Services\CarService;

class CarAutoApproveJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public $applicationUuid;
    public $step;

    public function __construct(string $applicationUuid, int $step)
    {
        $this->applicationUuid = $applicationUuid;
        $this->step = $step;
    }

    public function handle()
    {
        $application = CarApplication::where('uuid', $this->applicationUuid)->first();
        if (!$application) {
            return;
        }

        if ($application->status !== 'applying' || $application->step !== $this->step) {
            return;
        }

        try {
            (new CarService())->autoApprove($application);
        } catch (\Exception $e) {
            // 自动审批失败（如下一节点未找到审批人），记录错误日志，不再静默卡死
            Log::error('CarAutoApproveJob failed: ' . $e->getMessage(), [
                'application_uuid' => $this->applicationUuid,
                'step' => $this->step,
            ]);
        }
    }
}
