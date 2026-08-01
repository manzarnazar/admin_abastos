<?php

namespace App\Services;
use App\CentralLogics\Helpers;
use App\Traits\FileManagerTrait;
use Illuminate\Support\Facades\Storage;
use PhpParser\Node\Expr\Cast\Object_;

class DeliveryManService
{
    use FileManagerTrait;

    public function getAddData(Object $request): array
    {
        if ($request->has('image')) {
            $imageName = $this->upload('delivery-man/', 'png', $request->file('image'));
        } else {
            $imageName = 'def.png';
        }

        $encodeDocument = function ($file) {
            $ext = $file->getClientOriginalExtension();
            $filename = $this->upload('delivery-man/', $ext, $file);
            return json_encode([['img' => $filename, 'storage' => Helpers::getDisk()]]);
        };

        $ineImage = null;
        if ($request->hasFile('ine_image')) {
            $ineImage = $encodeDocument($request->file('ine_image'));
        }
        $ineBackImage = null;
        if ($request->hasFile('ine_back_image')) {
            $ineBackImage = $encodeDocument($request->file('ine_back_image'));
        }

        $driverLicenseImage = null;
        if ($request->has('driver_license_image')) {
            $ext = $request->file('driver_license_image')->getClientOriginalExtension();
            $driverLicenseImage = $this->upload('delivery-man/', $ext, $request->file('driver_license_image'));
        }

        $curpRfcCertificateImage = null;
        if ($request->has('curp_rfc_certificate_image')) {
            $ext = $request->file('curp_rfc_certificate_image')->getClientOriginalExtension();
            $curpRfcCertificateImage = $this->upload('delivery-man/', $ext, $request->file('curp_rfc_certificate_image'));
        }

        $cofeprisDocumentImage = null;
        if ($request->has('cofepris_document_image')) {
            $ext = $request->file('cofepris_document_image')->getClientOriginalExtension();
            $cofeprisDocumentImage = $this->upload('delivery-man/', $ext, $request->file('cofepris_document_image'));
        }

        return [
            'f_name' => $request->f_name,
            'l_name' => $request->l_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'vehicle_id' => $request->vehicle_id,
            'zone_id' => $request->zone_id,
            'ine_image' => $ineImage,
            'ine_back_image' => $ineBackImage,
            'image' => $imageName,
            'active' => 0,
            'earning' => $request->earning,
            'password' => bcrypt($request->password),
            'driver_license_image' => $driverLicenseImage,
            'curp_rfc' => $request->curp_rfc,
            'curp_rfc_certificate_image' => $curpRfcCertificateImage,
            'cofepris_document_image' => $cofeprisDocumentImage,
        ];
    }

    public function getUpdateData(Object $request, Object $deliveryMan): array
    {
        if ($request->has('image')) {
            $imageName = $this->updateAndUpload('delivery-man/', $deliveryMan->image, 'png', $request->file('image'));
        } else {
            $imageName = $deliveryMan['image'];
        }

        $encodeDocument = function ($file) {
            $ext = $file->getClientOriginalExtension();
            $filename = $this->upload('delivery-man/', $ext, $file);
            return json_encode([['img' => $filename, 'storage' => Helpers::getDisk()]]);
        };

        $ineImage = $deliveryMan->ine_image;
        if ($request->hasFile('ine_image')) {
            $ineImage = $encodeDocument($request->file('ine_image'));
        }
        $ineBackImage = $deliveryMan->ine_back_image;
        if ($request->hasFile('ine_back_image')) {
            $ineBackImage = $encodeDocument($request->file('ine_back_image'));
        }

        $driverLicenseImage = $deliveryMan->driver_license_image;
        if ($request->has('driver_license_image')) {
            $ext = $request->file('driver_license_image')->getClientOriginalExtension();
            $driverLicenseImage = $this->updateAndUpload('delivery-man/', $deliveryMan->driver_license_image ?? '', $ext, $request->file('driver_license_image'));
        }

        $curpRfcCertificateImage = $deliveryMan->curp_rfc_certificate_image;
        if ($request->has('curp_rfc_certificate_image')) {
            $ext = $request->file('curp_rfc_certificate_image')->getClientOriginalExtension();
            $curpRfcCertificateImage = $this->updateAndUpload('delivery-man/', $deliveryMan->curp_rfc_certificate_image ?? '', $ext, $request->file('curp_rfc_certificate_image'));
        }

        $cofeprisDocumentImage = $deliveryMan->cofepris_document_image;
        if ($request->has('cofepris_document_image')) {
            $ext = $request->file('cofepris_document_image')->getClientOriginalExtension();
            $cofeprisDocumentImage = $this->updateAndUpload('delivery-man/', $deliveryMan->cofepris_document_image ?? '', $ext, $request->file('cofepris_document_image'));
        }

        return [
            "f_name" => $request->f_name,
            "l_name" => $request->l_name,
            "email" => $request->email,
            "phone" => $request->phone,
            "vehicle_id" => $request->vehicle_id,
            "zone_id" => $request->zone_id,
            "ine_image" => $ineImage,
            "ine_back_image" => $ineBackImage,
            "image" => $imageName,
            "earning" => $request->earning,
            "driver_license_image" => $driverLicenseImage,
            "curp_rfc" => $request->curp_rfc,
            "curp_rfc_certificate_image" => $curpRfcCertificateImage,
            "cofepris_document_image" => $cofeprisDocumentImage,
            "password" => strlen($request->password)>1?bcrypt($request->password):$deliveryMan['password'],
            "application_status" => in_array($deliveryMan['application_status'], ['pending','denied']) ? 'approved' : $deliveryMan['application_status'],
            "status" => in_array($deliveryMan['application_status'], ['pending','denied']) ? 1 : $deliveryMan['status'],
        ];
    }

}
