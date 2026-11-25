<?php

namespace Tests\Feature;

use App\Models\PatientCredential;
use App\Models\ResearchRole;
use App\Models\StaffUser;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EducationCatalogTest extends TestCase
{
    public function test_catalog_import_review_versioning_publication_and_archival(): void
    {
        if (DB::getDriverName() !== 'mysql' || ! str_ends_with(DB::connection()->getDatabaseName(), '_test')) {
            $this->markTestSkipped('Requires a disposable MySQL schema.');
        }
        DB::beginTransaction();
        try {
            $staff = StaffUser::factory()->create();
            $staff->roles()->attach(ResearchRole::firstOrCreate(['kode' => 'PENELITI_UTAMA']));
            $patient = PatientCredential::factory()->create();
            $research = '/api/v1/research/education';
            $public = '/api/v1/patient/education';
            $this->artisan('telenapas:import-education-drafts')->assertExitCode(0);
            $this->getJson($research, $this->headers($staff))->assertOk()->assertJsonCount(4, 'data');
            $this->getJson($public, $this->headers($patient))->assertOk()->assertJsonCount(0, 'data');
            $this->getJson($public.'/EDU-02', $this->headers($patient))->assertNotFound();
            $this->getJson($research.'/EDU-02', $this->headers($patient))->assertForbidden();

            $draft = $this->getJson($research.'/EDU-02', $this->headers($staff))->assertOk()->json('data');
            $this->assertSame('https://youtu.be/QDgrjQTLdB0?si=2jHEYWwPSG03reOS', $draft['references'][3]['url']);
            $content = ['title' => $draft['title'], 'description' => 'Deskripsi untuk tes sintetis.', 'display_order' => 2,
                'references' => $draft['references'], 'expected_version' => 1];
            $this->putJson($research.'/EDU-02', $content, $this->headers($patient))->assertForbidden();
            $bad = $content;
            foreach (['javascript:alert(1)', 'https://youtu.be.example.com/QDgrjQTLdB0', 'https://www.youtube.com/watch?v=QDgrjQTLdB0&v=AAAAAAAAAAA'] as $url) {
                $bad['references'][3]['url'] = $url;
                $this->putJson($research.'/EDU-02', $bad, $this->headers($staff))->assertUnprocessable();
            }
            $this->putJson($research.'/EDU-02', $content, $this->headers($staff))->assertOk()->assertJsonPath('data.version', 2);
            $this->putJson($research.'/EDU-02', $content, $this->headers($staff))->assertConflict();
            $this->artisan('telenapas:import-education-drafts')->assertExitCode(0);
            $this->getJson($research.'/EDU-02', $this->headers($staff))->assertOk()->assertJsonPath('data.version', 2);
            $this->postJson($research.'/EDU-02/publish', ['expected_version' => 2, 'approval_reference' => 'SYNTHETIC_TEST'], $this->headers($staff))->assertUnprocessable();
            $publish = ['expected_version' => 2, 'approval_reference' => 'SYNTHETIC_TEST', 'confirm' => true];
            $this->postJson($research.'/EDU-02/publish', $publish, $this->headers($staff))->assertOk();
            $this->getJson($public.'/EDU-02', $this->headers($patient))->assertOk()->assertJsonPath('data.has_video', true)
                ->assertJsonPath('data.references.3.url', $draft['references'][3]['url'])->assertJsonMissingPath('data.actor_id');

            $this->postJson($research, ['code' => 'EDU-TEST', 'title' => 'Topik sintetis', 'description' => 'Uji urutan.',
                'display_order' => 1, 'references' => [['type' => 'ARTICLE', 'label' => 'Uji', 'url' => 'https://www.example.com/material']]], $this->headers($staff))->assertCreated();
            $this->postJson($research.'/EDU-TEST/publish', array_replace($publish, ['expected_version' => 1]), $this->headers($staff))->assertOk();
            $this->getJson($public, $this->headers($patient))->assertOk()->assertJsonPath('data.0.code', 'EDU-TEST')->assertJsonPath('data.1.code', 'EDU-02');

            $this->putJson($research.'/EDU-02', array_replace($content, ['expected_version' => 3, 'title' => 'Perubahan sintetis']), $this->headers($staff))->assertOk();
            $this->getJson($public.'/EDU-02', $this->headers($patient))->assertOk()->assertJsonPath('data.title', $draft['title']);
            $this->postJson($research.'/EDU-02/archive', ['expected_version' => 4, 'confirm' => true], $this->headers($staff))->assertOk();
            $this->getJson($public.'/EDU-02', $this->headers($patient))->assertNotFound();
            $this->getJson($research.'/EDU-02?version=3', $this->headers($staff))->assertOk()
                ->assertJsonPath('data.status', 'PUBLISHED')->assertJsonCount(5, 'data.history');
            $followUp = '/EDU-04';
            $short = $this->getJson($research.$followUp, $this->headers($staff))->assertOk()->json('data.references.0');
            $update = ['expected_version' => 1, 'title' => 'Topik untuk tes tautan pendek',
                'description' => 'Deskripsi sintetis.', 'display_order' => 4, 'references' => [$short]];
            $this->putJson($research.$followUp, $update, $this->headers($staff))->assertOk();
            $this->postJson($research.$followUp.'/publish', $publish, $this->headers($staff))->assertUnprocessable();
            $update['expected_version'] = 2;
            $update['references'][0]['canonical_url'] = 'https://www.example.com/reviewed';
            $this->putJson($research.$followUp, $update, $this->headers($staff))->assertOk();
            $this->postJson($research.$followUp.'/publish', array_replace($publish, ['expected_version' => 3]), $this->headers($staff))->assertOk();
            $this->getJson($public.$followUp, $this->headers($patient))->assertOk()
                ->assertJsonPath('data.references.0.url', 'https://www.example.com/reviewed');
            $this->getJson($research.$followUp, $this->headers($staff))->assertOk()
                ->assertJsonPath('data.references.0.url', 'https://share.google/8F7HRhBspoyXaj19q');
            $this->assertDatabaseHas('audit_log', ['aksi' => 'education.published', 'entity_id' => 'EDU-02']);
            $this->assertDatabaseHas('audit_log', ['aksi' => 'education.archived', 'entity_id' => 'EDU-02']);
            $staff->roles()->attach(ResearchRole::firstOrCreate(['kode' => 'ADMIN_IT']));
            $this->postJson($research.'/EDU-02/publish', $publish, $this->headers($staff))->assertForbidden();
            try {
                DB::table('education_revisions')->where('topic_code', 'EDU-02')->update(['title' => 'Changed']);
                $this->fail('Revision should be immutable.');
            } catch (QueryException $exception) {
                $this->assertSame('45000', $exception->errorInfo[0]);
            }
        } finally {
            DB::rollBack();
        }
    }

    private function headers($actor): array
    {
        Auth::forgetGuards();

        return ['Authorization' => 'Bearer '.$actor->createToken('synthetic-education', ['api'], now()->addHour())->plainTextToken];
    }
}
