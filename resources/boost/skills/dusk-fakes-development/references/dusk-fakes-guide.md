# Laravel Dusk Fakes Reference

Complete reference for `protonemedia/laravel-dusk-fakes`. Source: https://github.com/protonemedia/laravel-dusk-fakes

## Installation

```bash
composer require protonemedia/laravel-dusk-fakes --dev
```

## Configuration

Publish the config file:

```bash
php artisan vendor:publish --provider="ProtoneMedia\LaravelDuskFakes\LaravelDuskFakesServiceProvider" --tag="config"
```

### Config file (`config/dusk-fakes.php`)

```php
return [
    'bus' => [
        'enabled' => env('DUSK_FAKE_BUS', false),
        'storage_root' => storage_path('framework/testing/bus'),
    ],

    'mails' => [
        'enabled' => env('DUSK_FAKE_MAILS', false),
        'storage_root' => storage_path('framework/testing/mails'),
    ],

    'notifications' => [
        'enabled' => env('DUSK_FAKE_NOTIFICATIONS', false),
        'storage_root' => storage_path('framework/testing/notifications'),
    ],

    'queue' => [
        'enabled' => env('DUSK_FAKE_QUEUE', false),
        'storage_root' => storage_path('framework/testing/queue'),
    ],
];
```

### Environment setup

Set the environment variables in `.env.dusk` or `.env.dusk.local`:

```env
DUSK_FAKE_BUS=true
DUSK_FAKE_MAILS=true
DUSK_FAKE_NOTIFICATIONS=true
DUSK_FAKE_QUEUE=true
```

## How It Works

In standard Laravel tests, calling `Bus::fake()` or `Mail::fake()` swaps the facade with a fake that records interactions. However, in Dusk tests, each browser request runs in a separate PHP process, so the in-memory fake state is lost between requests.

Laravel Dusk Fakes solves this by serializing the fake state to disk after each interaction and loading it back before assertions. This allows you to assert jobs/mails/notifications/queue across multiple browser requests.

There are two ways to set up the fakes:
1. **Service Provider** (automatic): Fakes are swapped at boot time when the environment variable is enabled.
2. **Traits** (per-test): Fakes are swapped in `setUp` and cleaned up in `tearDown`.

## Persistent Bus

### Trait-based setup
```php
use ProtoneMedia\LaravelDuskFakes\Bus\PersistentBus;

class OrderTest extends DuskTestCase
{
    use PersistentBus;

    public function test_dispatch_job_after_confirming_order()
    {
        $this->browse(function (Browser $browser) {
            $order = Order::factory()->create();

            $browser->visit('/order/'.$order->id)
                ->press('Confirm')
                ->waitForText('We will generate an invoice!');

            Bus::assertDispatched(SendOrderInvoice::class);
        });
    }
}
```

### Faking specific jobs
```php
Bus::jobsToFake(ShipOrder::class);

$browser->visit('/order/'.$order->id)->press('Confirm');

Bus::assertDispatched(ShipOrder::class);
```

### Bus assertion methods
```php
Bus::assertDispatched(SendOrderInvoice::class);
Bus::assertDispatched(SendOrderInvoice::class, function ($job) use ($order) {
    return $job->order->id === $order->id;
});
Bus::assertNotDispatched(CancelOrder::class);
Bus::assertDispatchedTimes(SendOrderInvoice::class, 2);
Bus::assertNothingDispatched();
```

## Persistent Mails

### Trait-based setup
```php
use ProtoneMedia\LaravelDuskFakes\Mails\PersistentMails;

class OrderConfirmTest extends DuskTestCase
{
    use PersistentMails;

    public function test_send_order_confirmed_mailable()
    {
        $this->browse(function (Browser $browser) {
            $order = Order::factory()->create();

            $browser->visit('/order/'.$order->id)
                ->press('Confirm')
                ->waitForText('We have emailed your order confirmation!');

            Mail::assertSent(OrderConfirmed::class, function ($mail) use ($order) {
                return $mail->hasTo($order->user->email);
            });
        });
    }
}
```

### Mail assertion methods
```php
Mail::assertSent(OrderConfirmed::class);
Mail::assertSent(OrderConfirmed::class, function ($mail) use ($user) {
    return $mail->hasTo($user->email);
});
Mail::assertNotSent(OrderCancelled::class);
Mail::assertSentCount(2);
Mail::assertNothingSent();
Mail::assertQueued(WelcomeEmail::class);
Mail::assertNotQueued(WelcomeEmail::class);
Mail::assertNothingQueued();
```

