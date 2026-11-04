# Auto-Payout Feature

## Overview

Auto-Payout automates Stripe payouts from the platform to connected Express account bank accounts. It follows a two-phase approach:

1. **Calculate & Create** -- The system evaluates which connected accounts have earnings ready for payout, calculates net amounts per account, and either creates pending payout records (requiring approval) or executes payouts immediately.
2. **Approve & Execute** -- If approval is required, an admin reviews and approves/rejects each pending payout. Approved payouts are executed on the next scheduler run.

The feature is tenant-aware and runs per-tenant via the `stancl/tenancy` package.

---

## Architecture Flow

```
Scheduler (every minute)
  |
  v
tenants:run autopayout:process --tenants={tenant}
  |
  v
ProcessAutoPayouts Command
  |
  +-- Step 1: Create Pending Payouts
  |     |
  |     +-- Query AutoPayoutRules (enabled=true, next_execution_at <= now)
  |     |
  |     +-- For each rule:
  |     |     |
  |     |     +-- Get connected Stripe accounts (from tour_packages)
  |     |     |
  |     |     +-- For each account:
  |     |     |     |
  |     |     |     +-- Calculate net payout amount from transactions
  |     |     |     |
  |     |     |     +-- If amount >= minimum_amount:
  |     |     |           |
  |     |     |           +-- If requires_approval:
  |     |     |           |     Create PendingPayout + PendingPayoutItems
  |     |     |           |
  |     |     |           +-- If !requires_approval:
  |     |     |                 Execute immediately via Stripe
  |     |     |
  |     |     +-- Update rule: last_executed_at, next_execution_at
  |     |
  +-- Step 2: Execute Approved Payouts
        |
        +-- Query PendingPayouts (status = 'approved')
        |
        +-- For each approved payout:
              |
              +-- Check Stripe balance on connected account
              +-- Get bank account from connected account
              +-- Create Stripe Payout (amount in cents)
              +-- Create AutoPayout + AutoPayoutItems records
              +-- Mark bookings as payout_made = true
              +-- Delete PendingPayout record (cascade deletes items + logs)
```

---

## Database Schema

### `auto_payout_rules`

Scheduling configuration. The system supports a single rule per tenant.

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint (PK) | Auto-increment |
| `name` | varchar(255) | Display name |
| `frequency` | enum(`daily`,`weekly`,`monthly`,`yearly`) | Default: `weekly` |
| `day_of_week` | int, nullable | 0=Sunday, 6=Saturday. Used when frequency=weekly |
| `day_of_month` | int, nullable | 1-31. Used when frequency=monthly |
| `execution_time` | time | Default: `09:00:00` |
| `minimum_amount` | decimal(10,2) | Default: 0. Accounts below this are skipped |
| `enabled` | boolean | Default: true |
| `requires_approval` | boolean | Default: true. When false, payouts execute immediately |
| `last_executed_at` | datetime, nullable | Timestamp of last execution |
| `next_execution_at` | datetime, nullable | Calculated next execution time |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

### `auto_payouts`

Records of executed payouts.

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint (PK) | Auto-increment |
| `payout_reference` | varchar(255), unique | Format: `AP-YYYYMMDD-ACCT6-RAND4`, e.g. `AP-20260820-ZBRVO3-NDDH`. `ACCT6` is the last 6 chars of the Stripe account ID; `RAND4` is `strtoupper(Str::random(4))`. A payout that came through the approval queue keeps its parent's `PP-` reference verbatim instead. |
| `rule_id` | bigint (FK) | References `auto_payout_rules.id` (cascade) |
| `total_amount` | decimal(10,2) | Gross payment amount |
| `platform_fee` | decimal(10,2) | Stripe processing fees |
| `net_amount` | decimal(10,2) | Amount sent to bank account |
| `booking_count` | int | Number of bookings included |
| `processed_date` | timestamp, nullable | When the Stripe payout was created |
| `status` | enum(`pending`,`processing`,`completed`,`failed`) | Default: `pending` |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

### `auto_payout_items`

Per-booking breakdown for executed payouts.

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint (PK) | Auto-increment |
| `payout_id` | bigint (FK) | References `auto_payouts.id` (cascade) |
| `booking_id` | bigint (FK) | References `tour_bookings_master.id` (cascade) |
| `total_amount` | decimal(10,2) | Booking gross amount |
| `platform_fee` | decimal(10,2) | Booking fee amount |
| `net_amount` | decimal(10,2) | Booking net amount |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

### `pending_payouts`

