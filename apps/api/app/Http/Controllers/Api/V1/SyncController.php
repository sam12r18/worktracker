<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ActivitySession;
use App\Models\ActivityType;
use App\Models\ActivityTypeRule;
use App\Models\Device;
use App\Models\Project;
use App\Models\ProjectRule;
use App\Models\SyncConflict;
use App\Services\SyncConflictService;
use App\Services\WorkEventMaterializer;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SyncController extends Controller
{
    public function __invoke(Request $request, SyncConflictService $conflictService, WorkEventMaterializer $workEvents): JsonResponse
    {
        $correlationId = $this->correlationId($request);
        $startedAt = microtime(true);

        try {
            $data = $request->validate([
            'device_id' => ['required','uuid'],
            'cursor' => ['nullable','string','max:512'],
            'pull_limit' => ['nullable','integer','min:1','max:1000'],
            'acknowledged_conflict_ids' => ['nullable','array','max:500'],
            'acknowledged_conflict_ids.*' => ['uuid'],
            'changes' => ['present','array','max:1000'],
            'changes.*.entity' => ['required','in:project,project_rule,activity_session'],
            'changes.*.id' => ['required','string','max:64'],
            'changes.*.client_outbox_id' => ['nullable','string','max:64'],
            'changes.*.operation' => ['required','in:upsert'],
            'changes.*.version' => ['required','integer','min:1'],
            'changes.*.payload' => ['required','array'],
            ]);
        } catch (ValidationException $e) {
            Log::channel('worktracker_sync')->warning('sync.validation_failed', [
                'correlation_id' => $correlationId,
                'device_id' => $request->input('device_id'),
                'user_id' => $request->user()?->getKey(),
                'errors' => $e->errors(),
                'changes_count' => is_array($request->input('changes')) ? count($request->input('changes')) : null,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);
            throw $e;
        }

        $user = $request->user();
        $token = $user->currentAccessToken();
        abort_unless($token && ($token->can('admin:write') || $token->can('device:' . strtolower($data['device_id']))), 403, 'This token is not bound to the requested device id.');
        $device = Device::query()->whereKey($data['device_id'])->whereBelongsTo($user)->firstOrFail();
        abort_if($device->revoked_at, 403, 'Device is revoked.');
        $device->forceFill(['last_seen_at'=>now(),'last_sync_started_at'=>now(),'last_sync_error'=>null])->save();

        Log::channel('worktracker_sync')->info('sync.started', [
            'correlation_id' => $correlationId,
            'user_id' => $user->getKey(),
            'device_id' => $device->getKey(),
            'changes_count' => count($data['changes']),
            'changes_by_entity' => collect($data['changes'])->countBy('entity')->all(),
            'has_cursor' => !empty($data['cursor']),
            'cursor_hash' => empty($data['cursor']) ? null : substr(hash('sha256', (string) $data['cursor']), 0, 12),
            'pull_limit' => (int) ($data['pull_limit'] ?? 500),
            'acknowledged_conflicts' => count($data['acknowledged_conflict_ids'] ?? []),
        ]);

        if (!empty($data['acknowledged_conflict_ids'])) {
            SyncConflict::query()
                ->where('user_id',$user->getKey())->where('device_id',$device->getKey())
                ->where('status','resolved')->whereNull('acknowledged_at')
                ->whereIn('id',$data['acknowledged_conflict_ids'])
                ->update(['acknowledged_at'=>now(),'updated_at'=>now()]);
        }

        try {
            $accepted = [];
            $conflicts = [];
            $projectionDates = [];
            $syncStartedAt = CarbonImmutable::now();

            DB::transaction(function () use ($data, $user, $device, $conflictService, &$accepted, &$conflicts, &$projectionDates) {
                foreach ($data['changes'] as $change) {
                    $payload = $this->validateEntityPayload($change['entity'], $change['id'], $change['payload'], $user->getKey(), $device->getKey());
                    $version = (int) $change['version'];

                    if ($change['entity'] === 'project') {
                        $project = Project::query()->whereKey($change['id'])->whereBelongsTo($user)->lockForUpdate()->first();
                        if ($project && (int)$project->version > $version) {
                            $conflictId=$this->recordConflict($conflictService,$user->getKey(),$device->getKey(),'project',$change['id'],$version,(int)$project->version,$payload);
                            $conflicts[] = ['conflict_id'=>$conflictId,'entity'=>'project','id'=>$change['id'],'server_version'=>(int)$project->version,'reason'=>'server_newer'];
                            continue;
                        }
                        if ($project && (int)$project->version === $version) {
                            $accepted[]=['entity'=>'project','id'=>$project->getKey(),'version'=>(int)$project->version,'client_outbox_id'=>$change['client_outbox_id'] ?? null];
                            continue;
                        }
                        $wasNew = !$project;
                        $project ??= new Project(['id' => $change['id']]);
                        $parentId = $payload['parent_id'] ?? null;
                        if ($parentId) Project::query()->whereKey($parentId)->whereBelongsTo($user)->firstOrFail();
                        $project->fill(collect($payload)->only(['parent_id','name','code','status','color','is_archived','default_activity_type_id'])->all());
                        $project->version = $version;
                        $project->user()->associate($user);
                        $project->save();
                        if ($wasNew) DB::table('project_multiplier_history')->insert(['project_id'=>$project->id,'customer_id'=>$project->customer_id,'multiplier'=>$project->rate_multiplier ?? 1,'is_billable_default'=>$project->is_billable_default ?? true,'effective_from'=>now(),'created_at'=>now(),'updated_at'=>now()]);
                        $accepted[]=['entity'=>'project','id'=>$project->getKey(),'version'=>(int)$project->version,'client_outbox_id'=>$change['client_outbox_id'] ?? null];
                        continue;
                    }

                    if ($change['entity'] === 'project_rule') {
                        $project = Project::query()->whereKey($payload['project_id'] ?? null)->whereBelongsTo($user)->firstOrFail();
                        $rule = ProjectRule::query()->whereKey($change['id'])
                            ->whereHas('project', fn ($q) => $q->where('user_id', $user->id))
                            ->lockForUpdate()->first();
                        if ($rule && (int)$rule->version > $version) {
                            $conflictId=$this->recordConflict($conflictService,$user->getKey(),$device->getKey(),'project_rule',$change['id'],$version,(int)$rule->version,$payload);
                            $conflicts[] = ['conflict_id'=>$conflictId,'entity'=>'project_rule','id'=>$change['id'],'server_version'=>(int)$rule->version,'reason'=>'server_newer'];
                            continue;
                        }
                        if ($rule && (int)$rule->version === $version) {
                            $accepted[]=['entity'=>'project_rule','id'=>$rule->getKey(),'version'=>(int)$rule->version,'client_outbox_id'=>$change['client_outbox_id'] ?? null];
                            continue;
                        }
                        $rule ??= new ProjectRule(['id' => $change['id']]);
                        $rule->fill([
                            'rule_type'=>$payload['rule_type']??'WindowTitle','operator'=>$payload['operator']??'contains',
                            'pattern'=>$payload['pattern']??'','weight'=>$payload['weight']??50,'priority'=>$payload['priority']??0,
                            'is_enabled'=>$payload['is_enabled']??true,
                        ]);
                        $rule->version = $version;
                        $rule->project()->associate($project);
                        $rule->save();
                        $accepted[]=['entity'=>'project_rule','id'=>$rule->getKey(),'version'=>(int)$rule->version,'client_outbox_id'=>$change['client_outbox_id'] ?? null];
                        continue;
                    }

                    if (($payload['device_id'] ?? null) !== $device->getKey()) {
                        throw ValidationException::withMessages(['changes' => 'Activity device does not match sync device.']);
                    }

                    $existing = ActivitySession::query()->whereKey($change['id'])->whereBelongsTo($user)->lockForUpdate()->first();
                    if ($existing && (int)$existing->version > $version) {
                        $conflictId=$this->recordConflict($conflictService,$user->getKey(),$device->getKey(),'activity_session',$change['id'],$version,(int)$existing->version,$payload);
                        $conflicts[] = ['conflict_id'=>$conflictId,'entity'=>'activity_session','id'=>$change['id'],'server_version'=>(int)$existing->version,'reason'=>'server_newer'];
                        continue;
                    }
                    if ($existing && (int)$existing->version === $version) {
                        $accepted[] = ['entity'=>'activity_session','id'=>$change['id'],'version'=>(int)$existing->version,'client_outbox_id'=>$change['client_outbox_id'] ?? null];
                        continue;
                    }

                    // If a correction moves an Activity across a local day boundary, both the old
                    // and new projection dates must be rebuilt. Otherwise stale Work Events can remain.
                    if ($existing) {
                        foreach ($this->projectionDatesForRange($existing->started_at, $existing->ended_at) as $projectionDate) {
                            $projectionDates[$projectionDate] = true;
                        }
                    }

                    $session = $existing ?? new ActivitySession(['id' => $change['id']]);
                    $session->fill($payload);
                    $session->user()->associate($user);
                    $session->device()->associate($device);
                    $session->version = $version;
                    $session->save();
                    foreach ($this->projectionDatesForActivity($payload) as $projectionDate) {
                        $projectionDates[$projectionDate] = true;
                    }
                    $accepted[] = ['entity'=>'activity_session','id'=>$session->getKey(),'version'=>(int)$session->version,'client_outbox_id'=>$change['client_outbox_id'] ?? null];
                }
            });

            $projectionRebuild = ['requested'=>count($projectionDates),'rebuilt'=>0,'deferred'=>0];
            if ($projectionDates !== []) {
                $dates = array_keys($projectionDates);
                sort($dates);
                $maxDates = max(1, (int) config('worktracker.activity_intelligence.sync_rebuild_max_dates', 7));
                $selectedDates = array_slice($dates, -$maxDates);
                $projectionRebuild['deferred'] = max(0, count($dates) - count($selectedDates));
                foreach ($selectedDates as $projectionDate) {
                    try {
                        $workEvents->rebuildDate($user->getKey(), $projectionDate, (string) $device->getKey(), (string) config('worktracker.display_timezone', 'Asia/Tehran'), $correlationId);
                        $projectionRebuild['rebuilt']++;
                    } catch (\Throwable $projectionError) {
                        Log::channel('worktracker_sync')->warning('projection.rebuild_failed', [
                            'correlation_id'=>$correlationId,
                            'user_id'=>$user->getKey(),
                            'device_id'=>$device->getKey(),
                            'projection_date'=>$projectionDate,
                            'exception'=>$projectionError::class,
                            'message'=>$projectionError->getMessage(),
                        ]);
                    }
                }
            }

            $cursor = $this->decodeCursor($data['cursor'] ?? null);
            $limit = (int)($data['pull_limit'] ?? 500);
            [$remoteChanges, $nextCursor] = $this->pullConfigurationChanges($user->getKey(), $cursor, $syncStartedAt, $limit);

            $resolutions = SyncConflict::query()
                ->where('user_id',$user->getKey())->where('device_id',$device->getKey())
                ->where('status','resolved')->whereNull('acknowledged_at')
                ->orderBy('resolved_at')->limit(500)->get()
                ->map(fn(SyncConflict $c)=>[
                    'conflict_id'=>(string)$c->getKey(),'entity'=>$c->entity_type,'id'=>$c->entity_id,
                    'resolution'=>$c->resolution,'server_version'=>(int)$c->server_version,'server_payload'=>$c->server_payload,
                ])->values();

            $device->forceFill([
                'last_sync_succeeded_at'=>now(),'last_sync_error'=>null,
                'last_sync_pushed'=>count($accepted),'last_sync_pulled'=>count($remoteChanges),
                'last_seen_at'=>now(),
            ])->save();

            Log::channel('worktracker_sync')->info('sync.completed', [
                'correlation_id' => $correlationId,
                'user_id' => $user->getKey(),
                'device_id' => $device->getKey(),
                'accepted' => count($accepted),
                'conflicts' => count($conflicts),
                'resolutions' => count($resolutions),
                'remote_changes' => count($remoteChanges),
                'remote_by_entity' => collect($remoteChanges)->countBy('entity')->all(),
                'projection_rebuild' => $projectionRebuild,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            return response()->json([
                'accepted'=>$accepted,'conflicts'=>$conflicts,'resolutions'=>$resolutions,
                'remote_changes'=>$remoteChanges,'server_cursor'=>$nextCursor,
                'projection'=>$projectionRebuild,
            ])->header('X-WorkTracker-Correlation-ID', $correlationId);
        } catch (\Throwable $e) {
            $device->forceFill(['last_sync_error'=>mb_substr($e->getMessage(),0,4000),'last_seen_at'=>now()])->save();
            Log::channel('worktracker_sync')->error('sync.failed', [
                'correlation_id' => $correlationId,
                'user_id' => $user->getKey(),
                'device_id' => $device->getKey(),
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'validation_errors' => $e instanceof ValidationException ? $e->errors() : null,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);
            throw $e;
        }
    }

    /** @return array<string,mixed> */
    private function validateEntityPayload(string $entity, string $entityId, array $payload, int|string $userId, string $deviceId): array
    {
        if (isset($payload['id']) && (string) $payload['id'] !== $entityId) {
            throw ValidationException::withMessages(['changes' => 'Payload id must match change id.']);
        }

        if ($entity === 'project') {
            return Validator::make($payload, [
                'id' => ['nullable','uuid'],
                'parent_id' => ['nullable','string','max:36', Rule::exists('projects','id')->where('user_id',$userId)],
                'name' => ['required','string','max:180'],
                'code' => ['nullable','string','max:80'],
                'status' => ['nullable','string','max:30'],
                'color' => ['nullable','string','max:20'],
                'is_archived' => ['nullable','boolean'],
                'customer_id' => ['nullable','uuid', Rule::exists('customers','id')->where('user_id',$userId)],
                'rate_multiplier' => ['nullable','numeric','min:0','max:100'],
                'is_billable_default' => ['nullable','boolean'],
                'default_activity_type_id' => ['nullable','uuid', Rule::exists('activity_types','id')->where(fn($q)=>$q->whereNull('user_id')->orWhere('user_id',$userId))],
            ])->validate();
        }

        if ($entity === 'project_rule') {
            return Validator::make($payload, [
                'id' => ['nullable','uuid'],
                'project_id' => ['required','string','max:36', Rule::exists('projects','id')->where('user_id',$userId)],
                'rule_type' => ['required','in:WindowTitle,ProcessName,Path,ExecutablePath,Keyword'],
                'operator' => ['nullable','in:contains,equals,starts_with,ends_with,regex'],
                'pattern' => ['required','string','max:2000'],
                'weight' => ['required','integer','min:1','max:200'],
                'priority' => ['nullable','integer','min:-100000','max:100000'],
                'is_enabled' => ['nullable','boolean'],
            ])->validate();
        }

        $validated = Validator::make($payload, [
            'id' => ['nullable','uuid'],
            'device_id' => ['required','uuid'],
            'project_id' => ['nullable','string','max:36', Rule::exists('projects','id')->where('user_id',$userId)],
            'task_id' => ['nullable','string','max:26'],
            'activity_type_id' => ['nullable','uuid', Rule::exists('activity_types','id')->where(fn($q)=>$q->whereNull('user_id')->orWhere('user_id',$userId))],
            'activity_type_confidence' => ['nullable','numeric','min:0','max:1'],
            'activity_type_source' => ['nullable','string','max:64'],
            'activity_type_reason' => ['nullable','string','max:4096'],
            'source' => ['required','in:auto_foreground,manual_timer,manual_entry,idle_reclassified'],
            'process_name' => ['nullable','string','max:255'],
            'executable_path' => ['nullable','string','max:4096'],
            'window_title' => ['nullable','string','max:4096'],
            'classification_confidence' => ['nullable','numeric','min:0','max:1'],
            'classification_reason' => ['nullable','string','max:4096'],
            'started_at' => ['required','date'],
            'ended_at' => ['required','date','after:started_at'],
            'duration_seconds' => ['required','integer','min:1','max:604800'],
            'idle_seconds' => ['nullable','integer','min:0','max:604800'],
            'note' => ['nullable','string','max:20000'],
            'is_billable' => ['nullable','boolean'],
            'created_at_device' => ['nullable','date'],
            'updated_at_device' => ['nullable','date'],
        ])->validate();

        if ((string) $validated['device_id'] !== $deviceId) {
            throw ValidationException::withMessages(['changes' => 'Activity device does not match sync device.']);
        }

        return $validated;
    }


    /** @param array<string,mixed> $payload @return list<string> */
    private function projectionDatesForActivity(array $payload): array
    {
        return $this->projectionDatesForRange($payload['started_at'], $payload['ended_at']);
    }

    /** @return list<string> */
    private function projectionDatesForRange(mixed $startedAt, mixed $endedAt): array
    {
        $timezone = (string) config('worktracker.display_timezone', 'Asia/Tehran');
        $start = CarbonImmutable::parse($startedAt)->setTimezone($timezone)->startOfDay();
        $end = CarbonImmutable::parse($endedAt)->setTimezone($timezone);
        // End is exclusive for projection-day membership. Avoid creating the next date for an exact midnight end.
        $last = $end->subSecond()->startOfDay();
        $dates = [];
        for ($cursor = $start; $cursor->lessThanOrEqualTo($last); $cursor = $cursor->addDay()) {
            $dates[] = $cursor->toDateString();
            if (count($dates) >= 8) break;
        }
        return $dates;
    }

    private function recordConflict(SyncConflictService $service, int|string $userId, string $deviceId, string $entity, string $id, int $clientVersion, int $serverVersion, array $clientPayload): string
    {
        $conflict=$service->record($userId,$deviceId,$entity,$id,$clientVersion,$serverVersion,$clientPayload,$service->currentServerPayload($entity,$id,$userId));
        return (string)$conflict->getKey();
    }

    private function pullConfigurationChanges(int|string $userId, ?array $cursor, CarbonImmutable $until, int $limit): array
    {
        $from = isset($cursor['t']) ? CarbonImmutable::parse($cursor['t']) : null;
        $projectQuery = Project::query()->where('user_id', $userId);
        $ruleQuery = ProjectRule::query()->whereHas('project', fn($q) => $q->where('user_id', $userId));
        $activityTypeQuery = ActivityType::query()->where(fn($q)=>$q->whereNull('user_id')->orWhere('user_id',$userId));
        $activityTypeRuleQuery = ActivityTypeRule::query()->where('user_id', $userId);
        $this->applyCursorToEntityQuery($projectQuery, 'project', $cursor, $from);
        $this->applyCursorToEntityQuery($ruleQuery, 'project_rule', $cursor, $from);
        $this->applyCursorToEntityQuery($activityTypeQuery, 'activity_type', $cursor, $from);
        $this->applyCursorToEntityQuery($activityTypeRuleQuery, 'activity_type_rule', $cursor, $from);
        $projectRows = $projectQuery->where('updated_at', '<=', $until)->orderBy('updated_at')->orderBy('id')->limit($limit + 1)->get();
        $ruleRows = $ruleQuery->where('updated_at', '<=', $until)->orderBy('updated_at')->orderBy('id')->limit($limit + 1)->get();
        $activityTypeRows = $activityTypeQuery->where('updated_at','<=',$until)->orderBy('updated_at')->orderBy('id')->limit($limit + 1)->get();
        $activityTypeRuleRows = $activityTypeRuleQuery->where('updated_at','<=',$until)->orderBy('updated_at')->orderBy('id')->limit($limit + 1)->get();

        $changes = [];
        foreach ($projectRows as $project) {
            $changes[] = [
                'entity'=>'project','id'=>(string)$project->getKey(),'version'=>(int)$project->version,
                'updated_at'=>$project->updated_at->toISOString(),
                'payload'=>['name'=>$project->name,'code'=>$project->code,'parent_id'=>$project->parent_id,'status'=>$project->status,'color'=>$project->color,'is_archived'=>(bool)$project->is_archived,'customer_id'=>$project->customer_id,'rate_multiplier'=>(float)$project->rate_multiplier,'is_billable_default'=>(bool)$project->is_billable_default,'default_activity_type_id'=>$project->default_activity_type_id],
            ];
        }
        foreach ($ruleRows as $rule) {
            $changes[] = [
                'entity'=>'project_rule','id'=>(string)$rule->getKey(),'version'=>(int)$rule->version,
                'updated_at'=>$rule->updated_at->toISOString(),
                'payload'=>['project_id'=>(string)$rule->project_id,'rule_type'=>$rule->rule_type,'operator'=>$rule->operator,'pattern'=>$rule->pattern,'weight'=>(int)$rule->weight,'priority'=>(int)$rule->priority,'is_enabled'=>(bool)$rule->is_enabled],
            ];
        }

        foreach ($activityTypeRows as $type) {
            $changes[] = [
                'entity'=>'activity_type','id'=>(string)$type->getKey(),'version'=>(int)$type->version,
                'updated_at'=>$type->updated_at->toISOString(),
                'payload'=>['code'=>$type->code,'name'=>$type->name,'is_billable_default'=>(bool)$type->is_billable_default,'base_hourly_rate_minor'=>(int)$type->base_hourly_rate_minor,'currency'=>$type->currency,'is_active'=>(bool)$type->is_active,'sort_order'=>(int)$type->sort_order],
            ];
        }

        foreach ($activityTypeRuleRows as $rule) {
            $changes[] = [
                'entity'=>'activity_type_rule','id'=>(string)$rule->getKey(),'version'=>(int)$rule->version,
                'updated_at'=>$rule->updated_at->toISOString(),
                'payload'=>[
                    'project_id'=>$rule->project_id,
                    'activity_type_id'=>(string)$rule->activity_type_id,
                    'rule_type'=>$rule->rule_type,
                    'operator'=>$rule->operator,
                    'pattern'=>$rule->pattern,
                    'weight'=>(int)$rule->weight,
                    'priority'=>(int)$rule->priority,
                    'confidence'=>(float)$rule->confidence,
                    'is_enabled'=>(bool)$rule->is_enabled,
                ],
            ];
        }

        usort($changes, fn($a,$b) => [$a['updated_at'],$a['entity'],$a['id']] <=> [$b['updated_at'],$b['entity'],$b['id']]);
        if ($cursor) $changes = array_values(array_filter($changes, fn($c) => [$c['updated_at'],$c['entity'],$c['id']] > [$cursor['t'],$cursor['e'],$cursor['i']]));
        $page = array_slice($changes, 0, $limit);
        if (count($changes) > $limit && $page) {
            $last = $page[array_key_last($page)];
            $next = $this->encodeCursor(['t'=>$last['updated_at'],'e'=>$last['entity'],'i'=>$last['id']]);
        } else {
            $next = $this->encodeCursor(['t'=>$until->toISOString(),'e'=>'~','i'=>'~']);
        }
        return [$page, $next];
    }

    private function applyCursorToEntityQuery($query, string $entity, ?array $cursor, ?CarbonImmutable $from): void
    {
        if (! $cursor || ! $from) return;
        $cursorEntity = (string)$cursor['e']; $cursorId = (string)$cursor['i'];
        $query->where(function ($q) use ($entity, $cursorEntity, $cursorId, $from) {
            $q->where('updated_at', '>', $from);
            if ($entity > $cursorEntity) $q->orWhere('updated_at', '=', $from);
            elseif ($entity === $cursorEntity) $q->orWhere(function ($same) use ($from, $cursorId) {
                $same->where('updated_at', '=', $from)->where('id', '>', $cursorId);
            });
        });
    }

    private function correlationId(Request $request): string
    {
        $incoming = trim((string) $request->header('X-WorkTracker-Correlation-ID', ''));
        if ($incoming !== '' && preg_match('/^[A-Za-z0-9._-]{8,64}$/', $incoming)) return $incoming;
        return (string) Str::uuid();
    }

    private function decodeCursor(?string $cursor): ?array
    {
        if (! $cursor) return null;
        $raw = strtr($cursor, '-_', '+/'); $raw .= str_repeat('=', (4 - strlen($raw) % 4) % 4);
        $decoded = base64_decode($raw, true); if ($decoded === false) return null;
        $data = json_decode($decoded, true);
        if (! is_array($data) || ! isset($data['t'],$data['e'],$data['i'])) return null;
        try { CarbonImmutable::parse($data['t']); } catch (\Throwable) { return null; }
        return ['t'=>(string)$data['t'],'e'=>(string)$data['e'],'i'=>(string)$data['i']];
    }

    private function encodeCursor(array $cursor): string
    {
        return rtrim(strtr(base64_encode(json_encode($cursor, JSON_UNESCAPED_SLASHES)), '+/', '-_'), '=');
    }
}
