<?php

namespace App\Http\Controllers\WorkTracker;

use App\Http\Controllers\Controller;
use App\Models\ActivitySession;
use App\Models\ActivityType;
use App\Models\ActivityTypeRule;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ActivityIntelligenceController extends Controller
{
    public function index(Request $request): View
    {
        $userId = $request->user()->getKey();
        $from = now()->subDays(7);

        $rules = ActivityTypeRule::query()
            ->where('user_id', $userId)
            ->with(['project:id,name,code', 'activityType:id,name,code'])
            ->orderByDesc('is_enabled')
            ->orderByDesc('priority')
            ->orderByDesc('weight')
            ->orderBy('created_at')
            ->get();

        $stats = ActivitySession::query()
            ->where('user_id', $userId)
            ->where('started_at', '>=', $from)
            ->selectRaw('COUNT(*) sessions_count')
            ->selectRaw('SUM(CASE WHEN activity_type_id IS NULL THEN 1 ELSE 0 END) untyped_count')
            ->selectRaw('SUM(CASE WHEN activity_type_source = ? THEN 1 ELSE 0 END) rule_count', ['rule'])
            ->selectRaw('SUM(CASE WHEN activity_type_source = ? THEN 1 ELSE 0 END) project_default_count', ['project_default'])
            ->selectRaw('SUM(CASE WHEN activity_type_source = ? THEN 1 ELSE 0 END) ide_signal_count', ['ide_signal'])
            ->selectRaw('SUM(CASE WHEN activity_type_source = ? THEN 1 ELSE 0 END) user_override_count', ['user_override'])
            ->first();

        $recent = ActivitySession::query()
            ->where('user_id', $userId)
            ->whereNotNull('activity_type_id')
            ->with(['project:id,name', 'activityType:id,name'])
            ->orderByDesc('started_at')
            ->limit(40)
            ->get();

        return view('worktracker.activity-intelligence.index', [
            'rules' => $rules,
            'projects' => Project::query()->where('user_id', $userId)->where('is_archived', false)->orderBy('name')->get(['id','name','code','default_activity_type_id']),
            'activityTypes' => ActivityType::query()->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $userId))->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'stats' => $stats,
            'recent' => $recent,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['user_id'] = $request->user()->getKey();
        $data['is_enabled'] = (bool) ($data['is_enabled'] ?? false);
        $data['version'] = 1;
        ActivityTypeRule::query()->create($data);

        return back()->with('status', 'Rule تشخیص نوع فعالیت اضافه شد و در Sync بعدی به Agent می‌رسد.');
    }

    public function update(Request $request, ActivityTypeRule $rule): RedirectResponse
    {
        $this->authorizeRule($request, $rule);
        $data = $this->validated($request);
        $data['is_enabled'] = (bool) ($data['is_enabled'] ?? false);
        $rule->fill($data);
        $rule->version = ((int) $rule->version) + 1;
        $rule->save();

        return back()->with('status', 'Rule نوع فعالیت ذخیره شد.');
    }

    public function destroy(Request $request, ActivityTypeRule $rule): RedirectResponse
    {
        $this->authorizeRule($request, $rule);
        // Sync is cursor-based and currently has no deletion tombstone entity. Keep the row as
        // an explicit disabled tombstone so every Agent receives the deactivation deterministically.
        $rule->forceFill(['is_enabled' => false, 'version' => ((int) $rule->version) + 1])->save();
        return back()->with('status', 'Rule نوع فعالیت غیرفعال شد و وضعیت آن در Sync بعدی به Agentها می‌رسد.');
    }

    private function validated(Request $request): array
    {
        $userId = $request->user()->getKey();
        return $request->validate([
            'project_id' => ['nullable', 'string', 'max:36', Rule::exists('projects', 'id')->where('user_id', $userId)],
            'activity_type_id' => ['required', 'uuid', Rule::exists('activity_types', 'id')->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $userId))],
            'rule_type' => ['required', Rule::in(['ProcessName','WindowTitle','ExecutablePath','ContextKey','Keyword'])],
            'operator' => ['required', Rule::in(['contains','equals','starts_with','ends_with','regex'])],
            'pattern' => ['required', 'string', 'max:2000'],
            'weight' => ['required', 'integer', 'min:1', 'max:200'],
            'priority' => ['required', 'integer', 'min:-100000', 'max:100000'],
            'confidence' => ['required', 'numeric', 'min:0.5', 'max:1'],
            'is_enabled' => ['nullable', 'boolean'],
        ]);
    }

    private function authorizeRule(Request $request, ActivityTypeRule $rule): void
    {
        abort_unless((string) $rule->user_id === (string) $request->user()->getKey(), 404);
    }
}