Approval queue for payouts awaiting admin review.

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint (PK) | Auto-increment |
| `payout_reference` | varchar(255), unique | Format: `PP-YYYYMMDD-ACCT6-RAND4`, e.g. `PP-20260818-ZBRVO3-K53E`. Carried unchanged onto the `auto_payouts` row when the payout executes, and sent to Stripe as payout metadata. |
| `rule_id` | bigint (FK) | References `auto_payout_rules.id` (cascade) |
| `stripe_account_id` | varchar(255) | Stripe connected account ID (e.g., `acct_xxx`) |
| `total_amount` | decimal(10,2) | Gross payment amount |
| `platform_fee` | decimal(10,2) | Stripe processing fees |
| `net_amount` | decimal(10,2) | Amount to be sent to bank account |
| `booking_count` | int | Number of bookings included |
| `status` | enum(`pending_approval`,`approved`,`rejected`) | Default: `pending_approval` |
| `rule_snapshot` | json | Snapshot of the rule at creation time |
| `calculation_data` | json | Contains `booking_ids`, `booking_details`, `calculation_date`, `stripe_account_id` |
| `approved_at` | timestamp, nullable | When approved |
| `approved_by` | int unsigned, nullable (FK) | References `admins.id` (set null on delete) |
| `rejected_at` | timestamp, nullable | When rejected |
| `rejected_by` | int unsigned, nullable (FK) | References `admins.id` (set null on delete) |
| `rejection_reason` | text, nullable | Required when rejecting |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

**Indexes:** `status`, `stripe_account_id`, `created_at`, composite `[status, created_at]`

### `pending_payout_items`

Per-booking breakdown for pending payouts.

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint (PK) | Auto-increment |
| `pending_payout_id` | bigint (FK) | References `pending_payouts.id` (cascade) |
| `booking_id` | bigint (FK) | References `tour_bookings_master.id` (cascade) |
| `total_amount` | decimal(10,2) | Booking gross amount |
| `platform_fee` | decimal(10,2) | Booking fee amount |
| `net_amount` | decimal(10,2) | Booking net amount |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

### `payout_approval_logs`

Audit trail for all payout approval actions.

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint (PK) | Auto-increment |
| `pending_payout_id` | bigint (FK) | References `pending_payouts.id` (cascade) |
| `action` | enum(`created`,`approved`,`rejected`,`executed`,`failed`) | |
| `user_id` | int unsigned, nullable (FK) | References `admins.id` (set null on delete) |
| `ip_address` | varchar(45), nullable | |
| `user_agent` | text, nullable | |
| `notes` | text, nullable | |
| `metadata` | json, nullable | |
| `created_at` | timestamp | No `updated_at` column |

**Indexes:** `pending_payout_id`, `action`, `created_at`, composite `[pending_payout_id, action]`

---

## Payout Calculation Logic

The calculation happens in `AutoPayoutController::calculateAccountPayoutAmount()`. Here is the step-by-step logic:

### 1. Determine Cycle Date Range

```
cycle_start = rule.last_executed_at  (or now - 1 month if never executed)
cycle_end   = rule.next_execution_at - 1 second
```

### 2. Query Transactions

```sql
SELECT tra.amount, tra.fee_amount, tra.status, tra.tour_booking_id
FROM transactions tra
  JOIN tour_bookings_master tbm ON tbm.id = tra.tour_booking_id
  JOIN tour_slot_times tst ON tst.id = tbm.tour_slot_id
  JOIN tour_packages tp ON tp.id = tbm.tour_package_id
WHERE tra.status != 'Failed'
  AND tra.via = 'Card'
  AND tra.request_from != 'Insert'
  AND tbm.comments NOT LIKE '#%'
  AND tbm.payout_made = 0
  AND tp.stripe_destination_id = :stripeAccountId
  AND TIMESTAMP(tst.date, tst.time_from) BETWEEN :cycleStart AND :cycleEnd
```

**Key filters explained:**
- `status != 'Failed'` -- Excludes failed transactions but includes `Completed`, `Refund`, and `Initialized`
- `via = 'Card'` -- Only card payments (no cash, no external)
- `request_from != 'Insert'` -- Excludes manually inserted transactions
- `comments NOT LIKE '#%'` -- Excludes external/linked bookings
- `payout_made = 0` -- Only bookings not yet paid out
- **Cycle dates use tour date** (`tst.date` + `tst.time_from`), not payment date. This is intentional: pay after service delivery.

### 3. Aggregate Per Booking

