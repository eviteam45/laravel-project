<?php

namespace App\Jobs;

use App\Models\IncentiveApplication;
use App\Notifications\IncentiveReservedNotification;
use App\Services\TransitionNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessApplicationTransition implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const NOTIFIABLE = ['submitted', 'under_review', 'reserved', 'paid', 'rejected', 'withdrawn'];

    public function __construct(
        public int $applicationId,
        public string $from,
        public string $to,
        public ?int $actorId = null,
    ) {}

    public function handle(): void
    {
        $application = IncentiveApplication::with(['project.contractor.user', 'project.customer.user'])
            ->find($this->applicationId);

        if (! $application || ! $application->project) {
            return;
        }

        $notifier = app(TransitionNotifier::class);

        if (in_array($this->to, self::NOTIFIABLE, true)) {
            $customerUser = $application->project->customer?->user;

            $data = [
                'application_id' => $application->id,
                'project_id' => $application->project_id,
                'from' => $this->from,
                'to' => $this->to,
            ];

            foreach ($notifier->recipients($application->project, $this->actorId) as $recipient) {
                $created = $notifier->record($recipient, "application_{$this->to}", $data, ['application_id']);

                if ($created && $this->to === 'reserved' && $recipient->is($customerUser)) {
                    $recipient->notifyNow(new IncentiveReservedNotification($application));
                }
            }
        }

        if ($this->to === 'reserved' && $application->incentive_amount !== null) {
            $application->payments()->firstOrCreate(
                ['status' => 'scheduled'],
                ['amount' => $application->incentive_amount, 'scheduled_for' => now()->addDays(30)],
            );
        }

        if ($this->to === 'paid') {
            $application->payments()
                ->where('status', 'scheduled')
                ->update(['status' => 'paid', 'paid_at' => now()]);
        }
    }
}
