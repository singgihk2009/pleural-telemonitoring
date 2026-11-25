<?php

namespace App\Http\Controllers;

use App\Auth\SecurityAudit;
use App\Education\EducationCatalog;
use App\Models\Participant;
use App\Models\PatientCredential;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class EducationController extends Controller
{
    public function __construct(private EducationCatalog $catalog) {}

    public function patientIndex(Request $request): JsonResponse
    {
        abort_unless($request->user() instanceof PatientCredential, 403);
        $items = $this->catalog->selected(true)->paginate(25);

        return $this->response($items->getCollection()->map(fn ($row) => $this->summary($row, patient: true))->all(), meta: [
            'current_page' => $items->currentPage(), 'last_page' => $items->lastPage(),
        ]);
    }

    public function patientShow(Request $request, string $code): JsonResponse
    {
        abort_unless($request->user() instanceof PatientCredential, 403);
        $row = $this->catalog->selected(true)->where('t.code', $code)->first();
        abort_unless($row, 404);
        $references = array_map(fn ($r) => [
            'type' => $r['type'], 'label' => $r['label'], 'url' => $r['canonical_url'] ?? $r['url'],
        ], $this->catalog->references($row->id));

        return $this->response($this->summary($row, patient: true) + ['references' => $references]);
    }

    public function index(): JsonResponse
    {
        Gate::authorize('create', Participant::class);
        $page = $this->catalog->selected(false)->paginate(25);

        return $this->response($page->getCollection()->map(fn ($row) => $this->summary($row))->all(), meta: [
            'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(),
        ]);
    }

    public function show(Request $request, string $code): JsonResponse
    {
        Gate::authorize('create', Participant::class);
        $topic = $this->catalog->selected(false)->where('t.code', $code)->first();
        abort_unless($topic, 404);
        $history = DB::table('education_revisions')->where('topic_code', $code)->orderByDesc('version')
            ->paginate(25, ['version', 'status', 'recorded_at', 'actor_id', 'approval_reference']);
        $version = $request->validate(['version' => ['sometimes', 'integer', 'min:1']])['version'] ?? null;
        $row = $version === null ? $topic : DB::table('education_revisions')->where('topic_code', $code)
            ->where('version', $version)->first();
        abort_unless($row, 404);
        $row->published_version = $topic->published_version;

        return $this->response($this->summary($row) + ['references' => $this->catalog->references($row->id), 'history' => $history->items()], meta: [
            'current_page' => $history->currentPage(), 'last_page' => $history->lastPage(),
        ]);
    }

    public function create(Request $request, SecurityAudit $audit): JsonResponse
    {
        return $this->save($request, $audit, null);
    }

    public function update(Request $request, SecurityAudit $audit, string $code): JsonResponse
    {
        return $this->save($request, $audit, $code);
    }

    private function save(Request $request, SecurityAudit $audit, ?string $code): JsonResponse
    {
        Gate::authorize('create', Participant::class);
        $creating = $code === null;
        $data = $request->validate([
            'code' => $creating ? ['required', 'regex:/\A[A-Z0-9-]{1,24}\z/'] : ['prohibited'],
            'expected_version' => $creating ? ['prohibited'] : ['required', 'integer', 'min:1'],
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:4000'],
            'display_order' => ['required', 'integer', 'between:1,65535'],
            'references' => ['present', 'array', 'list', 'max:30'],
            'references.*' => ['array:type,label,url,canonical_url'],
            'references.*.type' => ['required', 'in:ARTICLE,JOURNAL,DOI,PDF,YOUTUBE'],
            'references.*.label' => ['required', 'string', 'max:500'],
            'references.*.url' => ['nullable', 'string', 'max:2048'],
            'references.*.canonical_url' => ['nullable', 'string', 'max:2048'],
        ]);
        $data['description'] ??= '';
        $this->catalog->validateLinks($data['references']);
        $code ??= $data['code'];

        return DB::transaction(function () use ($request, $audit, $code, $data, $creating) {
            if ($creating) {
                abort_unless(DB::table('education_topics')->insertOrIgnore(['code' => $code]), 409);
            }
            $topic = $this->lockTopic($code, $data['expected_version'] ?? 0);
            $actor = $this->authorizedActor($request);
            $version = $this->catalog->append($topic, $data, 'DRAFT', $actor);
            $audit->record($request, 'education.draft_saved', 'SUCCESS', $code, 'education_topic');

            return $this->response(['code' => $code, 'version' => $version], $creating ? 201 : 200);
        }, 3);
    }

    public function publish(Request $request, SecurityAudit $audit, string $code): JsonResponse
    {
        return $this->transition($request, $audit, $code, 'PUBLISHED');
    }

    public function archive(Request $request, SecurityAudit $audit, string $code): JsonResponse
    {
        return $this->transition($request, $audit, $code, 'ARCHIVED');
    }

    private function transition(Request $request, SecurityAudit $audit, string $code, string $status): JsonResponse
    {
        Gate::authorize('create', Participant::class);
        $publishing = $status === 'PUBLISHED';
        $data = $request->validate([
            'expected_version' => ['required', 'integer', 'min:1'],
            'confirm' => ['required', 'accepted'],
            'approval_reference' => $publishing ? ['required', 'string', 'regex:/\A[A-Za-z0-9_-]{1,80}\z/'] : ['prohibited'],
        ]);

        return DB::transaction(function () use ($request, $audit, $code, $status, $publishing, $data) {
            $topic = $this->lockTopic($code, $data['expected_version']);
            $actor = $this->authorizedActor($request);
            $snapshot = (array) $this->catalog->selected(false)->where('t.code', $code)->first();
            abort_if($publishing ? $snapshot['status'] !== 'DRAFT' : $snapshot['status'] === 'ARCHIVED', 409);
            $snapshot['references'] = $this->catalog->references($snapshot['id']);
            if ($publishing) {
                if (trim($snapshot['description']) === '' || $snapshot['references'] === []) {
                    throw ValidationException::withMessages(['description' => ['Publikasi memerlukan deskripsi yang ditinjau dan setidaknya satu referensi.']]);
                }
                $this->catalog->validateLinks($snapshot['references'], publishing: true);
                $snapshot['approval_reference'] = $data['approval_reference'];
            }
            $version = $this->catalog->append($topic, $snapshot, $status, $actor);
            $audit->record($request, $publishing ? 'education.published' : 'education.archived', 'SUCCESS', $code, 'education_topic');

            return $this->response(['code' => $code, 'version' => $version]);
        }, 3);
    }

    private function authorizedActor(Request $request): int
    {
        $actor = $request->user()->newQuery()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
        $token = $actor->tokens()->whereKey($request->user()->currentAccessToken()->id)->first();
        abort_unless($token && $token->expires_at?->isFuture() && $token->can('api'), 401);
        Gate::forUser($actor)->authorize('create', Participant::class);

        return $actor->id;
    }

    private function lockTopic(string $code, int $version): object
    {
        $topic = DB::table('education_topics')->where('code', $code)->lockForUpdate()->first();
        abort_unless($topic, 404);
        abort_unless($topic->latest_version === $version, 409);

        return $topic;
    }

    private function summary(object $row, bool $patient = false): array
    {
        $public = Arr::only((array) $row, ['title', 'description', 'display_order', 'version']) + ['code' => $row->topic_code];

        return $patient ? $public + [
            'has_video' => (bool) $row->has_video,
        ] : $public + Arr::only((array) $row, ['status', 'published_version', 'approval_reference', 'actor_id', 'recorded_at']);
    }

    private function response(array $data, int $status = 200, array $meta = []): JsonResponse
    {
        return response()->json(['data' => $data] + ($meta === [] ? [] : ['meta' => $meta]), $status)->header('Cache-Control', 'no-store');
    }
}
