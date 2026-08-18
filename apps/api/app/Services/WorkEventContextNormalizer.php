<?php

namespace App\Services;

final class WorkEventContextNormalizer
{
    /** @return array{kind:string,key:string,display:string,stable_pattern:?string} */
    public function describe(?string $processName, ?string $windowTitle, ?array $ideContext = null): array
    {
        $process = $this->normalizeProcess($processName);
        $title = trim((string) $windowTitle);

        if (in_array($process, ['phpstorm64', 'phpstorm'], true) && is_array($ideContext)) {
            $projectName = trim((string) ($ideContext['project_name'] ?? ''));
            $projectPath = trim((string) ($ideContext['project_path'] ?? ''));
            $workspace = $projectName !== '' ? $projectName : ($projectPath !== '' ? basename(str_replace('\\', '/', $projectPath)) : '');
            $identity = $projectPath !== '' ? $projectPath : $workspace;
            if ($workspace !== '' && $identity !== '') {
                return ['kind'=>'ide','key'=>'ide:phpstorm:'.$this->keyPart($identity),'display'=>$workspace,'stable_pattern'=>$workspace];
            }
        }

        if (in_array($process, ['phpstorm64', 'phpstorm'], true)) {
            $normalized = $this->stripKnownApplicationSuffix($title, 'PhpStorm');
            $workspace = $this->chooseWorkspacePart($normalized, true);
            if ($workspace !== '') {
                return ['kind'=>'ide','key'=>'ide:phpstorm:'.$this->keyPart($workspace),'display'=>$workspace,'stable_pattern'=>$workspace];
            }
        }

        if (in_array($process, ['code', 'code-insiders'], true)) {
            $normalized = $this->stripKnownApplicationSuffix($title, 'Visual Studio Code');
            $workspace = $this->chooseWorkspacePart($normalized, false);
            if ($workspace !== '') {
                return ['kind'=>'ide','key'=>'ide:vscode:'.$this->keyPart($workspace),'display'=>$workspace,'stable_pattern'=>$workspace];
            }
        }

        if ($process === 'devenv') {
            $normalized = $this->stripKnownApplicationSuffix($title, 'Microsoft Visual Studio');
            $workspace = $this->chooseWorkspacePart($normalized, false);
            if ($workspace !== '') {
                return ['kind'=>'ide','key'=>'ide:visualstudio:'.$this->keyPart($workspace),'display'=>$workspace,'stable_pattern'=>$workspace];
            }
        }

        if (in_array($process, ['chrome','msedge','firefox','brave','opera','vivaldi'], true)) {
            $normalized = $this->stripBrowserSuffix($title);
            $display = $normalized !== '' ? $normalized : $process;
            return ['kind'=>'browser','key'=>'browser:'.$process.':'.$this->keyPart($display),'display'=>$display,'stable_pattern'=>null];
        }

        $generic = $title !== '' ? $title : $process;
        return ['kind'=>'generic','key'=>'app:'.$process.':'.$this->keyPart($generic),'display'=>$generic,'stable_pattern'=>null];
    }

    private function normalizeProcess(?string $value): string
    {
        $value = strtolower(trim((string) $value));
        return $value !== '' ? $value : 'unknown';
    }

    private function stripBrowserSuffix(string $title): string
    {
        foreach ([' - Google Chrome',' - Microsoft Edge',' — Mozilla Firefox',' - Brave',' - Opera',' - Vivaldi'] as $suffix) {
            $title = $this->stripSuffix($title, $suffix);
        }
        return trim($title);
    }

    private function stripKnownApplicationSuffix(string $title, string $appName): string
    {
        foreach ([' — ', ' – ', ' - '] as $separator) {
            $title = $this->stripSuffix($title, $separator.$appName);
        }
        return trim($title);
    }

    private function stripSuffix(string $value, string $suffix): string
    {
        if ($suffix !== '' && str_ends_with(strtolower($value), strtolower($suffix))) {
            return trim(substr($value, 0, -strlen($suffix)));
        }
        return $value;
    }

    private function chooseWorkspacePart(string $title, bool $preferFirst): string
    {
        $parts = $this->split($title);
        if ($parts === []) return trim($title);
        if (count($parts) === 1) return trim($parts[0]);

        $first = trim($parts[0]);
        $last = trim($parts[count($parts) - 1]);
        if ($this->looksLikeFile($first) && ! $this->looksLikeFile($last)) return $last;
        if ($this->looksLikeFile($last) && ! $this->looksLikeFile($first)) return $first;
        return $preferFirst ? $first : $last;
    }

    /** @return list<string> */
    private function split(string $title): array
    {
        foreach ([' — ', ' – ', ' - '] as $separator) {
            if (! str_contains($title, $separator)) continue;
            return array_values(array_filter(array_map('trim', explode($separator, $title)), static fn(string $x): bool => $x !== ''));
        }
        return trim($title) === '' ? [] : [trim($title)];
    }

    private function looksLikeFile(string $value): bool
    {
        $leaf = rtrim(trim($value), '*');
        $extension = pathinfo($leaf, PATHINFO_EXTENSION);
        return $extension !== '' && strlen($extension) <= 12;
    }

    private function keyPart(string $value): string
    {
        $normalized = strtolower(trim((string) preg_replace('/\s+/u', ' ', $value)));
        return mb_substr($normalized, 0, 180);
    }
}
