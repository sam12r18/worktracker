<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(Device::query()->where('user_id', $request->user()->getKey())->latest('last_seen_at')->get());
    }

    public function store(Request $request): JsonResponse
    {
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

        return response()->json($device, $device->wasRecentlyCreated ? 201 : 200);
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
