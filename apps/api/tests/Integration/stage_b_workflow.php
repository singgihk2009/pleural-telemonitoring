<?php

// Explicit opt-in synthetic integration UAT after B1/B2/B3 have been reviewed together.
// Not discovered by PHPUnit and never publishes a deployment instrument.
use App\Http\Controllers\InstrumentPublicationController;
use App\Models\AuditLog;
use App\Models\DailyEntry;
use App\Models\Participant;
use App\Models\PatientCredential;
use App\Models\ResearchRole;
use App\Models\ResearchSetting;
use App\Models\StaffUser;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
function verify(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
}
function callStageB(?string $token, string $method, string $path, array $body = []): array
{
    global $kernel;
    Auth::forgetGuards();
    $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
    if ($token) {
        $headers['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
    }
    $request = Request::create('http://localhost/api/v1'.$path, $method, [], [], [], $headers, json_encode($body, JSON_THROW_ON_ERROR));
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);

    return [$response->getStatusCode(), json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR)];
}
verify(getenv('TELENAPAS_STAGE_B_UAT') === '1' && app()->environment('testing') && DB::getDriverName() === 'mysql'
    && preg_match('/^[a-z0-9_]+_stage_b_integration_test$/', DB::connection()->getDatabaseName()), 'Explicit opt-in, testing and a dedicated _stage_b_integration_test MySQL schema required.');
