<?php

namespace App\Http\Controllers\WorkTracker;

use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DiagnosticsController extends Controller
{
    public function index(Request $request): View
    {
        $devices = Device::query()
            ->where('user_id', $request->user()->getKey())
            ->orderByDesc('last_seen_at')
            ->get();

        [$lines, $files] = $this->readRecentSyncLogs(400);

        return view('worktracker.diagnostics.index', [
            'devices' => $devices,
            'logLines' => $lines,
            'logFiles' => $files,
        ]);
    }

    /** @return array{0: array<int,string>, 1: array<int,string>} */
    private function readRecentSyncLogs(int $maxLines): array
    {
        $files = glob(storage_path('logs/worktracker-sync*.log')) ?: [];
        usort($files, fn (string $a, string $b) => filemtime($a) <=> filemtime($b));
        $selected = array_slice($files, -3);
        $lines = [];

        foreach ($selected as $path) {
            $lines = array_merge($lines, $this->tailFile($path, $maxLines));
        }

        return [array_slice($lines, -$maxLines), array_map('basename', $selected)];
    }

    /** @return array<int,string> */
    private function tailFile(string $path, int $maxLines): array
    {
        $handle = @fopen($path, 'rb');
        if (! $handle) return [];

        try {
            $size = filesize($path) ?: 0;
            $maxBytes = 768 * 1024;
            $offset = max(0, $size - $maxBytes);
            if ($offset > 0) {
                fseek($handle, $offset);
                fgets($handle); // discard partial first line
            }
            $content = stream_get_contents($handle) ?: '';
            $lines = preg_split('/\R/u', trim($content)) ?: [];
            return array_slice(array_values(array_filter($lines, fn ($line) => $line !== '')), -$maxLines);
        } finally {
            fclose($handle);
        }
    }
}
