<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $projects = Project::query()
            ->whereBelongsTo($request->user())
            ->with(['rules', 'customer:id,name,company_name,currency,rate_multiplier'])
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $projects]);
    }

    public function store(Request $request): JsonResponse
    {
        $userId = $request->user()->getKey();
        $data = $request->validate([
            'id' => ['nullable', 'uuid'],
            'name' => ['required', 'string', 'max:180'],
            'code' => ['nullable', 'string', 'max:80'],
            'parent_id' => ['nullable', 'uuid', Rule::exists('projects', 'id')->where('user_id', $userId)],
            'customer_id' => ['nullable', 'uuid', Rule::exists('customers', 'id')->where('user_id', $userId)],
            'status' => ['nullable', Rule::in(['active', 'paused', 'completed', 'archived'])],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'rate_multiplier' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_billable_default' => ['nullable', 'boolean'],
            'effective_from' => ['nullable', 'date'],
        ]);

        $effectiveFrom = $data['effective_from'] ?? now();
        unset($data['effective_from']);
        $data['is_billable_default'] = (bool) ($data['is_billable_default'] ?? true);

        $project = DB::transaction(function () use ($request, $data, $effectiveFrom) {
            $project = new Project($data);
            $project->id = $data['id'] ?? (string) Str::uuid();
            $project->version = 1;
            $project->user()->associate($request->user());
            if (($project->status ?? 'active') === 'archived') {
                $project->is_archived = true;
            }
            $project->save();

            $this->appendHistory($project, $effectiveFrom);
            return $project;
        });

        return response()->json(['data' => $project->load(['rules', 'customer'])], 201);
    }

    public function show(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($request, $project);
        return response()->json(['data' => $project->load(['rules', 'customer'])]);
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($request, $project);
        $userId = $request->user()->getKey();
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:180'],
            'code' => ['nullable', 'string', 'max:80'],
            'parent_id' => ['nullable', 'uuid', Rule::exists('projects', 'id')->where('user_id', $userId)],
            'customer_id' => ['nullable', 'uuid', Rule::exists('customers', 'id')->where('user_id', $userId)],
            'status' => ['sometimes', Rule::in(['active', 'paused', 'completed', 'archived'])],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_archived' => ['sometimes', 'boolean'],
            'rate_multiplier' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'is_billable_default' => ['sometimes', 'boolean'],
            'effective_from' => ['nullable', 'date'],
        ]);

        if (($data['parent_id'] ?? null) === $project->id) {
            abort(422, 'A project cannot be its own parent.');
        }
        if (!empty($data['parent_id']) && $this->wouldCreateParentCycle($project, $data['parent_id'])) {
            abort(422, 'Project parent selection creates a cycle.');
        }

        $effectiveFrom = $data['effective_from'] ?? now();
        unset($data['effective_from']);

        DB::transaction(function () use ($project, $data, $effectiveFrom) {
            $project->fill($data);
            if (($project->status ?? null) === 'archived') {
                $project->is_archived = true;
            }
            $pricingChanged = $project->isDirty(['customer_id', 'rate_multiplier', 'is_billable_default']);
            if ($project->isDirty()) {
                $project->version = ((int) $project->version) + 1;
            }
            $project->save();
            if ($pricingChanged) {
                $this->appendHistory($project, $effectiveFrom);
            }
        });

        return response()->json(['data' => $project->load(['rules', 'customer'])]);
    }

    public function destroy(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($request, $project);
        $project->is_archived = true;
        $project->status = 'archived';
        $project->version = ((int) $project->version) + 1;
        $project->save();
        return response()->json([], 204);
    }

    private function authorizeProject(Request $request, Project $project): void
    {
        abort_unless((string) $project->user_id === (string) $request->user()->getKey(), 404);
    }

    private function wouldCreateParentCycle(Project $project, string $candidateParentId): bool
    {
        $seen = [$project->id => true];
        $current = Project::query()->where('user_id', $project->user_id)->find($candidateParentId);
        while ($current) {
            if (isset($seen[$current->id])) {
                return true;
            }
            $seen[$current->id] = true;
            if (!$current->parent_id) {
                return false;
            }
            $current = Project::query()->where('user_id', $project->user_id)->find($current->parent_id);
        }
        return false;
    }

    private function appendHistory(Project $project, $effectiveFrom): void
    {
        DB::table('project_multiplier_history')->insert([
            'project_id' => $project->id,
            'customer_id' => $project->customer_id,
            'multiplier' => $project->rate_multiplier ?? 1,
            'is_billable_default' => $project->is_billable_default ?? true,
            'effective_from' => $effectiveFrom,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
