<?php

namespace App\Services\Network\Booking;

use App\Constants\Constants;
use App\Models\Customer;
use App\Services\Network\NetworkService;
use App\Models\TourPackage;
use App\Models\TourBooking;
use App\Services\Network\Affiliate\AffiliateService;
use App\Services\Network\Booking\BookingTransactService;
use App\Services\Network\Stripe\StripeService;
use App\Services\Network\Customer\CustomerService;
use Illuminate\Http\Request;

class BookingService extends NetworkService
{
    /**
     * @var AffiliateService
     */
    protected $affiliateService;

    /**
     * @var CustomerService
     */
    protected $customerService;

    /**
     * @var BookingTransactService
     */
    protected $bookingTransactService;

    /**
     * @var StripeService
     */
    protected $stripeService;

    public function __construct(
        AffiliateService $affiliateService,
        CustomerService $customerService,
        BookingTransactService $bookingTransactService,
        StripeService $stripeService
    ) {
        $this->affiliateService = $affiliateService;
        $this->customerService = $customerService;
        $this->bookingTransactService = $bookingTransactService;
        $this->stripeService = $stripeService;
    }

    /**
     * Create bookings
     * 
     * @param array $cartItem
     * @param array $customerInfo
     * 
     * @return array
     */
    public function createBookingForTenant(array $cartItem, array $customerInfo): array
    {
        // find package
        $package = TourPackage::findOrFail($cartItem['packageId']);

        // create or fetch customer
        // $affiliate = $this->affiliateService->getAffiliate();
        $customer = $this->customerService->findOrCreateCustomer($customerInfo);

        // create booking master entry
        $bookingMaster = $this->createBookingMaster($cartItem, $package, $customer);

        // create transactions (rate groups)
        $this->bookingTransactService->createBookingTransactions($bookingMaster, $cartItem, $package, $customer);

        // process paymentIntent metadata (via stripe)
        // $this->stripeService->attachPaymentIntent($customer, $bookingMaster->id, $cartItem['paymentIntentId']);

        // optional: handle payouts to partner if applicable

        return [
            'bookingId' => $bookingMaster->id,
            'status' => 'Booked'
        ];
    }

    /**
     * Create booking master entry
     * 
     * @param array $cartItem
     * @param TourPackage $package
     * @param Customer $customer
     * 
     * @return TourBooking
     */
    public function createBookingMaster(array $cartItem, TourPackage $package, Customer $customer): TourBooking
    {
        $bookingMaster = new TourBooking();
        $bookingMaster->tour_package_id = $package->id;
        $bookingMaster->customer_id = $customer->id;
        $bookingMaster->tour_promotion_id = $cartItem['appliedPromoCode']['id'];
        $bookingMaster->tour_slot_id = $cartItem['selectedSlot']['id'];
        // $bookingMaster->cart_id = count($bookingRequest->items) > 1 ? $cartId : null;
        $bookingMaster->name = $customer->name;
        $bookingMaster->phone_number = $customer->phone_number;
        $bookingMaster->email = $customer->email;
        // $bookingMaster->tourists = array_sum($bookingRequest['people_group']);
        // $bookingMaster->comments = $bookingRequest['comment'] ?? "";
        // $bookingMaster->discount2_value = $bookingRequest['discount2_value'];
        // $bookingMaster->discount2_percentage = $bookingRequest['discount2_percentage'];
        // $bookingMaster->service_commission = $bookingRequest['fees'];
        // $bookingMaster->total = $bookingRequest['total'];
        // $bookingMaster->booking_total = $bookingRequest['total'];
        $bookingMaster->created_by = $customer->id;
        $bookingMaster->created_by_user_type = 'Customer';
        $bookingMaster->status = 'Cancelled';
        $bookingMaster->is_group_booking = $package->is_group_rate_enabled;
        $bookingMaster->is_partner_booking = $package->is_partner_package;
        $bookingMaster->visitor = Request::getClientIp();
        $bookingMaster->request_from = Constants::FRONTEND;
        $bookingMaster->save();

        return $bookingMaster;
    }
}
