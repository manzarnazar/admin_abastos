<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\DeliveryMan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;

class DeliveryManLoginController extends Controller
{
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required',
            'password' => 'required|min:6'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $data = [
            'phone' => $request->phone,
            'password' => $request->password
        ];

        if (auth('delivery_men')->attempt($data)) {
            $token = Str::random(120);

            if(auth('delivery_men')->user()->application_status != 'approved')
            {
                return response()->json([
                    'errors' => [
                        ['code' => 'auth-003', 'message' => translate('messages.Your_account_is_not_approved_yet.')]
                    ]
                ], 401);
            }
            else if(!auth('delivery_men')->user()->status)
            {
                $errors = [];
                array_push($errors, ['code' => 'auth-003', 'message' => translate('messages.your_account_has_been_suspended')]);
                return response()->json([
                    'errors' => $errors
                ], 401);
            }

            $delivery_man =  DeliveryMan::where(['phone' => $request['phone']])->first();
            $delivery_man->auth_token = $token;
            $delivery_man->save();

            $topic = 'restaurant_dm_'.$delivery_man?->store_id;
            if(isset($delivery_man->zone)){
                if($delivery_man->vehicle_id){

                    $topic = 'delivery_man_'.$delivery_man->zone->id.'_'.$delivery_man->vehicle_id;
                }else{
                    $topic = $delivery_man->type=='zone_wise'?$delivery_man->zone->deliveryman_wise_topic:'restaurant_dm_'.$delivery_man->store_id;
                }
                $zone_topic =  $delivery_man->type=='zone_wise'?$delivery_man->zone->deliveryman_wise_topic.'_push':'';
            }
            return response()->json(['token' => $token, 'topic'=> isset($topic)?$topic:'No_topic_found', 'zone_topic' =>  $zone_topic?? ''], 200);
        } else {
            $errors = [];
            array_push($errors, ['code' => 'auth-001', 'message' => translate('Incorrect_credential,_please_try_again')]);
            return response()->json([
                'errors' => $errors
            ], 401);
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'f_name' => 'required',
            'email' => 'required|unique:delivery_men',
            'phone' => 'required|regex:/^([0-9\s\-\+\(\)]*)$/|min:10|unique:delivery_men',
            'password' => ['required', Password::min(8)->mixedCase()->letters()->numbers()->symbols()->uncompromised()],
            'zone_id' => 'required',
            'vehicle_id' => 'required',
            'earning' => 'required',
            'curp_rfc_image' => 'required|file|max:2048|mimes:jpeg,jpg,png,gif,webp,pdf',
            'ine_image' => 'required|file|max:2048|mimes:jpeg,jpg,png,gif,webp',
            'ine_back_image' => 'required|file|max:2048|mimes:jpeg,jpg,png,gif,webp',
            'cofepris_image' => 'nullable|file|max:2048|mimes:jpeg,jpg,png,gif,webp,pdf',
        ], [
            'f_name.required' => translate('messages.first_name_is_required'),
            'zone_id.required' => translate('messages.select_a_zone'),
            'earning.required' => translate('messages.select_dm_type'),
            'vehicle_id.required' => translate('messages.select_a_vehicle'),
            'password.required' => translate('The password is required'),
            'password.min_length' => translate('The password must be at least :min characters long'),
            'password.mixed' => translate('The password must contain both uppercase and lowercase letters'),
            'password.letters' => translate('The password must contain letters'),
            'password.numbers' => translate('The password must contain numbers'),
            'password.symbols' => translate('The password must contain symbols'),
            'password.uncompromised' => translate('The password is compromised. Please choose a different one'),
            'curp_rfc_image.required' => translate('messages.CURP_RFC_document_is_required'),
            'ine_image.required' => translate('messages.INE_image_is_required'),
            'ine_back_image.required' => translate('messages.INE_back_image_is_required'),
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)],403);
        }

        if ($request->has('image')) {
            $image_name = Helpers::upload('delivery-man/', 'png', $request->file('image'));
        } else {
            $image_name = 'def.png';
        }

        $encodeDocument = function ($file) {
            $extension = $file->getClientOriginalExtension();
            $filename = Helpers::upload('delivery-man/', $extension, $file);
            return json_encode([['img' => $filename, 'storage' => Helpers::getDisk()]]);
        };

        $curp_rfc_image = $encodeDocument($request->file('curp_rfc_image'));
        $ine_image = $encodeDocument($request->file('ine_image'));
        $ine_back_image = $encodeDocument($request->file('ine_back_image'));
        $cofepris_image = $request->hasFile('cofepris_image') ? $encodeDocument($request->file('cofepris_image')) : null;

        $dm = New DeliveryMan();
        $dm->f_name = $request->f_name;
        $dm->l_name = $request->l_name;
        $dm->email = $request->email;
        $dm->phone = $request->phone;
        $dm->vehicle_id = $request->vehicle_id;
        $dm->image = $image_name;
        $dm->curp_rfc_image = $curp_rfc_image;
        $dm->ine_image = $ine_image;
        $dm->ine_back_image = $ine_back_image;
        $dm->cofepris_image = $cofepris_image;
        $dm->status = 0;
        $dm->active = 0;
        $dm->application_status = 'pending';
        $dm->zone_id = $request->zone_id;
        $dm->earning = $request->earning;
        $dm->password = bcrypt($request->password);
        $dm->curp_rfc = $request->curp_rfc;
        $dm->driver_license_image = $request->file('driver_license_image') ? Helpers::upload('delivery-man/', $request->file('driver_license_image')->getClientOriginalExtension(), $request->file('driver_license_image')) : null;
        $dm->curp_rfc_certificate_image = $request->file('curp_rfc_certificate_image') ? Helpers::upload('delivery-man/', $request->file('curp_rfc_certificate_image')->getClientOriginalExtension(), $request->file('curp_rfc_certificate_image')) : null;
        $dm->cofepris_document_image = $request->file('cofepris_document_image') ? Helpers::upload('delivery-man/', $request->file('cofepris_document_image')->getClientOriginalExtension(), $request->file('cofepris_document_image')) : null;

        $dm->save();
        try{
            $admin= Admin::where('role_id', 1)->first();
            $mail_status = Helpers::get_mail_status('registration_mail_status_dm');
            if(config('mail.status') && $mail_status == '1' && Helpers::getNotificationStatusData('deliveryman','deliveryman_registration','mail_status')){
                Mail::to($request->email)->send(new \App\Mail\DmSelfRegistration('pending', $dm->f_name.' '.$dm->l_name));
            }
            $mail_status = Helpers::get_mail_status('dm_registration_mail_status_admin');
            if(config('mail.status') && $mail_status == '1' && Helpers::getNotificationStatusData('admin','deliveryman_self_registration','mail_status')){
                Mail::to($admin['email'])->send(new \App\Mail\DmRegistration('pending', $dm->f_name.' '.$dm->l_name));
            }
        }catch(\Exception $ex){
            info($ex->getMessage());
        }

        return response()->json(['message' => translate('messages.deliveryman_added_successfully')], 200);
    }
}
