<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected $tenancy = true;

    protected function setUp(): void {
        parent::setUp();
        if ($this->tenancy) {
            $this->initializeTenancy();
        }
        Storage::fake('s3');
        Mail::fake();
    }

    public function initializeTenancy(){
        tenancy()->initialize('dixies');
        $this->allowNextYearBookingsInTests();
    }

    /**
     * stopNextYearBookings() (app/helpers.php) blocks bookings dated in the next real calendar
     * year unless the tenant has ALLOW_NEXT_YEAR_BOOKINGS enabled -- it's off by default in
     * production. Tests build fixture dates as offsets from "today" (e.g. addDays(190)), so any
     * of them can start throwing once that offset crosses into next year, for reasons that have
     * nothing to do with what the test is actually about. Force it on for the test tenant.
     */
    private function allowNextYearBookingsInTests(): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => 'ALLOW_NEXT_YEAR_BOOKINGS'],
            [
                'value'        => 1,
                'order_number' => 103,
                'comment'      => 'No - Disable, Yes - Enable : Allow bookings for next calendar year in production',
                'input_type'   => 'toggle',
                'updated_at'   => now(),
            ]
        );
    }

    protected function tearDown() :void {
        foreach (\Illuminate\Support\Facades\DB::getConnections() as $connection) {
            $connection->disconnect();
        }
        parent::tearDown();

        // Recreate a fresh app so any tearDownAfterClass() hooks in child
        // classes still have the DB facade available. Without this, PHPUnit's
        // class-level teardown runs against a flushed container and fails to
        // resolve "config" / "db". Only classes that actually declare their
        // own tearDownAfterClass() need this -- doing it unconditionally
        // means every test pays for a full app boot + tenancy re-init just
        // to support the minority of classes that use the hook.
        if ($this->classHasOwnTearDownAfterClass()) {
            $this->refreshApplication();
            if ($this->tenancy) {
                $this->initializeTenancy();
            }
        }
    }

    private function classHasOwnTearDownAfterClass(): bool
    {
        static $cache = [];
        $class = static::class;
        if (!array_key_exists($class, $cache)) {
            $cache[$class] = (new \ReflectionMethod($class, 'tearDownAfterClass'))
                ->getDeclaringClass()
                ->getName() !== \PHPUnit\Framework\TestCase::class;
        }
        return $cache[$class];
    }

}
