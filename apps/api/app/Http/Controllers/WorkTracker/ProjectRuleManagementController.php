<?php

namespace App\Http\Controllers\WorkTracker;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectRuleManagementController extends Controller
{
    private const BROWSER_RULE_TYPES = ['BrowserHost', 'BrowserPath', 'BrowserTitle'];

    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $data = $this->validateRule($request);
        $data['is_enabled'] = (bool) ($data['is_enabled'] ?? false);
        $rule = new ProjectRule($data);
        $rule->version = 1;
        $project->rules()->save($rule);
        $this->bumpProject($project);
        return back()->with('status', 'Rule تشخیص پروژه ثبت شد و در Sync بعدی به Agent می‌رسد.');
    }

    public function update(Request $request, Project $project, ProjectRule $rule): RedirectResponse
    {
        $this->authorizeRule($request, $project, $rule);
        $data = $this->validateRule($request);
        $data['is_enabled'] = (bool) ($data['is_enabled'] ?? false);

        // The legacy project page did not originally render Browser* options. If an
        // existing Browser Rule is submitted from an older/stale UI, preserve its
        // type instead of silently converting it to ProcessName/WindowTitle.
        if (in_array((string) $rule->rule_type, self::BROWSER_RULE_TYPES, true)
            && ! in_array((string) $data['rule_type'], self::BROWSER_RULE_TYPES, true)) {
            $data['rule_type'] = $rule->rule_type;
        }

        $rule->fill($data);
        if ($rule->isDirty()) {
            $rule->version = ((int) $rule->version) + 1;
            $rule->save();
            $this->bumpProject($project);
        }
        return back()->with('status', 'Rule به‌روزرسانی شد.');
    }

    public function destroy(Request $request, Project $project, ProjectRule $rule): RedirectResponse
    {
        $this->authorizeRule($request, $project, $rule);
        $rule->delete();
        $this->bumpProject($project);
        return back()->with('status', 'Rule حذف شد.');
    }

    private function validateRule(Request $request): array
    {
        return $request->validate([
            'rule_type' => ['required', Rule::in(['Path', 'WindowTitle', 'ProcessName', 'ExecutablePath', 'BrowserHost', 'BrowserPath', 'BrowserTitle', 'Keyword'])],
            'operator' => ['required', Rule::in(['contains', 'equals', 'starts_with', 'ends_with', 'regex'])],
            'pattern' => ['required', 'string', 'max:1000'],
            'weight' => ['required', 'integer', 'min:1', 'max:200'],
            'priority' => ['required', 'integer', 'min:-100000', 'max:100000'],
            'is_enabled' => ['nullable', 'boolean'],
        ]);
    }

    private function authorizeProject(Request $request, Project $project): void
    {
        abort_unless((string) $project->user_id === (string) $request->user()->getKey(), 404);
    }

    private function authorizeRule(Request $request, Project $project, ProjectRule $rule): void
    {
        $this->authorizeProject($request, $project);
        abort_unless((string) $rule->project_id === (string) $project->id, 404);
    }

    private function bumpProject(Project $project): void
    {
        $project->forceFill(['version' => ((int) $project->version) + 1])->save();
    }
}
