---
paths:
  - 'app/Models/**'
---

# Models

## Always pass an explicit array to #[Guarded] / #[Fillable] / #[Hidden]
Write `#[Guarded([])]`, never a bare `#[Guarded]`.

In Laravel 13.24.0 these Eloquent attributes declare `__construct(array|string ...$columns)` and the body reads `$columns[0]` unconditionally. With no argument the variadic is empty, so PHP emits "Undefined array key 0" and Laravel's error handler escalates that warning to an ErrorException. The computed value is `[]` either way — only the explicit form is warning-free.

Found the hard way: a bare `#[Guarded]` broke 7 mass-assignment tests while `php artisan tinker` showed the attribute resolving fine. Same trap applies to `#[Fillable]` and `#[Hidden]`, which share the constructor shape; `User` already passes arrays and is unaffected.
