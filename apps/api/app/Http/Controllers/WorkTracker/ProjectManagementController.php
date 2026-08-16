<?php

namespace App\Http\Controllers\WorkTracker;

use App\Http\Controllers\Controller;
use App\Models\ActivitySession;
use App\Models\Customer;
use App\Models\PricingOverride;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProjectManagementController extends Controller
{
    public function index(Request $request): View
    {
        $userId = $request->user()->getKey();
        $query = Project::query()
            ->where('user_id', $userId)
            ->with(['customer:id,name,company_name', 'parent:id,name'])
            ->withCount(['rules', 'tasks']);

        if ($search = trim((string) $request->query('q'))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $status = (string) $request->query('status', 'active');
        if ($status === 'archived') {
            $query->where('is_archived', true);
        } elseif ($status !== 'all') {
            $query->where('is_archived', false);
            if ($status !== 'active') {
                $query->where('status', $status);
            }
        }

        if ($customerId = $request->query('customer_id')) {
            $query->where('customer_id', $customerId);
        }

        return view('worktracker.projects.index', [
            'projects' => $query->orderBy('name')->paginate(40)->withQueryString(),
            'customers' => Customer::query()->where('user_id', $userId)->orderBy('name')->get(),
            'parents' => Project::query()->where('user_id', $userId)->where('is_archived', false)->orderBy('name')->get(),
            'filters' => ['q' => $search ?? '', 'status' => $status, 'customer_id' => (string) $request->query('customer_id', '')],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $userId = $request->user()->getKey();
        $data = $this->validateProject($request);
        $data['is_billable_default'] = (bool) ($data['is_billable_default'] ?? false);
        $data['is_archived'] = (bool) ($data['is_archived'] ?? false);
        $effectiveFrom = $data['effective_from'] ?? now();
        unset($data['effective_from']);

        $project = DB::transaction(function () use ($request, $data, $effectiveFrom) {
            $project = new Project($data);
            $project->user()->associate($request->user());
            $project->version = 1;
            $project->is_archived = (bool) ($data['is_archived'] ?? false);
            if ($project->is_archived) {
                $project->status = 'archived';
            }
            $project->save();

            $this->appendProjectPricingHistory($project, $effectiveFrom);
            return $project;
        });

        return redirect()->route('worktracker.projects.show', $project)
            ->with('status', 'پروژه ساخته شد و آماده تعریف Rule و Task است.');
    }

    public function show(Request $request, Project $project): View
    {
        $this->authorizeProject($request, $project);
        $userId = $request->user()->getKey();

        $project->load([
            'customer:id,name,company_name,currency,rate_multiplier,is_active',
            'parent:id,name',
            'rules' => fn ($q) => $q->orderByDesc('is_enabled')->orderByDesc('priority')->orderByDesc('weight'),
        ]);

        $tasks = Task::query()
            ->where('project_id', $project->id)
            ->with('parent:id,title')
            ->orderByRaw("CASE status WHEN 'in_progress' THEN 1 WHEN 'planned' THEN 2 WHEN 'blocked' THEN 3 WHEN 'backlog' THEN 4 WHEN 'done' THEN 5 ELSE 6 END")
            ->orderByDesc('priority')
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get();

        $activityStats = ActivitySession::query()
            ->where('user_id', $userId)
            ->where('project_id', $project->id)
            ->selectRaw('COUNT(*) as sessions_count, COALESCE(SUM(duration_seconds),0) as effort_seconds')
            ->first();

        return view('worktracker.projects.show', [
            'project' => $project,
            'customers' => Customer::query()->where('user_id', $userId)->orderByDesc('is_active')->orderBy('name')->get(),
            'parents' => Project::query()->where('user_id', $userId)->where('id', '!=', $project->id)->where('is_archived', false)->orderBy('name')->get(),
            'tasks' => $tasks,
            'pricingHistory' => DB::table('project_multiplier_history')->where('project_id', $project->id)->orderByDesc('effective_from')->limit(50)->get(),
            'pricingOverrides' => PricingOverride::query()->where('user_id', $userId)->where('project_id', $project->id)->with('activityType:id,name,code')->orderByDesc('effective_from')->get(),
            'activityStats' => $activityStats,
            'recentRuleSamples' => ActivitySession::query()
                ->where('user_id', $userId)
                ->whereNotNull('window_title')
                ->where('window_title', '!=', '')
                ->where('started_at', '>=', now()->subDays(7))
                ->orderByDesc('started_at')
                ->limit(300)
                ->get(['window_title', 'process_name', 'executable_path', 'project_id'])
                ->unique(fn ($row) => ($row->process_name ?? '') . '|' . ($row->executable_path ?? '') . '|' . $row->window_title . '|' . ($row->project_id ?? ''))
                ->take(100)
                ->values(),
        ]);
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $data = $this->validateProject($request, $project);
        $data['is_billable_default'] = (bool) ($data['is_billable_default'] ?? false);
        $data['is_archived'] = (bool) ($data['is_archived'] ?? false);
        $effectiveFrom = $data['effective_from'] ?? now();
        unset($data['effective_from']);

        if (($data['parent_id'] ?? null) === $project->id) {
            return back()->withErrors(['parent_id' => 'پروژه نمی‌تواند والد خودش باشد.'])->withInput();
        }
        if (!empty($data['parent_id']) && $this->wouldCreateParentCycle($project, $data['parent_id'])) {
            return back()->withErrors(['parent_id' => 'این انتخاب باعث حلقه در ساختار پروژه‌ها می‌شود.'])->withInput();
        }

        DB::transaction(function () use ($project, $data, $effectiveFrom) {
            $project->fill($data);
            $project->is_archived = (bool) ($data['is_archived'] ?? false);
            if ($project->is_archived) {
                $project->status = 'archived';
            } elseif ($project->status === 'archived') {
                $project->status = 'active';
            }

            $pricingChanged = $project->isDirty(['customer_id', 'rate_multiplier', 'is_billable_default']);
            if ($project->isDirty()) {
                $project->version = ((int) $project->version) + 1;
            }
            $project->save();

            if ($pricingChanged) {
                $this->appendProjectPricingHistory($project, $effectiveFrom);
            }
        });

        return back()->with('status', 'تنظیمات پروژه ذخیره شد.');
    }

    public function archive(Request $request, Project $project): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $project->forceFill([
            'is_archived' => true,
            'status' => 'archived',
            'version' => ((int) $project->version) + 1,
        ])->save();

        return redirect()->route('worktracker.projects.index')->with('status', 'پروژه آرشیو شد؛ اطلاعات زمانی حذف نشد.');
    }

    public function restore(Request $request, Project $project): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $project->forceFill([
            'is_archived' => false,
            'status' => 'active',
            'version' => ((int) $project->version) + 1,
        ])->save();

        return back()->with('status', 'پروژه دوباره فعال شد.');
    }

    private function validateProject(Request $request, ?Project $project = null): array
    {
        $userId = $request->user()->getKey();
        $required = $project ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$required, 'string', 'max:180'],
            'code' => ['nullable', 'string', 'max:80'],
            'parent_id' => ['nullable', 'uuid', Rule::exists('projects', 'id')->where('user_id', $userId)],
            'customer_id' => ['nullable', 'uuid', Rule::exists('customers', 'id')->where('user_id', $userId)],
            'status' => [$required, Rule::in(['active', 'paused', 'completed', 'archived'])],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'rate_multiplier' => [$required, 'numeric', 'min:0', 'max:100'],
            'is_billable_default' => ['nullable', 'boolean'],
            'is_archived' => ['nullable', 'boolean'],
            'effective_from' => ['nullable', 'date'],
        ]);
    }

    private function authorizeProject(Request $request, Project $project): void
    {
        abort_unless((string) $project->user_id === (string) $request->user()->getKey(), 404);
    }

    private function appendProjectPricingHistory(Project $project, $effectiveFrom): void
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
}
