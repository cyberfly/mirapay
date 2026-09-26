<?php

use App\Actions\Shop\MarkOrderAsPaid;
use App\Mail\NewOrderAdminMail;
use App\Mail\OrderPaidMail;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

it('emails the customer and admins when an order is paid', function () {
    Mail::fake();

    $admin = User::factory()->create(['is_admin' => true]);
    User::factory()->create(['is_admin' => false]);
    $order = Order::factory()->create(['email' => 'buyer@example.com']);

    app(MarkOrderAsPaid::class)($order);

    Mail::assertQueued(OrderPaidMail::class, fn ($mail) => $mail->hasTo('buyer@example.com'));
    Mail::assertQueued(NewOrderAdminMail::class, fn ($mail) => $mail->hasTo($admin->email) && count($mail->to) === 1);
});

it('renders both emails', function () {
    $order = Order::factory()->create();

    expect((new OrderPaidMail($order))->render())->toContain($order->order_number)
        ->and((new NewOrderAdminMail($order))->render())->toContain($order->order_number);
});
