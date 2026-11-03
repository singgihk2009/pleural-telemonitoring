<?php

namespace App\Auth;

enum AccessGrantScope: string
{
    case Technical = 'TECHNICAL';
    case Contact = 'CONTACT';
    case ClinicalRead = 'CLINICAL_READ';
}