For each booking, the system accumulates:
- `amount` -- sum of all `Completed` transaction amounts
- `fee_amount` -- sum of all `Completed` transaction fees
- `refund` -- sum of all `Refund` transaction amounts
- `refund_fee_amount` -- sum of all `Refund` transaction fees
- `refund_remove_fee` = `refund - refund_fee_amount` (net refund after fee recovery)

### 4. Calculate Net Per Booking

```
net_total = amount - refund_remove_fee - fee_amount
```

Expanded:
```
net_total = payment_amount - (refund_amount - refund_fee) - payment_fee
```

### 5. Worked Example

A booking with:
- Payment: $100.00 (fee: $3.20)
- Refund: $25.00 (fee: $0.80)

Calculation:
```
amount           = 100.00
fee_amount       = 3.20
refund           = 25.00
refund_fee       = 0.80
refund_remove_fee = 25.00 - 0.80 = 24.20
net_total        = 100.00 - 24.20 - 3.20 = 72.60
```

The operator receives $72.60 for this booking.

### 6. Total for Payout

```
total_amount  = sum of all booking amounts (gross payments)
platform_fee  = sum of all booking fee_amounts
net_amount    = sum of all booking net_totals
booking_count = count of distinct bookings
```

---

## API Endpoints

All routes are prefixed with the admin route group and require admin authentication.

### Rule Management

| Method | Route | Controller Method | Purpose |
|--------|-------|-------------------|---------|
| GET | `/admin/auto-payout` | `index` | Render the auto-payout page |
| GET | `/admin/auto-payout/fetch-rule` | `fetchRule` | Get the current payout rule (JSON) |
| POST | `/admin/auto-payout/store-rule` | `saveRule` | Create or update the payout rule |
| POST | `/admin/auto-payout/setup-stripe` | `setupStripePayouts` | Trigger payout processing (pending or immediate) |
| POST | `/admin/auto-payout/dashboard` | `getPayoutDashboardData` | Get dashboard metrics |
| POST | `/admin/auto-payout/report` | `getPayoutReportData` | Get report data with filters |
| GET | `/admin/auto-payout/test-command` | `testCommand` | Run `autopayout:process --dry-run` |

### Approval Workflow

| Method | Route | Controller | Purpose |
|--------|-------|------------|---------|
| GET | `/admin/auto-payout/approval/pending` | `PayoutApprovalController` | List pending payouts (paginated, filterable) |
| GET | `/admin/auto-payout/approval/dashboard-data` | `PayoutApprovalController` | Approval dashboard metrics |
| GET | `/admin/auto-payout/approval/{id}/details` | `PayoutApprovalController` | View payout details + booking breakdown |
| POST | `/admin/auto-payout/approval/{id}/approve` | `PayoutApprovalController` | Approve a pending payout |
| POST | `/admin/auto-payout/approval/{id}/reject` | `PayoutApprovalController` | Reject a pending payout (reason required) |
| POST | `/admin/auto-payout/approval/bulk-approve` | `PayoutApprovalController` | Bulk approve multiple payouts |
| POST | `/admin/auto-payout/approval/bulk-reject` | `PayoutApprovalController` | Bulk reject multiple payouts |

---

## Scheduling

### How It Works

The `ProcessAutoPayouts` command is registered in `app/Console/Kernel.php` and scheduled to run every minute per tenant:

```php
$schedule->command("tenants:run autopayout:process --tenants={$tenant->id}")
    ->everyMinute()
    ->timezone($timezone)
    ->withoutOverlapping()
    ->onOneServer();
```

- **`everyMinute()`** -- The command checks every minute whether any rules are due
- **`withoutOverlapping()`** -- Prevents concurrent execution of the same tenant's command
- **`onOneServer()`** -- In multi-server deployments, only one server runs the command

### `next_execution_at` Calculation

When a rule is saved or executed, the next execution time is calculated based on frequency:

| Frequency | Logic |
|-----------|-------|
| `daily` | Tomorrow at `execution_time` |
| `weekly` | Next occurrence of `day_of_week` at `execution_time` |
| `monthly` | Day `day_of_month` of next month at `execution_time` |

The calculation uses the tenant's timezone (`tourOperator()->timezone`).

### Dry Run

```bash
docker exec nat-app php artisan tenants:run autopayout:process -- --dry-run --tenants=dixies
```

In dry-run mode, the command reports which rules would be processed and how many approved payouts would be executed, without creating any records or calling Stripe.

---

## Key Source Files

