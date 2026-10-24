@extends($prefix === 'admin' ? 'adminmodule::layouts.master' : 'providermanagement::layouts.master')
@section('title', __('bookingmodule::execution.cash_operations'))
@section('content')
<div class="container-fluid p-4 nirab-cash-operations">
    <h2>{{ __('bookingmodule::execution.cash_operations') }}</h2>
    @if(request('branch_id'))<a class="btn btn-outline-primary mb-3" href="{{ route($prefix.'.branch.index', ['branch_id' => request('branch_id'), 'tab' => 'finance']) }}">{{ __('branch.return') }}</a>@endif
    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @foreach($errors->all() as $error) <div class="alert alert-danger">{{ $error }}</div> @endforeach
    @if($prefix === 'admin' && auth()->user()->can('service_update'))
        <a class="btn btn-outline-primary mb-3" href="{{ route('admin.cash.setup') }}">{{ __('bookingmodule::execution.execution_setup') }}</a>
    @endif
    <form method="get" class="row g-2 mb-4 nirab-cash-form">
        <div class="col-md-3"><label>{{ __('bookingmodule::execution.branch') }}</label><select name="branch_id" class="form-control">
            <option value="">{{ __('bookingmodule::execution.all') }}</option>
            @foreach($branches as $branch) <option value="{{ $branch->id }}" @selected(request('branch_id') === $branch->id)>{{ $branch->company_name }}</option> @endforeach
        </select></div>
        <div class="col-md-2"><label>{{ __('bookingmodule::execution.method') }}</label><select name="method" class="form-control">
            <option value="">{{ __('bookingmodule::execution.all') }}</option>
            @foreach(['cash', 'bank_transfer'] as $method) <option value="{{ $method }}" @selected(request('method') === $method)>{{ __('bookingmodule::execution.'.$method) }}</option> @endforeach
        </select></div>
        <div class="col-md-3"><label>{{ __('bookingmodule::execution.status') }}</label><select name="status" class="form-control">
            <option value="">{{ __('bookingmodule::execution.all') }}</option>
            @foreach(['submitted_by_technician','branch_verified','branch_rejected','accountant_approved','accountant_rejected','ready_for_accounting_sync'] as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ __('bookingmodule::execution.'.$status) }}</option>
            @endforeach
        </select></div>
        <div class="col-md-2"><label>{{ __('bookingmodule::execution.date') }}</label><input class="form-control" type="date" name="date" value="{{ request('date') }}"></div>
        <div class="col-md-2 align-self-end"><button class="btn btn-primary">{{ __('bookingmodule::execution.filter') }}</button></div>
    </form>
    @if($prefix === 'admin')<section class="card mb-4 nirab-operation-section"><div class="card-body">@endif
<h3>{{ __('bookingmodule::execution.payment_collections') }}</h3>
    <div class="table-responsive nirab-cash-table"><table class="table table-bordered">
        <thead><tr>@foreach(['technician','amount','method','status','proof','review'] as $label)<th>{{ __('bookingmodule::execution.'.$label) }}</th>@endforeach</tr></thead>
        <tbody>@forelse($collections as $collection)
            <tr>
                <td>{{ $collection->technician?->user?->first_name }} {{ $collection->technician?->user?->last_name }}<br><small>{{ $collection->booking?->readable_id ? __('operational.booking_reference', ['reference' => $collection->booking->readable_id]) : __('operational.fields.booking_id') }}</small><details><summary>{{ __('operational.reference') }}</summary><code dir="ltr">{{ $collection->booking_repeat_id ?? $collection->booking_id }}</code></details></td>
                <td>{{ $collection->amount }} {{ $collection->currency }}<br>{{ $collection->collected_at }}</td>
                <td>{{ app(\App\Services\OperationalPresentation::class)->value('method', $collection->method) }}<br>{{ $collection->reference }}</td>
                <td>{{ app(\App\Services\OperationalPresentation::class)->value('status', $collection->status) }}<br>{{ $collection->rejection_reason }}</td>
                <td>@if($collection->proof?->full_path)<a href="{{ $collection->proof->full_path }}" target="_blank" rel="noopener">{{ __('bookingmodule::execution.proof') }}</a>@endif</td>
                <td>
                    @php($stage = $collection->status === 'submitted_by_technician' ? 'branch' : ($collection->status === 'branch_verified' ? 'accountant' : null))
                    @if($stage && auth()->user()->can('nirab_'.($stage === 'branch' ? 'branch_verify' : 'accountant_approve'), $collection->provider_id))
                        <form method="post" action="{{ route($prefix.'.cash.review', ['id' => $collection->id, 'stage' => $stage]) }}">@csrf
                            <textarea name="note" class="form-control mb-2" maxlength="4000" placeholder="{{ __('bookingmodule::execution.review_note') }}"></textarea>
                            <button name="approve" value="1" class="btn btn-success btn-sm">{{ __('bookingmodule::execution.approve') }}</button>
                            <button name="approve" value="0" class="btn btn-danger btn-sm">{{ __('bookingmodule::execution.reject') }}</button>
                        </form>
                    @endif
                    <a href="{{ route($prefix.'.cash.index', ['entity_id' => $collection->id]) }}">{{ __('bookingmodule::execution.audit_details') }}</a>
                    <details><summary>{{ __('bookingmodule::execution.review') }}</summary>
                        <p>{{ __('operational.fields.branch_verified_by') }}: {{ $reviewers[$collection->branch_verified_by] ?? __('operational.empty') }} — {{ $collection->branch_verified_at }} — {{ $collection->branch_verified_note }}</p>
                        <p>{{ __('operational.fields.accountant_approved_by') }}: {{ $reviewers[$collection->accountant_approved_by] ?? __('operational.empty') }} — {{ $collection->accountant_approved_at }} — {{ $collection->accountant_approved_note }}</p>
                        <details><summary>{{ __('operational.advanced') }}</summary><code dir="ltr">{{ $collection->branch_verified_by }} / {{ $collection->accountant_approved_by }}</code></details>
                    </details>
                </td>
            </tr>
        @empty <tr><td colspan="6">{{ __('bookingmodule::execution.no_records') }}</td></tr> @endforelse</tbody>
    </table></div>
    {{ $collections->links() }}

