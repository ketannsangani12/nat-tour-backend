<?php

namespace App\Providers;

use App\Events\BookingAffiliate;
use App\Events\BookingNew;
use App\Events\BookingRequestNotification;
use App\Listeners\Broadcast\BroadcastAffiliateBookingToTenantChannel;
use App\Listeners\Broadcast\BroadcastBookingRequestToTenantChannel;
use App\Listeners\Broadcast\BroadcastNewBookingToTenantChannel;
use App\Models\TourBooking;
use App\Observers\SentEmailObserver;
use App\Observers\TourBookingObserver;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use jdavidbakr\MailTracker\Model\SentEmail;

class EventServiceProvider extends ServiceProvider
{
	/**
	 * The event listener mappings for the application.
	 *
	 * @var array
	 */
	protected $listen = [
		// New tenant-channel bridge listeners (Phase 2 migration)
		BookingNew::class => [
			BroadcastNewBookingToTenantChannel::class,
		],
		BookingAffiliate::class => [
			BroadcastAffiliateBookingToTenantChannel::class,
		],
		BookingRequestNotification::class => [
			BroadcastBookingRequestToTenantChannel::class,
		],

		'Illuminate\Auth\Events\Authenticated' => [
			'App\Listeners\UserAuthenticated',
		],
		'jdavidbakr\MailTracker\Events\EmailSentEvent' => [
			'App\Listeners\MailTracker\EmailSent',
		],
		'jdavidbakr\MailTracker\Events\ViewEmailEvent' => [
			'App\Listeners\InvoiceEmailViewed',
			'App\Listeners\MailTracker\EmailViewed',
		],
		// 'jdavidbakr\MailTracker\Events\LinkClickedEvent' => [
		// 	'App\Listeners\MailTracker\EmailLinkClicked',
		// ],
		// 'Illuminate\Mail\Events\MessageSending' => [
		// 	'App\Listeners\LogSendingMessage',
		// ],
		// 'Illuminate\Mail\Events\MessageSent' => [
		// 	'App\Listeners\LogSentMessage',
		// ],
		// 'jdavidbakr\MailTracker\Events\EmailDeliveredEvent' => [
		// 	'App\Listeners\MailTracker\EmailDelivered',
		// ],
		// 'jdavidbakr\MailTracker\Events\ComplaintMessageEvent' => [
		// 	'App\Listeners\MailTracker\EmailComplaint',
		// ],
		// 'jdavidbakr\MailTracker\Events\PermanentBouncedMessageEvent' => [
		// 	'App\Listeners\MailTracker\BouncedEmail',
		// ],
		// 'stripe-webhooks::payment_intent.succeeded' => [
		// 	\App\Listeners\StripeWebhooks\PaymentIntentSucceeded::class,
		// ],
	];

	/**
	 * Register any events for your application.
	 *
	 * @return void
	 */
	public function boot()
	{
		SentEmail::observe(SentEmailObserver::class);
		TourBooking::observe(TourBookingObserver::class);
	}
}
