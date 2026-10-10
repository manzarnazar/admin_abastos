<?php

namespace App\Http\Controllers\Admin\Diablero;

use App\CentralLogics\DiableroLogic;
use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\Diablero;
use App\Models\DiableroTask;
use App\Models\DiableroWallet;
use App\Models\Zone;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class DiableroController extends Controller
{
    public function index(Request $request)
    {
        $key = $request->search ? explode(' ', $request->search) : [];
        $diableros = Diablero::with(['zone', 'wallet'])
            ->withCount(['tasks as completed_tasks_count' => fn ($q) => $q->where('status', DiableroTask::HANDED_OVER)])
            ->when($request->zone_id, fn ($q) => $q->where('zone_id', $request->zone_id))
            ->when(count($key), function ($q) use ($key) {
                $q->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('f_name', 'like', "%{$value}%")->orWhere('l_name', 'like', "%{$value}%")
                            ->orWhere('phone', 'like', "%{$value}%")->orWhere('email', 'like', "%{$value}%");
                    }
                });
            })
            ->latest()->paginate(config('default_pagination'))->withQueryString();
        $zones = Zone::orderBy('name')->get(['id', 'name']);

        return view('admin-views.diablero.list', compact('diableros', 'zones'));
    }

    public function create()
    {
        $zones = Zone::where('status', 1)->orderBy('name')->get(['id', 'name']);
        return view('admin-views.diablero.form', ['diablero' => null, 'zones' => $zones]);
    }

    private function rules(?Diablero $diablero = null): array
    {
        return [
            'f_name' => 'required|max:100',
            'l_name' => 'nullable|max:100',
            'phone' => ['required', 'max:20', Rule::unique('diableros', 'phone')->ignore($diablero?->id)],
            'email' => ['nullable', 'email', 'max:100', Rule::unique('diableros', 'email')->ignore($diablero?->id)],
            'zone_id' => 'required|exists:zones,id',
            'identity_type' => 'nullable|max:50',
            'identity_number' => 'nullable|max:30',
            'image' => 'nullable|image|max:2048',
            'identity_image.*' => 'nullable|image|max:2048',
            'password' => [$diablero ? 'nullable' : 'required', Password::min(8)->letters()->numbers()],
        ];
    }

    public function store(Request $request)
    {
        $request->validate($this->rules());

        $identityImages = [];
        foreach ((array) $request->file('identity_image', []) as $img) {
            $identityImages[] = ['img' => Helpers::upload('diablero/', 'png', $img), 'storage' => Helpers::getDisk()];
        }

        $diablero = Diablero::create([
            'f_name' => $request->f_name,
            'l_name' => $request->l_name,
            'phone' => $request->phone,
            'email' => $request->email,
            'zone_id' => $request->zone_id,
            'identity_type' => $request->identity_type,
            'identity_number' => $request->identity_number,
            'identity_image' => $identityImages,
            'image' => $request->hasFile('image') ? Helpers::upload('diablero/', 'png', $request->file('image')) : null,
            'password' => bcrypt($request->password),
            'status' => 1,
            'application_status' => 'approved',
        ]);
        DiableroWallet::firstOrCreate(['diablero_id' => $diablero->id]);

        Toastr::success(translate('messages.diablero_added_successfully'));
        return redirect()->route('admin.diablero.list');
    }

    public function edit($id)
    {
        $diablero = Diablero::findOrFail($id);
        $zones = Zone::where('status', 1)->orderBy('name')->get(['id', 'name']);
        return view('admin-views.diablero.form', compact('diablero', 'zones'));
    }

    public function update(Request $request, $id)
    {
        $diablero = Diablero::findOrFail($id);
        $request->validate($this->rules($diablero));

        $identityImages = $diablero->identity_image ?? [];
        if ($request->hasFile('identity_image')) {
            $identityImages = [];
            foreach ((array) $request->file('identity_image') as $img) {
                $identityImages[] = ['img' => Helpers::upload('diablero/', 'png', $img), 'storage' => Helpers::getDisk()];
            }
        }

        $diablero->fill([
            'f_name' => $request->f_name,
            'l_name' => $request->l_name,
            'phone' => $request->phone,
            'email' => $request->email,
            'zone_id' => $request->zone_id,
            'identity_type' => $request->identity_type,
            'identity_number' => $request->identity_number,
            'identity_image' => $identityImages,
        ]);
        if ($request->hasFile('image')) {
            $diablero->image = Helpers::update('diablero/', $diablero->image, 'png', $request->file('image'));
        }
        if ($request->filled('password')) {
            $diablero->password = bcrypt($request->password);
            $diablero->auth_token = null;
        }
        $diablero->save();

        Toastr::success(translate('messages.diablero_updated_successfully'));
        return redirect()->route('admin.diablero.list');
    }

    public function updateStatus($id, $status)
    {
        $diablero = Diablero::findOrFail($id);
        $diablero->status = (int) $status ? 1 : 0;
        if (!$diablero->status) {
            $diablero->auth_token = null;
            $diablero->active = 0;
        }
        $diablero->save();

        Toastr::success(translate('messages.status_updated'));
        return back();
    }

    public function destroy($id)
    {
        $diablero = Diablero::findOrFail($id);
        if (DiableroTask::where('diablero_id', $diablero->id)->whereIn('status', DiableroTask::ONGOING_STATUSES)->exists()) {
            Toastr::warning(translate('messages.diablero_has_ongoing_tasks'));
            return back();
        }
        if (DiableroTask::where('diablero_id', $diablero->id)->exists() || ($diablero->wallet && $diablero->wallet->balance > 0)) {
            $diablero->update(['status' => 0, 'active' => 0, 'auth_token' => null]);
            Toastr::info(translate('messages.diablero_has_history_so_it_was_deactivated'));
            return back();
        }
        if ($diablero->image) {
            Helpers::check_and_delete('diablero/', $diablero->image);
        }
        $diablero->wallet()?->delete();
        $diablero->delete();

        Toastr::success(translate('messages.diablero_deleted_successfully'));
        return back();
    }

    public function show(Request $request, $id)
    {
        $diablero = Diablero::with(['zone'])->findOrFail($id);
        $wallet = DiableroWallet::firstOrCreate(['diablero_id' => $diablero->id]);
        $tab = $request->get('tab', 'tasks');
        $tasks = DiableroTask::with(['loadingZone'])->withCount('stops')->where('diablero_id', $diablero->id)
            ->latest()->paginate(config('default_pagination'), ['*'], 'task_page')->withQueryString();
        $transactions = $diablero->walletTransactions()->latest()
            ->paginate(config('default_pagination'), ['*'], 'tx_page')->withQueryString();
        $stats = [
            'completed' => DiableroTask::where('diablero_id', $diablero->id)->where('status', DiableroTask::HANDED_OVER)->count(),
            'ongoing' => DiableroTask::where('diablero_id', $diablero->id)->whereIn('status', DiableroTask::ONGOING_STATUSES)->count(),
            'failed' => DiableroTask::where('diablero_id', $diablero->id)->whereIn('status', [DiableroTask::FAILED, DiableroTask::CANCELED])->count(),
        ];

        return view('admin-views.diablero.view', compact('diablero', 'wallet', 'tasks', 'transactions', 'stats', 'tab'));
    }

    public function recordPayout(Request $request, $id)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'reference' => 'nullable|max:191',
            'note' => 'nullable|max:500',
        ]);
        $diablero = Diablero::findOrFail($id);
        [$ok, $message] = DiableroLogic::recordPayout($diablero, (float) $request->amount, $request->reference, $request->note);
        $ok ? Toastr::success($message) : Toastr::error($message);

        return back();
    }
}
