<?php

use App\Models\AuditLog;
use App\Models\Participant;
use App\Models\PatientCredential;
use App\Models\ResearchRole;
use App\Models\StaffUser;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

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
check(app()->environment('testing') && DB::getDriverName() === 'mysql' && preg_match('/^[a-z0-9_]+_credential_concurrency_test$/', DB::connection()->getDatabaseName()), 'Use testing and a dedicated _credential_concurrency_test MySQL schema.');
check(Schema::hasColumn('patient_credentials', 'revision'), 'Apply additive migrations to the disposable schema first.');

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

check(Participant::count() === 0 && StaffUser::count() === 0, 'Refusing a non-empty concurrency fixture schema; use a new disposable schema.');
$staff = [];
$tokens = [];
foreach ([1, 2] as $unused) {
    $actor = StaffUser::factory()->create();
    $actor->roles()->attach(ResearchRole::firstOrCreate(['kode' => 'PENELITI_UTAMA']));
    $staff[] = $actor;
    $tokens[] = $actor->createToken('synthetic-concurrency', ['api'], now()->addHour())->plainTextToken;
}
$participant = Participant::factory()->create();
$path = '/participants/'.$participant->id.'/credentials';
[$code, $issued] = callApi($tokens[0], 'POST', $path, ['pin' => '012345', 'expected_revision' => null]);
check($code === 201, 'Initial synthetic issuance failed.');
$credential = PatientCredential::findOrFail($participant->id);
$oldToken = $credential->createToken('synthetic-patient', ['api'], now()->addHour())->plainTextToken;
$revision = $issued['data']['revision'];
foreach ($tokens as $token) {
    [$code, $state] = callApi($token, 'GET', $path);
    check($code === 200 && $state['data']['revision'] === $revision, 'Both clients must load the same initial revision.');
}

function simultaneous(array $payloads): array
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
        DB::commit(); // Both real DB connections now contend on the same row.
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

$pins = ['001234', '006789'];
$payloads = [];
foreach ($tokens as $index => $token) {
    $payloads[] = ['token' => $token, 'method' => 'POST', 'path' => $path, 'data' => ['pin' => $pins[$index], 'expected_revision' => $revision]];
}
$results = simultaneous($payloads);
$codes = array_column($results, 0);
sort($codes);
check($codes === [201, 409], 'Exactly one simultaneous reset must commit; the stale reset must conflict.');
$winner = $results[0][0] === 201 ? 0 : 1;
$credential = $credential->fresh();
check($credential->revision === $results[$winner][1]['data']['revision'], 'Receipt must identify the committed revision.');
check(Hash::check($pins[$winner], $credential->pin_hash) && ! Hash::check($pins[1 - $winner], $credential->pin_hash), 'Only the winning PIN may authenticate.');
check($credential->tokens()->count() === 0 && callApi($oldToken, 'GET', '/auth/me')[0] === 401, 'Reset must revoke prior sessions.');
check(AuditLog::where('aksi', 'credential.reset')->count() === 1, 'The conflicting operation must not append a mutation audit.');

// A status read must finish even while another connection holds the participant lock.
DB::beginTransaction();
Participant::whereKey($participant->id)->lockForUpdate()->firstOrFail();
$process = proc_open([PHP_BINARY, __FILE__, 'worker'], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
try {
    stream_set_timeout($pipes[1], 10);
    fwrite($pipes[0], json_encode(['token' => $tokens[0], 'method' => 'GET', 'path' => $path, 'data' => []])."\n");
    fflush($pipes[0]);
    check(trim(fgets($pipes[1]) ?: '') === 'READY', 'Status reader did not start.');
    fwrite($pipes[0], "GO\n");
    fflush($pipes[0]);
    $line = trim(fgets($pipes[1]) ?: '');
    check($line !== 'LOCK_REQUEST', 'Status GET must not wait for an exclusive participant lock.');
    $read = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
    check($read[0] === 200 && $read[1]['data']['revision'] === $credential->revision, 'Non-locking read must return a consistent current snapshot.');
} finally {
    DB::rollBack();
    foreach ($pipes as $pipe) {
        fclose($pipe);
    }
    proc_close($process);
}

// Reset and revoke race on the same next revision: one succeeds, one conflicts.
$revision = $credential->revision;
$results = simultaneous([
    ['token' => $tokens[0], 'method' => 'POST', 'path' => $path, 'data' => ['pin' => '007890', 'expected_revision' => $revision]],
    ['token' => $tokens[1], 'method' => 'DELETE', 'path' => $path, 'data' => ['expected_revision' => $revision]],
]);
$codes = array_column($results, 0);
sort($codes);
check($codes === [200, 409] || $codes === [201, 409], 'Reset/revoke race must commit exactly one operation.');
$winner = $results[0][0] === 409 ? 1 : 0;
check($credential->fresh()->revision === $results[$winner][1]['data']['revision'], 'Reset/revoke receipt must identify the committed state.');
check(AuditLog::whereIn('aksi', ['credential.issued', 'credential.reset', 'credential.revoked'])->count() === 3, 'Only committed mutations may append mutation audits.');
foreach ($staff as $actor) {
    $actor->tokens()->delete();
}
echo "PASS: two real MySQL writer processes; reset/reset and reset/revoke barriers, CAS conflict, correct receipt/hash, non-locking status read, session revocation and single mutation audits.\n";