verify(Schema::hasTable('instrument_publications') && class_exists(InstrumentPublicationController::class), 'B1 publication implementation must be present.');
verify(Participant::count() === 0 && ResearchSetting::count() === 0, 'Use a new empty fixture schema, never reset existing data.');
config(['app.debug' => false]);
$clock = CarbonImmutable::parse('2026-11-08T02:00:00Z');
Date::setTestNow($clock);
CarbonImmutable::setTestNow($clock);
DB::beginTransaction();
try {
    $staff = StaffUser::factory()->create();
    $staff->roles()->attach(ResearchRole::firstOrCreate(['kode' => 'PENELITI_UTAMA']));
    $staffToken = $staff->createToken('synthetic-stage-b-uat', ['api'], now()->addHour())->plainTextToken;
    $participant = Participant::factory()->create(['tanggal_pulang' => '2026-11-07']);
    $pin = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    PatientCredential::factory()->create(['responden_id' => $participant->id, 'pin_hash' => $pin]);
    [$status, $login] = callStageB(null, 'POST', '/auth/patient/login', ['code' => $participant->kode, 'pin' => $pin]);
    verify($status === 200, 'Patient login failed.');
    $token = $login['access_token'];
    [$status, $me] = callStageB($token, 'GET', '/auth/me');
    verify($status === 200 && is_string($me['data']['credential_revision'] ?? null), 'B2 patient credential revision missing.');
    [$status, $unavailable] = callStageB($token, 'GET', '/patient/assessments/status');
    verify($status === 200 && $unavailable['data']['available'] === false, 'Absent instrument must remain unavailable.');
    $definition = ['version' => 'SYNTHETIC_STAGE_B_UAT', 'expected_current_version' => null, 'confirm_publication' => true,
        'approval_reference' => 'SYNTHETIC_TEST_ONLY', 'effective_at' => $clock->addMinute()->format('Y-m-d\TH:i:s.v\Z'),
        'expires_at' => $clock->addMinutes(2)->format('Y-m-d\TH:i:s.v\Z'),
        'schedule' => ['timezone' => 'Asia/Jakarta', 'first_day' => 1, 'last_day' => 30, 'late_policy' => 'same_day_only'],
        'questions' => [['code' => 'SYNTHETIC_ITEM', 'label' => 'Synthetic test only, not clinical', 'required' => true,
            'options' => [['value' => 'SYNTHETIC_A', 'label' => 'Synthetic option A']]]]];
    [$status] = callStageB($staffToken, 'POST', '/research/daily-instruments/publish', $definition);
    verify($status === 201, 'Explicit synthetic publication failed.');
    Date::setTestNow($clock->addSeconds(61));
    CarbonImmutable::setTestNow($clock->addSeconds(61));
    [$status, $available] = callStageB($token, 'GET', '/patient/assessments/status');
    verify($status === 200 && $available['data']['can_submit'] === true, 'Explicitly effective instrument unavailable.');
    $current = $available['data'];
    $body = ['submission_id' => $current['submission_id'], 'version' => $current['questionnaire']['version'],
        'date_wib' => $current['date_wib'], 'vas_mm' => 100, 'filled_by' => 'KELUARGA',
        'device_created_at' => now()->utc()->format('Y-m-d\TH:i:s.v\Z'),
        'answers' => [['code' => 'SYNTHETIC_ITEM', 'option_id' => $current['questionnaire']['questions'][0]['options'][0]['id']]]];
    [$status] = callStageB($token, 'POST', '/patient/assessments', $body);
    verify($status === 201, 'Published-instrument ingestion failed.');
    // Treat response as lost. Reconciliation uses exact own UUID, then verifies replay.
    [$status, $history] = callStageB($token, 'GET', '/patient/assessments?submission_id='.$body['submission_id']);
    verify($status === 200 && $history['meta']['total'] === 1 && $history['data'][0]['id'] === $body['submission_id'], 'B2 exact UUID reconciliation failed.');
    [$status, $replay] = callStageB($token, 'POST', '/patient/assessments', $body);
    verify($status === 200 && $replay['replayed'] === true, 'Identical replay failed.');
    [$status, $monitoring] = callStageB($staffToken, 'GET', '/participants/'.$participant->id.'/assessments?from='.$body['date_wib'].'&to='.$body['date_wib'].'&type=HARIAN');
    verify($status === 200 && $monitoring['meta']['total'] === 1 && $monitoring['meta']['filters']['type'] === 'HARIAN'
        && $monitoring['data'][0]['answers'][0]['answer_label'] === 'Synthetic option A', 'B3 filtered historical projection failed.');
    Date::setTestNow($clock->addSeconds(121));
    CarbonImmutable::setTestNow($clock->addSeconds(121));
    [$status, $expired] = callStageB($token, 'GET', '/patient/assessments/status');
    verify($status === 200 && $expired['data']['available'] === false, 'Expired instrument must fail closed.');
    [$status, $replay] = callStageB($token, 'POST', '/patient/assessments', $body);
    verify($status === 200 && $replay['replayed'] === true, 'Original receipt must survive publication expiry.');
    [$status] = callStageB($token, 'POST', '/patient/assessments', array_replace($body, ['submission_id' => (string) Str::uuid()]));
    verify($status === 409, 'No new submission may bypass expired instrument.');
    verify(DailyEntry::count() === 1 && AuditLog::where('aksi', 'assessment.submitted')->count() === 1
        && AuditLog::where('aksi', 'instrument.published')->count() === 1, 'Duplicate entry or mutation audit.');
    foreach (['klasifikasi', 'peringatan', 'notification_deliveries'] as $table) {
        verify(DB::table($table)->count() === 0, 'Unapproved clinical side effect.');
    }
    [$status] = callStageB($token, 'POST', '/auth/logout');
    verify($status === 200, 'Logout failed.');
    [$status] = callStageB($token, 'GET', '/auth/me');
    verify($status === 401, 'Logged-out token remained usable.');
    echo "PASS: explicit publication → patient login/revision → ingestion → exact UUID reconciliation/replay → scoped dashboard filters/labels → expiry fail-closed → logout; one entry/audit and zero clinical effects.\n";
} catch (Throwable $error) {
    $failed = true;
    fwrite(STDERR, 'FAIL: '.(get_class($error) === RuntimeException::class ? $error->getMessage() : 'Unexpected integration error; details suppressed to protect credentials.').PHP_EOL);
} finally {
    DB::rollBack();
    Date::setTestNow();
    CarbonImmutable::setTestNow();
}

if ($failed ?? false) {
    exit(1);
}
