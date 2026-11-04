# Auto-Payout Manual Testing Plan

## Prerequisites

Before starting, ensure the following are in place:

- [ ] Stripe **test mode** keys configured in `.env` / `config/constants.php`
- [ ] At least one connected Express account with `stripe_destination_id` set on a `tour_packages` record
- [ ] The connected account has a test bank account attached (Stripe Dashboard > Connect > Account > External Accounts)
- [ ] Test bookings exist with `Completed` card transactions linked to that package
- [ ] The bookings' tour slots have dates within the expected payout cycle window
- [ ] `tour_bookings_master.payout_made = 0` for those bookings
- [ ] Access to Stripe Dashboard (test mode) at dashboard.stripe.com
- [ ] SSH access to run artisan commands on the server / docker host
- [ ] Database access to inspect records directly

**Connected account ID for testing:** `___________________________`
**Tour package ID:** `___________________________`
**Test booking IDs:** `___________________________`

---

## Test Group A: Rule Configuration (Low Risk -- UI Only)

### A1. Create a weekly rule
1. Navigate to Admin > Auto Payout
2. Create a rule: name="Weekly Payout", frequency=weekly, day=Monday, time=09:00, minimum=$50
3. Save the rule

```
[ ] PASS / [ ] FAIL -- Rule saves successfully
Notes:
Verify: next_execution_at is next Monday at 09:00 in tenant timezone
DB value of next_execution_at: ___________________________
```

### A2. Disable the rule
1. Set enabled=false and save
2. Run: `docker exec nat-app php artisan tenants:run autopayout:process -- --tenants=dixies`

```
[ ] PASS / [ ] FAIL -- Scheduler skips the disabled rule (output says "No payout rules are due")
Notes:
```

### A3. Verify requires_approval defaults to true
1. Re-enable the rule
2. Check the database directly:

```sql
SELECT requires_approval FROM auto_payout_rules LIMIT 1;
```

```
[ ] PASS / [ ] FAIL -- requires_approval = 1 (true)
Notes:
```

---

## Test Group B: Dry Run (Zero Risk)

### B1. Run dry-run command
```bash
docker exec nat-app php artisan tenants:run autopayout:process -- --dry-run --tenants=dixies
```

```
[ ] PASS / [ ] FAIL -- Command outputs "DRY RUN: Would process rule..." without errors
Output:
```

### B2. Verify no records created
```sql
SELECT COUNT(*) FROM pending_payouts;
SELECT COUNT(*) FROM auto_payouts;
```

```
[ ] PASS / [ ] FAIL -- Both counts are 0 (or same as before the run)
Notes:
```

### B3. Verify no Stripe API calls
Check Stripe Dashboard > Developers > Logs for any API calls during the dry run window.

```
[ ] PASS / [ ] FAIL -- No payout-related API calls in Stripe logs
Notes:
```

---

## Test Group C: Pending Payout Creation (Low Risk -- No Stripe Payout)

**Precondition:** Rule exists, is enabled, `requires_approval = true`, `next_execution_at` is in the past or NULL.

### C1. Run the command (live)
```bash
docker exec nat-app php artisan tenants:run autopayout:process -- --tenants=dixies
```

```
[ ] PASS / [ ] FAIL -- Command completes without errors
Output:
```

### C2. Verify pending_payouts record
```sql
SELECT id, payout_reference, stripe_account_id, total_amount, platform_fee,
       net_amount, booking_count, status, created_at
FROM pending_payouts
ORDER BY created_at DESC LIMIT 5;
```

```
[ ] PASS / [ ] FAIL -- Record created with status = 'pending_approval'
payout_reference: ___________________________
stripe_account_id matches tour package: [ ] Yes / [ ] No
net_amount: ___________________________
booking_count: ___________________________
```

### C3. Verify payout_approval_logs
```sql
SELECT * FROM payout_approval_logs
WHERE pending_payout_id = <ID_FROM_C2>
ORDER BY created_at;
```

