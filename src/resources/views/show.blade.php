@extends('web::layouts.grids.12')

@section('title', 'Account Spy Hunter Report')
@section('page_header', 'Account Spy Hunter Report')

@section('content')
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start flex-wrap">
                <div>
                    @php($accountUserId = $report->account_user_id ?: $report->user_id)
                    <h4 class="mb-1">{{ $report->character_name ?: ('User #' . $accountUserId) }}</h4>
                    <p class="text-muted mb-0">
                        SeAT User #{{ $accountUserId }}
                        @if($report->corporation_name || $report->corporation_id)
                            / {{ $report->corporation_name ?: $report->corporation_id }}
                        @endif
                        @if($report->alliance_name || $report->alliance_id)
                            / {{ $report->alliance_name ?: $report->alliance_id }}
                        @endif
                    </p>
                </div>
                @php
                    $badge = data_get(['critical' => 'danger', 'high' => 'warning', 'watch' => 'info', 'clear' => 'success'], $report->rating, 'secondary');
                @endphp
                <div class="text-right">
                    <span class="badge badge-{{ $badge }} p-2">{{ ucfirst($report->rating) }}</span>
                    <div class="h4 mb-0 mt-2">{{ $report->score }}/100</div>
                </div>
            </div>

            <div class="row mt-4">
                <div class="col-md-3 col-6 mb-3">
                    <small class="text-muted d-block">Hostile Contacts</small>
                    <strong>{{ $report->hostile_contact_count }}</strong>
                </div>
                <div class="col-md-3 col-6 mb-3">
                    <small class="text-muted d-block">Hostile Mail</small>
                    <strong>{{ $report->hostile_mail_count }}</strong>
                </div>
                <div class="col-md-3 col-6 mb-3">
                    <small class="text-muted d-block">Hostile Wallet</small>
                    <strong>{{ $report->hostile_wallet_count }}</strong>
                </div>
                <div class="col-md-3 col-6 mb-3">
                    <small class="text-muted d-block">IP Signals</small>
                    <strong>{{ $report->shared_ip_user_count + $report->vpn_ip_count }}</strong>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Evidence</h3>
        </div>
        <div class="card-body">
            @forelse($report->evidence as $evidence)
                @continue($evidence->category === 'account_characters')
                <div class="border rounded p-3 mb-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h5 class="mb-1">{{ $evidence->title }}</h5>
                            <small class="text-muted">{{ str_replace('_', ' ', ucfirst($evidence->category)) }}</small>
                        </div>
                        <span class="badge badge-secondary">{{ $evidence->score }} pts</span>
                    </div>
                    @if($evidence->details)
                        <p class="mb-0 mt-3">{{ $evidence->details }}</p>
                    @endif
                </div>
            @empty
                <p class="text-muted mb-0">No evidence was recorded for this account.</p>
            @endforelse
        </div>
        <div class="card-footer">
            <a href="{{ route('seat-spy-hunter.index') }}" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>
@endsection
