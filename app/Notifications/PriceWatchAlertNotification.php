<?php

namespace App\Notifications;

use App\Models\CanonicalProduct;
use App\Models\PriceWatchSubscription;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PriceWatchAlertNotification extends Notification
{
    /**
     * @param array{lowest_price: float|null, currency: string|null, stock_status: string} $snapshot
     */
    public function __construct(
        protected CanonicalProduct $canonicalProduct,
        protected PriceWatchSubscription $subscription,
        protected array $snapshot
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
        $currency = $this->snapshot['currency'] ?: '';
        $lowestPrice = $this->snapshot['lowest_price'] !== null
            ? number_format((float) $this->snapshot['lowest_price'], 2)." {$currency}"
            : 'N/A';
        $productUrl = $this->buildProductUrl();
        $unsubscribeUrl = $this->backendUrl('/api/watch-subscriptions/unsubscribe/'.$this->subscription->unsubscribe_token);

        return (new MailMessage)
            ->subject('Price watch update: '.$this->canonicalProduct->title)
            ->greeting('Price watch update')
            ->line("Product: {$this->canonicalProduct->title}")
            ->line("Lowest current price: {$lowestPrice}")
            ->line("Stock status: {$this->snapshot['stock_status']}")
            ->action('View Product', $productUrl)
            ->line("Unsubscribe: {$unsubscribeUrl}");
    }

    protected function buildProductUrl(): string
    {
        $template = config('services.frontend.compare_url_template');

        if (is_string($template) && $template !== '' && str_contains($template, '{slug}')) {
            return str_replace('{slug}', $this->canonicalProduct->slug, $template);
        }

        return rtrim(config('app.url'), '/').'/compare/'.$this->canonicalProduct->slug;
    }

    protected function backendUrl(string $path): string
    {
        return rtrim(config('app.url'), '/').$path;
    }
}
