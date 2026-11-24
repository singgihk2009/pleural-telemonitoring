<?php

namespace App\Assessment;

use Illuminate\Support\Facades\DB;

final class SyntheticUatPublication
{
    private const PREFIX = 'SYNTHETIC-UAT';

    private const REFERENCE = 'SYNTHETIC-UAT-TECHNICAL-ONLY';

    public static function allows(string $version, ?string $reference, array $questionCodes): bool
    {
        $uatVersion = str_starts_with($version, self::PREFIX.'-');
        $uatReference = $reference === self::REFERENCE;
        $uatQuestionCount = count(array_filter($questionCodes,
            fn ($code) => is_string($code) && str_starts_with(strtoupper($code), 'SYNTHETIC_UAT_')
        ));

        if (app()->environment('uat')) {
            return config('assessment.synthetic_uat') === true
                && DB::connection()->getDriverName() === 'mysql'
                && DB::connection()->getDatabaseName() === 'telenapas_synthetic_uat_test'
                && $uatVersion && $uatReference && $uatQuestionCount > 0 && $uatQuestionCount === count($questionCodes);
        }

        return $uatQuestionCount === 0
            && ! str_starts_with(strtoupper($version), self::PREFIX)
            && ! str_starts_with(strtoupper($reference ?? ''), self::PREFIX);
    }
}
