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
            ->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
        $projects = Project::where('user_id', $userId)->with('customer')->orderBy('name')->get();

        return view('worktracker.billing.index', compact('customers', 'types', 'projects'));
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
            'effective_from' => ['nullable','date'],
        ]);
        $effectiveFrom=$data['effective_from'] ?? now(); unset($data['effective_from']);
        $data['user_id'] = $request->user()->id;
        $data['is_billable_default'] = (bool)($data['is_billable_default'] ?? false);
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
        $data=$request->validate(['name'=>['required','string','max:190'],'base_hourly_rate_minor'=>['required','integer','min:0'],'currency'=>['required','string','max:8'],'is_billable_default'=>['nullable','boolean'],'effective_from'=>['required','date']]);
        $type->fill(collect($data)->except('effective_from')->all()); $type->is_billable_default=(bool)($data['is_billable_default']??false); $type->version=((int)$type->version)+1; $type->save();
        DB::table('activity_rate_history')->insert(['activity_type_id'=>$type->id,'hourly_rate_minor'=>$type->base_hourly_rate_minor,'currency'=>$type->currency,'is_billable_default'=>$type->is_billable_default,'effective_from'=>$data['effective_from'],'created_at'=>now(),'updated_at'=>now()]);
        return back()->with('status','نرخ فعالیت و تاریخچه آن به‌روزرسانی شد.');
    }

    public function storeOverride(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['nullable','uuid'],
            'project_id' => ['nullable','uuid'],
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
}
