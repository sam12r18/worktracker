<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ActivityTypeRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ActivityTypeRuleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $rules = ActivityTypeRule::query()
            ->where('user_id', $request->user()->getKey())
            ->with(['project:id,name,code', 'activityType:id,name,code'])
            ->orderByDesc('is_enabled')->orderByDesc('priority')->orderByDesc('weight')->get();
        return response()->json(['data' => $rules]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['user_id'] = $request->user()->getKey();
        $data['is_enabled'] = (bool) ($data['is_enabled'] ?? true);
        $data['version'] = 1;
        $rule = ActivityTypeRule::query()->create($data);
        return response()->json(['data' => $rule->load(['project:id,name,code', 'activityType:id,name,code'])], 201);
    }

    public function update(Request $request, ActivityTypeRule $activityTypeRule): JsonResponse
    {
        $this->authorizeRule($request, $activityTypeRule);
        $data = $this->validated($request, true);
        if (array_key_exists('is_enabled', $data)) $data['is_enabled'] = (bool) $data['is_enabled'];
        $activityTypeRule->fill($data);
        if ($activityTypeRule->isDirty()) $activityTypeRule->version = ((int) $activityTypeRule->version) + 1;
        $activityTypeRule->save();
        return response()->json(['data' => $activityTypeRule->load(['project:id,name,code', 'activityType:id,name,code'])]);
    }

    public function destroy(Request $request, ActivityTypeRule $activityTypeRule): JsonResponse
    {
        $this->authorizeRule($request, $activityTypeRule);
        $activityTypeRule->forceFill(['is_enabled' => false, 'version' => ((int) $activityTypeRule->version) + 1])->save();
        return response()->json([], 204);
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $userId = $request->user()->getKey();
        $required = $partial ? 'sometimes' : 'required';
        return $request->validate([
            'project_id' => ['nullable', 'string', 'max:36', Rule::exists('projects', 'id')->where('user_id', $userId)],
            'activity_type_id' => [$required, 'uuid', Rule::exists('activity_types', 'id')->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $userId))],
            'rule_type' => [$required, Rule::in(['ProcessName','WindowTitle','ExecutablePath','ContextKey','Keyword'])],
            'operator' => [$required, Rule::in(['contains','equals','starts_with','ends_with','regex'])],
            'pattern' => [$required, 'string', 'max:2000'],
            'weight' => [$required, 'integer', 'min:1', 'max:200'],
            'priority' => [$required, 'integer', 'min:-100000', 'max:100000'],
            'confidence' => [$required, 'numeric', 'min:0.5', 'max:1'],
            'is_enabled' => ['sometimes', 'boolean'],
        ]);
    }

    private function authorizeRule(Request $request, ActivityTypeRule $rule): void
    {
        abort_unless((string) $rule->user_id === (string) $request->user()->getKey(), 404);
    }
}
