<?php

namespace App\Notifications;

use App\Models\CustomDomain;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomDomainConnectedNotification extends Notification
{
    use Queueable;

    public function __construct(public CustomDomain $domain) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Custom Domain Is Now Live!')
            ->greeting('Hello '.$notifiable->name.',')
            ->line("Great news! \"{$this->domain->domain}\" is now verified, secured with SSL, and live.")
            ->line('Your school\'s website is now reachable directly at your own branded domain.')
            ->action('View Your Website', 'https://'.$this->domain->domain)
            ->line('You can manage your domain any time from Settings > Website > Custom Domain.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Custom Domain Connected',
            'body' => "\"{$this->domain->domain}\" is now live and secured with SSL.",
            'url' => route('website.index'),
        ];
    }
}
