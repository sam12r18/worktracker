<?php
namespace App\Services;

use App\Models\{ActivitySession,ActivityType,Customer,PricingOverride,Project};
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class PricingService
{
    public function resolve(ActivitySession $activity): array
    {
        $activity->loadMissing('project');
        $type = ActivityType::query()
            ->whereKey($activity->activity_type_id)
            ->where(fn($q) => $q->whereNull('user_id')->orWhere('user_id', $activity->user_id))
            ->firstOrFail();
        $at = $activity->started_at;
        $project = $activity->project;

        $activityPricing = $this->activityPricingAt($type, $at);
        $projectPricing = $project ? $this->projectPricingAt($project, $at) : [
            'customer_id'=>null,'multiplier'=>1.0,'is_billable_default'=>true,
        ];
        $customer = $projectPricing['customer_id']
            ? Customer::query()->whereKey($projectPricing['customer_id'])->where('user_id', $activity->user_id)->first()
            : null;
        $customerPricing = $customer ? $this->customerPricingAt($customer, $at) : ['multiplier'=>1.0,'currency'=>$activityPricing['currency']];

        $override = $this->findOverride((int)$activity->user_id, $type->id, $at, $project?->id, $customer?->id);
        $base = $activityPricing['rate'];
        $currency = $activityPricing['currency'];
        $customerMultiplier = (float)$customerPricing['multiplier'];
        $projectMultiplier = (float)$projectPricing['multiplier'];

        if ($override) {
            $effective = (int)$override->hourly_rate_minor;
            $source = $override->project_id ? 'project_activity_override' : 'customer_activity_override';
            $currency = $override->currency;
        } else {
            if ($customer && $customerPricing['currency'] !== $currency) {
                abort(422, 'Activity and customer currencies differ. Explicit FX support is required.');
            }
            $effective = (int)round($base * $customerMultiplier * $projectMultiplier);
            $source = 'historical_base_x_customer_x_project';
        }

        $billable = $activity->is_billable
            ?? ((!$projectPricing['is_billable_default']) ? false : (bool)$activityPricing['is_billable_default']);
        $seconds = $billable ? max(0, (int)$activity->duration_seconds) : 0;
        $amount = (int)round($effective * ($seconds / 3600));

        return [
            'customer_id'=>$customer?->id,
            'base_rate_minor'=>$base,
            'customer_multiplier'=>$customerMultiplier,
            'project_multiplier'=>$projectMultiplier,
            'effective_rate_minor'=>$effective,
            'billable_seconds'=>$seconds,
            'amount_minor'=>$amount,
            'currency'=>$currency,
            'resolution_source'=>$source,
            'pricing_override_id'=>$override?->id,
        ];
    }

    public function customerIdAt(Project $project, CarbonInterface $at): ?string
    {
        return $this->projectPricingAt($project, $at)['customer_id'];
    }

    public function customerCurrencyAt(Customer $customer, CarbonInterface $at): string
    {
        return (string)$this->customerPricingAt($customer, $at)['currency'];
    }

    private function activityPricingAt(ActivityType $type, CarbonInterface $at): array
    {
        $query = DB::table('activity_rate_history')->where('activity_type_id', $type->id);
        $row = (clone $query)->where('effective_from', '<=', $at)->orderByDesc('effective_from')->orderByDesc('id')->first()
            ?? (clone $query)->orderBy('effective_from')->orderBy('id')->first();
        return [
            'rate'=>(int)($row->hourly_rate_minor ?? $type->base_hourly_rate_minor),
            'currency'=>(string)($row->currency ?? $type->currency),
            'is_billable_default'=>(bool)($row->is_billable_default ?? $type->is_billable_default),
        ];
    }

    private function customerPricingAt(Customer $customer, CarbonInterface $at): array
    {
        $query = DB::table('customer_multiplier_history')->where('customer_id', $customer->id);
        $row = (clone $query)->where('effective_from', '<=', $at)->orderByDesc('effective_from')->orderByDesc('id')->first()
            ?? (clone $query)->orderBy('effective_from')->orderBy('id')->first();
        return [
            'multiplier'=>(float)($row->multiplier ?? $customer->rate_multiplier),
            'currency'=>(string)($row->currency ?? $customer->currency),
        ];
    }

    private function projectPricingAt(Project $project, CarbonInterface $at): array
    {
        $query = DB::table('project_multiplier_history')->where('project_id', $project->id);
        $row = (clone $query)->where('effective_from', '<=', $at)->orderByDesc('effective_from')->orderByDesc('id')->first()
            ?? (clone $query)->orderBy('effective_from')->orderBy('id')->first();
        return [
            'customer_id'=>$row->customer_id ?? $project->customer_id,
            'multiplier'=>(float)($row->multiplier ?? $project->rate_multiplier ?? 1.0),
            'is_billable_default'=>(bool)($row->is_billable_default ?? $project->is_billable_default ?? true),
        ];
    }

    private function findOverride(int $userId, string $activityTypeId, CarbonInterface $at, ?string $projectId, ?string $customerId): ?PricingOverride
    {
        $base = PricingOverride::query()->where('user_id',$userId)->where('activity_type_id',$activityTypeId)
            ->where('effective_from','<=',$at)
            ->where(fn($q)=>$q->whereNull('effective_until')->orWhere('effective_until','>',$at));
        if ($projectId) {
            $match = (clone $base)->where('project_id',$projectId)->orderByDesc('effective_from')->orderByDesc('created_at')->first();
            if ($match) return $match;
        }
        if ($customerId) return (clone $base)->whereNull('project_id')->where('customer_id',$customerId)->orderByDesc('effective_from')->orderByDesc('created_at')->first();
        return null;
    }
}
