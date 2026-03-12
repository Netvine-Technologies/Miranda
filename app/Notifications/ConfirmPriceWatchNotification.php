<?php

namespace App\Notifications;

use App\Models\CanonicalProduct;
use App\Models\PriceWatchSubscription;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ConfirmPriceWatchNotification extends Notification
{
    public function __construct(
        protected CanonicalProduct $canonicalProduct,
        protected PriceWatchSubscription $subscription
    ) {
    }

    /**
     * @param mixed $notifiable
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * @param mixed $notifiable
     */
    public function toMail(object $notifiable): MailMessage
    {
        $confirmUrl = $this->backendUrl('/api/watch-subscriptions/confirm/'.$this->subscription->confirm_token);
        $unsubscribeUrl = $this->backendUrl('/api/watch-subscriptions/unsubscribe/'.$this->subscription->unsubscribe_token);

        return (new MailMessage)
            ->subject('Confirm your price watch subscription')
            ->greeting('Confirm your subscription')
            ->line("You requested price alerts for: {$this->canonicalProduct->title}")
            ->action('Confirm Subscription', $confirmUrl)
            ->line('If this was not you, no action is required.')
            ->line("Unsubscribe: {$unsubscribeUrl}");
    }

    protected function backendUrl(string $path): string
    {
        return rtrim(config('app.url'), '/').$path;
    }
}
