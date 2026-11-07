<?php

use App\Models\AuditLog;
use App\Models\DailyEntry;
use App\Models\Participant;
use App\Models\PatientCredential;
use App\Models\StaffUser;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\SyntheticDailyInstrument;

// Standalone real-process regression; never run against a study/development schema.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

function check(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}
function callApi(string $token, string $method, string $path, array $data = []): array
{
    global $kernel;
    Auth::forgetGuards();
    $request = Request::create('http://localhost/api/v1'.$path, $method, [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer '.$token], json_encode($data));
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);

    return [$response->getStatusCode(), json_decode($response->getContent(), true)];
}
check(app()->environment('testing') && DB::getDriverName() === 'mysql' && preg_match('/^[a-z0-9_]+_assessment_concurrency_test$/', DB::connection()->getDatabaseName()), 'Use testing and a dedicated _assessment_concurrency_test schema.');
check(Schema::hasColumn('entri_harian', 'submission_fingerprint'), 'Apply additive migrations first.');

if (($argv[1] ?? '') === 'worker') {
    $input = json_decode(trim(fgets(STDIN)), true, flags: JSON_THROW_ON_ERROR);
    DB::connection()->beforeExecuting(function (string $sql): void {
        if (str_contains(strtolower($sql), 'from `responden`') && str_contains(strtolower($sql), 'for update')) {
            fwrite(STDOUT, "LOCK_REQUEST\n");
            fflush(STDOUT);
        }
    });
    fwrite(STDOUT, "READY\n");
    fflush(STDOUT);
    fgets(STDIN); // Parent releases both workers, then waits for both locking reads.
    $result = callApi($input['token'], $input['method'], $input['path'], $input['data']);
    fwrite(STDOUT, json_encode($result)."\n");
    exit(0);
}

function simultaneous(array $payloads, ?callable $beforeRelease = null): array
{
    global $participant;
    $children = [];
    DB::beginTransaction();
    Participant::whereKey($participant->id)->lockForUpdate()->firstOrFail();
    try {
        foreach ($payloads as $payload) {
            $process = proc_open([PHP_BINARY, __FILE__, 'worker'], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
            check(is_resource($process), 'Could not start concurrency worker.');
            stream_set_timeout($pipes[1], 20);
            fwrite($pipes[0], json_encode($payload)."\n");
            fflush($pipes[0]);
            $children[] = [$process, $pipes];
        }
        foreach ($children as [$process, $pipes]) {
            check(trim(fgets($pipes[1]) ?: '') === 'READY', 'Worker failed before the barrier.');
        }
        foreach ($children as [$process, $pipes]) {
            fwrite($pipes[0], "GO\n");
            fflush($pipes[0]);
        }
        foreach ($children as [$process, $pipes]) {
            check(trim(fgets($pipes[1]) ?: '') === 'LOCK_REQUEST', 'Both workers must reach the held participant lock.');
        }
        if ($beforeRelease) {
            $beforeRelease();
        }
        DB::commit(); // Real DB connections now contend on the same row.
        $results = [];
        foreach ($children as [$process, $pipes]) {
            $results[] = json_decode(trim(fgets($pipes[1]) ?: ''), true, flags: JSON_THROW_ON_ERROR);
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }
            check(proc_close($process) === 0, 'Concurrency worker failed.');
        }

        return $results;
    } finally {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        foreach ($children as [$process, $pipes]) {
            if (is_resource($process)) {
                proc_terminate($process);
                foreach ($pipes as $pipe) {
                    if (is_resource($pipe)) {
                        fclose($pipe);
                    }
                }
                proc_close($process);
            }
        }
    }
}

check(Participant::count() === 0 && StaffUser::count() === 0, 'Use a new empty disposable fixture schema; no reset is performed.');
$setting = SyntheticDailyInstrument::create();
$today = now('Asia/Jakarta')->toDateString();
$base = ['version' => $setting->versi, 'date_wib' => $today, 'vas_mm' => 0, 'filled_by' => 'PASIEN',
    'device_created_at' => now()->utc()->format('Y-m-d\TH:i:s.v\Z'),
    'answers' => [['code' => 'SYNTHETIC_ITEM', 'option_id' => $setting->nilai_json['questions'][0]['options'][0]['id']]]];
foreach (['same_identity', 'different_identity', 'same_identity_changed_body'] as $scenario) {
    $participant = Participant::factory()->create(['tanggal_pulang' => now('Asia/Jakarta')->subDay()->toDateString()]);
    $patient = PatientCredential::factory()->create(['responden_id' => $participant->id]);
    $token = $patient->createToken('synthetic-contention', ['api'], now()->addHour())->plainTextToken;
    $first = $base + ['submission_id' => (string) Str::uuid()];
    $second = $first;
    if ($scenario === 'different_identity') {
        $second['submission_id'] = (string) Str::uuid();
    }
    if ($scenario === 'same_identity_changed_body') {
        $second['vas_mm'] = 1;
    }
    $payload = ['token' => $token, 'method' => 'POST', 'path' => '/patient/assessments'];
    $results = simultaneous([$payload + ['data' => $first], $payload + ['data' => $second]]);
    $codes = array_column($results, 0);
    sort($codes);
    check($codes === ($scenario === 'same_identity' ? [200, 201] : [201, 409]), 'Incorrect concurrent submission result.');
    check(DailyEntry::where('responden_id', $participant->id)->count() === 1, 'Concurrent write duplicated an entry.');
    $entry = DailyEntry::where('responden_id', $participant->id)->firstOrFail();
    check($entry->symptoms()->count() === 1, 'Missing or duplicate symptom responses.');
    check(AuditLog::where('aksi', 'assessment.submitted')->where('entity_id', $entry->id)->count() === 1, 'Must audit only the single committed write.');
    if ($scenario === 'same_identity') {
        check($results[0][1]['data'] === $results[1][1]['data'], 'Replay receipt differs.');
    }
    $patient->tokens()->delete();
}
// The patient has passed Sanctum/middleware before blocking at the writer lock.
// Commit an actual PR-10 endpoint mutation inside the coordinator's transaction,
// then prove the waiting old-token submission cannot write after that commit.
$staffToken = $setting->approver->createToken('synthetic-credential-race', ['api'], now()->addHour())->plainTextToken;
foreach (['reset', 'revoke'] as $operation) {
    $participant = Participant::factory()->create(['tanggal_pulang' => now('Asia/Jakarta')->subDay()->toDateString()]);
    $patient = PatientCredential::factory()->create(['responden_id' => $participant->id]);
    $token = $patient->createToken('synthetic-stale-session', ['api'], now()->addHour())->plainTextToken;
    $body = $base + ['submission_id' => (string) Str::uuid()];
    $results = simultaneous([['token' => $token, 'method' => 'POST', 'path' => '/patient/assessments', 'data' => $body]], function () use ($patient, $staffToken, $operation): void {
        $payload = ['expected_revision' => $patient->revision];
        if ($operation === 'reset') {
            $payload['pin'] = '654321';
        }
        [$status] = callApi($staffToken, $operation === 'reset' ? 'POST' : 'DELETE', '/participants/'.$patient->responden_id.'/credentials', $payload);
        check($status === ($operation === 'reset' ? 201 : 200), 'Credential operation must complete before releasing the patient submission.');
    });
    check($results[0][0] === 401, 'A token invalidated after middleware must be rejected inside the assessment transaction.');
    check(DailyEntry::where('responden_id', $participant->id)->count() === 0, 'Credential race created an unauthorized entry.');
}
$setting->approver->tokens()->delete();
check(DB::table('klasifikasi')->count() === 0 && DB::table('peringatan')->count() === 0 && DB::table('notification_deliveries')->count() === 0, 'Unexpected clinical side effects.');
fwrite(STDOUT, "PASS: real concurrent submissions, identical replay, changed-body and daily conflicts; one immutable entry, response and audit per participant; reset/revocation after middleware block old-token writes; no clinical side effects.\n");
