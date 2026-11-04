<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Http\Requests\API\PartnerApi\ApiAuthRequest;
use App\Models\Customer;

class PartnerApiValidateKey
{
    public function handle(Request $request, Closure $next)
    {
        // Existing scheme: API key supplied in the request body as data.ApiKey.
        $authData = ApiAuthRequest::PartnerAuthorize($request->all());

        // OCTO-shaped scheme: the same reseller_api_key supplied as an
        // Authorization: Bearer token (so the combo endpoints can use octo-
        // identical transport, including header-only GET requests).
        if (!$authData) {
            $authData = $this->authorizeBearer($request);
        }

        if (!$authData) {
            return response()->json([
                'code' => Response::HTTP_UNAUTHORIZED,
                'message' => 'Unauthorized: Invalid or missing API key',
                'data' => []
            ], Response::HTTP_UNAUTHORIZED);
        }
        // Merge the authenticated affiliate data into the request
        $request['data'] = array_merge($request->get('data', []), $authData);

        return $next($request);
    }

    /**
     * Resolve the affiliate from an Authorization: Bearer <reseller_api_key>
     * header. Returns the same payload shape as ApiAuthRequest::PartnerAuthorize.
     */
    private function authorizeBearer(Request $request): ?array
    {
        $header = $request->header('Authorization', '');
        if (!str_starts_with($header, 'Bearer ')) {
            return null;
        }
        $token = trim(substr($header, 7));
        if ($token === '') {
            return null;
        }

        $customer = Customer::where('reseller_api_key', $token)->first();
        if (!$customer) {
            return null;
        }

        return [
            'AffiliateName'           => $customer->name,
            'AffiliateId'             => $customer->id,
            'AffiliateTourOperatorId' => $customer->tour_operator_id,
        ];
    }
}
