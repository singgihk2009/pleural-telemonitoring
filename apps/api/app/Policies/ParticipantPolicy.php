<?php

namespace App\Policies;

use App\Auth\ParticipantAccess;
use App\Auth\ResearchRoleCode;
use App\Models\Participant;
use App\Models\PatientCredential;
use App\Models\StaffUser;
use Illuminate\Contracts\Auth\Authenticatable;

class ParticipantPolicy
{
    public function __construct(private ParticipantAccess $access) {}

    public function view(Authenticatable $actor, Participant $participant): bool
    {
        return $this->access->canView($actor, $participant);
    }

    public function viewMonitoring(Authenticatable $actor, Participant $participant): bool
    {
        return ! $this->access->blinded($actor) && $this->view($actor, $participant);
    }

    public function viewContact(Authenticatable $actor, Participant $participant): bool
    {
        return ! ($actor instanceof StaffUser && $actor->hasRole(ResearchRoleCode::Assessor)) && $this->view($actor, $participant);
    }

    public function viewFollowUps(Authenticatable $actor, Participant $participant): bool
    {
        return ! $actor instanceof PatientCredential && $this->viewMonitoring($actor, $participant);
    }

    public function manageCredentials(Authenticatable $actor, Participant $participant): bool
    {
        return $this->access->researcher($actor) && $participant->kelompok === 'INTERVENSI' && $participant->status === 'AKTIF';
    }

    public function grantIdentifiedAccess(Authenticatable $actor, Participant $participant): bool
    {
        return $this->access->researcher($actor) && ! $this->access->blinded($actor);
    }
}
