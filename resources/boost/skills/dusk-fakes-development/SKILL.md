---
name: dusk-fakes-development
description: Write Laravel Dusk browser tests that assert dispatched jobs, sent mails, sent notifications, and queued jobs across multiple browser requests using persistent fake facades.
license: MIT
metadata:
  author: Protone Media
---

# Dusk Fakes Development

## Overview
Use protonemedia/laravel-dusk-fakes to persist Laravel's fake facades (Bus, Mail, Notification, Queue) across browser requests in Dusk tests. This solves the problem of facade state being lost between HTTP requests since each request runs in its own PHP process.

## When to Activate
- Activate when writing Dusk tests that need to verify jobs, mails, notifications, or queued jobs were dispatched.
- Activate when code references `PersistentBus`, `PersistentMails`, `PersistentNotifications`, `PersistentQueue`, or any of the persistent fake classes.
- Activate when the user wants to assert facade interactions across multiple browser requests in Dusk.

## Scope
- In scope: persistent Bus/Mail/Notification/Queue fakes for Dusk tests, environment configuration, trait-based and service-provider-based setup.
- Out of scope: standard Laravel feature tests (which don't need persistence), non-Dusk browser testing frameworks.

## Workflow
1. Identify which facade(s) need to be faked (Bus, Mail, Notification, Queue).
2. Read `references/dusk-fakes-guide.md` and focus on the relevant section.
3. Apply the patterns from the reference, keeping tests minimal and following Laravel Dusk conventions.

## Core Concepts

### Environment Setup
Enable each fake via environment variables in the Dusk `.env` file:

```env
DUSK_FAKE_BUS=true
DUSK_FAKE_MAILS=true
DUSK_FAKE_NOTIFICATIONS=true
DUSK_FAKE_QUEUE=true
```

### Trait-Based Usage (Recommended)
Add the corresponding trait to your Dusk test class. No manual `fake()` call is needed:

```php
use ProtoneMedia\LaravelDuskFakes\Bus\PersistentBus;
use ProtoneMedia\LaravelDuskFakes\Mails\PersistentMails;
use ProtoneMedia\LaravelDuskFakes\Notifications\PersistentNotifications;
use ProtoneMedia\LaravelDuskFakes\Queue\PersistentQueue;

class OrderTest extends DuskTestCase
{
    use PersistentBus;
    use PersistentMails;

    public function test_order_confirmation()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/order/1')
                ->press('Confirm')
                ->waitForText('Order confirmed!');

            Bus::assertDispatched(SendOrderInvoice::class);
            Mail::assertSent(OrderConfirmed::class);
        });
    }
}
```

### Faking Specific Jobs
Selectively fake only certain jobs while letting others execute:

```php
Bus::jobsToFake(ShipOrder::class);

$browser->visit('/order/1')->press('Confirm');

Bus::assertDispatched(ShipOrder::class);
```

### Asserting Dispatched/Sent Items
Use standard Laravel assertion methods on the facades:

```php
Bus::assertDispatched(SendOrderInvoice::class);
Mail::assertSent(OrderConfirmed::class, fn ($mail) => $mail->hasTo($user->email));
Notification::assertSentTo($user, ResetPassword::class);
Queue::assertPushed(ProcessPayment::class);
```

## Do and Don't

Do:
- Set the `DUSK_FAKE_*` environment variables in your `.env.dusk` or `.env.dusk.local` file.
- Use the trait-based approach (`PersistentBus`, `PersistentMails`, etc.) for per-test control.
- Place facade assertions after browser interactions that trigger the dispatched job/mail/notification.
- Use `jobsToFake()` on Bus or Queue when you only want to intercept specific jobs.

Don't:
- Don't manually call `Bus::fake()`, `Mail::fake()`, etc. — the traits handle this automatically.
- Don't forget to enable the corresponding environment variable — fakes won't activate without it.
- Don't use this package for standard Laravel feature tests — it's designed specifically for Dusk browser tests where state must persist across HTTP requests.
- Don't mix the trait approach and the service provider approach for the same facade in the same test.

## References
- `references/dusk-fakes-guide.md`
