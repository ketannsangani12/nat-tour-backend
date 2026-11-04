# Partner API pricing

How money flows when CHD books a combo:

```
CHD -> Partner API -> tenant_lc (pkg 3) -> tenant_apm  (pkg 1)
                                        -> tenant_kens (pkg 1)
```

Notes from tracing the dev data on 2026-07-16.

## Two prices, not one

The lc rate and the origin rate are separate numbers. Nothing converts one into the other.

- The **lc rate** is what CHD pays lc. It comes from lc's own package (pkg 3), on CHD's affiliate row.
- The **origin rate** is what apm and kens each record. It comes from *their* package (pkg 1), on lc's affiliate row in that tenant.

lc's price never crosses the tenant line. Only the headcount does. Each origin looks up its own rate for the lc affiliate and prices the booking itself.

So the apm booking says $50 while CHD was quoted $118. Both are right. The difference is lc's margin.

If you change what CHD pays, apm and kens don't move. If you change apm's rate, CHD's quote doesn't move. The only thing tying them together is the booking reference.

## Why permit fee and tax were $0.00

Two separate reasons that looked identical.

**Permit fee was actually zero.** apm's rate row had `permit_fee = 0.00`, so the booking copied a zero. Setting it on apm's rate fixes it. Setting it on the lc rate does nothing, because apm never reads lc's rate.

**Tax is a naming problem.** Three places say "Tax" and they don't mean the same column:

| Where | What it reads |
|---|---|
| Rate form | `additional_charge` |
| OCTO API `includedTaxes` | `additional_charge` |
| Booking screen "Tax" column | `additional_tax_percentage` |

The rate form's Tax box writes `additional_charge`. The header says Tax (`generalRates.js:161`), the input under it is `data-name="additional_charge"` (`:199`). Typing 1.50 into that box is right. It's the only way to set tax, and the OCTO API reads it back as the Tax line.

The booking screen is the odd one out. Its Tax column is computed from `additional_tax_percentage`, which is a different column that no form writes. Out of 844 rate rows across lc, apm, kens and dixies, exactly one has it set. It's a dead field, so that column shows $0.00 no matter what you type.

With apm's rate at 50 / 1.50 / 15 a new booking shows:

```
Price (Per Person)  $50.00   <- rate only
Permit Fee          $15.00
Tax                  $0.00   <- reads the dead column
Total Cost          $66.50   <- rate + additional_charge + permit_fee
```

The columns add up to $65.00 but the charge is $66.50. The $1.50 is in the total and on no line. The money is right, the breakdown just doesn't mention it. Read Total Cost, not Tax, to see if tax landed.

This is documented, not patched. The form stays as it is.

## The partner fee does nothing here

Verified 2026-07-16. lc pkg 3 is flagged `is_partner_package = 1` and its rate rows carry `partner_fee_type = Fixed`, `partner_fee_amount = 5.00`. The OCTO API ignores all of it.

`buildRatesPricing()` (`OctoRepository:919`) quotes:

```
base = rate + additional_charge + permit_fee
```

No partner fee. It isn't referenced in `PartnerBookings` either, and the only mention in `OctoRepository` is line 2100 hardcoding `partner_fees_amount => 0`. Every lc pkg 3 booking so far stores `partner_fees_amount = 0.00` even though the rate says 5.00.

The field isn't dead everywhere. In the normal booking flow it's the intended margin mechanism — the customer pays it and it comes off the operator payout (`BookingService:3562-3571`, `ExtendBookingCardService:399`, `payoutAmount = total - partnerFees`). The OCTO path just never implemented it.

**So lc's margin is only the spread between lc's rate and the sum of the origin rates.** Raising the partner fee changes nothing for CHD. Raise lc's rate instead.

Trade-off of baking margin into the rate: it works, but lc's earnings stop being a visible number. `payoutAmount = total - partnerFees` will read $0 margin forever.

### Watch the spread

Nothing computes the margin and nothing warns when it goes negative. It's just the gap between two calculations that don't know about each other. Check it by hand after any rate change.

With the rates as of 2026-07-16:

