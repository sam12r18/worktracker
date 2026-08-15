<?php

namespace App\Http\Controllers\WorkTracker;

use App\Http\Controllers\Controller;
use App\Models\ActivitySession;
use App\Models\ActivityType;
use App\Models\Customer;
use App\Models\PricingOverride;
use App\Models\Project;
use App\Services\PricingService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class BillingController extends Controller
{
    public function index(Request $request, PricingService $pricing)
    {
        $userId = $request->user()->id;
        $customers = Customer::where('user_id', $userId)->orderBy('name')->get();
        $types = ActivityType::where(fn($q) => $q->whereNull('user_id')->orWhere('user_id', $userId))
            ->orderByDesc('is_active')->orderBy('sort_order')->orderBy('name')->get();
        $projects = Project::where('user_id', $userId)->with('customer')->orderBy('name')->get();
        $overrides = PricingOverride::query()
            ->where('user_id', $userId)
            ->with(['customer:id,name', 'project:id,name', 'activityType:id,name,code'])
            ->orderByDesc('effective_from')->get();
        $activityRateHistory = DB::table('activity_rate_history as h')
            ->join('activity_types as t', 't.id', '=', 'h.activity_type_id')
            ->where(fn($q) => $q->whereNull('t.user_id')->orWhere('t.user_id', $userId))
            ->orderByDesc('h.effective_from')->limit(100)
            ->get(['h.*', 't.name as activity_type_name', 't.code as activity_type_code']);

        return view('worktracker.billing.index', compact('customers', 'types', 'projects', 'overrides', 'activityRateHistory'));
    }

    public function preview(Request $request, PricingService $pricing)
    {
        $data = $request->validate([
            'project_id' => ['required', 'uuid'],
            'activity_type_id' => ['required','uuid',Rule::exists('activity_types','id')->where(fn($q)=>$q->whereNull('user_id')->orWhere('user_id',$request->user()->id))],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'effective_at' => ['nullable','date'],
        ]);

        $project = Project::where('user_id', $request->user()->id)->findOrFail($data['project_id']);
        $at=isset($data['effective_at']) ? \Carbon\CarbonImmutable::parse($data['effective_at']) : now();
        $activity = new ActivitySession([
            'project_id' => $project->id,
            'activity_type_id' => $data['activity_type_id'],
            'duration_seconds' => $data['duration_minutes'] * 60,
            'started_at' => $at,
            'ended_at' => $at->copy()->addMinutes($data['duration_minutes']),
        ]);
        $activity->user_id = $request->user()->id;
        $activity->setRelation('project', $project->load('customer'));

        return response()->json($pricing->resolve($activity));
    }

    public function storeCustomer(Request $request)
    {
        $data = $request->validate([
            'name' => ['required','string','max:190'],
            'company_name' => ['nullable','string','max:190'],
            'rate_multiplier' => ['required','numeric','min:0','max:100'],
            'currency' => ['required','string','max:8'],
            'effective_from' => ['nullable','date'],
        ]);
        $effectiveFrom=$data['effective_from'] ?? now(); unset($data['effective_from']);
        $data['user_id'] = $request->user()->id;
        $customer=Customer::create($data);
        DB::table('customer_multiplier_history')->insert(['customer_id'=>$customer->id,'multiplier'=>$customer->rate_multiplier,'currency'=>$customer->currency,'effective_from'=>$effectiveFrom,'created_at'=>now(),'updated_at'=>now()]);
        return back()->with('status', 'مشتری ثبت شد.');
    }

    public function storeActivityType(Request $request)
    {
        $data = $request->validate([
            'code' => ['required','alpha_dash','max:64'],
            'name' => ['required','string','max:190'],
            'base_hourly_rate_minor' => ['required','integer','min:0'],
            'currency' => ['required','string','max:8'],
            'is_billable_default' => ['nullable','boolean'],
            'is_active' => ['nullable','boolean'],
            'sort_order' => ['nullable','integer','min:0','max:100000'],
            'effective_from' => ['nullable','date'],
        ]);
        $effectiveFrom=$data['effective_from'] ?? now(); unset($data['effective_from']);
        $data['user_id'] = $request->user()->id;
        $data['is_billable_default'] = (bool)($data['is_billable_default'] ?? false);
        $data['is_active'] = (bool)($data['is_active'] ?? false);
        $data['sort_order'] = (int)($data['sort_order'] ?? 0);
        $data['version'] = 1;
        $type=ActivityType::create($data);
        DB::table('activity_rate_history')->insert(['activity_type_id'=>$type->id,'hourly_rate_minor'=>$type->base_hourly_rate_minor,'currency'=>$type->currency,'is_billable_default'=>$type->is_billable_default,'effective_from'=>$effectiveFrom,'created_at'=>now(),'updated_at'=>now()]);
        return back()->with('status', 'نوع فعالیت و نرخ پایه ثبت شد.');
    }

    public function updateProjectPricing(Request $request, string $projectId)
    {
        $project = Project::where('user_id', $request->user()->id)->findOrFail($projectId);
        $data = $request->validate([
            'customer_id' => ['nullable','uuid'],
            'rate_multiplier' => ['required','numeric','min:0','max:100'],
            'is_billable_default' => ['nullable','boolean'],
            'effective_from' => ['required','date'],
        ]);
        $effectiveFrom=$data['effective_from']; unset($data['effective_from']);
        if (!empty($data['customer_id'])) {
            Customer::where('user_id', $request->user()->id)->findOrFail($data['customer_id']);
        }
        $data['is_billable_default'] = (bool)($data['is_billable_default'] ?? false);
        $project->fill($data);
        if ($project->isDirty()) $project->version = ((int)$project->version) + 1;
        $project->save();
        DB::table('project_multiplier_history')->insert(['project_id'=>$project->id,'customer_id'=>$project->customer_id,'multiplier'=>$project->rate_multiplier,'is_billable_default'=>$project->is_billable_default,'effective_from'=>$effectiveFrom,'created_at'=>now(),'updated_at'=>now()]);
        return back()->with('status', 'قیمت‌گذاری پروژه به‌روزرسانی شد.');
    }

    public function updateCustomer(Request $request, string $customerId)
    {
        $customer=Customer::where('user_id',$request->user()->id)->findOrFail($customerId);
        $data=$request->validate(['name'=>['required','string','max:190'],'company_name'=>['nullable','string','max:190'],'rate_multiplier'=>['required','numeric','min:0','max:100'],'currency'=>['required','string','max:8'],'effective_from'=>['required','date']]);
        $customer->update(collect($data)->except('effective_from')->all());
        DB::table('customer_multiplier_history')->insert(['customer_id'=>$customer->id,'multiplier'=>$customer->rate_multiplier,'currency'=>$customer->currency,'effective_from'=>$data['effective_from'],'created_at'=>now(),'updated_at'=>now()]);
        return back()->with('status','مشتری و تاریخچه ضریب به‌روزرسانی شد.');
    }

    public function updateActivityType(Request $request, string $activityTypeId)
    {
        $type=ActivityType::where(fn($q)=>$q->whereNull('user_id')->orWhere('user_id',$request->user()->id))->findOrFail($activityTypeId);
        abort_if($type->user_id===null,403,'Global activity types are read-only for this user.');
        $data=$request->validate(['name'=>['required','string','max:190'],'base_hourly_rate_minor'=>['required','integer','min:0'],'currency'=>['required','string','max:8'],'is_billable_default'=>['nullable','boolean'],'is_active'=>['nullable','boolean'],'sort_order'=>['required','integer','min:0','max:100000'],'effective_from'=>['required','date']]);
        $type->fill(collect($data)->except('effective_from')->all()); $type->is_billable_default=(bool)($data['is_billable_default']??false); $type->is_active=(bool)($data['is_active']??false); $type->version=((int)$type->version)+1; $type->save();
        DB::table('activity_rate_history')->insert(['activity_type_id'=>$type->id,'hourly_rate_minor'=>$type->base_hourly_rate_minor,'currency'=>$type->currency,'is_billable_default'=>$type->is_billable_default,'effective_from'=>$data['effective_from'],'created_at'=>now(),'updated_at'=>now()]);
        return back()->with('status','نرخ فعالیت و تاریخچه آن به‌روزرسانی شد.');
    }

    public function storeOverride(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['nullable','uuid',Rule::exists('customers','id')->where('user_id',$request->user()->id)],
            'project_id' => ['nullable','uuid',Rule::exists('projects','id')->where('user_id',$request->user()->id)],
            'activity_type_id' => ['required','uuid',Rule::exists('activity_types','id')->where(fn($q)=>$q->whereNull('user_id')->orWhere('user_id',$request->user()->id))],
            'hourly_rate_minor' => ['required','integer','min:0'],
            'currency' => ['required','string','max:8'],
            'effective_from' => ['required','date'],
            'effective_until' => ['nullable','date','after:effective_from'],
            'note' => ['nullable','string','max:500'],
        ]);
        abort_if(empty($data['customer_id']) && empty($data['project_id']), 422, 'Customer or project is required.');
        if (!empty($data['project_id'])) {
            $project = Project::where('user_id', $request->user()->id)->findOrFail($data['project_id']);
            $data['customer_id'] ??= $project->customer_id;
        } elseif (!empty($data['customer_id'])) {
            Customer::where('user_id', $request->user()->id)->findOrFail($data['customer_id']);
        }
        $data['user_id'] = $request->user()->id;
        PricingOverride::create($data);
        return back()->with('status', 'نرخ استثنا ثبت شد.');
    }

    public function updateOverride(Request $request, PricingOverride $override)
    {
        abort_unless((string) $override->user_id === (string) $request->user()->id, 404);
        $data = $this->validateOverride($request);
        if (!empty($data['project_id'])) {
            $project = Project::where('user_id', $request->user()->id)->findOrFail($data['project_id']);
            $data['customer_id'] ??= $project->customer_id;
        } elseif (!empty($data['customer_id'])) {
            Customer::where('user_id', $request->user()->id)->findOrFail($data['customer_id']);
        }
        $override->update($data);
        return back()->with('status', 'نرخ استثنا به‌روزرسانی شد.');
    }

    public function expireOverride(Request $request, PricingOverride $override)
    {
        abort_unless((string) $override->user_id === (string) $request->user()->id, 404);
        $data = $request->validate(['effective_until' => ['nullable', 'date']]);
        $until = isset($data['effective_until']) ? \Carbon\CarbonImmutable::parse($data['effective_until']) : now();
        if ($until->lt($override->effective_from)) {
            return back()->withErrors(['effective_until' => 'تاریخ پایان نمی‌تواند قبل از شروع Override باشد.']);
        }
        $override->forceFill(['effective_until' => $until])->save();
        return back()->with('status', 'Override پایان یافت و سابقه آن حفظ شد.');
    }

    private function validateOverride(Request $request): array
    {
        $data = $request->validate([
            'customer_id' => ['nullable','uuid',Rule::exists('customers','id')->where('user_id',$request->user()->id)],
            'project_id' => ['nullable','uuid',Rule::exists('projects','id')->where('user_id',$request->user()->id)],
            'activity_type_id' => ['required','uuid',Rule::exists('activity_types','id')->where(fn($q)=>$q->whereNull('user_id')->orWhere('user_id',$request->user()->id))],
            'hourly_rate_minor' => ['required','integer','min:0'],
            'currency' => ['required','string','max:8'],
            'effective_from' => ['required','date'],
            'effective_until' => ['nullable','date','after:effective_from'],
            'note' => ['nullable','string','max:500'],
        ]);
        abort_if(empty($data['customer_id']) && empty($data['project_id']), 422, 'Customer or project is required.');
        return $data;
    }

}
