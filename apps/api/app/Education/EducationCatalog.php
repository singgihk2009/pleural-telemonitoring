<?php

namespace App\Education;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EducationCatalog
{
    public function selected(bool $published): Builder
    {
        $query = DB::table('education_topics as t')->join('education_revisions as r', function ($join) use ($published) {
            $join->on('r.topic_code', '=', 't.code')
                ->on('r.version', '=', $published ? 't.published_version' : 't.latest_version');
        })->select('r.*', 't.published_version')->orderBy('r.display_order')->orderBy('t.code');

        return $published ? $query->selectRaw("EXISTS (SELECT 1 FROM education_references e WHERE e.revision_id = r.id AND e.type = 'YOUTUBE') AS has_video") : $query;
    }

    public function references(int $id): array
    {
        return DB::table('education_references')->where('revision_id', $id)->orderBy('position')
            ->get(['type', 'label', 'url', 'canonical_url'])->map(fn ($reference) => (array) $reference)->all();
    }

    public function append(object $topic, array $data, string $status, ?int $actor): int
    {
        $version = $topic->latest_version + 1;
        $id = DB::table('education_revisions')->insertGetId([
            'topic_code' => $topic->code, 'version' => $version, 'status' => $status,
            'title' => $data['title'], 'description' => $data['description'], 'display_order' => $data['display_order'],
            'approval_reference' => $data['approval_reference'] ?? null, 'actor_id' => $actor, 'recorded_at' => now(),
        ]);
        foreach ($data['references'] as $position => $reference) {
            DB::table('education_references')->insert([
                'revision_id' => $id, 'position' => $position, 'type' => $reference['type'], 'label' => $reference['label'],
                'url' => $reference['url'] ?? null, 'canonical_url' => $reference['canonical_url'] ?? null,
            ]);
        }
        DB::table('education_topics')->where('code', $topic->code)->update([
            'latest_version' => $version,
            'published_version' => match ($status) {
                'PUBLISHED' => $version,
                'ARCHIVED' => null,
                default => $topic->published_version,
            },
        ]);

        return $version;
    }

    public function validateLinks(array $references, bool $publishing = false): void
    {
        $video = false;
        foreach ($references as $index => $reference) {
            $url = $reference['url'] ?? null;
            $canonical = $reference['canonical_url'] ?? null;
            $isVideo = $reference['type'] === 'YOUTUBE';
            $untypedVideo = ! $isVideo && ($this->isYouTubeHost($url) || $this->isYouTubeHost($canonical));
            $valid = ! $untypedVideo && ($url === null ? $reference['type'] === 'JOURNAL' : $this->isHttps($url))
                && ($canonical === null || ($url !== null && $this->isHttps($canonical) && ! $this->isShortReference($canonical)))
                && (! $isVideo || (! $video && $canonical === null && $this->isYouTube($url)))
                && (! $publishing || $url === null || ! $this->isShortReference($url) || $canonical !== null);
            if (! $valid) {
                throw ValidationException::withMessages(["references.$index.url" => ['Periksa URL HTTPS, tautan YouTube tunggal, dan tujuan kanonik tautan pendek sebelum publikasi.']]);
            }
            $video = $video || $isVideo;
        }
    }

    private function isHttps(string $url): bool
    {
        if (! filter_var($url, FILTER_VALIDATE_URL) || str_contains($url, chr(92)) || preg_match('/[\x00-\x20\x7f]/', $url)) {
            return false;
        }
        $parts = parse_url($url);

        return ($parts['scheme'] ?? null) === 'https' && ! isset($parts['user']) && ! isset($parts['pass'])
            && (! isset($parts['port']) || $parts['port'] === 443)
            && ! filter_var(trim($parts['host'] ?? '', '[]'), FILTER_VALIDATE_IP)
            && str_contains($parts['host'] ?? '', '.')
            && ! preg_match('/\.(localhost|local|internal|test|invalid)\.?$/i', $parts['host'] ?? '');
    }

    private function isShortReference(string $url): bool
    {
        return strtolower(rtrim(parse_url($url, PHP_URL_HOST) ?? '', '.')) === 'share.google';
    }

    private function isYouTubeHost(?string $url): bool
    {
        $host = $url === null ? '' : strtolower(rtrim(parse_url($url, PHP_URL_HOST) ?: '', '.'));

        return $host === 'youtu.be' || $host === 'youtube.com' || str_ends_with($host, '.youtube.com');
    }

    private function isYouTube(?string $url): bool
    {
        if ($url === null) {
            return false;
        }
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        if ($host === 'youtu.be') {
            return preg_match('~\A/[A-Za-z0-9_-]{11}\z~', $parts['path'] ?? '') === 1;
        }
        if (! in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com'], true) || ($parts['path'] ?? '') !== '/watch') {
            return false;
        }
        $ids = array_values(array_filter(explode('&', $parts['query'] ?? ''), fn ($part) => str_starts_with($part, 'v=')));

        return count($ids) === 1 && preg_match('/\Av=[A-Za-z0-9_-]{11}\z/', $ids[0]) === 1;
    }
}