```
[ ] PASS / [ ] FAIL -- Log entry exists with action = 'created'
Notes:
```

### C4. Verify pending_payout_items
```sql
SELECT * FROM pending_payout_items
WHERE pending_payout_id = <ID_FROM_C2>;
```

```
[ ] PASS / [ ] FAIL -- One row per booking, amounts look reasonable
Row count: ___________________________
```

### C5. Verify next_execution_at was updated
```sql
SELECT next_execution_at, last_executed_at FROM auto_payout_rules LIMIT 1;
```

```
[ ] PASS / [ ] FAIL -- next_execution_at moved forward, last_executed_at updated
new next_execution_at: ___________________________
```

### C6. Verify NO Stripe payout was created
Check Stripe Dashboard > Connect > [Account] > Payouts

```
[ ] PASS / [ ] FAIL -- No new payout in Stripe Dashboard
Notes:
```

### C7. Verify bookings NOT marked as paid out
```sql
SELECT id, payout_made FROM tour_bookings_master
WHERE id IN (<BOOKING_IDS_FROM_C4>);
```

```
[ ] PASS / [ ] FAIL -- All payout_made = 0
Notes:
```

---

## Test Group D: Manual Calculation Verification (Critical -- Verify the Math)

### D1. Run the raw transaction query
For the pending payout from Test Group C, run the source query manually:

```sql
SELECT
    tra.tour_booking_id,
    tra.amount,
    tra.fee_amount,
    tra.status,
    tra.via,
    tra.request_from
FROM transactions tra
JOIN tour_bookings_master tbm ON tbm.id = tra.tour_booking_id
JOIN tour_slot_times tst ON tst.id = tbm.tour_slot_id
JOIN tour_packages tp ON tp.id = tbm.tour_package_id
WHERE tra.status != 'Failed'
  AND tra.via = 'Card'
  AND tra.request_from != 'Insert'
  AND tbm.comments NOT LIKE '#%'
  AND tbm.payout_made = 0
  AND tp.stripe_destination_id = '<STRIPE_ACCOUNT_ID>'
ORDER BY tra.tour_booking_id, tra.status;
```

```
[ ] PASS / [ ] FAIL -- Query returns results matching the pending payout
Row count: ___________________________
```

### D2. Manually compute net per booking
For each booking, calculate:
```
net = payment_amount - (refund_amount - refund_fee) - payment_fee
```

```
[ ] PASS / [ ] FAIL -- Manual calculation matches pending_payout_items.net_amount for each booking
Notes (show work for at least 2 bookings):

Booking ___: payment=$___ (fee=$___) refund=$___ (fee=$___) => net=$___
Booking ___: payment=$___ (fee=$___) refund=$___ (fee=$___) => net=$___
```

### D3. Verify total matches
Sum all booking nets. Compare to `pending_payouts.net_amount`.

```
[ ] PASS / [ ] FAIL -- Totals match exactly
Manual sum: ___________________________
DB net_amount: ___________________________
Difference: ___________________________
```

### D4. Check for Initialized transactions
```sql
SELECT tra.tour_booking_id, tra.amount, tra.status
FROM transactions tra
JOIN tour_bookings_master tbm ON tbm.id = tra.tour_booking_id
JOIN tour_packages tp ON tp.id = tbm.tour_package_id
WHERE tra.status = 'Initialized'
  AND tra.via = 'Card'
  AND tbm.payout_made = 0
  AND tp.stripe_destination_id = '<STRIPE_ACCOUNT_ID>';
```

```
[ ] PASS / [ ] FAIL -- No Initialized transactions included (or document if present)
Notes:
```

---

## Test Group E: Approval Workflow (Medium Risk)

### E1. View pending payout in approval queue
Navigate to Admin > Auto Payout > Approval Queue.

```
[ ] PASS / [ ] FAIL -- Pending payout appears in the list
Notes:
```

### E2. View payout details
Click on the pending payout to view details.

