<?php

namespace Module\Car\Services;

use Module\Car\Models\CarApprovalChain;
use Module\Car\Models\CarApprovalNode;
use Module\Car\Models\CarApplication;

class CarApprovalService
{
    public function getCurrentNode(CarApplication $application): ?CarApprovalNode
    {
        $chain = CarApprovalChain::getActiveChain();
        if (!$chain) {
            return null;
        }

        return CarApprovalNode::where('chain_uuid', $chain->uuid)
            ->where('step', $application->step)
            ->first();
    }

    public function getCurrentApprovers(CarApplication $application): array
    {
        $node = $this->getCurrentNode($application);
        if (!$node) {
            return [];
        }

        return $node->getApproverUuids($application->user ?? null);
    }

    public function isApprover(CarApplication $application, string $userUuid): bool
    {
        $approverUuids = $this->getCurrentApprovers($application);
        return in_array($userUuid, $approverUuids);
    }

    public function getNextNode(CarApplication $application): ?CarApprovalNode
    {
        $chain = CarApprovalChain::getActiveChain();
        if (!$chain) {
            return null;
        }

        return CarApprovalNode::where('chain_uuid', $chain->uuid)
            ->where('step', '>', $application->step)
            ->orderBy('step', 'asc')
            ->first();
    }

    public function isLastNode(CarApplication $application): bool
    {
        return is_null($this->getNextNode($application));
    }

    public function getNextStep(CarApplication $application): int
    {
        $nextNode = $this->getNextNode($application);
        return $nextNode ? $nextNode->step : $application->step;
    }
}
