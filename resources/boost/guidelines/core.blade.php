{{-- Laravel Dusk Fakes Guidelines for AI Code Assistants --}}
{{-- Source: https://github.com/protonemedia/laravel-dusk-fakes --}}
{{-- License: MIT | (c) Protone Media --}}

## Dusk Fakes

- `protonemedia/laravel-dusk-fakes` provides persistent fake implementations for Laravel's Bus, Mail, Notification, and Queue facades during Dusk browser tests.
- Always activate the `dusk-fakes-development` skill when working with Dusk tests that need to assert dispatched jobs, sent mails, sent notifications, or queued jobs across browser requests.