```
[ ] PASS / [ ] FAIL -- Booking breakdown is displayed with correct amounts
Notes:
```

### E3. Approve a payout
Click "Approve" on the pending payout.

```
[ ] PASS / [ ] FAIL -- Status changes to 'approved'
```

Verify in DB:
```sql
SELECT status, approved_at, approved_by FROM pending_payouts WHERE id = <ID>;
SELECT * FROM payout_approval_logs WHERE pending_payout_id = <ID> AND action = 'approved';
```

```
[ ] PASS / [ ] FAIL -- approved_at and approved_by set, log entry created
Notes:
```

### E4. Reject a different payout
If you have a second pending payout, reject it with a reason.

```
[ ] PASS / [ ] FAIL -- Status changes to 'rejected', reason stored
```

Verify in DB:
```sql
SELECT status, rejected_at, rejected_by, rejection_reason FROM pending_payouts WHERE id = <ID>;
```

```
[ ] PASS / [ ] FAIL -- All rejection fields populated
rejection_reason: ___________________________
```

### E5. Bulk approve
Create multiple pending payouts (run the command after resetting `next_execution_at`), then bulk approve.

```
[ ] PASS / [ ] FAIL -- All selected payouts change to 'approved'
Count approved: ___________________________
```

### E6. Approve an already-approved payout
Try to approve a payout that is already approved.

```
[ ] PASS / [ ] FAIL -- Returns error (HTTP 400 or similar), status unchanged
Error message: ___________________________
```

### E7. Approve an already-rejected payout
Try to approve a payout that was rejected.

```
[ ] PASS / [ ] FAIL -- Returns error, status remains 'rejected'
Error message: ___________________________
```

---

## Test Group F: Payout Execution -- THE DANGEROUS PART

**BEFORE RUNNING:** Note the connected account's current Stripe balance.

**Starting Stripe balance:** `$___________________________`

### F1. Execute approved payouts
```bash
docker exec nat-app php artisan tenants:run autopayout:process -- --tenants=dixies
```

```
[ ] PASS / [ ] FAIL -- Command reports payout executed successfully
Output:
```

### F2. Check Stripe Dashboard immediately
Go to Stripe Dashboard > Connect > [Account] > Payouts.

```
[ ] PASS / [ ] FAIL -- New Payout appears in Stripe
Stripe Payout ID: ___________________________
Amount: ___________________________
Currency: ___________________________
Destination (bank): ___________________________
```

Verify metadata:
```
[ ] PASS / [ ] FAIL -- Metadata contains pending_payout_id and payout_reference
```

### F3. Check database records

**auto_payouts:**
```sql
SELECT * FROM auto_payouts ORDER BY created_at DESC LIMIT 1;
```

```
[ ] PASS / [ ] FAIL -- Record created with status = 'completed'
payout_reference: ___________________________
net_amount: ___________________________
```

**auto_payout_items:**
```sql
SELECT * FROM auto_payout_items WHERE payout_id = <PAYOUT_ID>;
```

```
[ ] PASS / [ ] FAIL -- Items match the original pending payout items
Item count: ___________________________
```

**Bookings marked as paid out:**
```sql
SELECT id, payout_made FROM tour_bookings_master
WHERE id IN (<BOOKING_IDS>);
```

```
[ ] PASS / [ ] FAIL -- All payout_made = 1
Notes:
```

### F4. Verify pending payout was deleted (known bug)
```sql
SELECT * FROM pending_payouts WHERE id = <ORIGINAL_PENDING_ID>;
SELECT * FROM payout_approval_logs WHERE pending_payout_id = <ORIGINAL_PENDING_ID>;
```

```
[ ] PASS / [ ] FAIL -- PendingPayout record is deleted (this is the current behavior -- it is a bug)
[ ] PASS / [ ] FAIL -- Approval logs are also deleted (cascade delete -- this is a bug, audit trail lost)
Notes:
```

