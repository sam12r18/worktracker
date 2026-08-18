<?php

namespace App\Services;

use App\Models\ActivitySession;
use App\Models\Project;
use App\Models\ProjectRule;
use App\Models\SyncConflict;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SyncConflictService
{
    public function record(
        int|string $userId,
        string $deviceId,
        string $entity,
        string $entityId,
        int $clientVersion,
        int $serverVersion,
        array $clientPayload,
        ?array $serverPayload,
        string $reason = 'server_newer'
    ): SyncConflict {
        return SyncConflict::query()->updateOrCreate(
            [
                'device_id'=>$deviceId,
                'entity_type'=>$entity,
                'entity_id'=>$entityId,
                'client_version'=>$clientVersion,
            ],
            [
                'user_id'=>$userId,
                'server_version'=>$serverVersion,
                'client_payload'=>$clientPayload,
                'server_payload'=>$serverPayload,
                'reason'=>$reason,
                'status'=>'open',
                'resolution'=>null,
                'resolved_by_user_id'=>null,
                'resolved_at'=>null,
                'acknowledged_at'=>null,
            ]
        );
    }

    public function resolve(SyncConflict $conflict, string $resolution, int|string $actorUserId): SyncConflict
    {
        if (! in_array($resolution, ['keep_server','accept_client'], true)) {
            throw ValidationException::withMessages(['resolution'=>'Unsupported conflict resolution.']);
        }

        return DB::transaction(function () use ($conflict, $resolution, $actorUserId) {
            $conflict = SyncConflict::query()->whereKey($conflict->getKey())->lockForUpdate()->firstOrFail();
            if ($conflict->status === 'resolved') return $conflict;

            if ($resolution === 'accept_client') {
                $newVersion = $this->applyClientPayload($conflict);
                $conflict->server_version = $newVersion;
                $conflict->server_payload = $this->currentServerPayload($conflict->entity_type, $conflict->entity_id, $conflict->user_id);
            } else {
                $conflict->server_version = $this->currentServerVersion($conflict->entity_type,$conflict->entity_id,$conflict->user_id) ?? $conflict->server_version;
                $conflict->server_payload = $this->currentServerPayload($conflict->entity_type, $conflict->entity_id, $conflict->user_id);
            }

            $conflict->status='resolved';
            $conflict->resolution=$resolution;
            $conflict->resolved_by_user_id=$actorUserId;
            $conflict->resolved_at=now();
            $conflict->save();
            return $conflict->fresh();
        });
    }

    /** @return array<string,mixed>|null */
    public function currentServerPayload(string $entity, string $id, int|string $userId): ?array
    {
        if ($entity === 'project') {
            $m=Project::query()->whereKey($id)->where('user_id',$userId)->first();
            return $m ? [
                'parent_id'=>$m->parent_id,'name'=>$m->name,'code'=>$m->code,'status'=>$m->status,
                'color'=>$m->color,'is_archived'=>(bool)$m->is_archived,'customer_id'=>$m->customer_id,'rate_multiplier'=>(float)$m->rate_multiplier,'is_billable_default'=>(bool)$m->is_billable_default,'default_activity_type_id'=>$m->default_activity_type_id,
            ] : null;
        }
        if ($entity === 'project_rule') {
            $m=ProjectRule::query()->whereKey($id)->whereHas('project',fn($q)=>$q->where('user_id',$userId))->first();
            return $m ? [
                'project_id'=>(string)$m->project_id,'rule_type'=>$m->rule_type,'operator'=>$m->operator,
                'pattern'=>$m->pattern,'weight'=>(int)$m->weight,'priority'=>(int)$m->priority,'is_enabled'=>(bool)$m->is_enabled,
            ] : null;
        }
        if ($entity === 'activity_session') {
            $m=ActivitySession::query()->whereKey($id)->where('user_id',$userId)->first();
            return $m ? collect($m->toArray())->only([
                'device_id','project_id','task_id','activity_type_id','is_billable','source','process_name','executable_path','window_title',
                'classification_confidence','classification_reason','activity_type_confidence','activity_type_source','activity_type_reason','ide_context','started_at','ended_at','duration_seconds',
                'idle_seconds','note','is_billable','created_at_device','updated_at_device'
            ])->all() : null;
        }
        return null;
    }

    private function applyClientPayload(SyncConflict $conflict): int
    {
        $payload=$conflict->client_payload;
        if ($conflict->entity_type === 'project') {
            $m=Project::query()->whereKey($conflict->entity_id)->where('user_id',$conflict->user_id)->lockForUpdate()->firstOrFail();
            if (!empty($payload['parent_id'])) Project::query()->whereKey($payload['parent_id'])->where('user_id',$conflict->user_id)->firstOrFail();
            $newVersion=max((int)$m->version,(int)$conflict->client_version)+1;
            $m->fill(collect($payload)->only(['parent_id','name','code','status','color','is_archived','default_activity_type_id'])->all());
            $m->version=$newVersion; $m->save(); return $newVersion;
        }
        if ($conflict->entity_type === 'project_rule') {
            $m=ProjectRule::query()->whereKey($conflict->entity_id)->whereHas('project',fn($q)=>$q->where('user_id',$conflict->user_id))->lockForUpdate()->firstOrFail();
            $m->fill(collect($payload)->only(['rule_type','operator','pattern','weight','priority','is_enabled'])->all());
            if (!empty($payload['project_id'])) {
                $project=Project::query()->whereKey($payload['project_id'])->where('user_id',$conflict->user_id)->firstOrFail();
                $m->project()->associate($project);
            }
            $newVersion=max((int)$m->version,(int)$conflict->client_version)+1;
            $m->version=$newVersion; $m->save(); return $newVersion;
        }
        if ($conflict->entity_type === 'activity_session') {
            $m=ActivitySession::query()->whereKey($conflict->entity_id)->where('user_id',$conflict->user_id)->lockForUpdate()->firstOrFail();
            if (($payload['device_id'] ?? null) !== $conflict->device_id) {
                throw ValidationException::withMessages(['conflict'=>'Activity device cannot change during conflict resolution.']);
            }
            $newVersion=max((int)$m->version,(int)$conflict->client_version)+1;
            $m->fill($payload); $m->version=$newVersion; $m->save(); return $newVersion;
        }
        throw ValidationException::withMessages(['conflict'=>'Unknown entity type.']);
    }

    private function currentServerVersion(string $entity,string $id,int|string $userId): ?int
    {
        if($entity==='project') return Project::query()->whereKey($id)->where('user_id',$userId)->value('version');
        if($entity==='project_rule') return ProjectRule::query()->whereKey($id)->whereHas('project',fn($q)=>$q->where('user_id',$userId))->value('version');
        if($entity==='activity_session') return ActivitySession::query()->whereKey($id)->where('user_id',$userId)->value('version');
        return null;
    }
}

