<?php

namespace App\Jobs;

use App\Models\IncentiveApplication;
use App\Models\Notification;
use App\Notifications\IncentiveReservedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Runs after an application status change: notifies the customer and,
 * on approval, schedules the incentive payment.
 *
 * Receives scalar IDs (not the model) so the payload stays small and
 * always reflects fresh data when the job runs.
 */
class ProcessApplicationTransition implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $applicationId,
        public string $from,
        public string $to,
    ) {}

    public function handle(): void
    {
        $application = IncentiveApplication::with('project.customer.user')->find($this->applicationId);

        if (! $application) {
            return;
        }

        $customerUser = $application->project?->customer?->user;

        // Only notify the customer about meaningful milestones.
        $notifiable = ['submitted', 'under_review', 'reserved', 'paid', 'rejected', 'withdrawn'];
        $type = "application_{$this->to}";

        // Idempotent: don't re-create the same milestone notification on retry.
        $alreadyNotified = $customerUser && Notification::query()
            ->where('user_id', $customerUser->id)
            ->where('type', $type)
            ->where('data->application_id', $application->id)
            ->exists();

        if ($customerUser && in_array($this->to, $notifiable, true) && ! $alreadyNotified) {
            Notification::create([
                'user_id' => $customerUser->id,
                'type' => $type,
                'data' => [
                    'application_id' => $application->id,
                    'project_id' => $application->project_id,
                    'from' => $this->from,
                    'to' => $this->to,
                ],
            ]);

            // Queued email on reservation (6.6).
            if ($this->to === 'reserved') {
                $customerUser->notify(new IncentiveReservedNotification($application));
            }
        }

        // Reserving funds schedules the incentive payout 30 days out.
        // firstOrCreate keeps this idempotent — a retry won't double-create.
        if ($this->to === 'reserved' && $application->incentive_amount !== null) {
            $application->payments()->firstOrCreate(
                ['status' => 'scheduled'],
                [
                    'amount' => $application->incentive_amount,
                    'scheduled_for' => now()->addDays(30),
                ],
            );
        }

        // Marking paid settles any scheduled payments (naturally idempotent).
        if ($this->to === 'paid') {
            $application->payments()
                ->where('status', 'scheduled')
                ->update(['status' => 'paid', 'paid_at' => now()]);
        }
    }
}
