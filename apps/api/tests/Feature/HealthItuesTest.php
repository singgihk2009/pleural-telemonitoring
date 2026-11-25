<?php

namespace Tests\Feature;

use App\Models\PatientCredential;
use App\Models\ResearchRole;
use App\Models\StaffUser;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class HealthItuesTest extends TestCase
{
    public function test_version_one_catalog_is_pinned_to_the_reviewed_transcription(): void
    {
        $this->assertSame('e4c1b69e69931dc223188a726cafc91ff4aaf6386049fe67770a94352b377e39',
            hash_file('sha256', resource_path('instruments/health-itues-v1.0.json')));
    }

    public function test_content_review_is_audited_and_patient_preview_is_isolated(): void
    {
        if (DB::getDriverName() !== 'mysql' || ! str_ends_with(DB::connection()->getDatabaseName(), '_test')) {
            $this->markTestSkipped('Requires a disposable MySQL test schema.');
        }

        DB::beginTransaction();
        try {
            $review = '/api/v1/research/health-itues/instrument';
            $status = '/api/v1/patient/health-itues/status';
            $staff = StaffUser::factory()->create();
            $staff->roles()->attach(ResearchRole::firstOrCreate(['kode' => 'PENELITI_UTAMA']));
            $this->getJson($review)->assertUnauthorized();
            $this->getJson($review, $this->headers($staff))->assertOk()->assertHeader('Cache-Control', 'no-store, private')
                ->assertJsonPath('data.version', '1.0')->assertJsonCount(4, 'data.subscales')
                ->assertJsonPath('data.subscales.0.items.0.statement', 'Menggunakan [Sistem/Aplikasi] ini dapat meningkatkan kualitas layanan kesehatan yang saya berikan.')
                ->assertJsonPath('data.subscales.3.items.4.code', 'S20')->assertJsonMissingPath('data.scoring');
            $this->assertDatabaseHas('audit_log', ['aksi' => 'health_itues.reviewed', 'entity_id' => '1.0', 'actor_id' => $staff->id]);
            $staff->roles()->attach(ResearchRole::firstOrCreate(['kode' => 'ADMIN_IT']));
            $this->getJson($review, $this->headers($staff))->assertForbidden();
            $this->getJson($status, $this->headers($staff))->assertForbidden();

            $patient = PatientCredential::factory()->create();
            $this->getJson($review, $this->headers($patient))->assertForbidden();
            $this->getJson($status, $this->headers($patient))->assertOk()->assertJsonPath('data.can_submit', false)->assertJsonPath('data.preview', null);
            $connection = DB::connection();
            $originalName = $connection->getDatabaseName();
            $originalEnv = app()->environment();
            $originalFlag = config('assessment.synthetic_uat');
            try {
                app()->instance('env', 'uat');
                config(['assessment.synthetic_uat' => true]);
                $this->getJson($status, $this->headers($patient))->assertOk()->assertJsonPath('data.preview', null);
                // Simulate the isolated schema name without changing the test PDO connection.
                $connection->setDatabaseName('telenapas_synthetic_uat_test');
                $response = $this->getJson($status, $this->headers($patient))->assertOk()
                    ->assertJsonPath('data.can_submit', false)->assertJsonCount(4, 'data.preview.subscales');
                $this->assertSame(array_map(fn ($n) => sprintf('S%02d', $n), range(1, 20)),
                    collect($response->json('data.preview.subscales'))->flatMap(fn ($group) => array_column($group['items'], 'code'))->all());
            } finally {
                $connection->setDatabaseName($originalName);
                app()->instance('env', $originalEnv);
                config(['assessment.synthetic_uat' => $originalFlag]);
            }

            $this->assertDatabaseCount('health_itues_versions', 0);
            $this->assertDatabaseCount('health_itues_responses', 0);

            $fixture = '{"label":"SYNTHETIC STORAGE TEST"}';
            DB::table('health_itues_versions')->insert(['version' => 'synthetic-storage', 'definition' => $fixture, 'definition_sha256' => hash('sha256', $fixture)]);
            $id = Str::uuid()->toString();
            $entry = ['submission_id' => $id, 'version' => 'synthetic-storage', 'answers' => '{"S01":"SYNTHETIC"}', 'received_at' => now()];
            DB::table('health_itues_responses')->insert($entry);
            $checks = [
                [fn () => DB::table('health_itues_responses')->insert($entry), '23000'],
                [fn () => DB::table('health_itues_responses')->insert(array_replace($entry, [
                    'submission_id' => Str::uuid()->toString(), 'version' => 'unregistered',
                ])), '23000'],
                [fn () => DB::table('health_itues_versions')->where('version', 'synthetic-storage')->update(['definition' => '{}']), '45000'],
                [fn () => DB::table('health_itues_responses')->where('submission_id', $id)->delete(), '45000'],
            ];
            foreach ($checks as [$operation, $sqlState]) {
                try {
                    $operation();
                    $this->fail('Storage accepted an invalid operation.');
                } catch (QueryException $exception) {
                    $this->assertSame($sqlState, $exception->errorInfo[0]);
                }
            }
        } finally {
            DB::rollBack();
        }
    }

    private function headers($actor): array
    {
        Auth::forgetGuards();

        return ['Authorization' => 'Bearer '.$actor->createToken('synthetic-health-itues', ['api'], now()->addHour())->plainTextToken];
    }
}
