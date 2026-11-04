<?php

namespace App\Http\Middleware;

use Closure;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class ValidateBroadcastListeningRequests
{
	/**
	 * Handle an incoming request.
	 *
	 * @param \Illuminate\Http\Request $r
	 * @param \Closure $next
	 * @return mixed
	 */
	public function handle(Request $r, Closure $next)
	{
		$csrfToken = request()->header('csrfToken');
		$broadcastListeningToken = request()->header('broadcastListeningToken');

		try {
			$tokenInfo = Crypt::decrypt($broadcastListeningToken);
			// Log::info('broadcast listen req 1');

			if (!isset($tokenInfo['id'], $tokenInfo['uId'], $tokenInfo['uType'])) {
				// Log::info('e1');
				throw new AuthenticationException;
			}

			// Log::info('broadcast listen req 2');

			$token = \App\Models\Token::findOrFail($tokenInfo['id']);
			// Log::info('broadcast listen req 3');

			if (
				$token->user_id != $tokenInfo['uId'] ||
				$token->user_type != $tokenInfo['uType'] ||
				$token->csrf_token != $csrfToken
			) {
				// Log::info('e2');
				throw new AuthenticationException;
			}

			// Log::info('broadcast listen req 4');

			if (time() - strtotime($token->updated_at) > config('session.lifetime')) {
				$token->delete();
				throw new AuthenticationException;
			} else
				$token->update('updated_at', mysqlDT());

			return $next($r);
		} catch(Exception $exception) {
			Log::error($exception);
			return sendJsonErrMsg('Not Authorized', 403);
		}
	}
}
