<?php

namespace App\Http\Controllers;

use App\Services\MailTracker\BookingEmailService;
use App\Services\MailTracker\ClosureEmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use jdavidbakr\MailTracker\Model\SentEmail;
use jdavidbakr\MailTracker\Model\SentEmailUrlClicked;

class AmazonController extends Controller
{
    /**
     * emailNotifications.
     *
     * @return mixed
     */
    public function emailNotifications(Request $request)
    {
        $data = $request->json()->all();
        if ($data['Type'] == 'SubscriptionConfirmation') {
            Log::info('SubscriptionConfirmation');
            file_get_contents($data['SubscribeURL']);
        } elseif ($data['Type'] == 'Notification') {
            $message = json_decode($data['Message'], true);

            $headers = isset($message['mail']['headers']) ? $message['mail']['headers'] : [];
            $messageId = $message['mail']['messageId'] ?? null;
            $id = $emailType = $tenantId = 0;
            foreach ($headers as $header) {
                if ($header['name'] === 'X-Model-ID') {
                    $id = $header['value'];
                } elseif ($header['name'] === 'X-Model-TP') {
                    $emailType = $header['value'];
                } elseif ($header['name'] === 'X-Model-TID') {
                    $tenantId = $header['value'];
                }
            }

            if (isset($message['notificationType']) && $id && tenant()->id == $tenantId) {

                $methods = [
                    'Bounce' => 'bounce',
                    'Complaint' => 'complaint',
                    'Delivery' => 'delivery'
                ];

                $type = $methods[$message['notificationType']] ?? null;

                $reason = null;
                if ($type === 'bounce' && isset($message['bounce'])) {
                    $diagnostic = $message['bounce']['bouncedRecipients'][0]['diagnosticCode'] ?? '';
                    $reason = $diagnostic;
                }

                if ($type && $emailType == 1) {
                    ClosureEmailService::run($id, $type, $messageId, $reason);
                } else if ($type && ($emailType == 2 || $emailType == 10 || $emailType == 11 || $emailType == 4)) {
                    BookingEmailService::run($id, $type, $messageId, $reason);
                }
            }
        }

        return response('OK', 200);
    }

    /**
     * Index.
     *
     * @return mixed
     */
    public function getIndex()
    {
        session(['mail-tracker-index-page' => request()->page]);
        $search = session('mail-tracker-index-search');

        $query = SentEmail::query()->select([
            'id',
            'tour_booking_id',
            'recipient_name',
            'recipient_email',
            'subject',
            'opens',
            'clicks',
            'opened_at',
            'clicked_at',
            'created_at',
            'meta',
        ]);

        if ($search) {
            $terms = explode(" ", $search);
            foreach ($terms as $term) {
                $query->where(function ($q) use ($term) {
                    $q->where('sender_name', 'like', '%' . $term . '%')
                        ->orWhere('sender_email', 'like', '%' . $term . '%')
                        ->orWhere('recipient_name', 'like', '%' . $term . '%')
                        ->orWhere('recipient_email', 'like', '%' . $term . '%')
                        ->orWhere('subject', 'like', '%' . $term . '%')
                        ->orWhere('tour_booking_id', 'like', '%' . $term . '%');
                });
            }
        }

        $query->orderBy('created_at', 'desc');

        $emails = $query->paginate(config('mail-tracker.emails-per-page'));

        $vars = [
            'page_title' => 'Email Manager'
        ];

        return view('mail.tracker.index', $vars)
            ->with('emails', $emails);
    }

    /**
     * Sent email search
     */
    public function postSearch(Request $request)
    {
        session(['mail-tracker-index-search' => $request->search]);

        return redirect(route(routePrefix() . '.email-tracker'));
    }

    /**
     * Clear search
     */
    public function clearSearch()
    {
        session(['mail-tracker-index-search' => null]);

        return redirect(route(routePrefix() . '.email-tracker'));
    }

    /**
     * Show Email.
     *
     * @return mixed
     */
    public function getShowEmail($id)
    {
        $email = SentEmail::where('id', $id)->first();

        return view('mail.tracker.show')
            ->with('email', $email);
    }

    /**
     * Url Detail.
     *
     * @return mixed
     */
    public function getUrlDetail($id)
    {
        $details = SentEmailUrlClicked::where('sent_email_id', $id)->get();

        if (!$details) {
            return back();
        }

        $vars = [
            'page_title' => 'Email Manager'
        ];

        return view('mail.tracker.url-detail', $vars)
            ->with('details', $details);
    }
}
