<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DeviceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(Device::query()->where('user_id', $request->user()->getKey())->latest('last_seen_at')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $correlationId = trim((string) $request->header('X-WorkTracker-Correlation-ID', ''));
        if ($correlationId === '' || !preg_match('/^[A-Za-z0-9._-]{8,64}$/', $correlationId)) $correlationId = (string) Str::uuid();

        $data = $request->validate([
            'id' => ['required','uuid'],
            'name' => ['required','string','max:120'],
            'fingerprint_hash' => ['nullable','string','max:128'],
            'platform' => ['required','string','max:50'],
            'app_version' => ['nullable','string','max:32'],
        ]);

        $token = $request->user()->currentAccessToken();
        abort_unless($token && ($token->can('admin:write') || $token->can('device:' . strtolower($data['id']))), 403, 'This token is not bound to the requested device id.');

        $device = Device::query()->whereKey($data['id'])->first();
        if ($device && (string) $device->user_id !== (string) $request->user()->getKey()) {
            abort(409, 'Device identifier is already registered to another user.');
        }

        $device ??= new Device(['id' => $data['id']]);
        $device->fill($data);
        $device->user()->associate($request->user());
        $device->last_seen_at = now();
        $device->save();

        Log::channel('worktracker_sync')->info('device.registered', [
            'correlation_id' => $correlationId,
            'user_id' => $request->user()->getKey(),
            'device_id' => $device->getKey(),
            'created' => $device->wasRecentlyCreated,
            'platform' => $device->platform,
            'app_version' => $device->app_version,
        ]);

        return response()->json($device, $device->wasRecentlyCreated ? 201 : 200)
            ->header('X-WorkTracker-Correlation-ID', $correlationId);
    }

    public function show(Request $request, Device $device): JsonResponse
    {
        abort_unless((string) $device->user_id === (string) $request->user()->getKey(), 404);
        return response()->json($device);
    }

    public function update(Request $request, Device $device): JsonResponse
    {
        abort_unless((string) $device->user_id === (string) $request->user()->getKey(), 404);
        $data = $request->validate([
            'name' => ['sometimes','string','max:120'],
            'operator_label' => ['sometimes','nullable','string','max:120'],
            'app_version' => ['sometimes','nullable','string','max:32'],
        ]);
        $device->fill($data);
        $device->last_seen_at = now();
        $device->save();
        return response()->json($device);
    }
}
