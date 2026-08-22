<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\ActivitySession;
use App\Models\ProjectRule;
use App\Services\SyncConflictService;
use App\Services\WorkEventMaterializer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Compatibility adapter for the Alpha 8.1 browser-context protocol extension.
 *
 * The established SyncController remains the owner of authentication, conflict
 * handling, activity persistence and Work Event projection. This adapter only
 * validates the new browser payload, removes it before the legacy validator,
 * delegates the sync, then persists browser context for accepted Activities.
 * It also preserves Browser* project-rule types across the legacy rule validator.
 */
class BrowserContextSyncController extends SyncController
{
    private const BROWSER_RULE_TYPES = ['BrowserHost', 'BrowserPath', 'BrowserTitle'];

    public function __invoke(Request $request, SyncConflictService $conflictService, WorkEventMaterializer $workEvents): JsonResponse
    {
        [$changes, $browserContexts, $browserRuleTypes] = $this->prepareChanges($request);
        $request->merge(['changes' => $changes]);

        $response = parent::__invoke($request, $conflictService, $workEvents);
        $body = $response->getData(true);
        $accepted = is_array($body['accepted'] ?? null) ? $body['accepted'] : [];

        $acceptedActivities = [];
        $acceptedRules = [];
        foreach ($accepted as $row) {
            if (! is_array($row) || empty($row['id']) || empty($row['entity'])) {
                continue;
            }
            if ($row['entity'] === 'activity_session') {
                $acceptedActivities[(string) $row['id']] = true;
            } elseif ($row['entity'] === 'project_rule') {
                $acceptedRules[(string) $row['id']] = true;
            }
        }

        $userId = $request->user()->getKey();
        $deviceId = (string) $request->input('device_id');

        foreach ($browserContexts as $id => $context) {
            if (! isset($acceptedActivities[$id])) {
                continue;
            }
            $session = ActivitySession::query()
                ->whereKey($id)
                ->where('user_id', $userId)
                ->where('device_id', $deviceId)
                ->first();
            if (! $session) {
                continue;
            }
            $session->forceFill(['browser_context' => $context])->saveQuietly();
        }

        foreach ($browserRuleTypes as $id => $ruleType) {
            if (! isset($acceptedRules[$id])) {
                continue;
            }
            $rule = ProjectRule::query()
                ->whereKey($id)
                ->whereHas('project', fn ($query) => $query->where('user_id', $userId))
                ->first();
            if (! $rule) {
                continue;
            }
            $rule->forceFill(['rule_type' => $ruleType])->saveQuietly();
        }

        return $response;
    }

    /** @return array{0:array,1:array<string,array>,2:array<string,string>} */
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
                    $browserContexts[$id] = $this->validateBrowserContext($rawContext, $index);
                }
                unset($changes[$index]['payload']['browser_context']);
            }

            if ($entity === 'project_rule') {
                $ruleType = (string) ($change['payload']['rule_type'] ?? '');
                if (in_array($ruleType, self::BROWSER_RULE_TYPES, true)) {
                    $browserRuleTypes[$id] = $ruleType;
                    // Keyword is accepted by the legacy validator; the exact Browser* type
                    // is restored after the parent sync transaction accepts this Rule.
                    $changes[$index]['payload']['rule_type'] = 'Keyword';
                }
            }
        }

        return [$changes, $browserContexts, $browserRuleTypes];
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

        $parts = parse_url((string) $validated['url']);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($parts === false || ! in_array($scheme, ['http', 'https'], true) || $host === '') {
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
}