@if($prefix === 'admin')</div></section>@endif
    @if($prefix === 'admin')<section class="card mb-4 nirab-operation-section"><div class="card-body">@endif
<h3>{{ __('bookingmodule::execution.daily_settlement') }}</h3>
    <div class="table-responsive nirab-cash-table"><table class="table table-bordered">
        <thead><tr>@foreach(['technician','opening','collected','handed_over','adjustments','remaining','alerts'] as $label)<th>{{ __('bookingmodule::execution.'.$label) }}</th>@endforeach</tr></thead>
        <tbody>@forelse($settlement as $row)<tr>
            <td>{{ $row['name'] }}<details><summary>{{ __('operational.reference') }}</summary><code dir="ltr">{{ $row['technician_id'] }}</code></details></td>
            @foreach(['opening','collected','handed_over','adjustments','remaining'] as $field)<td>{{ number_format($row[$field], 3) }} SAR</td>@endforeach
            <td>@if($row['cash_blocked'])<span class="badge bg-danger">{{ __('bookingmodule::execution.cash_custody_limit_reached') }}</span>@endif
                @if($row['overdue'])<span class="badge bg-warning text-dark">{{ __('bookingmodule::execution.overdue_handover') }}</span>@endif</td>
        </tr>@empty<tr><td colspan="7">{{ __('bookingmodule::execution.no_records') }}</td></tr>@endforelse</tbody>
    </table></div>

@if($prefix === 'admin')</div></section>@endif
    @if($prefix === 'admin')<section class="card mb-4 nirab-operation-section"><div class="card-body">@endif
<h3>{{ __('bookingmodule::execution.cash_handovers') }}</h3>
    <div class="table-responsive nirab-cash-table"><table class="table table-bordered">
        <thead><tr>@foreach(['technician','amount','received_amount','status','review'] as $label)<th>{{ __('bookingmodule::execution.'.$label) }}</th>@endforeach</tr></thead>
        <tbody>@forelse($handovers as $handover)<tr>
            <td>{{ $handover->technician?->user?->first_name }} {{ $handover->technician?->user?->last_name }}<br>{{ $handover->created_at }}</td>
            <td>{{ $handover->amount }} SAR</td><td>{{ $handover->received_amount }} SAR</td>
            <td>{{ app(\App\Services\OperationalPresentation::class)->value('status', $handover->status) }}<br>{{ $handover->note }}</td>
            <td>
                @if($handover->status === 'submitted' && auth()->user()->can('nirab_branch_verify', $handover->provider_id))
                    <form method="post" action="{{ route($prefix.'.cash.confirm', $handover->id) }}">@csrf
                        <input class="form-control" name="received_amount" type="number" min="0.001" max="{{ $handover->amount }}" step="0.001" value="{{ $handover->amount }}" required>
                        <textarea class="form-control my-2" name="note" placeholder="{{ __('bookingmodule::execution.review_note') }}"></textarea>
                        <button class="btn btn-primary btn-sm">{{ __('bookingmodule::execution.confirm_received') }}</button>
                    </form>
                @elseif($handover->status === 'received' && !$handover->accountant_verified_at && auth()->user()->can('nirab_accountant_approve', $handover->provider_id))
                    <form method="post" action="{{ route($prefix.'.cash.verify', $handover->id) }}">@csrf
                        <textarea class="form-control my-2" name="note" placeholder="{{ __('bookingmodule::execution.review_note') }}"></textarea>
                        <button class="btn btn-primary btn-sm">{{ __('bookingmodule::execution.verify_settlement') }}</button>
                    </form>
                @endif
                <small>{{ $reviewers[$handover->confirmed_by] ?? __('operational.empty') }} — {{ $handover->confirmed_at }}<br>{{ $reviewers[$handover->accountant_verified_by] ?? __('operational.empty') }} — {{ $handover->accountant_verified_at }}</small>
                <details><summary>{{ __('operational.advanced') }}</summary><code dir="ltr">{{ $handover->confirmed_by }} / {{ $handover->accountant_verified_by }}</code></details>
            </td>
        </tr>@empty<tr><td colspan="5">{{ __('bookingmodule::execution.no_records') }}</td></tr>@endforelse</tbody>
    </table></div>{{ $handovers->links() }}