## Persistent Notifications

### Trait-based setup
```php
use ProtoneMedia\LaravelDuskFakes\Notifications\PersistentNotifications;

class PasswordResetTest extends DuskTestCase
{
    use PersistentNotifications;

    public function test_reset_password_link_can_be_requested()
    {
        $this->browse(function (Browser $browser) {
            $user = User::factory()->create();

            $browser->visit('/forgot-password')
                ->type('email', $user->email)
                ->press('Email Password Reset Link')
                ->waitForText('We have emailed your password reset link!');

            Notification::assertSentTo($user, ResetPassword::class);
        });
    }
}
```

### Notification assertion methods
```php
Notification::assertSentTo($user, ResetPassword::class);
Notification::assertSentTo($user, ResetPassword::class, function ($notification, $channels) {
    return in_array('mail', $channels);
});
Notification::assertNotSentTo($user, WelcomeNotification::class);
Notification::assertSentToTimes($user, ResetPassword::class, 1);
Notification::assertNothingSent();
Notification::assertCount(3);
```

## Persistent Queue

### Trait-based setup
```php
use ProtoneMedia\LaravelDuskFakes\Queue\PersistentQueue;

class OrderInvoiceTest extends DuskTestCase
{
    use PersistentQueue;

    public function test_queue_invoice_job_after_confirming_order()
    {
        $this->browse(function (Browser $browser) {
            $order = Order::factory()->create();

            $browser->visit('/order/'.$order->id)
                ->press('Confirm')
                ->waitForText('We will generate an invoice!');

            Queue::assertPushed(SendOrderInvoice::class);
        });
    }
}
```

### Faking specific jobs
```php
Queue::jobsToFake(ShipOrder::class);

$browser->visit('/order/'.$order->id)->press('Confirm');

Queue::assertPushed(ShipOrder::class);
```

### Queue assertion methods
```php
Queue::assertPushed(SendOrderInvoice::class);
Queue::assertPushed(SendOrderInvoice::class, function ($job) use ($order) {
    return $job->order->id === $order->id;
});
Queue::assertNotPushed(CancelOrder::class);
Queue::assertPushedOn('invoices', SendOrderInvoice::class);
Queue::assertNothingPushed();
Queue::assertCount(2);
```

## Combining Multiple Fakes

You can use multiple traits in a single test class:

```php
use ProtoneMedia\LaravelDuskFakes\Bus\PersistentBus;
use ProtoneMedia\LaravelDuskFakes\Mails\PersistentMails;
use ProtoneMedia\LaravelDuskFakes\Notifications\PersistentNotifications;
use ProtoneMedia\LaravelDuskFakes\Queue\PersistentQueue;

class CheckoutTest extends DuskTestCase
{
    use PersistentBus;
    use PersistentMails;
    use PersistentNotifications;
    use PersistentQueue;

    public function test_checkout_dispatches_jobs_and_sends_mail()
    {
        $this->browse(function (Browser $browser) {
            $order = Order::factory()->create();

            $browser->visit('/checkout/'.$order->id)
                ->press('Complete Order')
                ->waitForText('Thank you!');

            Bus::assertDispatched(ProcessPayment::class);
            Mail::assertSent(OrderConfirmed::class);
            Notification::assertSentTo($order->user, OrderShippedNotification::class);
        });
    }
}
```

## Available Traits and Classes

| Facade       | Trait                   | Fake Class                  | Namespace                                            |
|-------------|-------------------------|-----------------------------|------------------------------------------------------|
| Bus         | `PersistentBus`         | `PersistentBusFake`         | `ProtoneMedia\LaravelDuskFakes\Bus`                  |
| Mail        | `PersistentMails`       | `PersistentMailFake`        | `ProtoneMedia\LaravelDuskFakes\Mails`                |
| Notification| `PersistentNotifications`| `PersistentNotificationFake`| `ProtoneMedia\LaravelDuskFakes\Notifications`        |
| Queue       | `PersistentQueue`       | `PersistentQueueFake`       | `ProtoneMedia\LaravelDuskFakes\Queue`                |