### F5. Verify no duplicate payout
Run the command again immediately:
```bash
docker exec nat-app php artisan tenants:run autopayout:process -- --tenants=dixies
```

```
[ ] PASS / [ ] FAIL -- No new payout created (bookings already marked payout_made=1)
Stripe Payout count (should be exactly 1): ___________________________
Notes:
```

---

## Test Group G: Edge Cases (Critical)

### G1. Insufficient Stripe balance
Set `minimum_amount` to 0 (so the rule triggers) but ensure the connected account has insufficient Stripe balance.

```bash
docker exec nat-app php artisan tenants:run autopayout:process -- --tenants=dixies
```

```
[ ] PASS / [ ] FAIL -- Payout fails gracefully with "Insufficient balance" error logged
[ ] PASS / [ ] FAIL -- No Stripe payout created
Notes:
```

### G2. No bank account on connected account
Use a connected account that has no external bank account attached.

```
[ ] PASS / [ ] FAIL -- Error logged: "No bank account found for account..."
[ ] PASS / [ ] FAIL -- No Stripe payout attempted
Notes:
```

### G3. Below minimum threshold
Set `minimum_amount` higher than the calculated amount for an account.

```
[ ] PASS / [ ] FAIL -- Account is skipped with log message about amount below minimum
Notes:
```

### G4. Double execution race condition
Open two terminal sessions. Run the following simultaneously:

**Terminal 1:**
```bash
docker exec nat-app php artisan tenants:run autopayout:process -- --tenants=dixies
```

**Terminal 2:**
```bash
docker exec nat-app php artisan tenants:run autopayout:process -- --tenants=dixies
```

```
[ ] PASS / [ ] FAIL -- Only ONE payout created (not two)
Notes (this will likely FAIL due to lack of distributed locking -- document the result):
Number of payouts created: ___________________________
Number of Stripe payouts: ___________________________
```

### G5. HTTP + scheduler race condition
1. Approve a payout in the UI
2. Simultaneously: POST to `/admin/auto-payout/setup-stripe` in one browser tab AND run `autopayout:process` in the terminal

```
[ ] PASS / [ ] FAIL -- Only ONE payout created
Notes (this will likely FAIL -- document the result):
```

### G6. Negative net amount
Create a booking where refunds exceed payments (e.g., full refund + partial refund on different transactions).

```
[ ] PASS / [ ] FAIL -- Booking is either skipped or handled gracefully (NOT sent to Stripe as negative)
Notes:
net_amount calculated: ___________________________
Stripe behavior: ___________________________
```

---

## Test Group H: Post-Execution Verification

### H1. New booking picked up after previous payout
After a successful payout, create a NEW booking with a completed card payment for the same tour package.

Run dry-run:
```bash
docker exec nat-app php artisan tenants:run autopayout:process -- --dry-run --tenants=dixies
```

```
[ ] PASS / [ ] FAIL -- Only the NEW booking is picked up (not already-paid-out ones)
Notes:
```

### H2. Post-payout refund handling
Issue a refund on a booking that was already paid out (`payout_made = 1`).

Run the payout flow again.

```
[ ] PASS / [ ] FAIL -- Document behavior: is the refund deducted from the next payout?
Notes (known gap -- refund is NOT deducted from future payouts since payout_made=1 excludes the booking):
```

---

## Summary

| Group | Tests | Pass | Fail | Notes |
|-------|-------|------|------|-------|
| A: Rule Configuration | 3 | | | |
| B: Dry Run | 3 | | | |
| C: Pending Payout Creation | 7 | | | |
| D: Manual Calculation | 4 | | | |
| E: Approval Workflow | 7 | | | |
| F: Payout Execution | 5 | | | |
| G: Edge Cases | 6 | | | |
| H: Post-Execution | 2 | | | |
| **Total** | **37** | | | |

**Tested by:** ___________________________
**Date:** ___________________________
**Environment:** [ ] Staging / [ ] Production
**Stripe mode:** [ ] Test / [ ] Live
