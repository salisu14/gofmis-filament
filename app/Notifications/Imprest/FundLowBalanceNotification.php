<?php

namespace App\Notifications\Imprest;

use App\Models\ImprestFund;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FundLowBalanceNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly ImprestFund $fund) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Imprest Fund Low Balance Alert')
            ->line("Fund at {$this->fund->location} is running low.")
            ->line("Current balance: {$this->fund->current_balance}")
            ->line("Authorized amount: {$this->fund->authorized_amount}")
            ->action('View Fund', url('/imprest/funds/' . $this->fund->id));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'fund_id' => $this->fund->id,
            'location' => $this->fund->location,
            'current_balance' => $this->fund->current_balance,
            'message' => 'Imprest fund balance is below 20% threshold',
        ];
    }
}
