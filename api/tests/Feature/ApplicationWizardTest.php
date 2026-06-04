<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\Customer;
use App\Models\IncentiveApplication;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApplicationWizardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private IncentiveApplication $application;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->contractor()->create();
        $contractor = Contractor::factory()->create(['user_id' => $this->user->id]);
        $project = Project::factory()->for($contractor)->create();
        $this->application = IncentiveApplication::factory()->for($project)->create([
            'status' => 'in_progress',
            'current_step' => 'eligibility',
        ]);

        Sanctum::actingAs($this->user);
    }

    private function saveStep(string $key, array $data, bool $complete = true)
    {
        return $this->putJson("/api/applications/{$this->application->id}/steps/{$key}", [
            'data' => $data,
            'complete' => $complete,
        ]);
    }

    public function test_a_step_can_be_saved_as_a_draft_without_strict_validation(): void
    {
        $this->saveStep('eligibility', ['utility_provider' => 'Acme Power'], complete: false)
            ->assertSuccessful()
            ->assertJsonPath('data.is_complete', false);

        $this->assertDatabaseHas('application_steps', [
            'application_id' => $this->application->id,
            'step_key' => 'eligibility',
            'completed_at' => null,
        ]);
    }

    public function test_completing_a_step_enforces_its_validation_rules(): void
    {
        $this->saveStep('eligibility', ['owns_property' => true])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['data.utility_provider', 'data.average_monthly_bill']);
    }

    public function test_resume_pointer_advances_to_the_first_incomplete_step(): void
    {
        $this->saveStep('eligibility', [
            'owns_property' => true,
            'utility_provider' => 'Acme Power',
            'average_monthly_bill' => 180,
        ])->assertSuccessful();

        $this->assertSame('system', $this->application->fresh()->current_step);
    }

    public function test_first_step_save_advances_started_to_in_progress(): void
    {
        $this->application->update(['status' => 'started']);

        $this->saveStep('eligibility', [
            'owns_property' => true,
            'utility_provider' => 'Acme Power',
            'average_monthly_bill' => 180,
        ])->assertSuccessful();

        $this->assertSame('in_progress', $this->application->fresh()->status);
    }

    public function test_the_customer_applicant_can_also_complete_steps(): void
    {

        $customerUser = User::factory()->customer()->create();
        $customer = Customer::factory()->create(['user_id' => $customerUser->id]);
        $this->application->project->update(['customer_id' => $customer->id]);
        $this->application->update(['status' => 'started']);

        Sanctum::actingAs($customerUser);

        $this->putJson("/api/applications/{$this->application->id}/steps/eligibility", [
            'data' => [
                'owns_property' => true,
                'utility_provider' => 'Acme Power',
                'average_monthly_bill' => 180,
            ],
            'complete' => true,
        ])->assertSuccessful();

        $this->assertSame('in_progress', $this->application->fresh()->status);
    }

    public function test_documents_can_be_uploaded_and_downloaded_via_signed_url(): void
    {
        Storage::fake('local');

        $upload = $this->postJson("/api/applications/{$this->application->id}/documents", [
            'file' => UploadedFile::fake()->create('proof.pdf', 120, 'application/pdf'),
            'type' => 'proof',
        ]);

        $upload->assertCreated()
            ->assertJsonPath('data.type', 'proof')
            ->assertJsonStructure(['data' => ['id', 'file_name', 'download_url']]);

        $this->assertCount(1, Storage::disk('local')->allFiles());

        $url = $upload->json('data.download_url');
        $path = parse_url($url, PHP_URL_PATH).'?'.parse_url($url, PHP_URL_QUERY);
        $this->get($path)->assertSuccessful();
    }

    public function test_submitting_is_blocked_until_all_steps_and_a_document_are_present(): void
    {

        $this->postJson("/api/applications/{$this->application->id}/submit")
            ->assertStatus(422)
            ->assertJsonValidationErrors('steps');
    }

    public function test_full_wizard_can_be_completed_and_submitted(): void
    {
        Storage::fake('local');

        $this->saveStep('eligibility', [
            'owns_property' => true,
            'utility_provider' => 'Acme Power',
            'average_monthly_bill' => 180,
        ])->assertSuccessful();

        $this->saveStep('system', [
            'battery_oem' => 'Tesla',
            'battery_model' => 'Powerwall 3',
            'quantity' => 2,
            'usable_capacity_kwh' => 13.5,
        ])->assertSuccessful();

        $this->postJson("/api/applications/{$this->application->id}/documents", [
            'file' => UploadedFile::fake()->create('proof.pdf', 120, 'application/pdf'),
            'type' => 'proof',
        ])->assertCreated();

        $this->saveStep('documents', [])->assertSuccessful();
        $this->saveStep('review', ['accepted_terms' => true])->assertSuccessful();

        $this->assertNull($this->application->fresh()->current_step);

        $this->postJson("/api/applications/{$this->application->id}/submit")
            ->assertSuccessful()
            ->assertJsonPath('data.status', 'submitted');

        $this->assertNotNull($this->application->fresh()->submitted_at);

        $this->saveStep('review', ['accepted_terms' => true])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }
}
