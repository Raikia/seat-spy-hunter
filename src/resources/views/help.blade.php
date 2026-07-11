@extends('web::layouts.grids.12')

@section('title', 'Spy Hunter Help')
@section('page_header', 'Spy Hunter Help')

@section('content')
    <style>
        .spy-hunter-help-table td,
        .spy-hunter-help-table th {
            vertical-align: top;
        }

        .spy-hunter-help-table .category-code {
            font-size: 0.82rem;
            word-break: break-word;
        }

        .spy-hunter-help-range {
            min-width: 86px;
            text-align: center;
            white-space: nowrap;
        }
    </style>

    <div class="card mb-4">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-question-circle mr-1"></i> How Spy Hunter Scores Accounts</h3>
        </div>
        <div class="card-body">
            <p>
                Spy Hunter starts with every SeAT account that has at least one character in a configured monitored corporation or alliance.
                It then reviews all linked characters on that SeAT account, including alts outside the monitored group.
            </p>
            <p class="mb-0">
                Evidence points are added together, false-positive suppressions remove matching evidence categories, and linked-character mitigation is subtracted.
                The final account score is clamped between 0 and 100. A score is a triage aid, not a verdict.
            </p>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-thermometer-half mr-1"></i> Criticality Levels</h3>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                        <tr>
                            <th>Level</th>
                            <th>Score</th>
                            <th>Meaning</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($ratings as $rating)
                            <tr>
                                <td><span class="badge badge-{{ $rating['class'] }}">{{ $rating['rating'] }}</span></td>
                                <td>{{ $rating['range'] }}</td>
                                <td>{{ $rating['description'] }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-balance-scale mr-1"></i> Confidence</h3>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                        <tr>
                            <th>Level</th>
                            <th>Rule</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($confidenceLevels as $confidence)
                            <tr>
                                <td><span class="badge badge-{{ $confidence['class'] }}">{{ $confidence['level'] }}</span></td>
                                <td>{{ $confidence['rule'] }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-footer text-muted">
                    Low confidence usually means missing ESI scopes, deleted or missing tokens, or too little visible activity data.
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-list-ol mr-1"></i> Evidence Point Values</h3>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                Dynamic scores use the current plugin settings where applicable. Freshness rules generally add weight for activity inside 180 days, keep base value inside 2 years, and down-weight older activity.
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-striped table-bordered spy-hunter-help-table mb-0">
                    <thead>
                    <tr>
                        <th>Evidence</th>
                        <th class="spy-hunter-help-range">Points</th>
                        <th>What It Means</th>
                        <th>Scoring Rule</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($evidenceRows as $row)
                        @php
                            $points = (string) $row['points'];
                            $badge = 'secondary';

                            if (str_starts_with($points, '-')) {
                                $badge = 'success';
                            } elseif (preg_match('/\d+/', $points, $matches)) {
                                $max = collect(explode('-', $points))
                                    ->flatMap(fn($part) => preg_match_all('/\d+/', $part, $found) ? $found[0] : [])
                                    ->map(fn($value) => (int) $value)
                                    ->max();
                                $badge = $max >= 40 ? 'danger' : ($max >= 20 ? 'warning' : ($max > 0 ? 'info' : 'secondary'));
                            }
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ $row['label'] }}</strong>
                                <div class="text-muted category-code">{{ $row['category'] }}</div>
                            </td>
                            <td class="spy-hunter-help-range">
                                <span class="badge badge-{{ $badge }}">{{ $row['points'] }}</span>
                            </td>
                            <td>{{ $row['meaning'] }}</td>
                            <td>{{ $row['rule'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-user-check mr-1"></i> Review Statuses</h3>
                </div>
                <div class="card-body">
                    <p><span class="badge badge-secondary">New</span> has not been reviewed yet.</p>
                    <p><span class="badge badge-info">Reviewing</span> is actively being looked at.</p>
                    <p><span class="badge badge-info">Watchlisted</span>, <span class="badge badge-warning">Concerned</span>, and <span class="badge badge-danger">Escalated</span> are manual director labels. Spy Hunter does not automatically reset them when new evidence appears.</p>
                    <p><span class="badge badge-success">Cleared</span> hides the account from the default active dashboard. Depending on settings, new evidence may reopen it.</p>
                    <p class="mb-0"><span class="badge badge-success">Permanently Cleared</span> stays cleared even when new evidence appears.</p>
                </div>
            </div>
        </div>

        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-filter mr-1"></i> Suppressions and Caches</h3>
                </div>
                <div class="card-body">
                    <p>False-positive suppressions remove matching evidence categories before scoring. The suppressed evidence is still shown as context so reviewers can see what was ignored.</p>
                    <p>VPNAPI.io lookups are queued from public SeAT login IPs and cached indefinitely by IP address. Private, reserved, localhost, and Docker/internal IPs are skipped.</p>
                    <p class="mb-0">EveWho is only used to discover current hostile corp/alliance members. Employment history comparisons use SeAT ESI history after those hostile members are known.</p>
                </div>
            </div>
        </div>
    </div>
@endsection
