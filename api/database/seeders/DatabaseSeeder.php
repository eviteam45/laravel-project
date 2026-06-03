<?php

namespace Database\Seeders;

use App\Models\ApplicationStep;
use App\Models\AuditLog;
use App\Models\BatterySystem;
use App\Models\Contractor;
use App\Models\Customer;
use App\Models\Document;
use App\Models\IncentiveApplication;
use App\Models\IncentivePayment;
use App\Models\Note;
use App\Models\Notification;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed a coherent graph across all tables.
     */
    public function run(): void
    {
        // A known admin + login you can use straight away.
        $admin = User::factory()->admin()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        // Contractors and customers (each owns a role-scoped user).
        $contractors = Contractor::factory(5)->create();
        $customers = Customer::factory(15)->create();

        // Projects: each picks an existing contractor + recycles existing customers.
        $contractors->each(function (Contractor $contractor) use ($customers, $admin) {
            Project::factory(rand(2, 4))
                ->recycle($customers)
                ->for($contractor)
                ->create()
                ->each(fn (Project $project) => $this->seedProject($project, $admin));
        });

        // A handful of unread notifications for the admin.
        Notification::factory(4)->unread()->for($admin)->create();
    }

    /**
     * Fill out a single project: batteries, docs, notes, and (usually) an application.
     */
    protected function seedProject(Project $project, User $admin): void
    {
        BatterySystem::factory(rand(1, 3))->for($project)->create();

        Document::factory(rand(1, 3))->forOwner($project)->create(['uploaded_by' => $admin->id]);
        Note::factory(rand(0, 2))->forOwner($project)->create(['user_id' => $admin->id]);

        AuditLog::factory()->forSubject($project)->create([
            'user_id' => $admin->id,
            'action' => 'created',
        ]);

        // ~75% of projects have an incentive application.
        if (rand(1, 100) > 25) {
            $this->seedApplication($project, $admin);
        }
    }

    /**
     * Build an incentive application with steps, payments, docs, and notes.
     */
    protected function seedApplication(Project $project, User $admin): void
    {
        $application = IncentiveApplication::factory()->for($project)->create();

        // One step per stage, some completed.
        foreach (['eligibility', 'documents', 'review', 'payment'] as $stepKey) {
            ApplicationStep::factory()->for($application, 'application')->create([
                'step_key' => $stepKey,
                'completed_at' => fake()->boolean(60) ? fake()->dateTimeBetween('-3 months') : null,
            ]);
        }

        Document::factory(rand(1, 4))->forOwner($application)->create(['uploaded_by' => $admin->id]);
        Note::factory(rand(0, 2))->forOwner($application)->create(['user_id' => $admin->id]);

        // Reserved/paid applications get a payment.
        if (in_array($application->status, ['reserved', 'paid'], true)) {
            IncentivePayment::factory()
                ->for($application, 'application')
                ->state(['status' => $application->status === 'paid' ? 'paid' : 'scheduled'])
                ->create();
        }

        // Notify the customer's user about the application.
        Notification::factory()->for($project->customer->user)->create([
            'type' => 'application_submitted',
        ]);
    }
}