```
PER ADULT
  CHD pays lc       118.00    (100 + 3 + 15; the $5 partner fee is ignored)
  lc owes apm        66.50    (50 + 1.50 + 15)
  lc owes kens       55.00    (55 + 0 + 0)
                   -------
  lc keeps           -3.50    <- underwater
```

lc's rate has to clear $121.50/adult just to break even. It was +$13.00 before apm's rate gained a $15 permit and $1.50 tax.

Also worth checking: lc's rate charges a $15 permit and apm's rate now charges $15 too. If that's the same physical permit, CHD is billed once and lc pays it twice.

## The trace, with real numbers

Booking `LC-0872de39-b6b5-4e2e-a80d-0803fda3618d`, 2 adults.

**1. CHD asks lc for a price.** lc pkg 3, CHD's rate row (id 10) is 100 + 3.00 + 15.00:

```
net    = 100 + 3.00 + 15.00        = 118.00
retail = net / (1 - ota_pct/100)   = 118.00      (CHD's ota_affiliate_percentage is 0)
```

CHD pays $118/adult, $236 total. Raise CHD's `ota_affiliate_percentage` and retail climbs while net stays put.

**2. lc picks which origins fire.** `partner_cross_tenant_origins` for pkg 3:

| Departure | Origin | Origin time | lc affiliate there |
|---|---|---|---|
| 12:00 | apm / pkg 1 | 12:00 | 93 |
| 12:00 | kens / pkg 1 | 14:00 | 35352 |
| 15:00 | kens / pkg 1 | 15:00 | 35352 |
| 15:00 | apm / pkg 1 | 16:30 | 93 |

The booked lc slot's time picks the departure. Only those origins fire.

**3. Each origin prices itself** (`PartnerBookings.php:450`):

```
lineTotal = (rate + additional_charge + permit_fee)
          * (1 + processing_charge_percentage/100)
          * qty
```

apm used rate id 8 (50 + 0 + 0) and booked APM-1094 at $100. kens used rate id 416 (55 + 0 + 0) and booked KEN-439 at $110.

apm's rate has since been changed to 50 / 1.50 / 15, so the next apm booking prices at (50 + 1.50 + 15) × 2 = $133. APM-1094 stays at $100 — the transact row is a snapshot, and editing a rate never rewrites old bookings.

**4. The money:**

```
CHD pays lc      $236.00
lc owes apm      $100.00
lc owes kens     $110.00
                 -------
lc keeps          $26.00
```

Nothing computes that $26. It's just the gap between two calculations that don't know about each other.

## Where each number comes from

Every number starts in one `tour_package_rates` row.

| Column | Form label | In CHD's quote | In the booking total |
|---|---|---|---|
| `rate` | Rate | yes | yes |
| `permit_fee` | Permit Fee | yes (not for group rates) | yes |
| `additional_charge` | Tax | yes | yes |
| `additional_tax_percentage` | not on any form | yes | no |
| `processing_charge_percentage` | not on the rate form | no | yes |
| `ota_affiliate_percentage` (on the customer) | — | yes, grosses up retail | no |

The two percentage columns are the trap. Neither is settable from the rate form, both are 0 in all live data, and each one is used by only one of the two calculations. If `additional_tax_percentage` ever gets set by an import or by hand, the quote and the booking will quietly disagree. Check before trusting either.

How an origin picks its rate: `affiliate_id = origin_customer_id`, Active, `custom_date_id IS NULL`, `scheduling_year` = the slot's year. Adult and infant only. No group rates, no custom-date rates. If nothing matches, the confirm throws, the saga unwinds the other origins, and the API returns 422.

Group rates skip permit fee and tax and multiply by 1 instead of headcount. The partner path never picks them.

## Where to look

| Question | File |
|---|---|
| What does CHD pay? | `app/Repositories/Octo/OctoRepository.php:810-847` |
| What does an origin record? | `app/Services/PartnerBookings.php:446-480` |
| Which origins fire? | `partner_cross_tenant_origins`, matched on `local_time_from` |
| Why does the screen show that? | `resources/views/users/common/tour-booking-info.blade.php:687` |
| Where do the numbers live? | `tour_package_rates` -> copied into `tour_bookings_transact` |

`tour_bookings_transact` is a snapshot. Rate edits don't touch existing bookings, which is what keeps history honest.
