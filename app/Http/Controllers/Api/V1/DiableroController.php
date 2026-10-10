<?php

namespace App\Http\Controllers\Api\V1;

use App\CentralLogics\DiableroLogic;
use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\Diablero;
use App\Models\DiableroTask;
use App\Models\DiableroTaskRejection;
use App\Models\DiableroTaskStop;
use App\Models\DiableroWallet;
use App\Models\DiableroWalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class DiableroController extends Controller
{
    private function diablero(Request $request): Diablero
    {
        return $request->attributes->get('diablero');
    }

    private function error(string $code, string $message, int $status = 403)
    {
        return response()->json(['errors' => [['code' => $code, 'message' => $message]]], $status);
    }

    private function actionResponse(array $result)
    {
        [$ok, $code, $message] = $result;
        if (!$ok) {
            return $this->error($code ?? 'error', $message, $code === 'not_found' ? 404 : 403);
        }
        return response()->json(['message' => $message], 200);
    }

    private function paginate(Request $request, $query, Diablero $diablero, bool $detailed = false)
    {
        $limit = max(1, min((int) $request->input('limit', 10), 50));
        $offset = max(1, (int) $request->input('offset', 1));
        $paginator = $query->paginate($limit, ['*'], 'page', $offset);

        return response()->json([
            'total_size' => $paginator->total(),
            'limit' => $limit,
            'offset' => $offset,
            'tasks' => collect($paginator->items())->map(fn (DiableroTask $task) => DiableroLogic::formatTask($task, $diablero, $detailed))->values(),
        ], 200);
    }

    private function ownTask(Diablero $diablero, int $id): ?DiableroTask
    {
        return DiableroTask::where('id', $id)->where('diablero_id', $diablero->id)->first();
    }

    private function location(Request $request): array
    {
        $lat = is_numeric($request->latitude) ? (float) $request->latitude : null;
        $lng = is_numeric($request->longitude) ? (float) $request->longitude : null;
        return [$lat, $lng];
    }

    public function profile(Request $request)
    {
        $diablero = $this->diablero($request)->load('zone:id,name');
        $wallet = DiableroWallet::firstOrCreate(['diablero_id' => $diablero->id]);

        $data = $diablero->toArray();
        $data['image_full_url'] = $diablero->image_full_url;
        $data['zone_name'] = $diablero->zone?->name;
        $data['topic'] = DiableroLogic::zoneTopic($diablero->zone_id);
        $data['ongoing_tasks'] = DiableroTask::where('diablero_id', $diablero->id)->whereIn('status', DiableroTask::ONGOING_STATUSES)->count();
        $data['completed_tasks'] = DiableroTask::where('diablero_id', $diablero->id)->where('status', DiableroTask::HANDED_OVER)->count();
        $data['max_ongoing_tasks'] = DiableroLogic::MAX_ONGOING_TASKS;
        $data['total_earning'] = (float) $wallet->total_earning;
        $data['balance'] = $wallet->balance;
        $data['fee_name'] = DiableroLogic::settings()['fee_name'];

        return response()->json($data, 200);
    }

    public function updateProfile(Request $request)
    {
        $diablero = $this->diablero($request);
        $validator = Validator::make($request->all(), [
            'f_name' => 'required|max:100',
            'l_name' => 'nullable|max:100',
            'email' => ['nullable', 'email', Rule::unique('diableros', 'email')->ignore($diablero->id)],
            'image' => 'nullable|image|max:2048',
            'password' => ['nullable', Password::min(8)->letters()->numbers()],
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $diablero->f_name = $request->f_name;
        $diablero->l_name = $request->l_name;
        $diablero->email = $request->email;
        if ($request->hasFile('image')) {
            $diablero->image = Helpers::update('diablero/', $diablero->image, 'png', $request->file('image'));
        }
        if ($request->filled('password')) {
            $diablero->password = bcrypt($request->password);
        }
        $diablero->save();

        return response()->json(['message' => translate('messages.profile_updated_successfully')], 200);
    }

    public function updateActiveStatus(Request $request)
    {
        $diablero = $this->diablero($request);
        $diablero->active = $diablero->active ? 0 : 1;
        $diablero->save();

        return response()->json([
            'message' => $diablero->active ? translate('messages.you_are_online') : translate('messages.you_are_offline'),
            'active' => (int) $diablero->active,
        ], 200);
    }

    public function updateFcmToken(Request $request)
    {
        $validator = Validator::make($request->all(), ['fcm_token' => 'required|string|max:255']);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $this->diablero($request)->update(['fcm_token' => $request->fcm_token]);

        return response()->json(['message' => translate('messages.updated_successfully')], 200);
    }

    public function recordLocation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $this->diablero($request)->update([
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'location_updated_at' => now(),
        ]);

        return response()->json(['message' => translate('messages.location_recorded')], 200);
    }

    public function notifications(Request $request)
    {
        $diablero = $this->diablero($request);
        $notifications = DB::table('user_notifications')
            ->where('diablero_id', $diablero->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->latest()
            ->limit(100)
            ->get()
            ->map(function ($row) {
                $row->data = json_decode($row->data, true);
                return $row;
            });

        return response()->json($notifications, 200);
    }

    public function earnings(Request $request)
    {
        $diablero = $this->diablero($request);
        $wallet = DiableroWallet::firstOrCreate(['diablero_id' => $diablero->id]);
        $earnings = DiableroWalletTransaction::where('diablero_id', $diablero->id)->where('transaction_type', 'task_earning');

        return response()->json([
            'total_earning' => (float) $wallet->total_earning,
            'total_withdrawn' => (float) $wallet->total_withdrawn,
            'pending_withdraw' => (float) $wallet->pending_withdraw,
            'balance' => $wallet->balance,
            'today' => (float) (clone $earnings)->whereDate('created_at', today())->sum('credit'),
            'this_week' => (float) (clone $earnings)->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->sum('credit'),
            'this_month' => (float) (clone $earnings)->whereYear('created_at', now()->year)->whereMonth('created_at', now()->month)->sum('credit'),
            'completed_tasks' => DiableroTask::where('diablero_id', $diablero->id)->where('status', DiableroTask::HANDED_OVER)->count(),
        ], 200);
    }

    public function walletTransactions(Request $request)
    {
        $diablero = $this->diablero($request);
        $limit = max(1, min((int) $request->input('limit', 10), 50));
        $offset = max(1, (int) $request->input('offset', 1));
        $paginator = DiableroWalletTransaction::where('diablero_id', $diablero->id)
            ->when(in_array($request->type, ['task_earning', 'payout'], true), fn ($q) => $q->where('transaction_type', $request->type))
            ->latest()
            ->paginate($limit, ['*'], 'page', $offset);

        return response()->json([
            'total_size' => $paginator->total(),
            'limit' => $limit,
            'offset' => $offset,
            'transactions' => $paginator->items(),
        ], 200);
    }

    public function removeAccount(Request $request)
    {
        $diablero = $this->diablero($request);
        if (DiableroTask::where('diablero_id', $diablero->id)->whereIn('status', DiableroTask::ONGOING_STATUSES)->exists()) {
            return $this->error('ongoing_task', translate('messages.complete_your_ongoing_tasks_first'));
        }
        $wallet = DiableroWallet::where('diablero_id', $diablero->id)->first();
        if ($wallet && $wallet->balance > 0) {
            return $this->error('balance', translate('messages.withdraw_your_balance_before_removing_account'));
        }

        // Task history references the diablero, so the account is anonymised instead of deleted.
        $diablero->update([
            'status' => 0,
            'active' => 0,
            'auth_token' => null,
            'fcm_token' => null,
            'phone' => 'deleted_' . $diablero->id . '_' . $diablero->phone,
            'email' => null,
        ]);

        return response()->json(['message' => translate('messages.account_removed_successfully')], 200);
    }

    public function availableTasks(Request $request)
    {
        $diablero = $this->diablero($request);
        $rejected = DiableroTaskRejection::where('diablero_id', $diablero->id)->select('diablero_task_id');

        $query = DiableroTask::where('status', DiableroTask::AVAILABLE)
            ->whereNull('diablero_id')
            ->where('zone_id', $diablero->zone_id)
            ->whereNotIn('id', $rejected)
            ->orderBy('available_at');

        return $this->paginate($request, $query, $diablero, false);
    }

    public function currentTasks(Request $request)
    {
        $diablero = $this->diablero($request);
        $tasks = DiableroTask::where('diablero_id', $diablero->id)
            ->whereIn('status', DiableroTask::ONGOING_STATUSES)
            ->orderBy('accepted_at')
            ->get()
            ->map(fn (DiableroTask $task) => DiableroLogic::formatTask($task, $diablero, true))
            ->values();

        return response()->json($tasks, 200);
    }

    public function taskHistory(Request $request)
    {
        $diablero = $this->diablero($request);
        $statuses = in_array($request->status, DiableroTask::CLOSED_STATUSES, true) ? [$request->status] : DiableroTask::CLOSED_STATUSES;
        $query = DiableroTask::where('diablero_id', $diablero->id)->whereIn('status', $statuses)->latest('updated_at');

        return $this->paginate($request, $query, $diablero, false);
    }

    public function taskDetails(Request $request, int $id)
    {
        $diablero = $this->diablero($request);
        $task = DiableroTask::find($id);
        $visible = $task && (
            (int) $task->diablero_id === (int) $diablero->id
            || ($task->status === DiableroTask::AVAILABLE && (int) $task->zone_id === (int) $diablero->zone_id)
        );
        if (!$visible) {
            return $this->error('not_found', translate('messages.task_not_found'), 404);
        }

        return response()->json(DiableroLogic::formatTask($task, $diablero, true), 200);
    }

    public function acceptTask(Request $request, int $id)
    {
        $diablero = $this->diablero($request);
        $task = DiableroTask::find($id);
        if (!$task) {
            return $this->error('not_found', translate('messages.task_not_found'), 404);
        }
        $result = DiableroLogic::accept($task, $diablero);
        if (!$result[0]) {
            return $this->error($result[1], $result[2], $result[1] === 'already_accepted' ? 409 : 403);
        }

        return response()->json(['message' => $result[2], 'task' => DiableroLogic::formatTask($task->fresh(), $diablero, true)], 200);
    }

    public function rejectTask(Request $request, int $id)
    {
        $task = DiableroTask::find($id);
        if (!$task) {
            return $this->error('not_found', translate('messages.task_not_found'), 404);
        }

        return $this->actionResponse(DiableroLogic::reject($task, $this->diablero($request)));
    }

    public function updateTaskStatus(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:' . DiableroTask::ARRIVED . ',' . DiableroTask::IN_TRANSIT,
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $diablero = $this->diablero($request);
        $task = $this->ownTask($diablero, $id);
        if (!$task) {
            return $this->error('not_found', translate('messages.task_not_found'), 404);
        }
        [$lat, $lng] = $this->location($request);

        $result = DiableroLogic::updateStatusByDiablero($task, $diablero, $request->status, $lat, $lng);
        if (!$result[0]) {
            return $this->actionResponse($result);
        }

        return response()->json(['message' => $result[2], 'task' => DiableroLogic::formatTask($task->fresh(), $diablero, true)], 200);
    }

    public function collectStop(Request $request, int $id, int $stop)
    {
        return $this->handleStop($request, $id, $stop, false);
    }

    public function failStop(Request $request, int $id, int $stop)
    {
        $validator = Validator::make($request->all(), ['reason' => 'required|string|max:255']);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        return $this->handleStop($request, $id, $stop, true);
    }

    private function handleStop(Request $request, int $id, int $stopId, bool $failed)
    {
        $diablero = $this->diablero($request);
        $task = $this->ownTask($diablero, $id);
        $stop = $task ? DiableroTaskStop::where('id', $stopId)->where('diablero_task_id', $task->id)->first() : null;
        if (!$stop) {
            return $this->error('not_found', translate('messages.task_not_found'), 404);
        }
        [$lat, $lng] = $this->location($request);

        $result = $failed
            ? DiableroLogic::failStop($task, $stop, $diablero, $request->reason, $lat, $lng)
            : DiableroLogic::collectStop($task, $stop, $diablero, $lat, $lng);
        if (!$result[0]) {
            return $this->actionResponse($result);
        }

        return response()->json(['message' => $result[2], 'task' => DiableroLogic::formatTask($task->fresh(), $diablero, true)], 200);
    }
}
