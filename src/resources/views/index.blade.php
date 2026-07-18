@extends('web::layouts.grids.12')

@section('title', 'Spy Hunter')
@section('page_header', 'Spy Hunter')

@section('content')
    @php
        $sortLink = function (string $column) use ($sort, $order) {
            $nextOrder = $sort === $column && $order === 'asc' ? 'desc' : 'asc';

            return request()->fullUrlWithQuery([
                'sort' => $column,
                'order' => $nextOrder,
                'page' => null,
            ]);
        };
        $sortIcon = function (string $column) use ($sort, $order) {
            if ($sort !== $column) {
                return '<i class="fas fa-sort text-muted ml-1"></i>';
            }

            return $order === 'asc'
                ? '<i class="fas fa-sort-up ml-1"></i>'
                : '<i class="fas fa-sort-down ml-1"></i>';
        };
    @endphp
    <style>
        #spy-hunter-bulk-review-form .spy-hunter-bulk-status {
            min-width: 190px;
        }

        #spy-hunter-bulk-review-form .spy-hunter-updated-at {
            color: inherit;
            font-size: 0.95rem;
            line-height: 1.35;
            margin-top: 0.25rem;
            white-space: nowrap;
        }
    </style>

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
                <div class="col-md col-6 mb-3">
                    <div class="border rounded p-3 h-100">
                        <small class="text-muted d-block">Total</small>
                        <strong class="h4 mb-0">{{ $summary['total'] }}</strong>
                    </div>
                </div>
                <div class="col-md col-6 mb-3">
                    <div class="border rounded p-3 h-100">
                        <small class="text-muted d-block">Watchlisted</small>
                        <strong class="h4 mb-0 text-info">{{ $summary['watchlisted'] }}</strong>
                    </div>
                </div>
                <div class="col-md col-6 mb-3">
                    <div class="border rounded p-3 h-100">
                        <small class="text-muted d-block">Concerned</small>
                        <strong class="h4 mb-0 text-warning">{{ $summary['concerned'] }}</strong>
                    </div>
                </div>
                <div class="col-md col-6 mb-3">
                    <div class="border rounded p-3 h-100">
                        <small class="text-muted d-block">Escalated</small>
                        <strong class="h4 mb-0 text-danger">{{ $summary['escalated'] }}</strong>
                    </div>
                </div>
                <div class="col-md col-6 mb-3">
                    <div class="border rounded p-3 h-100">
                        <small class="text-muted d-block">Critical</small>
                        <strong class="h4 mb-0 text-danger">{{ $summary['critical'] }}</strong>
                    </div>
                </div>
                <div class="col-md col-6 mb-3">
                    <div class="border rounded p-3 h-100">
                        <small class="text-muted d-block">High</small>
                        <strong class="h4 mb-0 text-warning">{{ $summary['high'] }}</strong>
                    </div>
                </div>
                <div class="col-md col-6 mb-3">
                    <div class="border rounded p-3 h-100">
                        <small class="text-muted d-block">Updated</small>
                        <strong class="small">{{ $summary['last_analyzed_at'] ?: 'Never' }}</strong>
                    </div>
                </div>
            </div>

            <form method="GET" action="{{ route('seat-spy-hunter.index') }}" class="form-row align-items-end">
                <input type="hidden" name="sort" value="{{ $sort }}">
                <input type="hidden" name="order" value="{{ $order }}">
                <div class="form-group col-md-1">
                    <label for="rating">Risk</label>
                    <select name="rating" id="rating" class="form-control">
                        <option value="">Any</option>
                        @foreach(['critical' => 'Critical', 'high' => 'High', 'watch' => 'Watch', 'clear' => 'Clear'] as $value => $label)
                            <option value="{{ $value }}" {{ $rating === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-2">
                    <label for="review_status">Review</label>
                    <select name="review_status" id="review_status" class="form-control">
                        @foreach(['active' => 'Active Queue', 'new' => 'New', 'reviewing' => 'Reviewing', 'watchlisted' => 'Watchlisted', 'concerned' => 'Concerned', 'escalated' => 'Escalated', 'cleared' => 'Cleared', 'permanently_cleared' => 'Permanently Cleared', 'all' => 'All'] as $value => $label)
                            <option value="{{ $value }}" {{ $reviewStatus === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-2">
                    <label for="evidence_category">Evidence</label>
                    <select name="evidence_category" id="evidence_category" class="form-control">
                        <option value="">Any</option>
                        @foreach($evidenceCategories as $value => $label)
                            <option value="{{ $value }}" {{ $evidenceCategory === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-2">
                    <label for="suppressed">Suppressed</label>
                    <select name="suppressed" id="suppressed" class="form-control">
                        <option value="">Any</option>
                        <option value="with" {{ $suppressed === 'with' ? 'selected' : '' }}>Has hidden evidence</option>
                        <option value="without" {{ $suppressed === 'without' ? 'selected' : '' }}>No hidden evidence</option>
                    </select>
                </div>
                <div class="form-group col-md-2">
                    <label for="search">Search</label>
                    <input type="text" name="search" id="search" value="{{ $search }}" class="form-control" placeholder="User account, user ID, corporation, alliance">
                </div>
                <div class="form-group col-md-1">
                    <label for="per_page">Per Page</label>
                    <select name="per_page" id="per_page" class="form-control">
                        @foreach([25, 50, 100, 250] as $size)
                            <option value="{{ $size }}" {{ (int) $perPage === $size ? 'selected' : '' }}>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-2">
                    <button type="submit" class="btn btn-secondary btn-block">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <form method="POST" action="{{ route('seat-spy-hunter.reports.bulk-review') }}" id="spy-hunter-bulk-review-form">
        {{ csrf_field() }}
        <div class="card">
            <div class="card-header py-2">
                <div class="d-flex align-items-center flex-wrap">
                    <strong class="mr-3 mb-2 mb-md-0">Bulk Review</strong>
                    <div class="mr-2 mb-2 mb-md-0">
                        <label for="bulk_review_status" class="sr-only">Bulk Status</label>
                        <select name="review_status" id="bulk_review_status" class="form-control form-control-sm spy-hunter-bulk-status">
                            @foreach(['new' => 'New', 'reviewing' => 'Reviewing', 'watchlisted' => 'Watchlisted', 'concerned' => 'Concerned', 'escalated' => 'Escalated', 'cleared' => 'Cleared', 'permanently_cleared' => 'Permanently Cleared'] as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex-grow-1 mr-md-2 mb-2 mb-md-0">
                        <label for="bulk_review_notes" class="sr-only">Bulk Note</label>
                        <input type="text" name="review_notes" id="bulk_review_notes" class="form-control form-control-sm" maxlength="5000" placeholder="Optional note applied to every selected account">
                    </div>
                    <div class="d-flex align-items-center mb-2 mb-md-0">
                        <span class="badge badge-secondary mr-2"><span id="bulk_review_selected_count">0</span> selected</span>
                        <button type="submit" class="btn btn-primary btn-sm" id="bulk_review_submit" disabled>
                            <i class="fas fa-check-square"></i> Update Selected
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                <table id="spy-hunter-reports-table" class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th class="text-center">
                                <input type="checkbox" id="spy-hunter-select-all" aria-label="Select all visible reports">
                            </th>
                            <th>
                                <a href="{{ $sortLink('user') }}">User Account {!! $sortIcon('user') !!}</a>
                            </th>
                            <th>Account Characters</th>
                            <th>
                                <a href="{{ $sortLink('group') }}">Primary Group {!! $sortIcon('group') !!}</a>
                            </th>
                            <th>
                                <a href="{{ $sortLink('risk') }}">Risk {!! $sortIcon('risk') !!}</a>
                            </th>
                            <th>
                                <a href="{{ $sortLink('signals') }}">Signals {!! $sortIcon('signals') !!}</a>
                            </th>
                            <th>
                                <a href="{{ $sortLink('hostile') }}">Hostile {!! $sortIcon('hostile') !!}</a>
                            </th>
                            <th>
                                <a href="{{ $sortLink('ip') }}">IP {!! $sortIcon('ip') !!}</a>
                            </th>
                            <th>
                                <a href="{{ $sortLink('sp') }}">Account SP {!! $sortIcon('sp') !!}</a>
                            </th>
                            <th>
                                <a href="{{ $sortLink('updated') }}">Updated {!! $sortIcon('updated') !!}</a>
                            </th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reports as $report)
                        @php
                            $accountUserId = $report->account_user_id ?: $report->user_id;
                            $accountCharacters = data_get(optional($report->evidence->firstWhere('category', 'account_characters'))->meta, 'characters', []);
                            $accountConnectors = data_get(optional($report->evidence->firstWhere('category', 'account_connectors'))->meta, 'connectors', []);
                            $confidenceEvidence = $report->evidence->firstWhere('category', 'risk_confidence');
                            $confidenceLevel = data_get(optional($confidenceEvidence)->meta, 'level');
                            $confidenceBadge = data_get(['high' => 'success', 'medium' => 'warning', 'low' => 'danger'], $confidenceLevel, 'secondary');
                            $hasNewEvidence = (bool) $report->evidence->firstWhere('category', 'new_evidence_since_review');
                            $hasSuppressedEvidence = (bool) $report->evidence->firstWhere('category', 'suppressed_signals');
                            $freshnessDays = $report->last_analyzed_at ? $report->last_analyzed_at->diffInDays(now()) : null;
                        @endphp
                        <tr>
                            <td class="text-center" data-order="0">
                                <input type="checkbox" name="report_ids[]" value="{{ $report->id }}" class="spy-hunter-report-checkbox" aria-label="Select {{ $report->character_name ?: ('User #' . $accountUserId) }}">
                            </td>
                            <td data-order="{{ strtolower($report->character_name ?: ('user ' . $accountUserId)) }}">
                                <a href="{{ route('seatcore::configuration.users.edit', ['user_id' => $accountUserId]) }}">
                                    <strong>{{ $report->character_name ?: ('User #' . $accountUserId) }}</strong>
                                </a><br>
                                <small class="text-muted">User #{{ $accountUserId }}</small>
                                <div class="mt-1">
                                    @php
                                        $reviewLabels = ['new' => 'New', 'reviewing' => 'Reviewing', 'cleared' => 'Cleared', 'permanently_cleared' => 'Permanently Cleared', 'watchlisted' => 'Watchlisted', 'concerned' => 'Concerned', 'escalated' => 'Escalated'];
                                        $reviewBadge = data_get(['new' => 'secondary', 'reviewing' => 'info', 'cleared' => 'success', 'permanently_cleared' => 'success', 'watchlisted' => 'info', 'concerned' => 'warning', 'escalated' => 'danger'], $report->review_status, 'secondary');
                                    @endphp
                                    <span class="badge badge-{{ $reviewBadge }}">{{ $reviewLabels[$report->review_status] ?? ucfirst($report->review_status ?: 'new') }}</span>
                                    @if($hasNewEvidence)
                                        <span class="badge badge-primary">New evidence</span>
                                    @endif
                                    @if($hasSuppressedEvidence)
                                        <span class="badge badge-secondary">Suppressed</span>
                                    @endif
                                </div>
                            </td>
                            <td data-order="{{ count($accountCharacters) }}">
                                @forelse(array_slice($accountCharacters, 0, 3) as $character)
                                    <div>
                                        <a href="{{ route('seatcore::character.view.sheet', ['character' => $character['character_id']]) }}">
                                            {{ $character['name'] ?: $character['character_id'] }}
                                        </a>
                                        @if(!empty($character['main']))
                                            <span class="badge badge-info">Main</span>
                                        @endif
                                        @if(!empty($character['monitored']))
                                            <span class="badge badge-primary">Monitored</span>
                                        @endif
                                    </div>
                                @empty
                                    <span class="text-muted">No linked characters captured</span>
                                @endforelse
                                @if(count($accountCharacters) > 3)
                                    <small class="text-muted">+{{ count($accountCharacters) - 3 }} more</small>
                                @endif
                            </td>
                            <td data-order="{{ strtolower(($report->corporation_name ?: $report->corporation_id) . ' ' . ($report->alliance_name ?: $report->alliance_id)) }}">
                                {{ $report->corporation_name ?: $report->corporation_id }}<br>
                                <small class="text-muted">{{ $report->alliance_name ?: ($report->alliance_id ?: 'No alliance') }}</small>
                            </td>
                            <td data-order="{{ data_get(['escalated' => 300000, 'concerned' => 200000, 'watchlisted' => 100000], $report->review_status, 0) + $report->score }}">
                                @php
                                    $badge = data_get(['critical' => 'danger', 'high' => 'warning', 'watch' => 'info', 'clear' => 'success'], $report->rating, 'secondary');
                                @endphp
                                <span class="badge badge-{{ $badge }}">{{ ucfirst($report->rating) }}</span>
                                <div class="small text-muted">{{ $report->score }}/100</div>
                                @if($confidenceLevel)
                                    <span class="badge badge-{{ $confidenceBadge }}">Confidence {{ ucfirst($confidenceLevel) }}</span>
                                @endif
                            </td>
                            <td data-order="{{ $report->evidence_count }}">{{ $report->evidence_count }}</td>
                            <td data-order="{{ $report->hostile_contact_count + $report->hostile_mail_count + $report->hostile_wallet_count }}">
                                <span class="small">Contacts {{ $report->hostile_contact_count }}</span><br>
                                <span class="small">Mail {{ $report->hostile_mail_count }}</span><br>
                                <span class="small">Wallet {{ $report->hostile_wallet_count }}</span><br>
                                <span class="small">Connectors {{ count($accountConnectors) }}</span>
                            </td>
                            <td data-order="{{ $report->shared_ip_user_count + $report->vpn_ip_count }}">
                                <span class="small">Shared users {{ $report->shared_ip_user_count }}</span><br>
                                <span class="small">VPN/proxy {{ $report->vpn_ip_count }}</span>
                            </td>
                            <td data-order="{{ $report->skillpoints ?: 0 }}">
                                {{ $report->skillpoints !== null ? number_format($report->skillpoints) : 'Unknown' }}<br>
                                <small class="text-muted">{{ count($accountCharacters) }} linked character{{ count($accountCharacters) === 1 ? '' : 's' }}</small>
                            </td>
                            <td data-order="{{ optional($report->last_analyzed_at)->timestamp ?: 0 }}">
                                @if($report->last_analyzed_at)
                                    @if($freshnessDays !== null && $freshnessDays >= 14)
                                        <span class="badge badge-warning">Stale</span>
                                    @else
                                        <span class="badge badge-success">Fresh</span>
                                    @endif
                                    <div class="spy-hunter-updated-at">{{ $report->last_analyzed_at->diffForHumans() }}</div>
                                @else
                                    <span class="badge badge-secondary">Never</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <button type="button" class="btn btn-outline-secondary btn-sm" data-toggle="modal" data-target="#intel-report-modal-{{ $report->id }}">
                                    <i class="fas fa-search"></i> Review
                                </button>
                            </td>
                        </tr>
                    @empty
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if(method_exists($reports, 'links'))
                <div class="card-footer d-flex justify-content-between align-items-center flex-wrap">
                    <div class="text-muted mb-2 mb-md-0">
                        Showing {{ $reports->firstItem() ?: 0 }}-{{ $reports->lastItem() ?: 0 }} of {{ $reports->total() }} matching reports
                    </div>
                    <div>
                        {{ $reports->links() }}
                    </div>
                </div>
            @endif
        </div>
    </form>

    @foreach($reports as $report)
        @include('seat-spy-hunter::partials.report-modal', ['report' => $report])
    @endforeach
@endsection

@push('javascript')
    <script>
        $(function () {
            function updateBulkReviewControls() {
                var selectedCount = $('.spy-hunter-report-checkbox:checked').length;

                $('#bulk_review_selected_count').text(selectedCount);
                $('#bulk_review_submit').prop('disabled', selectedCount === 0);

                var visibleCheckboxes = $('#spy-hunter-reports-table .spy-hunter-report-checkbox');
                var visibleSelectedCount = visibleCheckboxes.filter(':checked').length;

                $('#spy-hunter-select-all')
                    .prop('checked', visibleCheckboxes.length > 0 && visibleSelectedCount === visibleCheckboxes.length)
                    .prop('indeterminate', visibleSelectedCount > 0 && visibleSelectedCount < visibleCheckboxes.length);
            }

            $('#spy-hunter-select-all').on('change', function () {
                $('#spy-hunter-reports-table .spy-hunter-report-checkbox').prop('checked', this.checked);

                updateBulkReviewControls();
            });

            $('#spy-hunter-reports-table').on('change', '.spy-hunter-report-checkbox', updateBulkReviewControls);

            $('#spy-hunter-bulk-review-form').on('submit', function (event) {
                if ($('.spy-hunter-report-checkbox:checked').length === 0) {
                    event.preventDefault();
                }
            });
        });
    </script>
@endpush
