<?php

namespace App\Auth;

use App\Models\AuditLog;
use App\Models\PatientCredential;
use App\Models\StaffUser;
use Illuminate\Http\Request;

class SecurityAudit
{
    public function record(Request $request, string $action, string $outcome, ?string $entityId = null, ?string $entityType = null): void
    {
        $actor = $request->user();
        AuditLog::create([
            'actor_id' => $actor instanceof StaffUser ? $actor->id : null,
            'aksi' => $action,
            'entity_type' => $entityType ?? ($actor instanceof PatientCredential ? 'patient_credential' : 'security'),
            'entity_id' => $entityId,
            'terjadi_pada' => now(),
            // Fixed server codes only: no request bodies, paths, IPs, emails, PINs or tokens.
            'metadata_redacted' => ['outcome_code' => $outcome],
        ]);
    }
}
