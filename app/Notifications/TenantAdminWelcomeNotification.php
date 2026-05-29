<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Central\Tenant;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TenantAdminWelcomeNotification extends Notification
{
    use Queueable;

    public function __construct(protected readonly Tenant $tenant) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        /** @var User $notifiable */
        $loginUrl = config('app.url').'/login';

        return (new MailMessage)
            ->subject("Welcome to {$this->tenant->name} — Your Admin Account is Ready")
            ->greeting("Hello {$notifiable->first_name},")
            ->line("Your administrator account for **{$this->tenant->name}** has been set up successfully.")
            ->line('Here are your account details:')
            ->line("**Name:** {$notifiable->first_name} {$notifiable->last_name}")
            ->line("**Email:** {$notifiable->email}")
            ->line("**Username:** {$notifiable->username}")
            ->line("**Organisation:** {$this->tenant->name}")
            ->action('Log In to Your Dashboard', $loginUrl)
            ->line('**Next Steps:**')
            ->line('1. Log in using your email and the temporary password provided separately.')
            ->line('2. Change your password immediately after your first login.')
            ->line('3. Invite team members and configure your organisation settings.')
            ->line('If you have any questions, contact our support team.')
            ->salutation('The AIAS Team');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'tenant_id' => $this->tenant->id,
            'tenant_name' => $this->tenant->name,
        ];
    }
}
