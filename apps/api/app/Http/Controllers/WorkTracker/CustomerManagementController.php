<?php

namespace App\Http\Controllers\WorkTracker;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PricingOverride;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CustomerManagementController extends Controller
{
    public function index(Request $request): View
    {
        $userId = $request->user()->getKey();
        $query = Customer::query()->where('user_id', $userId)->withCount('projects');

        if ($search = trim((string) $request->query('q'))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%");
            });
        }
        if ($request->query('status') === 'active') {
            $query->where('is_active', true);
        } elseif ($request->query('status') === 'inactive') {
            $query->where('is_active', false);
        }

        return view('worktracker.customers.index', [
            'customers' => $query->orderByDesc('is_active')->orderBy('name')->paginate(40)->withQueryString(),
            'filters' => ['q' => $search ?? '', 'status' => (string) $request->query('status', 'all')],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateCustomer($request);
        $effectiveFrom = $data['effective_from'] ?? now();
        unset($data['effective_from']);
        $data['user_id'] = $request->user()->getKey();
        $data['is_active'] = array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true;

        $customer = DB::transaction(function () use ($data, $effectiveFrom) {
            $customer = Customer::create($data);
            $this->appendHistory($customer, $effectiveFrom);
            return $customer;
        });

        return redirect()->route('worktracker.customers.show', $customer)->with('status', 'مشتری ثبت شد. حالا می‌توانی پروژه‌هایش را تعریف کنی.');
    }

    public function show(Request $request, Customer $customer): View
    {
        $this->authorizeCustomer($request, $customer);
        $userId = $request->user()->getKey();

        return view('worktracker.customers.show', [
            'customer' => $customer,
            'projects' => Project::query()->where('user_id', $userId)->where('customer_id', $customer->id)->withCount(['tasks', 'rules'])->orderBy('name')->get(),
            'history' => DB::table('customer_multiplier_history')->where('customer_id', $customer->id)->orderByDesc('effective_from')->limit(50)->get(),
            'overrides' => PricingOverride::query()->where('user_id', $userId)->where('customer_id', $customer->id)->with(['activityType:id,name,code', 'project:id,name'])->orderByDesc('effective_from')->get(),
            'invoiceStats' => Invoice::query()->where('user_id', $userId)->where('customer_id', $customer->id)->selectRaw('COUNT(*) as invoices_count, COALESCE(SUM(total_minor),0) as total_minor')->first(),
        ]);
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorizeCustomer($request, $customer);
        $data = $this->validateCustomer($request, true);
        $effectiveFrom = $data['effective_from'] ?? now();
        unset($data['effective_from']);
        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        DB::transaction(function () use ($customer, $data, $effectiveFrom) {
            $customer->fill($data);
            $historyChanged = $customer->isDirty(['rate_multiplier', 'currency']);
            $customer->save();
            if ($historyChanged) {
                $this->appendHistory($customer, $effectiveFrom);
            }
        });

        return back()->with('status', 'اطلاعات مشتری ذخیره شد.');
    }

    public function toggle(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorizeCustomer($request, $customer);
        $customer->forceFill(['is_active' => !$customer->is_active])->save();
        return back()->with('status', $customer->is_active ? 'مشتری فعال شد.' : 'مشتری غیرفعال شد؛ پروژه‌ها و سوابق حذف نشدند.');
    }

    private function validateCustomer(Request $request, bool $update = false): array
    {
        $required = $update ? 'sometimes' : 'required';
        return $request->validate([
            'name' => [$required, 'string', 'max:190'],
            'company_name' => ['nullable', 'string', 'max:190'],
            'rate_multiplier' => [$required, 'numeric', 'min:0', 'max:100'],
            'currency' => [$required, 'string', 'max:8'],
            'billing_notes' => ['nullable', 'string', 'max:3000'],
            'is_active' => ['nullable', 'boolean'],
            'effective_from' => ['nullable', 'date'],
        ]);
    }

    private function authorizeCustomer(Request $request, Customer $customer): void
    {
        abort_unless((string) $customer->user_id === (string) $request->user()->getKey(), 404);
    }

    private function appendHistory(Customer $customer, $effectiveFrom): void
    {
        DB::table('customer_multiplier_history')->insert([
            'customer_id' => $customer->id,
            'multiplier' => $customer->rate_multiplier,
            'currency' => $customer->currency,
            'effective_from' => $effectiveFrom,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
