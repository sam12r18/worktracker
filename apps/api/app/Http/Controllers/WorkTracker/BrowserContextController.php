<?php

namespace App\Http\Controllers\WorkTracker;

use App\Http\Controllers\Controller;
use App\Models\ActivitySession;
use App\Models\Project;
use App\Models\ProjectRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BrowserContextController extends Controller
{
    private const BROWSER_RULE_TYPES = ['BrowserHost', 'BrowserPath', 'BrowserTitle'];

    public function index(Request $request): View
    {
        $userId = $request->user()->getKey();

        $contexts = ActivitySession::query()
            ->where('user_id', $userId)
            ->whereNotNull('browser_context')
            ->with('project:id,name')
            ->orderByDesc('started_at')
            ->limit(100)
            ->get([
                'id',
                'project_id',
                'process_name',
                'window_title',
                'browser_context',
                'classification_confidence',
                'classification_reason',
                'started_at',
                'ended_at',
                'duration_seconds',
            ]);

        $browserRules = ProjectRule::query()
            ->whereIn('rule_type', self::BROWSER_RULE_TYPES)
            ->whereHas('project', fn ($query) => $query->where('user_id', $userId))
            ->with('project:id,name')
            ->orderByDesc('priority')
            ->orderByDesc('weight')
            ->get();

        $projects = Project::query()
            ->where('user_id', $userId)
            ->where('is_archived', false)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $hostCount = $contexts
            ->map(fn (ActivitySession $session) => data_get($session->browser_context, 'host'))
            ->filter()
            ->unique()
            ->count();

        return view('worktracker.browser-context.index', [
            'contexts' => $contexts,
            'browserRules' => $browserRules,
            'projects' => $projects,
            'lastContext' => $contexts->first(),
            'hostCount' => $hostCount,
        ]);
    }

    public function storeRule(Request $request): RedirectResponse
    {
        $userId = $request->user()->getKey();
        $data = $request->validate([
            'project_id' => ['required', 'uuid', Rule::exists('projects', 'id')->where('user_id', $userId)],
            'rule_type' => ['required', Rule::in(self::BROWSER_RULE_TYPES)],
            'operator' => ['required', Rule::in(['contains', 'equals', 'starts_with', 'ends_with', 'regex'])],
            'pattern' => ['required', 'string', 'max:1000'],
            'weight' => ['required', 'integer', 'min:1', 'max:200'],
            'priority' => ['required', 'integer', 'min:-100000', 'max:100000'],
            'is_enabled' => ['nullable', 'boolean'],
        ]);

        $project = Project::query()
            ->where('user_id', $userId)
            ->whereKey($data['project_id'])
            ->firstOrFail();

        $pattern = trim($data['pattern']);
        if ($pattern === '') {
            return back()->withErrors(['pattern' => 'Pattern نمی‌تواند خالی باشد.'])->withInput();
        }

        $duplicate = ProjectRule::query()
            ->where('project_id', $project->id)
            ->where('rule_type', $data['rule_type'])
            ->where('operator', $data['operator'])
            ->where('pattern', $pattern)
            ->exists();

        if ($duplicate) {
            return back()->withErrors(['pattern' => 'یک Browser Rule مشابه برای این پروژه وجود دارد.'])->withInput();
        }

        $rule = new ProjectRule([
            'rule_type' => $data['rule_type'],
            'operator' => $data['operator'],
            'pattern' => $pattern,
            'weight' => (int) $data['weight'],
            'priority' => (int) $data['priority'],
            'is_enabled' => (bool) ($data['is_enabled'] ?? false),
        ]);
        $rule->version = 1;
        $project->rules()->save($rule);
        $project->forceFill(['version' => ((int) $project->version) + 1])->save();

        return back()->with('status', 'Browser Rule ثبت شد و در Sync بعدی به Agent می‌رسد.');
    }
}
