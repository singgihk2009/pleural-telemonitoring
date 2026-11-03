<?php

namespace App\Auth;

enum ResearchRoleCode: string
{
    case Patient = 'PASIEN_KELUARGA';
    case Nurse = 'PERAWAT_PEMANTAU';
    case BackupNurse = 'PERAWAT_CADANGAN';
    case Researcher = 'PENELITI_UTAMA';
    case Assessor = 'PENILAI_HASIL';
    case Administrator = 'ADMIN_IT';
}
