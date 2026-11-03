<?php

namespace App\Auth;

use App\Models\IdentifiedAccessGrant;
use App\Models\Participant;
use App\Models\PatientCredential;
use App\Models\StaffUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;

class ParticipantAccess
{
    public function blinded(Authenticatable $actor): bool
    {
        return config('research_auth.blinded_assessment') && $actor instanceof StaffUser && $actor->hasRole(ResearchRoleCode::Assessor);
    }

    public function researcher(Authenticatable $actor): bool
    {
        // Deny-first: adding another role never gives an IT administrator clinical authority.
        return $actor instanceof StaffUser && $actor->status === 'AKTIF' && ! $this->blinded($actor) && ! $actor->hasRole(ResearchRoleCode::Administrator) && $actor->hasRole(ResearchRoleCode::Researcher);
    }

    public function visible(Authenticatable $actor, Builder $query, AccessGrantScope $scope = AccessGrantScope::ClinicalRead): Builder
    {
        // TECHNICAL is never a patient-data read capability. Contact is independently scoped.
        if ($scope === AccessGrantScope::Technical || ($scope === AccessGrantScope::Contact && $actor instanceof StaffUser && $actor->hasRole(ResearchRoleCode::Assessor))) {
            return $query->whereRaw('1 = 0');
        }
        if ($actor instanceof PatientCredential) {
            if ($actor->revoked_at !== null) {
                return $query->whereRaw('1 = 0');
            }

            return $query->where('id', $actor->responden_id)->where('kelompok', 'INTERVENSI')->where('status', 'AKTIF');
        }
        if (! $actor instanceof StaffUser || $actor->status !== 'AKTIF' || $actor->hasRole(ResearchRoleCode::Patient)) {
            return $query->whereRaw('1 = 0');
        }
        if ($actor->hasRole(ResearchRoleCode::Administrator)) {
            return $query->whereIn('id', $this->grants($actor, $scope)->select('responden_id'));
        }
        if ($actor->hasRole(ResearchRoleCode::Researcher) || $actor->hasRole(ResearchRoleCode::Assessor)) {
            return $query;
        }
        $nurseTypes = [];
        if ($actor->hasRole(ResearchRoleCode::Nurse)) {
            $nurseTypes[] = 'PEMANTAU';
        }
        if ($actor->hasRole(ResearchRoleCode::BackupNurse)) {
            $nurseTypes[] = 'CADANGAN';
        }

        return $query->where('kelompok', 'INTERVENSI')->whereHas('assignments', fn (Builder $assignment) => $assignment
            ->where('pengguna_id', $actor->id)->whereIn('jenis', $nurseTypes)
            ->where('mulai_pada', '<=', now()->format('Y-m-d H:i:s.u'))->where(fn (Builder $end) => $end->whereNull('berakhir_pada')->orWhere('berakhir_pada', '>', now()->format('Y-m-d H:i:s.u'))));
    }

    public function monitoringVisible(Authenticatable $actor, Builder $query, bool $staffOnly = false): Builder
    {
        if ($this->blinded($actor) || ($staffOnly && $actor instanceof PatientCredential)) {
            return $query->whereRaw('1 = 0');
        }

        return $this->visible($actor, $query, AccessGrantScope::ClinicalRead);
    }

    public function canView(Authenticatable $actor, Participant $participant, AccessGrantScope $scope = AccessGrantScope::ClinicalRead): bool
    {
        return $this->visible($actor, Participant::query(), $scope)->whereKey($participant->id)->exists();
    }

    public function grants(StaffUser $actor, AccessGrantScope $scope): Builder
    {
        return IdentifiedAccessGrant::query()->where('pengguna_id', $actor->id)->whereNull('revoked_at')
            ->whereHas('scopes', fn (Builder $binding) => $binding->where('scope', $scope->value))
            ->where('starts_at', '<=', now()->format('Y-m-d H:i:s.u'))->where('expires_at', '>', now()->format('Y-m-d H:i:s.u'))
            // A disabled approver or revoked researcher role invalidates outstanding grants.
            ->whereHas('approver', fn (Builder $staff) => $staff->where('status', 'AKTIF')
                ->whereHas('roles', fn (Builder $role) => $role->where('kode', ResearchRoleCode::Researcher->value))
                ->whereDoesntHave('roles', fn (Builder $role) => $role->where('kode', ResearchRoleCode::Administrator->value))
                ->when(config('research_auth.blinded_assessment'), fn (Builder $staff) => $staff->whereDoesntHave('roles', fn (Builder $role) => $role->where('kode', ResearchRoleCode::Assessor->value))));
    }
}
