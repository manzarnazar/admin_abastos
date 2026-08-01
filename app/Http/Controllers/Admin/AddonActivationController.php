<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Services\AddonService;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;

class AddonActivationController extends Controller
{
    public function __construct(
        private readonly AddonService $addonService,
    )
    {
    }

    public function index()
    {
        return view('admin-views.addon-activation.index');
    }

    public function activation(Request $request): Redirector|RedirectResponse|Application
    {
        $activationStatus = (int)($request['status'] ?? 0);
        $data = $this->addonService->addonActivationProcess(request: $request);

        if ($data['status']) {
            Helpers::businessUpdateOrInsert(['key' => $request['key']], [
                'value' => json_encode([
                    'activation_status' => $activationStatus,
                    'username' => $request['username'] ?? '',
                    'purchase_key' => $request['purchase_key'] ?? '',
                ])
            ]);
            Toastr::success($activationStatus
                ? translate('activated_successfully')
                : translate('status_updated_successfully'));
        } else {
            Toastr::error($data['message'] ?? translate('Activation_failed'));
        }
        return back();
    }
}
