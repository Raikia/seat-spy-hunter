@extends('web::layouts.grids.12')

@section('title', 'Spy Hunter')
@section('page_header', 'Spy Hunter')

@section('content')
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start flex-wrap">
                <div>
                    <h4 class="mb-1">Suspicion Dashboard</h4>
                    <p class="text-muted mb-0">Read-only scoring for SeAT user accounts with monitored corporation and alliance characters.</p>
                </div>
                <form action="{{ route('seat-spy-hunter.refresh') }}" method="POST">
                    {{ csrf_field() }}
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-sync-alt"></i> Refresh Reports
                    </button>
                </form>
            </div>

            <div class="row mt-4">
                <div class="col-md-2 col-6 mb-3">
                    <div class="border rounded p-3 h-100">
                        <small class="text-muted d-block">Total</small>
                        <strong class="h4 mb-0">{{ $summary['total'] }}</strong>
                    </div>
                </div>
                <div class="col-md-2 col-6 mb-3">
                    <div class="border rounded p-3 h-100">
                        <small class="text-muted d-block">Critical</small>
                        <strong class="h4 mb-0 text-danger">{{ $summary['critical'] }}</strong>
                    </div>
                </div>
                <div class="col-md-2 col-6 mb-3">
                    <div class="border rounded p-3 h-100">
                        <small class="text-muted d-block">High</small>
                        <strong class="h4 mb-0 text-warning">{{ $summary['high'] }}</strong>
                    </div>
                </div>
                <div class="col-md-2 col-6 mb-3">
                    <div class="border rounded p-3 h-100">
                        <small class="text-muted d-block">Watch</small>
                        <strong class="h4 mb-0 text-info">{{ $summary['watch'] }}</strong>
                    </div>
                </div>
                <div class="col-md-2 col-6 mb-3">
                    <div class="border rounded p-3 h-100">
                        <small class="text-muted d-block">Clear</small>
                        <strong class="h4 mb-0 text-success">{{ $summary['clear'] }}</strong>
                    </div>
                </div>
                <div class="col-md-2 col-6 mb-3">
                    <div class="border rounded p-3 h-100">
                        <small class="text-muted d-block">Updated</small>
                        <strong class="small">{{ $summary['last_analyzed_at'] ?: 'Never' }}</strong>
                    </div>
                </div>
            </div>

            <form method="GET" action="{{ route('seat-spy-hunter.index') }}" class="form-row align-items-end">
                <div class="form-group col-md-3">
                    <label for="rating">Risk</label>
                    <select name="rating" id="rating" class="form-control">
                        <option value="">Any</option>
                        @foreach(['critical' => 'Critical', 'high' => 'High', 'watch' => 'Watch', 'clear' => 'Clear'] as $value => $label)
                            <option value="{{ $value }}" {{ $rating === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-6">
                    <label for="search">Search</label>
                    <input type="text" name="search" id="search" value="{{ $search }}" class="form-control" placeholder="User account, user ID, corporation, alliance">
                </div>
                <div class="form-group col-md-3">
                    <button type="submit" class="btn btn-secondary btn-block">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body table-responsive p-0">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>User Account</th>
                        <th>Monitored Characters</th>
                        <th>Primary Group</th>
                        <th>Risk</th>
                        <th>Signals</th>
                        <th>Hostile</th>
                        <th>IP</th>
                        <th>Account SP</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reports as $report)
                        @php
                            $accountUserId = $report->account_user_id ?: $report->user_id;
                            $accountCharacters = data_get(optional($report->evidence->firstWhere('category', 'account_characters'))->meta, 'characters', []);
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ route('seatcore::configuration.users.edit', ['user_id' => $accountUserId]) }}">
                                    <strong>{{ $report->character_name ?: ('User #' . $accountUserId) }}</strong>
                                </a><br>
                                <small class="text-muted">User #{{ $accountUserId }}</small>
                                <div class="mt-1">
                                    @php($reviewBadge = ['new' => 'secondary', 'reviewing' => 'info', 'cleared' => 'success', 'watchlisted' => 'warning', 'escalated' => 'danger'][$report->review_status] ?? 'secondary')
                                    <span class="badge badge-{{ $reviewBadge }}">{{ ucfirst($report->review_status ?: 'new') }}</span>
                                </div>
                            </td>
                            <td>
                                @forelse(array_slice($accountCharacters, 0, 3) as $character)
                                    <div>
                                        <a href="{{ route('seatcore::character.view.sheet', ['character' => $character['character_id']]) }}">
                                            {{ $character['name'] ?: $character['character_id'] }}
                                        </a>
                                        @if(!empty($character['main']))
                                            <span class="badge badge-info">Main</span>
                                        @endif
                                    </div>
                                @empty
                                    <span class="text-muted">No monitored characters captured</span>
                                @endforelse
                                @if(count($accountCharacters) > 3)
                                    <small class="text-muted">+{{ count($accountCharacters) - 3 }} more</small>
                                @endif
                            </td>
                            <td>
                                {{ $report->corporation_name ?: $report->corporation_id }}<br>
                                <small class="text-muted">{{ $report->alliance_name ?: ($report->alliance_id ?: 'No alliance') }}</small>
                            </td>
                            <td>
                                @php($badge = ['critical' => 'danger', 'high' => 'warning', 'watch' => 'info', 'clear' => 'success'][$report->rating] ?? 'secondary')
                                <span class="badge badge-{{ $badge }}">{{ ucfirst($report->rating) }}</span>
                                <div class="small text-muted">{{ $report->score }}/100</div>
                            </td>
                            <td>{{ $report->evidence_count }}</td>
                            <td>
                                <span class="small">Contacts {{ $report->hostile_contact_count }}</span><br>
                                <span class="small">Mail {{ $report->hostile_mail_count }}</span><br>
                                <span class="small">Wallet {{ $report->hostile_wallet_count }}</span>
                            </td>
                            <td>
                                <span class="small">Shared users {{ $report->shared_ip_user_count }}</span><br>
                                <span class="small">VPN/proxy {{ $report->vpn_ip_count }}</span>
                            </td>
                            <td>
                                {{ $report->skillpoints !== null ? number_format($report->skillpoints) : 'Unknown' }}<br>
                                <small class="text-muted">{{ count($accountCharacters) }} monitored character{{ count($accountCharacters) === 1 ? '' : 's' }}</small>
                            </td>
                            <td class="text-right">
                                <button type="button" class="btn btn-outline-secondary btn-sm" data-toggle="modal" data-target="#intel-report-modal-{{ $report->id }}">
                                    <i class="fas fa-search"></i> Review
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-5">
                                No account spy hunter reports yet. Add monitored corporations or alliances in settings, then refresh reports.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($reports->hasPages())
            <div class="card-footer">
                {{ $reports->links() }}
            </div>
        @endif
    </div>

    @foreach($reports as $report)
        @include('seat-spy-hunter::partials.report-modal', ['report' => $report])
    @endforeach
@endsection
