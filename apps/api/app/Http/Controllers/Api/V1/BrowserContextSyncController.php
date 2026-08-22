<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\ActivitySession;
use App\Models\ProjectRule;
use App\Services\SyncConflictService;
use App\Services\WorkEventMaterializer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Compatibility adapter for the Alpha 8.1 browser-context protocol extension.
 *
 * SyncController remains the owner of authentication, conflict handling,
 * Activity persistence and Work Event projection. This adapter validates the
 * browser extension payload, removes Browser-only fields before delegating to
 * the stable sync pipeline, and persists them only for accepted entities.
 *
 * The adapter wraps the stable pipeline in an outer transaction. Laravel's
 * nested transaction handling keeps the legacy SyncController transaction and
 * the Browser Context/Browser Rule restoration in one atomic commit. This
 * prevents a partially accepted Browser Rule from being left behind as the
 * temporary Keyword compatibility type if post-processing fails.
 */
class BrowserContextSyncController extends SyncController
{
    private const BROWSER_RULE_TYPES = ['BrowserHost', 'BrowserPath', 'BrowserTitle'];

    public function __invoke(Request $request, SyncConflictService $conflictService, WorkEventMaterializer $workEvents): JsonResponse
    {
        [$changes, $browserContexts, $browserRuleTypes] = $this->prepareChanges($request);
        $this->assertReplayConsistency($request, $browserContexts, $browserRuleTypes);
        $request->merge(['changes' => $changes]);

        try {
            /** @var JsonResponse $response */
            $response = DB::transaction(function () use ($request, $conflictService, $workEvents, $browserContexts, $browserRuleTypes): JsonResponse {
                $response = parent::__invoke($request, $conflictService, $workEvents);
                $body = $response->getData(true);
                $accepted = is_array($body['accepted'] ?? null) ? $body['accepted'] : [];

                $acceptedActivities = [];
                $acceptedRules = [];
                foreach ($accepted as $row) {
                    if (! is_array($row) || empty($row['id']) || empty($row['entity'])) {
                        continue;
                    }

                    $version = (int) ($row['version'] ?? 0);
                    if ($row['entity'] === 'activity_session') {
                        $acceptedActivities[(string) $row['id']] = $version;
                    } elseif ($row['entity'] === 'project_rule') {
                        $acceptedRules[(string) $row['id']] = $version;
                    }
                }

                $userId = $request->user()->getKey();
                $deviceId = (string) $request->input('device_id');

                foreach ($browserContexts as $id => $incoming) {
                    if (! isset($acceptedActivities[$id]) || $acceptedActivities[$id] !== $incoming['version']) {
                        continue;
                    }

                    $session = ActivitySession::query()
                        ->whereKey($id)
                        ->where('user_id', $userId)
                        ->where('device_id', $deviceId)
                        ->where('version', $incoming['version'])
                        ->first();

                    if (! $session) {
                        continue;
                    }

                    if ($session->browser_context === null || ! $this->contextsEqual($session->browser_context, $incoming['context'])) {
                        $session->forceFill(['browser_context' => $incoming['context']])->saveQuietly();
                    }
                }

                foreach ($browserRuleTypes as $id => $incoming) {
                    if (! isset($acceptedRules[$id]) || $acceptedRules[$id] !== $incoming['version']) {
                        continue;
                    }

                    $rule = ProjectRule::query()
                        ->whereKey($id)
                        ->where('version', $incoming['version'])
                        ->whereHas('project', fn ($query) => $query->where('user_id', $userId))
                        ->first();

                    if (! $rule) {
                        continue;
                    }

                    if ($rule->rule_type !== $incoming['rule_type']) {
                        $rule->forceFill(['rule_type' => $incoming['rule_type']])->saveQuietly();
                    }
                }

                return $response;
            });

            return $response;
        } catch (\Throwable $e) {
            Log::channel('worktracker_sync')->error('browser_context_sync.atomic_extension_failed', [
                'user_id' => $request->user()?->getKey(),
                'device_id' => $request->input('device_id'),
                'browser_context_changes' => count($browserContexts),
                'browser_rule_changes' => count($browserRuleTypes),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * @return array{
     *   0:array,
     *   1:array<string,array{context:array<string,mixed>,version:int}>,
     *   2:array<string,array{rule_type:string,version:int}>
     * }
     */
    private function prepareChanges(Request $request): array
    {
        $changes = $request->input('changes', []);
        if (! is_array($changes)) {
            return [$changes, [], []];
        }

        $browserContexts = [];
        $browserRuleTypes = [];

        foreach ($changes as $index => $change) {
            if (! is_array($change) || ! is_array($change['payload'] ?? null)) {
                continue;
            }

            $entity = (string) ($change['entity'] ?? '');
            $id = (string) ($change['id'] ?? '');
            $version = (int) ($change['version'] ?? 0);
            if ($id === '') {
                continue;
            }

            if ($entity === 'activity_session' && array_key_exists('browser_context', $change['payload'])) {
                $rawContext = $change['payload']['browser_context'];
                if ($rawContext !== null) {
                    if (! is_array($rawContext)) {
                        throw ValidationException::withMessages([
                            "changes.$index.payload.browser_context" => 'Browser context must be an object.',
                        ]);
                    }

                    $browserContexts[$id] = [
                        'context' => $this->validateBrowserContext($rawContext, $index),
                        'version' => $version,
                    ];
                }

                unset($changes[$index]['payload']['browser_context']);
            }

            if ($entity === 'project_rule') {
                $ruleType = (string) ($change['payload']['rule_type'] ?? '');
                if (in_array($ruleType, self::BROWSER_RULE_TYPES, true)) {
                    $browserRuleTypes[$id] = [
                        'rule_type' => $ruleType,
                        'version' => $version,
                    ];

                    // Keyword is accepted by the legacy validator. Because the parent Sync
                    // transaction is now nested inside our outer transaction, this temporary
                    // compatibility type is never committed independently of the Browser* type.
                    $changes[$index]['payload']['rule_type'] = 'Keyword';
                }
            }
        }

        return [$changes, $browserContexts, $browserRuleTypes];
    }

    /**
     * Same-version retries must be idempotent. Browser metadata may be filled on
     * a legacy row where it is still NULL, but it must never silently rewrite a
     * different Context or Browser Rule without a version increment.
     *
     * @param array<string,array{context:array<string,mixed>,version:int}> $browserContexts
     * @param array<string,array{rule_type:string,version:int}> $browserRuleTypes
     */
    private function assertReplayConsistency(Request $request, array $browserContexts, array $browserRuleTypes): void
    {
        $userId = $request->user()->getKey();
        $deviceId = (string) $request->input('device_id');

        if ($browserContexts !== []) {
            $sessions = ActivitySession::query()
                ->where('user_id', $userId)
                ->where('device_id', $deviceId)
                ->whereIn('id', array_keys($browserContexts))
                ->get(['id', 'version', 'browser_context'])
                ->keyBy(fn (ActivitySession $session) => (string) $session->getKey());

            foreach ($browserContexts as $id => $incoming) {
                $existing = $sessions->get($id);
                if (! $existing || (int) $existing->version !== $incoming['version'] || $existing->browser_context === null) {
                    continue;
                }

                if (! $this->contextsEqual($existing->browser_context, $incoming['context'])) {
                    throw ValidationException::withMessages([
                        'changes' => "Browser context replay changed without a version increment for activity $id.",
                    ]);
                }
            }
        }

        if ($browserRuleTypes !== []) {
            $rules = ProjectRule::query()
                ->whereIn('id', array_keys($browserRuleTypes))
                ->whereHas('project', fn ($query) => $query->where('user_id', $userId))
                ->get(['id', 'version', 'rule_type'])
                ->keyBy(fn (ProjectRule $rule) => (string) $rule->getKey());

            foreach ($browserRuleTypes as $id => $incoming) {
                $existing = $rules->get($id);
                if (! $existing || (int) $existing->version !== $incoming['version']) {
                    continue;
                }

                if ((string) $existing->rule_type !== $incoming['rule_type']) {
                    throw ValidationException::withMessages([
                        'changes' => "Browser rule type changed without a version increment for rule $id.",
                    ]);
                }
            }
        }
    }

    /** @param array<string,mixed> $context @return array<string,mixed> */
    private function validateBrowserContext(array $context, int|string $index): array
    {
        $validated = Validator::make($context, [
            'protocol_version' => ['required', 'integer', 'in:1'],
            'extension_version' => ['nullable', 'string', 'max:64'],
            'browser' => ['required', 'in:chrome'],
            'title' => ['nullable', 'string', 'max:1024'],
            'url' => ['required', 'string', 'max:4096'],
            'host' => ['required', 'string', 'max:512'],
            'path' => ['required', 'string', 'max:2048'],
            'tab_id' => ['nullable', 'integer'],
            'window_id' => ['nullable', 'integer'],
            'incognito' => ['required', 'boolean'],
            'focused' => ['required', 'boolean'],
            'observed_at_utc' => ['required', 'date'],
            'source' => ['required', 'in:chrome_extension'],
        ])->validate();

        if ((bool) $validated['incognito']) {
            throw ValidationException::withMessages([
                "changes.$index.payload.browser_context" => 'Incognito browser context is not accepted.',
            ]);
        }
        if (! (bool) $validated['focused']) {
            throw ValidationException::withMessages([
                "changes.$index.payload.browser_context" => 'Only focused browser context is accepted.',
            ]);
        }

        foreach (['url', 'host', 'path'] as $field) {
            if (preg_match('/[\x00-\x1F\x7F]/', (string) $validated[$field])) {
                throw ValidationException::withMessages([
                    "changes.$index.payload.browser_context.$field" => 'Browser context contains invalid control characters.',
                ]);
            }
        }

        $parts = parse_url((string) $validated['url']);
        if ($parts === false) {
            throw ValidationException::withMessages([
                "changes.$index.payload.browser_context.url" => 'Browser URL must be an absolute HTTP(S) URL.',
            ]);
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw ValidationException::withMessages([
                "changes.$index.payload.browser_context.url" => 'Browser URL must be an absolute HTTP(S) URL.',
            ]);
        }
        if (isset($parts['query']) || isset($parts['fragment']) || isset($parts['user']) || isset($parts['pass'])) {
            throw ValidationException::withMessages([
                "changes.$index.payload.browser_context.url" => 'Browser URL must be privacy-normalized before sync.',
            ]);
        }

        $authority = $host . (isset($parts['port']) ? ':' . (int) $parts['port'] : '');
        $path = (string) ($parts['path'] ?? '/');
        if ($path === '') {
            $path = '/';
        }
        if (! str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        if (strtolower(trim((string) $validated['host'])) !== $authority || (string) $validated['path'] !== $path) {
            throw ValidationException::withMessages([
                "changes.$index.payload.browser_context" => 'Browser host/path must match the normalized URL.',
            ]);
        }

        $validated['browser'] = 'chrome';
        $validated['host'] = $authority;
        $validated['path'] = $path;
        $validated['url'] = $scheme . '://' . $authority . $path;
        $validated['incognito'] = false;
        $validated['focused'] = true;
        $validated['source'] = 'chrome_extension';

        return $validated;
    }

    /** @param array<string,mixed> $left @param array<string,mixed> $right */
    private function contextsEqual(array $left, array $right): bool
    {
        ksort($left);
        ksort($right);
        return json_encode($left, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            === json_encode($right, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
