<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $rows = Customer::query()
            ->where('user_id', $request->user()->getKey())
            ->withCount('projects')
            ->orderByDesc('is_active')->orderBy('name')->get();
        return response()->json(['data' => $rows]);
    }

    public function show(Request $request, Customer $customer): JsonResponse
    {
        $this->authorizeCustomer($request, $customer);
        return response()->json(['data' => $customer->load('projects')]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateCustomer($request);
        $effectiveFrom = $data['effective_from'] ?? now();
        unset($data['effective_from']);
        $data['user_id'] = $request->user()->getKey();
        $data['is_active'] = (bool) ($data['is_active'] ?? true);

        $customer = DB::transaction(function () use ($data, $effectiveFrom) {
            $customer = Customer::create($data);
            $this->appendHistory($customer, $effectiveFrom);
            return $customer;
        });

        return response()->json(['data' => $customer], 201);
    }

    public function update(Request $request, Customer $customer): JsonResponse
    {
        $this->authorizeCustomer($request, $customer);
        $data = $this->validateCustomer($request, true);
        $effectiveFrom = $data['effective_from'] ?? now();
        unset($data['effective_from']);

        DB::transaction(function () use ($customer, $data, $effectiveFrom) {
            $customer->fill($data);
            $historyChanged = $customer->isDirty(['rate_multiplier', 'currency']);
            $customer->save();
            if ($historyChanged) $this->appendHistory($customer, $effectiveFrom);
        });

        return response()->json(['data' => $customer]);
    }

    public function destroy(Request $request, Customer $customer): JsonResponse
    {
        $this->authorizeCustomer($request, $customer);
        $customer->forceFill(['is_active' => false])->save();
        return response()->json([], 204);
    }

    private function validateCustomer(Request $request, bool $update = false): array
    {
        $required = $update ? 'sometimes' : 'required';
        return $request->validate([
            'name' => [$required, 'string', 'max:190'],
            'company_name' => ['nullable', 'string', 'max:190'],
            'rate_multiplier' => [$required, 'numeric', 'min:0', 'max:100'],
            'currency' => [$required, 'string', 'max:8'],
            'is_active' => ['sometimes', 'boolean'],
            'billing_notes' => ['nullable', 'string', 'max:3000'],
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
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
