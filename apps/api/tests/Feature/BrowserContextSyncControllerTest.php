<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\V1\BrowserContextSyncController;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;
use Tests\TestCase;

class BrowserContextSyncControllerTest extends TestCase
{
    public function test_valid_browser_context_is_accepted_and_normalized(): void
    {
        $validated = $this->validateBrowserContext($this->validContext());

        $this->assertSame('chrome', $validated['browser']);
        $this->assertSame('github.com', $validated['host']);
        $this->assertSame('/sam12r18/worktracker/issues', $validated['path']);
        $this->assertSame('https://github.com/sam12r18/worktracker/issues', $validated['url']);
        $this->assertFalse($validated['incognito']);
        $this->assertTrue($validated['focused']);
    }

    public function test_query_fragment_and_url_credentials_are_rejected(): void
    {
        foreach ([
            'https://github.com/sam12r18/worktracker/issues?token=secret',
            'https://github.com/sam12r18/worktracker/issues#private',
            'https://user:pass@github.com/sam12r18/worktracker/issues',
        ] as $url) {
            try {
                $this->validateBrowserContext($this->validContext(['url' => $url]));
                $this->fail("Expected browser URL to be rejected: {$url}");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('changes.0.payload.browser_context.url', $e->errors());
            }
        }
    }

    public function test_incognito_and_unfocused_context_are_rejected(): void
    {
        foreach ([
            ['incognito' => true],
            ['focused' => false],
        ] as $override) {
            $this->expectBrowserContextValidationFailure($this->validContext($override));
        }
    }

    public function test_host_or_path_must_match_normalized_url(): void
    {
        foreach ([
            ['host' => 'example.com'],
            ['path' => '/other/project'],
        ] as $override) {
            try {
                $this->validateBrowserContext($this->validContext($override));
                $this->fail('Expected host/path integrity validation to fail.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('changes.0.payload.browser_context', $e->errors());
            }
        }
    }

    public function test_prepare_changes_strips_browser_context_and_wraps_browser_rule_type_for_legacy_sync(): void
    {
        $activityId = '11111111-1111-4111-8111-111111111111';
        $ruleId = '22222222-2222-4222-8222-222222222222';
        $context = $this->validContext();

        $request = Request::create('/api/v1/sync', 'POST', [
            'changes' => [
                [
                    'entity' => 'activity_session',
                    'id' => $activityId,
                    'version' => 3,
                    'payload' => [
                        'device_id' => '33333333-3333-4333-8333-333333333333',
                        'browser_context' => $context,
                    ],
                ],
                [
                    'entity' => 'project_rule',
                    'id' => $ruleId,
                    'version' => 2,
                    'payload' => [
                        'rule_type' => 'BrowserPath',
                        'pattern' => '/sam12r18/worktracker',
                    ],
                ],
            ],
        ]);

        [$changes, $browserContexts, $browserRuleTypes] = $this->invokePrivate('prepareChanges', [$request]);

        $this->assertArrayNotHasKey('browser_context', $changes[0]['payload']);
        $this->assertSame('Keyword', $changes[1]['payload']['rule_type']);
        $this->assertSame(3, $browserContexts[$activityId]['version']);
        $this->assertSame('github.com', $browserContexts[$activityId]['context']['host']);
        $this->assertSame(['rule_type' => 'BrowserPath', 'version' => 2], $browserRuleTypes[$ruleId]);
    }

    public function test_prepare_changes_preserves_explicit_null_as_a_versioned_context_clear(): void
    {
        $activityId = '44444444-4444-4444-8444-444444444444';
        $request = Request::create('/api/v1/sync', 'POST', [
            'changes' => [[
                'entity' => 'activity_session',
                'id' => $activityId,
                'version' => 4,
                'payload' => [
                    'device_id' => '33333333-3333-4333-8333-333333333333',
                    'browser_context' => null,
                ],
            ]],
        ]);

        [$changes, $browserContexts] = $this->invokePrivate('prepareChanges', [$request]);

        $this->assertArrayNotHasKey('browser_context', $changes[0]['payload']);
        $this->assertArrayHasKey($activityId, $browserContexts);
        $this->assertSame(4, $browserContexts[$activityId]['version']);
        $this->assertNull($browserContexts[$activityId]['context']);
    }

    public function test_nullable_context_equality_does_not_treat_clear_as_same_as_context(): void
    {
        $this->assertTrue($this->invokePrivate('contextsEqual', [null, null]));
        $this->assertFalse($this->invokePrivate('contextsEqual', [null, $this->validContext()]));
        $this->assertFalse($this->invokePrivate('contextsEqual', [$this->validContext(), null]));
    }

    /** @return array<string,mixed> */
    private function validContext(array $override = []): array
    {
        return array_replace([
            'protocol_version' => 1,
            'extension_version' => '0.1.0',
            'browser' => 'chrome',
            'title' => 'Issues · sam12r18/worktracker',
            'url' => 'https://github.com/sam12r18/worktracker/issues',
            'host' => 'github.com',
            'path' => '/sam12r18/worktracker/issues',
            'tab_id' => 12,
            'window_id' => 3,
            'incognito' => false,
            'focused' => true,
            'observed_at_utc' => '2026-08-22T12:00:00Z',
            'source' => 'chrome_extension',
        ], $override);
    }

    /** @param array<string,mixed> $context @return array<string,mixed> */
    private function validateBrowserContext(array $context): array
    {
        return $this->invokePrivate('validateBrowserContext', [$context, 0]);
    }

    /** @param array<string,mixed> $context */
    private function expectBrowserContextValidationFailure(array $context): void
    {
        try {
            $this->validateBrowserContext($context);
            $this->fail('Expected browser context validation to fail.');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }
    }

    private function invokePrivate(string $methodName, array $arguments): mixed
    {
        $controller = app(BrowserContextSyncController::class);
        $method = new ReflectionMethod($controller, $methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($controller, $arguments);
    }
}
