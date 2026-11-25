<?php

use App\Auth\ResearchRoleCode;
use App\Education\EducationCatalog;
use App\Models\AuditLog;
use App\Models\ResearchRole;
use App\Models\StaffUser;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

Artisan::command('telenapas:create-staff', function () {
    // Trusted console operator only; no password argument, environment variable or default account.
    $email = mb_strtolower(trim($this->ask('Staff email')));
    $name = $this->ask('Staff name');
    $role = $this->choice('Research role', array_values(array_map(fn ($role) => $role->value, array_filter(ResearchRoleCode::cases(), fn ($role) => $role !== ResearchRoleCode::Patient))));
    $password = $this->secret('Password (12–72 characters)');
    $confirmation = $this->secret('Confirm password');
    Validator::make(['email' => $email, 'nama' => $name, 'password' => $password, 'password_confirmation' => $confirmation], [
        'email' => 'required|email|max:190|unique:pengguna,email', 'nama' => 'required|string|max:120',
        'password' => ['required', 'string', 'min:12', 'max:72', 'confirmed', function ($attribute, $value, $fail) {
            if (strlen($value) > 72) {
                $fail('The password must not exceed 72 bytes.');
            }
        }],
    ])->validate();
    DB::transaction(function () use ($email, $name, $password, $role) {
        $staff = StaffUser::create(['email' => $email, 'nama' => $name, 'password_hash' => Hash::make($password), 'status' => 'AKTIF']);
        $staff->roles()->attach(ResearchRole::firstOrCreate(['kode' => $role]));
        AuditLog::create(['actor_id' => null, 'aksi' => 'staff.provisioned', 'entity_type' => 'staff', 'entity_id' => (string) $staff->id, 'terjadi_pada' => now(), 'metadata_redacted' => ['outcome_code' => 'CONSOLE_OPERATOR']]);
    });
    $this->info('Staff account provisioned. Credentials are not displayed.');
})->purpose('Provision a staff account through a trusted, interactive console operator');

Artisan::command('telenapas:import-education-drafts', function (EducationCatalog $catalog) {
    if (! app()->environment(['local', 'testing', 'uat']) || DB::connection()->getDriverName() !== 'mysql'
        || ! (DB::connection()->getDatabaseName() === 'pleural_dev' || str_ends_with(DB::connection()->getDatabaseName(), '_test'))) {
        $this->error('Use a local development or disposable UAT MySQL schema.');

        return 1;
    }
    $drafts = json_decode(file_get_contents(resource_path('education/research-baseline.json')), true, flags: JSON_THROW_ON_ERROR);
    DB::transaction(function () use ($drafts, $catalog) {
        foreach ($drafts as $draft) {
            $catalog->validateLinks($draft['references']);
            if (! DB::table('education_topics')->insertOrIgnore(['code' => $draft['code']])) {
                continue;
            }
            $catalog->append((object) ['code' => $draft['code'], 'latest_version' => 0, 'published_version' => null], $draft, 'DRAFT', null);
            AuditLog::create(['actor_id' => null, 'aksi' => 'education.draft_imported', 'entity_type' => 'education_topic',
                'entity_id' => $draft['code'], 'terjadi_pada' => now(), 'metadata_redacted' => ['outcome_code' => 'CONSOLE_OPERATOR']]);
        }
    }, 3);
    $this->info('Missing topics imported as drafts. Existing topics preserved.');
})->purpose('Import the supplied education references as drafts in development or isolated UAT');
