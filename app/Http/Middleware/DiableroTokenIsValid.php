<?php

namespace App\Http\Middleware;

use App\Models\Diablero;
use Closure;
use Illuminate\Http\Request;

class DiableroTokenIsValid
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken() ?: $request->input('token');
        $diablero = $token ? Diablero::where('auth_token', $token)->first() : null;

        if (!$diablero) {
            return response()->json(['errors' => [['code' => 'auth-001', 'message' => translate('messages.unauthorized')]]], 401);
        }
        if (!$diablero->status || $diablero->application_status !== 'approved') {
            return response()->json(['errors' => [['code' => 'auth-003', 'message' => translate('messages.your_account_has_been_suspended')]]], 401);
        }

        $request->attributes->set('diablero', $diablero);

        return $next($request);
    }
}
