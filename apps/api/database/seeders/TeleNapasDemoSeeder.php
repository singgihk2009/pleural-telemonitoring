<?php

namespace Database\Seeders;

use App\Auth\ResearchRoleCode;
use App\Models\AuditLog;
use App\Models\DailyEntry;
use App\Models\Participant;
use App\Models\ParticipantAssignment;
use App\Models\ParticipantContact;
use App\Models\ResearchRole;
use App\Models\StaffUser;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class TeleNapasDemoSeeder extends Seeder
{
    private const MARKER = 'demo.seeded.v1';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing']) || config('telenapas_demo.enabled') !== true) {
            throw new RuntimeException('Demo seeding requires APP_ENV=local/testing and TELENAPAS_DEMO_ENABLED=true.');
        }
        if (DB::getDriverName() !== 'mysql') {
            throw new RuntimeException('Demo seeding requires a dedicated MySQL 8.4 schema.');
        }
        $database = DB::selectOne('SELECT DATABASE() AS name, VERSION() AS version');
        if (! preg_match('/^[a-zA-Z0-9_]+_(demo|test)$/', $database->name ?? '') || ! preg_match('/^8\.4\./', $database->version)) {
            throw new RuntimeException('Demo seeding requires MySQL 8.4 and a dedicated database ending in _demo or _test.');
        }
        // Serialize initializers across processes; no schema/index/trigger changes are needed.
        $lock = 'telenapas.demo.'.substr(hash('sha256', $database->name), 0, 40);
        if ((int) DB::selectOne('SELECT GET_LOCK(?, 5) AS acquired', [$lock])->acquired !== 1) {
            throw new RuntimeException('Another demo initializer is running; retry later.');
        }
        try {
            $created = DB::transaction(function (): bool {
                if (AuditLog::where('aksi', self::MARKER)->where('entity_type', 'development_demo')->exists()) {
                    return false;
                }
                // Never adopt, change or reset an existing research/staff dataset.
                foreach (Schema::getTables() as $table) {
                    if (! in_array($table['name'], ['migrations', 'user_roles', 'auth_rate_limits', 'auth_rate_limit_locks'], true) && DB::table($table['name'])->exists()) {
                        throw new RuntimeException('First demo initialization requires empty application tables. Use a new dedicated schema; no existing data was changed.');
                    }
                }
                $password = $this->password();
                $staff = [];
                foreach ([
                    'researcher' => ResearchRoleCode::Researcher,
                    'nurse' => ResearchRoleCode::Nurse,
                    'backup' => ResearchRoleCode::BackupNurse,
                    'assessor' => ResearchRoleCode::Assessor,
                    'it' => ResearchRoleCode::Administrator,
                ] as $name => $role) {
                    $staff[$name] = StaffUser::factory()->create([
                        'nama' => 'SYNTHETIC DEMO '.strtoupper($name),
                        'email' => $name.'@telenapas-demo.invalid',
                        'password_hash' => Hash::make($password),
                    ]);
                    $staff[$name]->roles()->attach(ResearchRole::firstOrCreate(['kode' => $role->value]));
                    $this->audit('staff.provisioned');
                }
                // No patient credentials, IT grants, clinical catalogs or fake human approvals.
                $anchor = CarbonImmutable::now('Asia/Jakarta')->startOfDay()->subDays(7);
                foreach ([['I-001', 'INTERVENSI', 18], ['I-002', 'INTERVENSI', 32], ['C-001', 'KONTROL', 24], ['C-002', 'KONTROL', 12]] as [$suffix, $group, $baseline]) {
                    $participant = Participant::factory()->create([
                        'kode' => 'SYN-DEMO-'.$suffix, 'kelompok' => $group,
                        'tanggal_pulang' => $anchor->toDateString(), 'vas_baseline_mm' => $baseline,
                        'tanggal_consent' => $anchor->toDateString(), 'versi_consent' => 'SYNTHETIC-DEMO-v1',
                        'status' => $suffix === 'C-002' ? 'DROP_OUT' : 'AKTIF',
                        'alasan_drop_out' => $suffix === 'C-002' ? 'SYNTHETIC DEMO withdrawal; not a research outcome.' : null,
                    ]);
                    ParticipantContact::factory()->create([
                        'responden_id' => $participant->id, 'inisial_encrypted' => 'SYN-DEMO',
                        'no_hp_pasien_encrypted' => 'SYNTHETIC-NOT-A-PHONE',
                        'no_hp_keluarga_encrypted' => 'SYNTHETIC-FAMILY-NOT-A-PHONE',
                    ]);
                    $this->audit('participant.enrolled', $staff['researcher']);
                    if ($group !== 'INTERVENSI') {
                        continue;
                    }
                    $nurse = $staff[$suffix === 'I-001' ? 'nurse' : 'backup'];
                    ParticipantAssignment::factory()->create([
                        'responden_id' => $participant->id, 'pengguna_id' => $nurse->id,
                        'jenis' => $suffix === 'I-001' ? 'PEMANTAU' : 'CADANGAN',
                        'mulai_pada' => $anchor->utc(),
                    ]);
                    $this->audit('demo.assignment.created', $staff['researcher']);
                    // Wholly fabricated raw observations, never classified or scored.
                    foreach (($suffix === 'I-001' ? [18, 22, 20] : [34, 31, 33]) as $index => $vas) {
                        $date = $anchor->addDays($index + 1);
                        $filled = $date->setTime(8, 30)->utc();
                        DailyEntry::factory()->create([
                            'responden_id' => $participant->id, 'tanggal_wib' => $date->toDateString(),
                            'vas_mm' => $vas, 'diisi_oleh' => $index === 1 ? 'KELUARGA' : 'PASIEN',
                            'dibuat_di_perangkat_pada' => $filled, 'diterima_server_pada' => $filled->addMinute(),
                        ]);
                        $this->audit('demo.entry.created', $staff['researcher']);
                    }
                }
                $this->audit(self::MARKER, $staff['researcher']);

                return true;
            });
        } finally {
            DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$lock]);
        }
        $this->command?->info($created
            ? 'Synthetic demo v1 created: 5 staff, 4 participants, 4 encrypted contacts, 2 assignments, 6 raw observations. Credentials are not displayed.'
            : 'Synthetic demo v1 already initialized. No records, roles, passwords, dates or audit rows were changed.');
        $this->command?->warn('No clinical results, alerts, follow-ups, outcomes, questionnaires, patient PINs or notifications are seeded.');
    }

    private function password(): string
    {
        $path = config('telenapas_demo.password_file');
        if (! is_string($path) || ! is_file($path) || is_link($path) || ! is_readable($path) || ((@fileperms($path) ?: 0) & 0077) !== 0 || filesize($path) > 74) {
            throw new RuntimeException('Provide TELENAPAS_DEMO_PASSWORD_FILE as a private regular file (chmod 600), containing a password of at least 12 characters and at most 72 bytes.');
        }
        $raw = @file_get_contents($path);
        $password = is_string($raw) ? rtrim($raw, "\r\n") : '';
        if (! mb_check_encoding($password, 'UTF-8') || mb_strlen($password) < 12 || strlen($password) > 72 || str_contains($password, "\n") || str_contains($password, "\r") || str_contains($password, "\0")) {
            throw new RuntimeException('The private demo password file must contain one password of at least 12 characters and at most 72 bytes.');
        }

        return $password;
    }

    private function audit(string $action, ?StaffUser $actor = null): void
    {
        AuditLog::create([
            'actor_id' => $actor?->id, 'aksi' => $action, 'entity_type' => 'development_demo',
            'entity_id' => null, 'terjadi_pada' => now(),
            'metadata_redacted' => ['outcome_code' => 'SYNTHETIC_DEMO_V1'],
        ]);
    }
}
