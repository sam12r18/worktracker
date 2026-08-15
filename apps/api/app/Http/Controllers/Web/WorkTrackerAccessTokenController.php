<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WorkTrackerAccessTokenController extends Controller
{
    public function index(Request $request): View
    {
        $userId = $request->user()->getKey();

        return view('worktracker.tokens.index', [
            'tokens' => $request->user()->tokens()->where('name', 'like', 'worktracker:%')->latest()->get(),
            'devices' => Device::query()->where('user_id', $userId)->latest('last_seen_at')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(['device', 'admin'])],
            'label' => ['required', 'string', 'max:80'],
            'device_id' => ['nullable', 'required_if:kind,device', 'uuid'],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $kind = $data['kind'];
        $defaultDays = (int) config(
            $kind === 'device' ? 'worktracker.device_token_expiration_days' : 'worktracker.admin_token_expiration_days',
            $kind === 'device' ? 90 : 30
        );
        $days = (int) ($data['expires_in_days'] ?? $defaultDays);

        $abilities = $kind === 'device'
            ? ['device:register', 'device:sync', 'device:' . strtolower($data['device_id'])]
            : ['admin:read', 'admin:write'];

        $token = $request->user()->createToken(
            'worktracker:' . $kind . ':' . trim($data['label']),
            $abilities,
            now()->addDays($days)
        );

        return redirect()->route('worktracker.tokens.index')
            ->with('status', 'توکن ساخته شد. فقط همین یک‌بار مقدار کامل آن نمایش داده می‌شود.')
            ->with('new_worktracker_token', $token->plainTextToken);
    }

    public function destroy(Request $request, string $tokenId): RedirectResponse
    {
        $token = $request->user()->tokens()
            ->whereKey($tokenId)
            ->where('name', 'like', 'worktracker:%')
            ->firstOrFail();

        $token->delete();

        return back()->with('status', 'توکن لغو شد و دیگر برای API قابل استفاده نیست.');
    }
}
