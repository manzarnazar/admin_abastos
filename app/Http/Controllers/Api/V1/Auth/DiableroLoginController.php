<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\CentralLogics\DiableroLogic;
use App\CentralLogics\Helpers;
use App\CentralLogics\SMS_module;
use App\Http\Controllers\Controller;
use App\Models\Diablero;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Modules\Gateways\Traits\SmsGateway;

class DiableroLoginController extends Controller
{
    private const RESET_OTP_TTL_SECONDS = 600;
    private const RESET_RESEND_SECONDS = 60;

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required',
            'password' => 'required|min:6',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $diablero = Diablero::where('phone', $request->phone)->first();
        if (!$diablero || !Hash::check($request->password, $diablero->password)) {
            return response()->json(['errors' => [['code' => 'auth-001', 'message' => translate('Incorrect_credential,_please_try_again')]]], 401);
        }
        if ($diablero->application_status !== 'approved') {
            return response()->json(['errors' => [['code' => 'auth-003', 'message' => translate('messages.Your_account_is_not_approved_yet.')]]], 401);
        }
        if (!$diablero->status) {
            return response()->json(['errors' => [['code' => 'auth-003', 'message' => translate('messages.your_account_has_been_suspended')]]], 401);
        }

        $token = Str::random(120);
        $diablero->auth_token = $token;
        $diablero->save();

        return response()->json([
            'token' => $token,
            'topic' => DiableroLogic::zoneTopic($diablero->zone_id),
        ], 200);
    }

    public function logout(Request $request)
    {
        $diablero = $request->attributes->get('diablero');
        $diablero->update(['auth_token' => null, 'fcm_token' => null, 'active' => 0]);

        return response()->json(['message' => translate('messages.logged_out_successfully')], 200);
    }

    private function resetKey(string $phone): string
    {
        return 'diablero:' . $phone;
    }

    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), ['phone' => 'required']);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $diablero = Diablero::where('phone', $request->phone)->first();
        if (!$diablero) {
            return response()->json(['errors' => [['code' => 'not-found', 'message' => translate('messages.phone_number_not_found')]]], 404);
        }

        $key = $this->resetKey($diablero->phone);
        $existing = DB::table('password_resets')->where('email', $key)->first();
        if ($existing && Carbon::parse($existing->created_at)->diffInSeconds() < self::RESET_RESEND_SECONDS) {
            return response()->json(['errors' => [['code' => 'otp', 'message' => translate('messages.please_try_again_after_') . self::RESET_RESEND_SECONDS . ' ' . translate('messages.seconds')]]], 405);
        }

        $token = (string) random_int(100000, 999999);
        DB::table('password_resets')->updateOrInsert(['email' => $key], ['token' => $token, 'created_at' => now()]);

        $response = null;
        try {
            $response = addon_published_status('Gateways') == 1
                ? SmsGateway::send($diablero->phone, $token)
                : SMS_module::send($diablero->phone, $token);
        } catch (\Throwable $e) {
            info($e->getMessage());
        }

        if ($response !== 'success') {
            return response()->json(['errors' => [['code' => 'otp', 'message' => translate('messages.failed_to_send_sms')]]], 405);
        }

        return response()->json(['message' => translate('messages.Otp_Successfully_Sent_To_Your_Phone')], 200);
    }

    private function findValidReset(string $phone, string $token)
    {
        $row = DB::table('password_resets')->where('email', $this->resetKey($phone))->first();
        if (!$row || !hash_equals((string) $row->token, $token) || Carbon::parse($row->created_at)->diffInSeconds() > self::RESET_OTP_TTL_SECONDS) {
            return null;
        }
        return $row;
    }

    public function verifyToken(Request $request)
    {
        $validator = Validator::make($request->all(), ['phone' => 'required', 'reset_token' => 'required']);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        if (!$this->findValidReset($request->phone, (string) $request->reset_token)) {
            return response()->json(['errors' => [['code' => 'reset_token', 'message' => translate('messages.invalid_token')]]], 400);
        }

        return response()->json(['message' => translate('messages.token_found_you_can_proceed')], 200);
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required',
            'reset_token' => 'required',
            'password' => ['required', Password::min(8)->letters()->numbers()],
            'confirm_password' => 'required|same:password',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        if (!$this->findValidReset($request->phone, (string) $request->reset_token)) {
            return response()->json(['errors' => [['code' => 'reset_token', 'message' => translate('messages.invalid_token')]]], 400);
        }

        Diablero::where('phone', $request->phone)->update(['password' => bcrypt($request->password), 'auth_token' => null]);
        DB::table('password_resets')->where('email', $this->resetKey($request->phone))->delete();

        return response()->json(['message' => translate('messages.password_changed_successfully')], 200);
    }
}