| File | Purpose |
|------|---------|
| `app/Services/PayoutApprovalService.php` | Approval state machine, execution logic |
| `app/Http/Controllers/Admin/AutoPayout/AutoPayoutController.php` | Calculation logic, HTTP endpoints, immediate execution |
| `app/Http/Controllers/Admin/AutoPayout/PayoutApprovalController.php` | Approval UI endpoints |
| `app/Console/Commands/ProcessAutoPayouts.php` | Scheduled command |
| `app/Console/Kernel.php` | Scheduler registration |
| `app/Models/PendingPayout.php` | Pending payout model with scopes |
| `app/Models/AutoPayoutRule.php` | Rule model |
| `app/Models/AutoPayout.php` | Executed payout model |
| `app/Models/PendingPayoutItem.php` | Per-booking items (pending) |
| `app/Models/AutoPayoutItem.php` | Per-booking items (executed) |
| `app/Models/PayoutApprovalLog.php` | Audit log model |

---

## Known Issues

### Critical

1. **Kernel.php syntax error** -- Missing comma between `RemoveBokunReservations::class` and `ProcessAutoPayouts::class` on line 26-27 of `app/Console/Kernel.php`. This is a PHP parse error that will prevent all scheduled commands from running.

2. **Double-payout risk (no distributed lock)** -- The HTTP endpoint (`POST /admin/auto-payout/setup-stripe`) and the scheduler (`autopayout:process`) can both trigger payout processing concurrently. `withoutOverlapping()` only prevents the scheduler from overlapping with itself, not with HTTP requests. This can result in duplicate payouts for the same bookings.

3. **No Stripe idempotency keys** -- `Payout::create()` calls do not include Stripe idempotency keys. If a network retry or duplicate request occurs, Stripe may create duplicate payouts.

4. **`payout_made` set AFTER Stripe call** -- In `executeSinglePayout()`, bookings are marked as `payout_made = true` after the Stripe payout is created. If the process crashes between the Stripe call and the DB update, the bookings will be paid out on Stripe but not marked in the database, leading to double payouts on the next run.

5. **`executePayoutsImmediately()` has no DB transaction** -- The immediate execution path in the controller does not wrap operations in a database transaction. Partial failures can leave inconsistent state.

6. **No maximum payout amount ceiling** -- There is no upper limit on payout amounts. A calculation error or data issue could result in an unexpectedly large payout.

7. **Audit trail deleted on payout execution** -- `executeSinglePayout()` calls `$pendingPayout->delete()` after execution. Because `payout_approval_logs` has `onDelete('cascade')` on the `pending_payout_id` FK, all approval logs for that payout are permanently deleted. The full audit trail (who created, approved, and executed the payout) is lost.

8. **`Initialized` transactions can inflate booking count** -- The transaction query filters `status != 'Failed'` which includes `Initialized` (in-progress) transactions. These incomplete transactions add bookings to the count and may contribute amounts that are not yet settled.

### High

9. **`requires_approval` not configurable in UI** -- The `saveRule()` method does not accept `requires_approval` from the request. It defaults to `true` in the database and cannot be changed through the admin interface.

10. **Post-payout refunds not deducted from future payouts** -- If a refund is issued after a booking has been paid out (`payout_made = 1`), the refund amount is not deducted from future payouts. The operator has already received the full amount.

11. **Stale amounts at execution time** -- When an approved payout is executed, it uses the `net_amount` calculated at creation time. If new transactions (refunds, additional payments) occurred between creation and execution, the executed amount may be incorrect.

12. **Command instantiates controller directly** -- `ProcessAutoPayouts` creates `new AutoPayoutController()` which calls `parent::__construct()`. Controller constructors may depend on middleware or request context that does not exist in a CLI context.

13. **Payout reference collisions possible (largely mitigated)** -- The reference format appends `strtoupper(Str::random(4))`, which makes the cross-account clash described here effectively unreachable. Two residual notes: `Str::random` returns mixed-case alphanumerics and is then upper-cased, so the effective alphabet is `[A-Z0-9]` (36^4, not 62^4); and a collision still raises an unretried `QueryException` against the unique constraint rather than regenerating the reference.

### Medium

14. **No retry mechanism** -- Failed payouts are logged but not retried. Manual intervention is required.

15. **Currency assumed from helper** -- `currencyFormation(true)` returns the tenant's currency code, but there is no validation that this matches the Stripe account's default currency.

16. **`yearly` frequency in enum but not in `calculateNextExecution()`** -- The migration allows `yearly` as a frequency value, but the calculation method only handles `daily`, `weekly`, and `monthly`. A yearly rule would fall through to the default (add 1 day).

17. **Balance check race condition** -- The Stripe balance check and payout creation are not atomic. The balance could change between the check and the payout creation.