@if($prefix === 'admin')</div></section>@endif
    @if(auth()->user()->can('nirab_custody_adjust'))
        @if($prefix === 'admin')<section class="card mb-4 nirab-operation-section"><div class="card-body">@endif
<h3>{{ __('bookingmodule::execution.custody_adjust') }}</h3>
        <form method="post" action="{{ route($prefix.'.cash.adjust') }}" class="row g-2 mb-4 nirab-cash-form">@csrf
            <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}"><div class="col-md-4"><select class="form-control" name="technician_id" required aria-label="{{ __('bookingmodule::execution.technician') }}"><option value="">{{ __('operational.select') }}</option>@foreach($adjustmentTechnicians as $technician)<option value="{{ $technician['id'] }}" @selected(old('technician_id') === $technician['id'])>{{ $technician['name'] }}</option>@endforeach</select></div>
            <div class="col-md-2"><input class="form-control" name="amount" type="number" step="0.001" required placeholder="{{ __('bookingmodule::execution.amount') }}"></div>
            <div class="col-md-4"><input class="form-control" name="reason" required placeholder="{{ __('bookingmodule::execution.reason') }}"></div>
            <div class="col-md-2"><button class="btn btn-primary">{{ __('bookingmodule::execution.save') }}</button></div>
        </form>

@if($prefix === 'admin')</div></section>@endif
    @endif
    @can('nirab_force_complete')
        @if($prefix === 'admin')<section class="card mb-4 nirab-operation-section"><div class="card-body">@endif
<h3>{{ __('bookingmodule::execution.force_complete') }}</h3>
        <form method="post" action="{{ route($prefix.'.cash.override') }}" enctype="multipart/form-data" class="row g-2 mb-4 nirab-cash-form">@csrf
            <div class="col-md-2"><select class="form-control" name="kind"><option value="booking">{{ __('bookingmodule::execution.booking') }}</option><option value="booking_repeat">{{ __('bookingmodule::execution.booking_repeat') }}</option></select></div>
            <div class="col-md-3"><input class="form-control" name="booking_id" required placeholder="{{ __('bookingmodule::execution.booking_id') }}"></div>
            <div class="col-md-3"><input class="form-control" name="reason" required placeholder="{{ __('bookingmodule::execution.reason') }}"></div>
            <div class="col-md-4"><input class="form-control" name="note" required placeholder="{{ __('bookingmodule::execution.review_note') }}"></div>
            <div class="col-md-6"><label>{{ __('bookingmodule::execution.override_evidence') }}</label><input class="form-control" name="image" type="file" accept="image/*" @required(config('nirab_execution.override_requires_evidence'))></div>
            <div class="col-md-6 align-self-end"><button class="btn btn-warning">{{ __('bookingmodule::execution.force_complete') }}</button></div>
        </form>

@if($prefix === 'admin')</div></section>@endif
    @endcan
    @if($prefix === 'admin')<section class="card mb-4 nirab-operation-section"><div class="card-body">@endif
<h3>{{ __('bookingmodule::execution.audit_details') }}</h3>
    @forelse($audit as $entry)
        @include('components.audit-entry', ['entry' => $entry, 'references' => $auditReferences])
    @empty<p>{{ __('bookingmodule::execution.no_records') }}</p>@endforelse
    {{ $audit->links() }}
@if($prefix === 'admin')</div></section>@endif

</div>
@endsection
