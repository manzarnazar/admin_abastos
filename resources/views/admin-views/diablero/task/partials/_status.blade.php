@php($badge = [
    'pending' => 'badge-soft-secondary',
    'available' => 'badge-soft-info',
    'accepted' => 'badge-soft-primary',
    'arrived' => 'badge-soft-primary',
    'collected' => 'badge-soft-warning',
    'in_transit' => 'badge-soft-warning',
    'handed_over' => 'badge-soft-success',
    'failed' => 'badge-soft-danger',
    'canceled' => 'badge-soft-danger',
][$status] ?? 'badge-soft-dark')
<span class="badge {{ $badge }}">{{ translate('messages.diablero_task_' . $status) }}</span>
