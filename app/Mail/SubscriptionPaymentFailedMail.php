<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionPaymentFailedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Subscription $subscription, public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Bayaran langganan {$this->subscription->product_name} belum diterima");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.subscriptions.payment-failed');
    }
}
