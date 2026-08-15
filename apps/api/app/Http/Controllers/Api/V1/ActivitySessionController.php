<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ActivitySession;
use App\Services\TimeAccountingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivitySessionController extends Controller
{
    public function index(Request $request, TimeAccountingService $accounting): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after:from'],
            'project_id' => ['nullable', 'string'],
            'device_id' => ['nullable', 'uuid'],
            'source' => ['nullable', 'string', 'max:40'],
        ]);

        $from = isset($validated['from'])
            ? CarbonImmutable::parse($validated['from'])
            : CarbonImmutable::now()->startOfDay();
        $to = isset($validated['to'])
            ? CarbonImmutable::parse($validated['to'])
            : $from->addDay();

        $query = ActivitySession::query()
            ->where('user_id', $request->user()->id)
            ->where('ended_at', '>', $from)
            ->where('started_at', '<', $to)
            ->orderBy('started_at');

        if (isset($validated['project_id'])) {
            $query->where('project_id', $validated['project_id']);
        }
        if (isset($validated['device_id'])) {
            $query->where('device_id', $validated['device_id']);
        }
        if (isset($validated['source'])) {
            $query->where('source', $validated['source']);
        }

        $sessions = $query->get();

        return response()->json([
            'data' => $sessions,
            'summary' => $accounting->summarize($sessions, $from, $to),
            'accounting_semantics' => [
                'effort' => 'additive_sum_of_all_valid_activity_intervals',
                'elapsed_coverage' => 'union_of_intervals_counting_overlap_once',
                'overlap_policy' => 'legitimate_overlap_is_preserved_even_on_same_user_device_project',
            ],
        ]);
    }

    public function show(Request $request, ActivitySession $activitySession): JsonResponse
    {
        abort_unless((int) $activitySession->user_id === (int) $request->user()->id, 404);
        return response()->json(['data' => $activitySession]);
    }
}
