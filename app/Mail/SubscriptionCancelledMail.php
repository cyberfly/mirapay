<?php

namespace App\Mail;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionCancelledMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Subscription $subscription, public bool $expired = false) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->expired
            ? "Langganan {$this->subscription->product_name} telah tamat"
            : "Langganan {$this->subscription->product_name} dibatalkan");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.subscriptions.cancelled');
    }
}
