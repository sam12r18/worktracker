<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    public function index(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($request, $project);
        return response()->json(['data' => $project->tasks()->with('parent:id,title')->orderBy('sort_order')->orderBy('created_at')->get()]);
    }

    public function show(Request $request, Project $project, Task $task): JsonResponse
    {
        $this->authorizeTask($request, $project, $task);
        return response()->json(['data' => $task]);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($request, $project);
        $data = $this->validateTask($request, $project);
        if (($data['status'] ?? null) === 'in_progress') $data['started_at'] = now();
        if (($data['status'] ?? null) === 'done') $data['completed_at'] = now();
        $task = $project->tasks()->create($data);
        return response()->json(['data' => $task], 201);
    }

    public function update(Request $request, Project $project, Task $task): JsonResponse
    {
        $this->authorizeTask($request, $project, $task);
        $data = $this->validateTask($request, $project, true);
        if (($data['parent_id'] ?? null) === $task->id) abort(422, 'A task cannot be its own parent.');
        if (!empty($data['parent_id']) && $this->wouldCreateParentCycle($task, $data['parent_id'])) abort(422, 'Task parent selection creates a cycle.');
        $oldStatus = $task->status;
        $task->fill($data);
        if ($task->status === 'in_progress' && !$task->started_at) $task->started_at = now();
        if ($task->status === 'done' && $oldStatus !== 'done') $task->completed_at = now();
        elseif ($task->status !== 'done') $task->completed_at = null;
        $task->save();
        return response()->json(['data' => $task]);
    }

    public function destroy(Request $request, Project $project, Task $task): JsonResponse
    {
        $this->authorizeTask($request, $project, $task);
        $task->delete();
        return response()->json([], 204);
    }

    private function validateTask(Request $request, Project $project, bool $update = false): array
    {
        $required = $update ? 'sometimes' : 'required';
        return $request->validate([
            'title' => [$required, 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'parent_id' => ['nullable', 'string', Rule::exists('tasks', 'id')->where('project_id', $project->id)],
            'status' => [$required, Rule::in(['backlog','planned','in_progress','blocked','done','cancelled'])],
            'priority' => [$required, Rule::in(['low','normal','high','urgent'])],
            'due_at' => ['nullable', 'date'],
            'estimated_minutes' => ['nullable', 'integer', 'min:0', 'max:525600'],
            'sort_order' => ['nullable', 'integer', 'min:-100000', 'max:100000'],
        ]);
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
            if (isset($seen[$current->id])) return true;
            $seen[$current->id] = true;
            if (!$current->parent_id) return false;
            $current = Task::query()->where('project_id', $task->project_id)->find($current->parent_id);
        }
        return false;
    }

}
