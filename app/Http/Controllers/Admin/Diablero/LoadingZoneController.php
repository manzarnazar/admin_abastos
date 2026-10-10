<?php

namespace App\Http\Controllers\Admin\Diablero;

use App\Http\Controllers\Controller;
use App\Models\DiableroTask;
use App\Models\LoadingZone;
use App\Models\Zone;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use MatanYadaev\EloquentSpatial\Objects\Point;

class LoadingZoneController extends Controller
{
    public function index(Request $request)
    {
        $zones = Zone::orderBy('name')->get(['id', 'name', 'diablero_status']);
        $loadingZones = LoadingZone::with('zone')
            ->when($request->zone_id, fn ($q) => $q->where('zone_id', $request->zone_id))
            ->latest()->paginate(config('default_pagination'))->withQueryString();

        return view('admin-views.diablero.loading-zone.index', [
            'zones' => $zones,
            'loadingZones' => $loadingZones,
            'loadingZone' => null,
        ]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'zone_id' => 'required|exists:zones,id',
            'name' => 'required|max:191',
            'address' => 'nullable|max:255',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'is_default' => 'nullable|boolean',
        ]);

        $inside = Zone::where('id', $data['zone_id'])
            ->whereContains('coordinates', new Point((float) $data['latitude'], (float) $data['longitude'], POINT_SRID))
            ->exists();
        if (!$inside) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'latitude' => translate('messages.loading_zone_must_be_inside_the_selected_zone'),
            ]);
        }

        $data['is_default'] = $request->boolean('is_default');
        return $data;
    }

    private function syncDefault(LoadingZone $loadingZone): void
    {
        if ($loadingZone->is_default) {
            LoadingZone::where('zone_id', $loadingZone->zone_id)->where('id', '!=', $loadingZone->id)->update(['is_default' => 0]);
        } elseif (!LoadingZone::where('zone_id', $loadingZone->zone_id)->where('is_default', 1)->exists()) {
            $loadingZone->update(['is_default' => 1]);
        }
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        DB::transaction(function () use ($data) {
            $loadingZone = LoadingZone::create($data + ['status' => 1]);
            $this->syncDefault($loadingZone);
        });

        Toastr::success(translate('messages.loading_zone_added_successfully'));
        return redirect()->route('admin.diablero.loading-zone.index', ['zone_id' => $data['zone_id']]);
    }

    public function edit($id)
    {
        $loadingZone = LoadingZone::findOrFail($id);
        $zones = Zone::orderBy('name')->get(['id', 'name', 'diablero_status']);
        $loadingZones = LoadingZone::with('zone')->where('zone_id', $loadingZone->zone_id)->latest()
            ->paginate(config('default_pagination'));

        return view('admin-views.diablero.loading-zone.index', compact('zones', 'loadingZones', 'loadingZone'));
    }

    public function update(Request $request, $id)
    {
        $loadingZone = LoadingZone::findOrFail($id);
        $data = $this->validated($request);
        DB::transaction(function () use ($loadingZone, $data) {
            $loadingZone->update($data);
            $this->syncDefault($loadingZone);
        });

        Toastr::success(translate('messages.loading_zone_updated_successfully'));
        return redirect()->route('admin.diablero.loading-zone.index', ['zone_id' => $loadingZone->zone_id]);
    }

    public function updateStatus($id, $status)
    {
        $loadingZone = LoadingZone::findOrFail($id);
        $loadingZone->update(['status' => (int) $status ? 1 : 0]);

        Toastr::success(translate('messages.status_updated'));
        return back();
    }

    public function destroy($id)
    {
        $loadingZone = LoadingZone::findOrFail($id);
        if (DiableroTask::where('loading_zone_id', $loadingZone->id)->open()->exists()) {
            Toastr::warning(translate('messages.loading_zone_has_open_tasks'));
            return back();
        }
        $zoneId = $loadingZone->zone_id;
        $wasDefault = $loadingZone->is_default;
        $loadingZone->delete();
        if ($wasDefault) {
            LoadingZone::where('zone_id', $zoneId)->orderBy('id')->first()?->update(['is_default' => 1]);
        }

        Toastr::success(translate('messages.loading_zone_deleted_successfully'));
        return back();
    }
}
