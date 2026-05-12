<?php

namespace Module\Car\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
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

        (new CarService())->autoApprove($application);
    }
}
