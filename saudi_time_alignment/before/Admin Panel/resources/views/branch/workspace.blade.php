@extends($layout)
@section('title', __('branch.center').' — '.$branch->company_name)
@section('content')
@include('branch.navigation')
<main class="container-fluid p-4 nirab-branch">
    @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
    @foreach($errors->all() as $error)<div class="alert alert-danger" role="alert">{{ $error }}</div>@endforeach
    <div id="branch-message" role="status" aria-live="polite"></div>
    @if(!in_array($tab, ['quotes','settings'])) @include('branch.filters') @endif
    @if(isset($summary))
        <p class="text-muted">{{ __('branch.visit_definition') }}</p>
        <div class="branch-cards mb-4">
            <div class="card card-body"><span>{{ __('branch.orders') }}</span><strong>{{ $summary['orders'] }}</strong></div>
            <div class="card card-body"><span>{{ __('branch.completion_rate') }}</span><strong>{{ $summary['completion_rate'] }}%</strong></div>
            <div class="card card-body"><span>{{ __('branch.cancellation_rate') }}</span><strong>{{ $summary['cancellation_rate'] }}%</strong></div>
            <div class="card card-body"><span>{{ __('branch.booking_value') }}</span><strong>{{ with_currency_symbol($summary['booking_value']) }}</strong></div>
        </div>
        <div class="branch-cards mb-4">@foreach($summary['states'] as $state => $count)<a class="card card-body" href="{{ $base . '?' . http_build_query(array_merge(request()->except(['page','queue']), ['branch_id' => $branchId, 'tab' => 'bookings', 'status' => $state, 'from' => $filters['from'], 'to' => $filters['to']])) }}"><span>{{ __('branch.'.$state) }}</span><strong>{{ $count }}</strong></a>@endforeach</div>
        <p class="text-muted">{{ __('branch.queue_definition', ['minutes' => config('nirab_branch.late_minutes')]) }}</p>
        <div class="branch-cards mb-4">@foreach($summary['queues'] as $queue => $count)<a class="card card-body" href="{{ $base . '?' . http_build_query(array_merge(request()->except('page'), ['branch_id' => $branchId, 'tab' => 'bookings', 'queue' => $queue, 'from' => $filters['from'], 'to' => $filters['to']])) }}"><span>{{ __('branch.'.$queue) }}</span><strong>{{ $count }}</strong></a>@endforeach</div>
        @include('branch.bookings')
        @if($tab === 'reports')<a class="btn btn-outline-primary mt-3" href="{{ $base . '?' . http_build_query(['branch_id' => $branchId, 'tab' => 'quality', 'from' => $filters['from'], 'to' => $filters['to']]) }}">{{ __('branch.quality_reports') }}</a>@endif
    @endif
    @if($tab === 'finance') @include('branch.finance') @endif
    @if($tab === 'invoices') @include('branch.invoices') @endif
    @if($tab === 'quotes') @include('branch.quotes') @endif
    @if($tab === 'audit')
        @forelse($audit as $entry) @include('components.audit-entry', ['entry' => $entry, 'references' => $auditReferences]) @empty<p>{{ __('branch.empty') }}</p>@endforelse{{ $audit->links() }}
    @endif
    @if($tab === 'settings')
        <section class="card card-body"><h2>{{ $branch->company_name }}</h2><p>{{ $branch->company_address }}</p><p>{{ $branch->company_phone }}</p><p>{{ $branch->company_email }}</p>
            <nav class="branch-tabs">
            <a class="btn btn-primary" href="{{ route($prefix.'.branch.setup.show', $branchId) }}">{{ __('branch_setup.resume') }}</a>
            <p>{{ $branch->intake_enabled === null ? __('branch_setup.legacy') : ($branch->intake_enabled ? __('branch_setup.receiving') : __('branch_setup.draft')) }}</p>
            @php($setupReport = app(\Modules\ProviderManagement\Services\BranchReadinessService::class)->report($branch))
            @foreach($setupReport['readiness']['blockers'] as $issue)
                <a class="btn btn-outline-secondary" href="{{ route($prefix.'.branch.setup.show', [$branchId, 'step' => $issue['step']]) }}">{{ $issue['message'] }}</a>
            @endforeach
            </nav><p class="mt-3 text-muted">{{ __('branch.settings_help') }}</p>
        </section>
    @endif
</main>
@endsection
@push('script')
<script>window.nirabBranch = {base: @json($base), branchId: @json($branchId), csrf: @json(csrf_token()), labels: @json(__('branch'))};</script>
<script src="{{ asset('public/assets/common/js/nirab-branch.js') }}"></script>
@endpush
