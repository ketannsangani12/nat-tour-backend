<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Corrects a name collision discovered on `dixies`: an earlier, since-reverted "Payout
 * Simplification proof-of-concept" (built and reverted on a prior branch, across all tenant DBs
 * except `kens`) used this exact same settings key, `PLATFORM_HELD_PAYOUT_ENABLED`, and left a
 * row behind with value=1 (enabled) and a "proof-of-concept" comment.
 *
 * Because 2026_09_18_000001_add_platform_held_payout_setting.php only inserts when the key does
 * NOT already exist, its safe value=0 default was silently skipped on `dixies` — the stale
 * value=1 row from the old, unrelated feature was left in place instead. This meant the kill
 * switch has been reading as ENABLED on `dixies` this entire time, even though every actual
 * PaymentIntent-creation call site (StripeUtils.php) only started calling PaymentModeResolver
 * during this same work session — so no real charge was ever misrouted (confirmed: zero
 * `transactions.payment_mode = 'platform_held'` rows exist), but the very next 2027+ Card
 * booking on `dixies` would have been, before Phase A's lifecycle is even verified end-to-end
 * and before Phase B/C exist.
 *
 * Only `dixies` carries this stale row — every other tenant DB has no row at all yet (this
 * feature's migrations haven't been deployed fleet-wide), so those will get the correct
 * value=0 default from the original migration whenever it does run there. This migration
 * targets the specific stale case: a row whose comment doesn't match the current canonical
 * text gets reset to the safe default. A row that already carries the current comment (i.e.
 * a deliberate, intentional toggle made after this feature's own rollout began) is left alone.
 */
return new class extends Migration
{
    private const CANONICAL_COMMENT = 'No - Disable, Yes - Enable : 2027+ Card/Card-Reader/Payment-Link bookings settle fully on the platform balance instead of a destination charge (Payout Simplification)';

    public function up(): void
    {
        $row = DB::table('settings')->where('key', 'PLATFORM_HELD_PAYOUT_ENABLED')->first();

        if (!$row) {
            return;
        }

        if ($row->comment === self::CANONICAL_COMMENT) {
            // Already the current feature's own row (inserted fresh, or a deliberate later
            // toggle) — not the stale collision this migration exists to fix. Leave it alone.
            return;
        }

        DB::table('settings')
            ->where('key', 'PLATFORM_HELD_PAYOUT_ENABLED')
            ->update([
                'value' => 0,
                'comment' => self::CANONICAL_COMMENT,
            ]);
    }

    public function down(): void
    {
        // Intentionally irreversible: reverting would mean restoring an unrelated, already-
        // reverted feature's stale enabled state, which is never correct.
    }
};
