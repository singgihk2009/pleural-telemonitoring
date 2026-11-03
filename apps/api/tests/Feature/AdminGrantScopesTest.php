<?php

namespace Tests\Feature;

use App\Auth\AccessGrantScope;
use App\Auth\ParticipantAccess;
use App\Auth\ResearchRoleCode;
use App\Models\Alert;
use App\Models\ClassificationRecord;
use App\Models\DailyEntry;
use App\Models\FollowUp;
use App\Models\IdentifiedAccessGrant;
use App\Models\Outcome;
use App\Models\Participant;
use App\Models\ParticipantContact;
use App\Models\ResearchRole;
use App\Models\StaffUser;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminGrantScopesTest extends TestCase
{
    private bool $transactionStarted = false;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Grant scope security tests require disposable MySQL.');
        }
        if (! preg_match('/^[a-z0-9_]+_test$/', DB::connection()->getDatabaseName())) {
            throw new \RuntimeException('Use a dedicated disposable database ending in _test.');
        }
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        DB::beginTransaction();
        $this->transactionStarted = true;
        config(['research_auth.blinded_assessment' => true]);
    }

    protected function tearDown(): void
    {
        if ($this->transactionStarted) {
            DB::rollBack();
        }
        parent::tearDown();
    }

    private function staff(ResearchRoleCode ...$roles): StaffUser
    {
        $staff = StaffUser::factory()->create();
        foreach ($roles as $role) {
            $staff->roles()->attach(ResearchRole::firstOrCreate(['kode' => $role->value]));
        }

        return $staff;
    }

    private function callAs(StaffUser $actor, string $method, string $path, array $data = [])
    {
        Auth::forgetGuards();
        $token = $actor->createToken('synthetic-scope-test', ['api'], now()->addHour())->plainTextToken;

        return $this->json($method, '/api/v1'.$path, $data, ['Authorization' => 'Bearer '.$token]);
    }

    private function fixture(): Participant
    {
        $entry = DailyEntry::factory()->create();
        $participant = $entry->participant;
        ParticipantContact::factory()->create(['responden_id' => $participant->id]);
        ClassificationRecord::factory()->create(['entri_id' => $entry->id]);
        $alert = Alert::factory()->create(['responden_id' => $participant->id, 'entri_id' => $entry->id]);
        FollowUp::factory()->create(['peringatan_id' => $alert->id]);
        Outcome::factory()->create(['responden_id' => $participant->id]);

        return $participant;
    }

    private function grant(StaffUser $pi, StaffUser $admin, Participant $participant, array $scopes): IdentifiedAccessGrant
    {
        $grant = IdentifiedAccessGrant::create([
            'pengguna_id' => $admin->id, 'responden_id' => $participant->id, 'approved_by' => $pi->id,
            'purpose_code' => 'SYNTHETIC_TEST', 'starts_at' => now()->subHour(), 'expires_at' => now()->addHour(),
        ]);
        foreach ($scopes as $scope) {
            $grant->scopes()->create(['scope' => $scope]);
        }

        return $grant;
    }

    #[DataProvider('scopeCombinations')]
    public function test_scope_matrix_denies_every_unapproved_resource_and_cross_participant_read(array $scopes): void
    {
        $pi = $this->staff(ResearchRoleCode::Researcher);
        // An additional researcher role must not bypass ADMIN_IT scope checks.
        $admin = $this->staff(ResearchRoleCode::Administrator, ResearchRoleCode::Researcher);
        $participant = $this->fixture();
        $other = $this->fixture();
        if ($scopes === []) {
            $this->grant($pi, $admin, $participant, []); // Legacy approval without explicit scopes.
        } else {
            $expected = $scopes;
            sort($expected);
            $this->callAs($pi, 'POST', '/participants/'.$participant->id.'/identified-access', [
                'staff_id' => $admin->id, 'scopes' => $scopes, 'purpose_code' => 'SYNTHETIC_TEST', 'expires_at' => now()->addHour()->toISOString(),
            ])->assertCreated()->assertJsonPath('data.scopes', $expected);
            $this->assertDatabaseHas('audit_log', ['aksi' => 'grant.scopes.approved', 'actor_id' => $pi->id]);
        }
        $clinical = in_array('CLINICAL_READ', $scopes, true);
        $contact = in_array('CONTACT', $scopes, true);
        foreach (['', '/entries', '/classifications', '/alerts', '/follow-ups', '/outcome', '/contact'] as $suffix) {
            $allowed = $suffix === '/contact' ? $contact : $clinical;
            $response = $this->callAs($admin, 'GET', '/participants/'.$participant->id.$suffix);
            if ($allowed) {
                $response->assertOk();
                if ($suffix === '/contact') {
                    $response->assertJsonPath('data.patient_contact', 'SYNTHETIC-NOT-A-PHONE');
                } elseif ($suffix === '') {
                    $response->assertJsonPath('data.id', $participant->id);
                } elseif ($suffix === '/outcome') {
                    $response->assertJsonPath('data.id', $participant->outcome->id);
                } else {
                    $response->assertJsonCount(1, 'data');
                }
            } else {
                $response->assertForbidden();
            }
            $this->callAs($admin, 'GET', '/participants/'.$other->id.$suffix)->assertForbidden();
        }
        $this->callAs($admin, 'GET', '/participants')->assertOk()->assertJsonCount($clinical ? 1 : 0, 'data')->assertJsonPath('meta.total', $clinical ? 1 : 0);
        $access = app(ParticipantAccess::class);
        $this->assertSame($clinical ? [$participant->id] : [], $access->visible($admin, Participant::query())->pluck('id')->all());
        $this->assertSame($contact ? [$participant->id] : [], $access->visible($admin, Participant::query(), AccessGrantScope::Contact)->pluck('id')->all());
        $this->assertSame(0, $access->visible($admin, Participant::query(), AccessGrantScope::Technical)->count());
    }

    public static function scopeCombinations(): array
    {
        return [
            'legacy unscoped' => [[]],
            'technical only' => [['TECHNICAL']],
            'contact only' => [['CONTACT']],
            'clinical only' => [['CLINICAL_READ']],
            'technical and contact' => [['TECHNICAL', 'CONTACT']],
            'technical and clinical' => [['TECHNICAL', 'CLINICAL_READ']],
            'contact and clinical' => [['CONTACT', 'CLINICAL_READ']],
            'all scopes' => [['TECHNICAL', 'CONTACT', 'CLINICAL_READ']],
        ];
    }

    #[DataProvider('singleScopes')]
    public function test_resource_queries_enforce_scopes_independently_of_policy_results(string $scope): void
    {
        $pi = $this->staff(ResearchRoleCode::Researcher);
        $admin = $this->staff(ResearchRoleCode::Administrator);
        $participant = $this->fixture();
        $this->grant($pi, $admin, $participant, [$scope]);
        // Simulate a mistaken future broad policy allowance: SQL must still withhold data.
        Gate::before(fn () => true);
        $clinical = $scope === 'CLINICAL_READ';
        $contact = $scope === 'CONTACT';
        $this->callAs($admin, 'GET', '/participants/'.$participant->id)->assertStatus($clinical ? 200 : 404);
        $this->callAs($admin, 'GET', '/participants/'.$participant->id.'/contact')->assertOk()->assertJsonPath('data', $contact ? [
            'initials' => 'SYNTHETIC', 'patient_contact' => 'SYNTHETIC-NOT-A-PHONE', 'family_contact' => null,
        ] : null);
        foreach (['entries', 'classifications', 'alerts', 'follow-ups'] as $resource) {
            $this->callAs($admin, 'GET', '/participants/'.$participant->id.'/'.$resource)->assertOk()->assertJsonCount($clinical ? 1 : 0, 'data')->assertJsonPath('meta.total', $clinical ? 1 : 0);
        }
        $outcome = $this->callAs($admin, 'GET', '/participants/'.$participant->id.'/outcome')->assertOk();
        if ($clinical) {
            $outcome->assertJsonPath('data.id', $participant->outcome->id);
        } else {
            $outcome->assertJsonPath('data', null);
        }
    }

    public static function singleScopes(): array
    {
        return [['TECHNICAL'], ['CONTACT'], ['CLINICAL_READ']];
    }

    #[DataProvider('invalidScopes')]
    public function test_scope_approval_requires_an_explicit_valid_nonempty_set(mixed $scopes): void
    {
        $pi = $this->staff(ResearchRoleCode::Researcher);
        $admin = $this->staff(ResearchRoleCode::Administrator);
        $participant = Participant::factory()->create();
        $body = ['staff_id' => $admin->id, 'purpose_code' => 'SYNTHETIC_TEST', 'expires_at' => now()->addHour()->toISOString()];
        if ($scopes !== 'OMITTED') {
            $body['scopes'] = $scopes;
        }
        $this->callAs($pi, 'POST', '/participants/'.$participant->id.'/identified-access', $body)->assertUnprocessable();
        $this->assertSame(0, IdentifiedAccessGrant::count());
        $this->assertSame(0, DB::table('identified_access_grant_scopes')->count());
    }

    public static function invalidScopes(): array
    {
        return [
            ['OMITTED'], [null], [[]], ['TECHNICAL'], [['technical']], [['ALL']],
            [['CONTACT', 'CONTACT']], [['TECHNICAL', 'CONTACT', 'CLINICAL_READ', 'ALL']],
            [['named' => 'CONTACT']], [[null]], [[['CONTACT']]],
        ];
    }

    public function test_scope_bindings_have_database_checks_unique_keys_and_foreign_keys(): void
    {
        $pi = $this->staff(ResearchRoleCode::Researcher);
        $admin = $this->staff(ResearchRoleCode::Administrator);
        $grant = $this->grant($pi, $admin, Participant::factory()->create(), ['TECHNICAL']);
        foreach ([['grant_id' => $grant->id, 'scope' => 'technical'], ['grant_id' => $grant->id, 'scope' => 'ALL'], ['grant_id' => $grant->id, 'scope' => 'CONTACT '], ['grant_id' => $grant->id, 'scope' => 'TECHNICAL'], ['grant_id' => PHP_INT_MAX, 'scope' => 'CONTACT']] as $invalid) {
            try {
                DB::table('identified_access_grant_scopes')->insert($invalid);
                $this->fail('Invalid scope binding unexpectedly succeeded.');
            } catch (QueryException $exception) {
                $this->assertNotEmpty($exception->errorInfo);
            }
        }
        $this->assertSame(AccessGrantScope::Technical, $grant->scopes->first()->scope);
    }

    public function test_independent_scope_grants_do_not_survive_their_own_revocation_or_expiration(): void
    {
        $pi = $this->staff(ResearchRoleCode::Researcher);
        $admin = $this->staff(ResearchRoleCode::Administrator);
        $participant = $this->fixture();
        $contact = $this->grant($pi, $admin, $participant, ['CONTACT']);
        $clinical = $this->grant($pi, $admin, $participant, ['CLINICAL_READ']);
        $this->grant($pi, $admin, $participant, ['TECHNICAL']);
        $this->callAs($admin, 'GET', '/participants/'.$participant->id.'/contact')->assertOk();
        $this->callAs($admin, 'GET', '/participants/'.$participant->id.'/outcome')->assertOk();
        $this->callAs($pi, 'DELETE', '/participants/'.$participant->id.'/identified-access/'.$contact->id)->assertOk();
        $this->callAs($admin, 'GET', '/participants/'.$participant->id.'/contact')->assertForbidden();
        $this->callAs($admin, 'GET', '/participants/'.$participant->id.'/outcome')->assertOk();
        $clinical->update(['expires_at' => now()->subSecond()]);
        foreach (['entries', 'classifications', 'alerts', 'follow-ups', 'outcome'] as $resource) {
            $this->callAs($admin, 'GET', '/participants/'.$participant->id.'/'.$resource)->assertForbidden();
        }
        $this->callAs($admin, 'GET', '/participants')->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 0);
        $this->assertDatabaseHas('audit_log', ['aksi' => 'identified.access.revoked']);
    }

    public function test_all_scopes_preserve_blinding_at_policy_and_query_levels(): void
    {
        $pi = $this->staff(ResearchRoleCode::Researcher);
        $admin = $this->staff(ResearchRoleCode::Administrator, ResearchRoleCode::Assessor);
        $participant = $this->fixture();
        $this->grant($pi, $admin, $participant, ['TECHNICAL', 'CONTACT', 'CLINICAL_READ']);
        $this->callAs($admin, 'GET', '/participants/'.$participant->id)->assertOk()->assertJsonMissingPath('data.kelompok');
        $this->callAs($admin, 'GET', '/participants/'.$participant->id.'/outcome')->assertOk();
        foreach (['entries', 'classifications', 'alerts', 'follow-ups', 'contact'] as $resource) {
            $this->callAs($admin, 'GET', '/participants/'.$participant->id.'/'.$resource)->assertForbidden();
        }
        $access = app(ParticipantAccess::class);
        $this->assertSame(0, $access->visible($admin, Participant::query(), AccessGrantScope::Contact)->count());
        $this->assertSame(0, $access->monitoringVisible($admin, Participant::query())->count());
        $pi->roles()->detach();
        $this->callAs($admin, 'GET', '/participants/'.$participant->id.'/outcome')->assertForbidden();
    }
}
