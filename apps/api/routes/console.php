<?php

use App\Auth\ResearchRoleCode;
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
