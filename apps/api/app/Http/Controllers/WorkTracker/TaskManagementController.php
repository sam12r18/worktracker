<?php

namespace App\Http\Controllers\WorkTracker;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaskManagementController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $data = $this->normalizeTaskDates($this->validateTask($request, $project));
        if (($data['status'] ?? null) === 'in_progress') {
            $data['started_at'] = now();
        }
        if (($data['status'] ?? null) === 'done') {
            $data['completed_at'] = now();
        }
        $project->tasks()->create($data);
        return back()->with('status', 'Task به پروژه اضافه شد.');
    }

    public function update(Request $request, Project $project, Task $task): RedirectResponse
    {
        $this->authorizeTask($request, $project, $task);
        $data = $this->normalizeTaskDates($this->validateTask($request, $project, $task));
        if (($data['parent_id'] ?? null) === $task->id) {
            return back()->withErrors(['parent_id' => 'Task نمی‌تواند والد خودش باشد.'])->withInput();
        }
        if (!empty($data['parent_id']) && $this->wouldCreateParentCycle($task, $data['parent_id'])) {
            return back()->withErrors(['parent_id' => 'این انتخاب باعث حلقه در ساختار Taskها می‌شود.'])->withInput();
        }

        $oldStatus = $task->status;
        $task->fill($data);
        if ($task->status === 'in_progress' && !$task->started_at) {
            $task->started_at = now();
        }
        if ($task->status === 'done' && $oldStatus !== 'done') {
            $task->completed_at = now();
        } elseif ($task->status !== 'done') {
            $task->completed_at = null;
        }
        $task->save();
        return back()->with('status', 'Task به‌روزرسانی شد.');
    }

    public function destroy(Request $request, Project $project, Task $task): RedirectResponse
    {
        $this->authorizeTask($request, $project, $task);
        $task->delete();
        return back()->with('status', 'Task حذف شد. Activityهای قبلی حذف نمی‌شوند و task_id آن‌ها در صورت وجود با قاعده دیتابیس مدیریت می‌شود.');
    }

    private function validateTask(Request $request, Project $project, ?Task $task = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'parent_id' => ['nullable', 'string', Rule::exists('tasks', 'id')->where('project_id', $project->id)],
            'status' => ['required', Rule::in(['backlog', 'planned', 'in_progress', 'blocked', 'done', 'cancelled'])],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'due_at' => ['nullable', 'date'],
            'estimated_minutes' => ['nullable', 'integer', 'min:0', 'max:525600'],
            'sort_order' => ['nullable', 'integer', 'min:-100000', 'max:100000'],
        ]);
    }

    private function normalizeTaskDates(array $data): array
    {
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        return $data;
    }

    private function authorizeProject(Request $request, Project $project): void
    {
        abort_unless((string) $project->user_id === (string) $request->user()->getKey(), 404);
    }

    private function authorizeTask(Request $request, Project $project, Task $task): void
    {
        $this->authorizeProject($request, $project);
        abort_unless((string) $task->project_id === (string) $project->id, 404);
    }
    private function wouldCreateParentCycle(Task $task, string $candidateParentId): bool
    {
        $seen = [$task->id => true];
        $current = Task::query()->where('project_id', $task->project_id)->find($candidateParentId);
        while ($current) {
            if (isset($seen[$current->id])) {
                return true;
            }
            $seen[$current->id] = true;
            if (!$current->parent_id) {
                return false;
            }
            $current = Task::query()->where('project_id', $task->project_id)->find($current->parent_id);
        }
        return false;
    }

}
