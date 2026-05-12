<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Module\Car\Models\CarApplication;
use Module\Car\Models\CarApprovalChain;
use Module\Car\Models\CarApprovalNode;
use Module\Car\Services\CarService;

/**
 * 自动审批车申请
 */
class CarAutoApprove extends Command
{
    protected $signature = 'car:auto-approve';
    protected $description = 'Auto-approve car applications at department head step after 2-minute timeout';

    public function handle()
    {
        $chain = CarApprovalChain::getActiveChain();
        if (!$chain) {
            return 0;
        }

        $deptHeadSteps = CarApprovalNode::where('chain_uuid', $chain->uuid)
            ->where('approver_type', 'dept_head')
            ->pluck('step')
            ->toArray();

        if (empty($deptHeadSteps)) {
            return 0;
        }

        $cutoffTime = now()->subMinutes(2);

        $applications = CarApplication::where('status', 'applying')
            ->whereIn('step', $deptHeadSteps)
            ->where('updated_at', '<=', $cutoffTime)
            ->get();

        if ($applications->isEmpty()) {
            return 0;
        }

        $carService = new CarService();
        $count = 0;

        foreach ($applications as $application) {
            try {
                $carService->autoApprove($application);
                $count++;
            } catch (\Exception $e) {
                $this->error("Auto-approve failed {$application->uuid}: {$e->getMessage()}");
            }
        }

        $this->info("Auto-approved {$count} application(s)");
        return 0;
    }
}
