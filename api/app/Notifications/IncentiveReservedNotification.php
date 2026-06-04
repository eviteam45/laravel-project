<?php

namespace App\Notifications;

use App\Models\IncentiveApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class IncentiveReservedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public IncentiveApplication $application) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amount = number_format((float) $this->application->incentive_amount, 2);

        return (new MailMessage)
            ->subject('Your solar incentive has been reserved')
            ->greeting("Hi {$notifiable->name},")
            ->line("An incentive of \${$amount} has been reserved for your project.")
            ->line('A payment has been scheduled and you will be notified when it is sent.')
            ->line('Thank you for using SolarIncentives.');
    }
}
