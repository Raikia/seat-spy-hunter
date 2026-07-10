@php
    $accountUserId = $report->account_user_id ?: $report->user_id;
    $badge = ['critical' => 'danger', 'high' => 'warning', 'watch' => 'info', 'clear' => 'success'][$report->rating] ?? 'secondary';
    $categoryLabels = [
        'hostile_contacts' => 'Hostile Contacts',
        'account_characters' => 'Account Characters',
        'account_connectors' => 'Connectors',
        'esi_coverage_health' => 'ESI Coverage',
        'multi_character_account' => 'Linked Characters',
        'low_account_skillpoints' => 'Low Account SP',
        'no_pve_wallet_history' => 'No PvE Wallet',
        'limited_recent_wallet_activity' => 'Low Wallet Activity',
        'stable_wallet_balance' => 'Stable Wallet',
        'hostile_corporation_history' => 'Hostile Corp History',
        'recent_neutral_corporation_history' => 'Recent Neutral Corp',
        'corporation_history_churn' => 'Corp Churn',
        'quiet_corporation_history' => 'Quiet Corp History',
        'thin_seat_footprint' => 'Thin Footprint',
        'no_productive_footprint' => 'No PvE/Indy/Market',
        'no_saved_fittings' => 'No Saved Fittings',
        'no_lossmails' => 'No Lossmails',
        'low_loyalty_points' => 'Low LP',
        'age_skill_mismatch' => 'Age vs SP',
        'low_assets' => 'Low Assets',
        'low_asset_value' => 'Low Asset Value',
        'hostile_asset_location' => 'Hostile Asset Location',
        'hostile_employment_overlap' => 'Hostile Employment Overlap',
        'hostile_mail' => 'Hostile Mail',
        'hostile_wallet' => 'Hostile Wallet',
        'hostile_wallet_direct' => 'Direct Wallet',
        'hostile_market_transaction' => 'Market Trade',
        'hostile_killmail' => 'Hostile Killmail',
        'hostile_contract' => 'Hostile Contract',
        'risk_confidence' => 'Risk Confidence',
        'new_evidence_since_review' => 'New Evidence',
        'shared_ip' => 'Shared IP',
        'vpn_ip' => 'VPN / Proxy',
        'missing_token' => 'Missing Token',
        'deleted_token' => 'Deleted Token',
        'missing_refresh_token' => 'Missing Refresh Token',
        'stale_token' => 'Stale Token',
        'no_login_history' => 'No Login History',
        'sparse_activity' => 'Sparse Activity',
        'few_trained_skills' => 'Few Skills',
        'low_skills' => 'Low SP',
        'new_character' => 'New Character',
        'suppressed_signals' => 'Suppressed Signals',
    ];
    $categoryBadges = [
        'hostile_contacts' => 'danger',
        'account_characters' => 'secondary',
        'account_connectors' => 'secondary',
        'esi_coverage_health' => 'primary',
        'multi_character_account' => 'success',
        'low_account_skillpoints' => 'info',
        'no_pve_wallet_history' => 'info',
        'limited_recent_wallet_activity' => 'info',
        'stable_wallet_balance' => 'info',
        'hostile_corporation_history' => 'danger',
        'recent_neutral_corporation_history' => 'warning',
        'corporation_history_churn' => 'warning',
        'quiet_corporation_history' => 'secondary',
        'thin_seat_footprint' => 'info',
        'no_productive_footprint' => 'info',
        'no_saved_fittings' => 'warning',
        'no_lossmails' => 'warning',
        'low_loyalty_points' => 'warning',
        'age_skill_mismatch' => 'info',
        'low_assets' => 'info',
        'low_asset_value' => 'warning',
        'hostile_asset_location' => 'warning',
        'hostile_employment_overlap' => 'danger',
        'hostile_mail' => 'danger',
        'hostile_wallet' => 'danger',
        'hostile_wallet_direct' => 'danger',
        'hostile_market_transaction' => 'warning',
        'hostile_killmail' => 'danger',
        'hostile_contract' => 'danger',
        'risk_confidence' => 'primary',
        'new_evidence_since_review' => 'primary',
        'shared_ip' => 'warning',
        'vpn_ip' => 'warning',
        'missing_token' => 'dark',
        'deleted_token' => 'dark',
        'missing_refresh_token' => 'dark',
        'stale_token' => 'secondary',
        'no_login_history' => 'secondary',
        'sparse_activity' => 'info',
        'few_trained_skills' => 'info',
        'low_skills' => 'info',
        'new_character' => 'info',
        'suppressed_signals' => 'secondary',
    ];
    $reviewLabels = ['new' => 'New', 'reviewing' => 'Reviewing', 'cleared' => 'Cleared', 'permanently_cleared' => 'Permanently Cleared', 'watchlisted' => 'Watchlisted', 'concerned' => 'Concerned', 'escalated' => 'Escalated'];
    $reviewBadge = ['new' => 'secondary', 'reviewing' => 'info', 'cleared' => 'success', 'permanently_cleared' => 'success', 'watchlisted' => 'info', 'concerned' => 'warning', 'escalated' => 'danger'][$report->review_status] ?? 'secondary';
    $characterLink = function ($characterId, $label) {
        if (!$characterId) {
            return e($label ?: 'Unknown');
        }

        return '<a href="' . route('seatcore::character.view.sheet', ['character' => $characterId]) . '" target="_blank" rel="noopener noreferrer">' . e($label ?: $characterId) . '</a>';
    };
    $scoreBadge = function ($score, $fallback = 'secondary') {
        $score = (int) $score;

        if ($score >= 40) {
            return 'danger';
        }

        if ($score >= 25) {
            return 'warning';
        }

        if ($score >= 10) {
            return 'info';
        }

        if ($score > 0) {
            return 'secondary';
        }

        return $fallback ?: 'light';
    };
@endphp

<div class="modal fade" id="intel-report-modal-{{ $report->id }}" tabindex="-1" role="dialog" aria-labelledby="intel-report-modal-title-{{ $report->id }}" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="intel-report-modal-title-{{ $report->id }}">
                        @if($report->character_id)
                            {!! $characterLink($report->character_id, $report->character_name ?: ('User #' . $accountUserId)) !!}
                        @else
                            {{ $report->character_name ?: ('User #' . $accountUserId) }}
                        @endif
                    </h5>
	                    <small class="text-muted">
                        SeAT User #{{ $accountUserId }}
                        @if($report->corporation_name || $report->corporation_id)
                            / {{ $report->corporation_name ?: $report->corporation_id }}
                        @endif
                        @if($report->alliance_name || $report->alliance_id)
                            / {{ $report->alliance_name ?: $report->alliance_id }}
                        @endif
	                    </small>
                        <div class="mt-1">
                            <span class="badge badge-{{ $reviewBadge }}">{{ $reviewLabels[$report->review_status] ?? ucfirst($report->review_status ?: 'new') }}</span>
                            @if($report->reviewed_at)
                                <span class="small text-muted">Reviewed {{ $report->reviewed_at->toDateTimeString() }}</span>
                            @endif
                        </div>
	                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

	            <div class="modal-body">
	                <div class="border rounded p-3 mb-3">
	                    <h6 class="mb-2">Review Workflow</h6>
	                    <form action="{{ route('seat-spy-hunter.reports.review', $report) }}" method="POST">
	                        {{ csrf_field() }}
	                        <div class="form-row">
	                            <div class="form-group col-md-3">
	                                <label>Status</label>
	                                <select name="review_status" class="form-control">
	                                    @foreach(['new' => 'New', 'reviewing' => 'Reviewing', 'watchlisted' => 'Watchlisted', 'concerned' => 'Concerned', 'escalated' => 'Escalated', 'cleared' => 'Cleared', 'permanently_cleared' => 'Permanently Cleared'] as $value => $label)
	                                        <option value="{{ $value }}" {{ $report->review_status === $value ? 'selected' : '' }}>{{ $label }}</option>
	                                    @endforeach
	                                </select>
                                    <small class="form-text text-muted">Permanently cleared accounts stay hidden from the active queue even when new evidence appears.</small>
	                            </div>
	                            <div class="form-group col-md-7">
	                                <label>Notes</label>
	                                <input type="text" name="review_notes" class="form-control" value="{{ $report->review_notes }}" placeholder="What did you decide and why?">
	                            </div>
	                            <div class="form-group col-md-2 d-flex align-items-end">
	                                <button type="submit" class="btn btn-primary btn-block btn-sm">
	                                    <i class="fas fa-save"></i> Save
	                                </button>
	                            </div>
	                        </div>
	                    </form>
	                    @if($report->suppressions->isNotEmpty())
	                        <hr>
	                        <h6 class="mb-2">Active Suppressions</h6>
	                        @foreach($report->suppressions as $suppression)
	                            <form action="{{ route('seat-spy-hunter.suppressions.destroy', $suppression) }}" method="POST" class="d-inline-block mb-1">
	                                {{ csrf_field() }}
	                                {{ method_field('DELETE') }}
	                                <button type="submit" class="btn btn-outline-secondary btn-sm">
	                                    <i class="fas fa-times"></i>
	                                    {{ $categoryLabels[$suppression->category] ?? str_replace('_', ' ', ucfirst($suppression->category)) }}
	                                </button>
	                            </form>
	                        @endforeach
	                    @endif
	                </div>

	                <div class="row mb-3">
                    @php
                        $confidenceEvidence = $report->evidence->firstWhere('category', 'risk_confidence');
                        $confidenceLevel = data_get(optional($confidenceEvidence)->meta, 'level');
                        $confidenceBadge = data_get(['high' => 'success', 'medium' => 'warning', 'low' => 'danger'], $confidenceLevel, 'secondary');
                    @endphp
	                    <div class="col-md-3 col-6 mb-3">
                        <div class="border rounded p-3 h-100">
                            <small class="text-muted d-block">Risk</small>
                            <span class="badge badge-{{ $badge }}">{{ ucfirst($report->rating) }}</span>
                            <strong class="d-block h4 mb-0 mt-2">{{ $report->score }}/100</strong>
                            @if($confidenceLevel)
                                <span class="badge badge-{{ $confidenceBadge }}">Confidence {{ ucfirst($confidenceLevel) }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="col-md-3 col-6 mb-3">
                        <div class="border rounded p-3 h-100">
                            <small class="text-muted d-block">Signals</small>
                            <strong class="h4 mb-0">{{ $report->evidence_count }}</strong>
                            <div class="small text-muted">Evidence rows</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6 mb-3">
                        <div class="border rounded p-3 h-100">
                            <small class="text-muted d-block">Hostile Touches</small>
                            <strong class="h4 mb-0">{{ $report->hostile_contact_count + $report->hostile_mail_count + $report->hostile_wallet_count }}</strong>
                            <div class="small text-muted">Contacts, mail, wallet</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6 mb-3">
                        <div class="border rounded p-3 h-100">
                            <small class="text-muted d-block">Account Characters</small>
                            <strong class="d-block">{{ $report->skillpoints !== null ? number_format($report->skillpoints) . ' SP' : 'Unknown SP' }}</strong>
                            <span class="small text-muted">Total linked-character SP</span>
                        </div>
	                    </div>
	                </div>

                    @php
                        $scoreEvidence = $report->evidence->filter(fn($row) => (int) $row->score > 0)->sortByDesc('score')->values();
                        $scoreSubtotal = (int) $scoreEvidence->sum('score');
                        $mitigationEvidence = $report->evidence
                            ->filter(fn($row) => (int) data_get($row->meta, 'mitigation_score', 0) > 0)
                            ->values();
                        $mitigationTotal = (int) $mitigationEvidence->sum(fn($row) => (int) data_get($row->meta, 'mitigation_score', 0));
                        $scoreAfterMitigation = max(0, $scoreSubtotal - $mitigationTotal);
                        $nonScoringEvidenceCount = $report->evidence->filter(fn($row) => (int) $row->score === 0 && (int) data_get($row->meta, 'mitigation_score', 0) === 0)->count();
                    @endphp

                    <div class="border rounded p-3 mb-3">
                        <div class="d-flex justify-content-between align-items-start flex-wrap mb-2">
                            <div>
                                <h6 class="mb-1">Score Explanation</h6>
                                <div class="small text-muted">Positive evidence minus mitigations, capped at 100.</div>
                            </div>
                            <span class="badge badge-{{ $badge }}">{{ $report->score }}/100 final</span>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-2">
                                <small class="text-muted d-block">Evidence subtotal</small>
                                <strong>{{ $scoreSubtotal }} pts</strong>
                            </div>
                            <div class="col-md-4 mb-2">
                                <small class="text-muted d-block">Mitigations</small>
                                <strong>{{ $mitigationTotal > 0 ? '-' . $mitigationTotal : 0 }} pts</strong>
                            </div>
                            <div class="col-md-4 mb-2">
                                <small class="text-muted d-block">After mitigation</small>
                                <strong>{{ $scoreAfterMitigation }} pts{{ $scoreAfterMitigation > 100 ? ' / capped' : '' }}</strong>
                            </div>
                        </div>
                        @if($scoreEvidence->isNotEmpty())
                            <div class="table-responsive mt-2">
                                <table class="table table-sm table-bordered mb-0">
                                    <thead>
                                    <tr>
                                        <th>Signal</th>
                                        <th>Reason</th>
                                        <th class="text-right">Points</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($scoreEvidence as $row)
                                        <tr>
                                            <td><span class="badge badge-{{ $scoreBadge($row->score, $categoryBadges[$row->category] ?? 'secondary') }}">{{ $categoryLabels[$row->category] ?? str_replace('_', ' ', ucfirst($row->category)) }}</span></td>
                                            <td>{{ $row->title }}</td>
                                            <td class="text-right">+{{ $row->score }}</td>
                                        </tr>
                                    @endforeach
                                    @foreach($mitigationEvidence as $row)
                                        <tr>
                                            <td><span class="badge badge-success">{{ $categoryLabels[$row->category] ?? str_replace('_', ' ', ucfirst($row->category)) }}</span></td>
                                            <td>{{ $row->title }}</td>
                                            <td class="text-right">-{{ (int) data_get($row->meta, 'mitigation_score', 0) }}</td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="text-muted mb-0">No scoring evidence is currently contributing points.</p>
                        @endif
                        @if($nonScoringEvidenceCount > 0)
                            <div class="small text-muted mt-2">{{ $nonScoringEvidenceCount }} non-scoring context row{{ $nonScoringEvidenceCount === 1 ? '' : 's' }} included below.</div>
                        @endif
                    </div>

	                <div class="row mb-3">
                    <div class="col-lg-5 mb-3">
                        <div class="border rounded p-3 h-100">
                            <h6 class="mb-2">Investigation Focus</h6>
                            @if($report->evidence->isEmpty())
                                <p class="text-muted mb-0">No current indicators were recorded for this character.</p>
                            @else
                                @foreach($report->evidence->take(5) as $evidence)
                                    <div class="mb-2">
                                        <span class="badge badge-{{ $scoreBadge($evidence->score, $categoryBadges[$evidence->category] ?? 'secondary') }}">{{ $categoryLabels[$evidence->category] ?? str_replace('_', ' ', ucfirst($evidence->category)) }}</span>
                                        <strong class="ml-1">{{ $evidence->score }} pts</strong>
                                        <div class="small">{{ $evidence->title }}</div>
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    </div>
                    <div class="col-lg-7 mb-3">
                        <div class="border rounded p-3 h-100">
                            <h6 class="mb-2">Quick Read</h6>
                            <div class="row">
                                <div class="col-sm-6">
                                    <small class="text-muted d-block">Hostile contacts</small>
                                    <strong>{{ $report->hostile_contact_count }}</strong>
                                </div>
                                <div class="col-sm-6">
                                    <small class="text-muted d-block">Hostile mail</small>
                                    <strong>{{ $report->hostile_mail_count }}</strong>
                                </div>
                                <div class="col-sm-6 mt-2">
                                    <small class="text-muted d-block">Hostile wallet</small>
                                    <strong>{{ $report->hostile_wallet_count }}</strong>
                                </div>
                                <div class="col-sm-6 mt-2">
                                    <small class="text-muted d-block">Shared/VPN IP</small>
                                    <strong>{{ $report->shared_ip_user_count + $report->vpn_ip_count }}</strong>
                                </div>
                            </div>
                            <hr>
                            <small class="text-muted d-block">Last analyzed</small>
                            <strong>{{ $report->last_analyzed_at ? $report->last_analyzed_at->toDateTimeString() : 'Never' }}</strong>
                        </div>
                    </div>
                </div>

                @php
                    $accountCharacterEvidence = $report->evidence->firstWhere('category', 'account_characters');
                    $accountConnectorEvidence = $report->evidence->firstWhere('category', 'account_connectors');
                    $accountCharacters = data_get(optional($accountCharacterEvidence)->meta, 'characters', []);
                    $accountLoginIps = data_get(optional($accountCharacterEvidence)->meta, 'login_ips', []);
                    $accountConnectors = data_get(optional($accountConnectorEvidence)->meta, 'connectors', []);
                @endphp
                @if(!empty($accountCharacters))
                    <h6 class="mb-3">Account Characters</h6>
                    <div class="table-responsive mb-3">
                        <table class="table table-sm table-bordered mb-0">
                            <thead>
                            <tr>
                                <th>Character</th>
                                <th>Corporation</th>
                                <th>Alliance</th>
                                <th>Skillpoints</th>
                                <th>Birthday</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($accountCharacters as $character)
                                <tr>
                                    <td>
                                        {!! $characterLink($character['character_id'], $character['name'] ?: $character['character_id']) !!}
                                        <span class="text-muted small">({{ $character['character_id'] }})</span>
                                        @if(!empty($character['main']))
                                            <span class="badge badge-info">Main</span>
                                        @endif
                                        @if(!empty($character['monitored']))
                                            <span class="badge badge-primary">Monitored Group</span>
                                        @endif
                                    </td>
                                    <td>{{ $character['corporation_name'] ?: ($character['corporation_id'] ?: 'Unknown') }}</td>
                                    <td>{{ $character['alliance_name'] ?: ($character['alliance_id'] ?: 'None') }}</td>
                                    <td>{{ $character['skillpoints'] !== null ? number_format($character['skillpoints']) : 'Unknown' }}</td>
                                    <td>{{ $character['birthday'] ?: 'Unknown' }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                <h6 class="mb-3">Login IPs</h6>
                <div class="table-responsive mb-3">
                    <table class="table table-sm table-bordered mb-0">
                        <thead>
                        <tr>
                            <th>IP Address</th>
                            <th>Scope</th>
                            <th>Logins</th>
                            <th>Last Seen</th>
                        </tr>
                        </thead>
                        <tbody>
	                        @forelse($accountLoginIps as $loginIp)
                                @php
                                    $ipIntel = data_get($loginIp, 'intelligence');
                                    $ipQueue = data_get($loginIp, 'queue');
                                    $ipRiskScore = (int) data_get($ipIntel, 'risk_score', 0);
                                @endphp
	                            <tr>
	                                <td>
                                        <a href="https://whatismyipaddress.com/ip/{{ urlencode($loginIp['ip']) }}" target="_blank" rel="noopener noreferrer">
                                            <code>{{ $loginIp['ip'] }}</code>
                                        </a>
                                    </td>
	                                <td>
	                                    @if(!empty($loginIp['public']))
	                                        <span class="badge badge-info">Public</span>
	                                    @else
	                                        <span class="badge badge-secondary">Private / Reserved</span>
	                                    @endif
                                        @if($ipIntel)
                                            @if(!empty($ipIntel['is_vpn']))
                                                <span class="badge badge-danger">VPN</span>
                                            @endif
                                            @if(!empty($ipIntel['is_proxy']))
                                                <span class="badge badge-danger">Proxy</span>
                                            @endif
                                            @if(!empty($ipIntel['is_tor']))
                                                <span class="badge badge-danger">Tor</span>
                                            @endif
                                            @if(!empty($ipIntel['is_hosting']))
                                                <span class="badge badge-warning">Hosting</span>
                                            @endif
                                            @if(empty($ipIntel['is_vpn']) && empty($ipIntel['is_proxy']) && empty($ipIntel['is_tor']) && empty($ipIntel['is_hosting']))
                                                <span class="badge badge-success">No VPN signal</span>
                                            @endif
	                                            @if($ipRiskScore >= 50)
	                                                <span class="badge badge-warning">Risk {{ $ipRiskScore }}</span>
                                            @endif
                                            <span class="text-muted small d-block">
                                                {{ $ipIntel['provider'] ?: 'manual' }}{{ !empty($ipIntel['checked_at']) ? ' / checked ' . $ipIntel['checked_at'] : '' }}
                                            </span>
                                        @elseif(!empty($loginIp['public']))
                                            @if(data_get($ipQueue, 'status') === 'pending')
                                                <span class="badge badge-warning">VPN lookup queued</span>
                                                @if(data_get($ipQueue, 'available_at'))
                                                    <span class="text-muted small d-block">Available {{ data_get($ipQueue, 'available_at') }}</span>
                                                @endif
                                            @elseif(data_get($ipQueue, 'status') === 'complete')
                                                <span class="badge badge-success">Lookup complete</span>
                                            @elseif($ipQueue)
                                                <span class="badge badge-danger">Lookup retry pending</span>
                                                @if(data_get($ipQueue, 'last_error'))
                                                    <span class="text-muted small d-block">{{ data_get($ipQueue, 'last_error') }}</span>
                                                @endif
                                            @else
                                                <span class="badge badge-light">Not queued yet</span>
                                            @endif
                                        @endif
	                                </td>
	                                <td>{{ $loginIp['login_count'] }}</td>
	                                <td>{{ $loginIp['last_seen_at'] ?: 'Unknown' }}</td>
	                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-muted">No login IP history found for this SeAT account.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                <h6 class="mb-3">Connectors</h6>
                <div class="table-responsive mb-3">
                    <table class="table table-sm table-bordered mb-0">
                        <thead>
                        <tr>
                            <th>Connector</th>
                            <th>Name</th>
                            <th>Connector ID</th>
                            <th>Last Updated</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($accountConnectors as $connector)
                            <tr>
                                <td>{{ ucfirst($connector['type'] ?: 'unknown') }}</td>
                                <td>{{ $connector['name'] ?: 'Unknown' }}</td>
                                <td>{{ $connector['connector_id'] ?: 'Unknown' }}</td>
                                <td>{{ $connector['updated_at'] ?: 'Unknown' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-muted">No connector registrations found for this SeAT account.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                <h6 class="mb-3">Evidence Detail</h6>
                <div class="accordion" id="spy-hunter-evidence-accordion-{{ $report->id }}">
                @forelse($report->evidence as $evidence)
                    @continue(in_array($evidence->category, ['account_characters', 'account_connectors']))
                    @php
                        $linkedDetails = e($evidence->details ?: '');
                        $knownCharacters = collect($accountCharacters)
                            ->map(fn($character) => [
                                'character_id' => data_get($character, 'character_id'),
                                'name' => data_get($character, 'name'),
                            ])
                            ->merge(collect(data_get($evidence->meta, 'matches', []))->flatMap(fn($match) => [
                                ['character_id' => data_get($match, 'character_id'), 'name' => data_get($match, 'character_name')],
                                ['character_id' => data_get($match, 'hostile_character_id'), 'name' => data_get($match, 'hostile_character_name')],
                            ]))
                            ->filter(fn($character) => data_get($character, 'character_id') && data_get($character, 'name'))
                            ->unique(fn($character) => data_get($character, 'character_id') . ':' . data_get($character, 'name'));

                        foreach ($knownCharacters as $knownCharacter) {
                            $linkedDetails = str_replace(
                                e(data_get($knownCharacter, 'name')),
                                $characterLink(data_get($knownCharacter, 'character_id'), data_get($knownCharacter, 'name')),
                                $linkedDetails
                            );
                        }

                            $evidencePanelId = 'spy-hunter-evidence-panel-' . $report->id . '-' . $evidence->id;
	                        $contextId = 'spy-hunter-evidence-context-' . $report->id . '-' . $evidence->id;
                            $rawContextId = 'spy-hunter-evidence-raw-' . $report->id . '-' . $evidence->id;
                            $meta = collect($evidence->meta ?: []);
                            $structuredKeys = [
                                'characters', 'contacts', 'new_items', 'suppressed', 'corporations', 'matches',
                                'shared_users', 'latest_received', 'latest_sent', 'latest_journal', 'latest_transactions',
                                'top_assets', 'contracts',
                            ];
                            $summaryMeta = $meta
                                ->reject(fn($value, $key) => in_array($key, $structuredKeys, true))
                                ->reject(fn($value) => is_array($value) || is_object($value))
                                ->reject(fn($value) => $value === null || $value === '')
                                ->map(fn($value, $key) => [
                                    'label' => str_replace('_', ' ', ucfirst((string) $key)),
                                    'value' => is_bool($value) ? ($value ? 'Yes' : 'No') : $value,
                                ])
                                ->values();
                            $nestedMeta = $meta
                                ->reject(fn($value, $key) => in_array($key, $structuredKeys, true))
                                ->filter(fn($value) => is_array($value) || is_object($value))
                                ->values();
                            $visibleContextCount = $summaryMeta->count() + $nestedMeta->count();
                            $detailSummary = trim(strip_tags($evidence->details ?: ''));

                            if (mb_strlen($detailSummary) > 180) {
                                $detailSummary = mb_substr($detailSummary, 0, 177) . '...';
                            }
	                    @endphp
                    <div class="border rounded mb-2">
                        <button class="btn btn-link btn-block text-left text-reset p-3" type="button" data-toggle="collapse" data-target="#{{ $evidencePanelId }}" aria-expanded="false" aria-controls="{{ $evidencePanelId }}">
                            <div class="d-flex justify-content-between align-items-start flex-wrap">
                                <div class="pr-3">
                                    <span class="badge badge-{{ $scoreBadge($evidence->score, $categoryBadges[$evidence->category] ?? 'secondary') }}">{{ $categoryLabels[$evidence->category] ?? str_replace('_', ' ', ucfirst($evidence->category)) }}</span>
                                    <strong class="d-block mt-2">{{ $evidence->title }}</strong>
                                    @if($detailSummary)
                                        <span class="small text-muted d-block mt-1">{{ $detailSummary }}</span>
                                    @endif
                                </div>
                                <div class="text-right">
                                    <span class="badge badge-{{ $scoreBadge($evidence->score, 'light') }}">{{ $evidence->score }} pts</span>
                                    @if($visibleContextCount > 0)
                                        <span class="badge badge-secondary">{{ $visibleContextCount }} context</span>
                                    @endif
                                    <i class="fas fa-chevron-down text-muted ml-2"></i>
                                </div>
                            </div>
                        </button>
                        <div id="{{ $evidencePanelId }}" class="collapse" data-parent="#spy-hunter-evidence-accordion-{{ $report->id }}">
                            <div class="px-3 pb-3">
	                        @if($evidence->details)
	                            <p class="mb-2">{!! $linkedDetails !!}</p>
	                        @endif
                        @if($summaryMeta->isNotEmpty())
                            <div class="row mb-2">
                                @foreach($summaryMeta->take(8) as $metaRow)
                                    <div class="col-md-3 col-sm-6 mb-2">
                                        <div class="border rounded px-2 py-1 h-100">
                                            <small class="text-muted d-block">{{ data_get($metaRow, 'label') }}</small>
                                            <strong>{{ is_numeric(data_get($metaRow, 'value')) ? number_format((float) data_get($metaRow, 'value')) : data_get($metaRow, 'value') }}</strong>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                        @if(!empty(data_get($evidence->meta, 'counts')))
                            <div class="mb-2">
                                @foreach(data_get($evidence->meta, 'counts', []) as $countName => $countValue)
                                    <span class="badge badge-light">{{ str_replace('_', ' ', ucfirst($countName)) }}: {{ number_format((int) $countValue) }}</span>
                                @endforeach
                            </div>
                        @endif
                        @if(!empty(data_get($evidence->meta, 'freshness.rule')))
                            <div class="alert alert-secondary py-2 mb-2">
                                <strong>Freshness:</strong> {{ data_get($evidence->meta, 'freshness.rule') }}
                                @if(data_get($evidence->meta, 'freshness.age_days') !== null)
                                    <span class="text-muted">({{ number_format((int) data_get($evidence->meta, 'freshness.age_days')) }} days old)</span>
                                @endif
                            </div>
                        @endif
                        @if($evidence->category === 'risk_confidence')
                            <div class="row mb-2">
                                <div class="col-md-3 col-sm-6 mb-2">
                                    <div class="border rounded px-2 py-1 h-100">
                                        <small class="text-muted d-block">Confidence</small>
                                        <span class="badge badge-{{ data_get($evidence->meta, 'badge', 'secondary') }}">{{ ucfirst(data_get($evidence->meta, 'level', 'unknown')) }}</span>
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6 mb-2">
                                    <div class="border rounded px-2 py-1 h-100">
                                        <small class="text-muted d-block">ESI Coverage</small>
                                        <strong>{{ data_get($evidence->meta, 'coverage_percent', 0) }}%</strong>
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6 mb-2">
                                    <div class="border rounded px-2 py-1 h-100">
                                        <small class="text-muted d-block">Visible Rows</small>
                                        <strong>{{ number_format((int) data_get($evidence->meta, 'visible_data_rows', 0)) }}</strong>
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6 mb-2">
                                    <div class="border rounded px-2 py-1 h-100">
                                        <small class="text-muted d-block">Token Issues</small>
                                        <strong>{{ number_format((int) data_get($evidence->meta, 'deleted_or_missing_token_count', 0)) }}</strong>
                                    </div>
                                </div>
                            </div>
                            @if(!empty(data_get($evidence->meta, 'missing_scope_groups')))
                                <div class="mb-2">
                                    <small class="text-muted d-block">Most common missing scope groups</small>
                                    @foreach(data_get($evidence->meta, 'missing_scope_groups', []) as $group => $count)
                                        <span class="badge badge-warning">{{ $group }} {{ $count }}</span>
                                    @endforeach
                                </div>
                            @endif
                        @endif
                        @if($evidence->category === 'vpn_ip' && !empty(data_get($evidence->meta, 'ips')))
                            <div class="table-responsive mb-2">
                                <table class="table table-sm table-bordered mb-0">
                                    <thead>
                                    <tr>
                                        <th>IP</th>
                                        <th>Signals</th>
                                        <th>Risk</th>
                                        <th>Provider</th>
                                        <th>Checked</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach(data_get($evidence->meta, 'ips', []) as $ipRecord)
                                        <tr>
                                            <td>
                                                <a href="https://whatismyipaddress.com/ip/{{ urlencode(data_get($ipRecord, 'ip')) }}" target="_blank" rel="noopener noreferrer">
                                                    <code>{{ data_get($ipRecord, 'ip') }}</code>
                                                </a>
                                            </td>
                                            <td>
                                                @foreach(['vpn' => 'VPN', 'proxy' => 'Proxy', 'tor' => 'Tor', 'hosting' => 'Hosting'] as $flag => $label)
                                                    @if(data_get($ipRecord, $flag))
                                                        <span class="badge badge-danger">{{ $label }}</span>
                                                    @endif
                                                @endforeach
                                                @if(!data_get($ipRecord, 'vpn') && !data_get($ipRecord, 'proxy') && !data_get($ipRecord, 'tor') && !data_get($ipRecord, 'hosting'))
                                                    <span class="badge badge-success">No VPN signal</span>
                                                @endif
                                            </td>
                                            <td>{{ (int) data_get($ipRecord, 'risk_score', 0) }}</td>
                                            <td>{{ data_get($ipRecord, 'provider') ?: 'manual' }}</td>
                                            <td>{{ data_get($ipRecord, 'checked_at') ?: 'Unknown' }}</td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                        @if($evidence->category === 'low_asset_value' && !empty(data_get($evidence->meta, 'top_assets')))
                            <div class="mb-2">
                                <span class="badge badge-warning">Estimated {{ number_format((float) data_get($evidence->meta, 'estimated_asset_value', 0), 0) }} ISK</span>
                                <span class="badge badge-light">Threshold {{ number_format((float) data_get($evidence->meta, 'threshold', 0), 0) }} ISK</span>
                                <span class="badge badge-light">{{ number_format((int) data_get($evidence->meta, 'priced_type_count', 0)) }} priced types</span>
                                @if((int) data_get($evidence->meta, 'unpriced_type_count', 0) > 0)
                                    <span class="badge badge-secondary">{{ number_format((int) data_get($evidence->meta, 'unpriced_type_count', 0)) }} unpriced types</span>
                                @endif
                            </div>
                            <div class="table-responsive mb-2">
                                <table class="table table-sm table-bordered mb-0">
                                    <thead>
                                    <tr>
                                        <th>Asset Type</th>
                                        <th>Quantity</th>
                                        <th>Unit Price</th>
                                        <th>Estimated Value</th>
                                        <th>Price Source</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach(data_get($evidence->meta, 'top_assets', []) as $asset)
                                        <tr>
                                            <td>
                                                {{ data_get($asset, 'type_name') ?: data_get($asset, 'type_id') }}
                                                <div class="small text-muted">{{ data_get($asset, 'type_id') }}</div>
                                            </td>
                                            <td>{{ number_format((int) data_get($asset, 'quantity', 0)) }}</td>
                                            <td>{{ number_format((float) data_get($asset, 'unit_price', 0), 2) }} ISK</td>
                                            <td>{{ number_format((float) data_get($asset, 'estimated_value', 0), 2) }} ISK</td>
                                            <td>{{ str_replace('_', ' ', data_get($asset, 'price_source') ?: 'Unknown') }}</td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                        @if($evidence->category === 'esi_coverage_health' && !empty(data_get($evidence->meta, 'characters')))
                            <div class="row mb-2">
                                <div class="col-sm-4">
                                    <small class="text-muted d-block">Coverage</small>
                                    <strong>{{ data_get($evidence->meta, 'coverage_percent', 0) }}%</strong>
                                </div>
                                <div class="col-sm-4">
                                    <small class="text-muted d-block">Healthy</small>
                                    <strong>{{ data_get($evidence->meta, 'healthy_count', 0) }} / {{ data_get($evidence->meta, 'character_count', 0) }}</strong>
                                </div>
                                <div class="col-sm-4">
                                    <small class="text-muted d-block">Characters With Issues</small>
                                    <strong>{{ data_get($evidence->meta, 'issue_count', 0) }}</strong>
                                </div>
                            </div>
                            <div class="table-responsive mb-2">
                                <table class="table table-sm table-bordered mb-0">
                                    <thead>
                                    <tr>
                                        <th>Character</th>
                                        <th>Status</th>
                                        <th>Scopes</th>
                                        <th>Token Activity</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach(data_get($evidence->meta, 'characters', []) as $coverage)
                                        @php
                                            $coverageStatus = data_get($coverage, 'status', 'unknown');
                                            $coverageBadge = [
                                                'healthy' => 'success',
                                                'scope_gaps' => 'warning',
                                                'stale_token' => 'secondary',
                                                'missing_token' => 'danger',
                                                'deleted_token' => 'danger',
                                                'missing_refresh_token' => 'danger',
                                            ][$coverageStatus] ?? 'secondary';
                                        @endphp
                                        <tr>
                                            <td>
                                                {!! $characterLink(data_get($coverage, 'character_id'), data_get($coverage, 'character_name') ?: data_get($coverage, 'character_id')) !!}
                                                <div class="small text-muted">{{ data_get($coverage, 'character_id') }}</div>
                                            </td>
                                            <td>
                                                <span class="badge badge-{{ $coverageBadge }}">{{ str_replace('_', ' ', ucfirst($coverageStatus)) }}</span>
                                                @foreach(data_get($coverage, 'issues', []) as $issue)
                                                    <span class="badge badge-light">{{ $issue }}</span>
                                                @endforeach
                                            </td>
                                            <td>
                                                <div>{{ data_get($coverage, 'scope_count', 0) }} scopes{{ data_get($coverage, 'scopes_profile') ? ' / ' . data_get($coverage, 'scopes_profile') : '' }}</div>
                                                @if(!empty(data_get($coverage, 'missing_scope_groups')))
                                                    <div class="mt-1">
                                                        @foreach(data_get($coverage, 'missing_scope_groups', []) as $group)
                                                            <span class="badge badge-warning">{{ $group }}</span>
                                                        @endforeach
                                                    </div>
                                                @else
                                                    <span class="badge badge-success">Required scope groups present</span>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="small">Updated: {{ data_get($coverage, 'updated_at') ?: 'Unknown' }}</div>
                                                <div class="small">Access expires: {{ data_get($coverage, 'expires_on') ?: 'Unknown' }}</div>
                                                @if(!empty(data_get($coverage, 'deleted_at')))
                                                    <div class="small text-danger">Deleted: {{ data_get($coverage, 'deleted_at') }}</div>
                                                @endif
                                                @if(!empty(data_get($coverage, 'has_refresh_token')))
                                                    <span class="badge badge-success">Refreshable</span>
                                                @else
                                                    <span class="badge badge-danger">No refresh token</span>
                                                @endif
                                                @if(!empty(data_get($coverage, 'access_token_current')))
                                                    <span class="badge badge-info">Current access token</span>
                                                @else
                                                    <span class="badge badge-light">Access token may refresh on demand</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
	                        @if($evidence->category === 'hostile_contacts' && !empty(data_get($evidence->meta, 'contacts')))
                                @if(!empty(data_get($evidence->meta, 'direction.standing_direction')))
                                    <div class="alert alert-warning py-2 mb-2">
                                        <strong>Direction:</strong> This is positive standing set by the character toward a hostile or monitored-negative entity.
                                        @if(data_get($evidence->meta, 'direction.max_positive_standing') !== null)
                                            Highest standing: {{ number_format((float) data_get($evidence->meta, 'direction.max_positive_standing'), 1) }}.
                                        @endif
                                    </div>
                                @endif
	                            <div class="table-responsive mb-2">
                                <table class="table table-sm table-bordered mb-0">
                                    <thead>
                                    <tr>
                                        <th>Contact</th>
                                        <th>Type</th>
                                        <th>Standing</th>
                                        <th>Flags</th>
                                        <th>Interpretation</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach(data_get($evidence->meta, 'contacts', []) as $contact)
                                        <tr>
                                            <td>
                                                {{ data_get($contact, 'name') ?: data_get($contact, 'id') }}
                                                <div class="small text-muted">{{ data_get($contact, 'id') }}</div>
                                            </td>
                                            <td>{{ data_get($contact, 'type') ?: 'Unknown' }}</td>
                                            <td><span class="badge badge-success">{{ number_format((float) data_get($contact, 'standing', 0), 1) }}</span></td>
                                            <td>
                                                @if(data_get($contact, 'watched'))
                                                    <span class="badge badge-warning">Watched</span>
                                                @endif
                                                @if(data_get($contact, 'blocked'))
                                                    <span class="badge badge-secondary">Blocked</span>
                                                @endif
                                                @if(!data_get($contact, 'watched') && !data_get($contact, 'blocked'))
                                                    <span class="text-muted">None</span>
                                                @endif
                                            </td>
                                            <td class="small">{{ data_get($contact, 'interpretation') ?: 'Positive standing toward a hostile or monitored-negative entity.' }}</td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                        @if($evidence->category === 'new_evidence_since_review' && !empty(data_get($evidence->meta, 'new_items')))
                            <div class="table-responsive mb-2">
                                <table class="table table-sm table-bordered mb-0">
                                    <thead>
                                    <tr>
                                        <th>New Evidence</th>
                                        <th>Category</th>
                                        <th>Score</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach(data_get($evidence->meta, 'new_items', []) as $item)
                                        <tr>
                                            <td>{{ data_get($item, 'title') ?: 'Unknown evidence' }}</td>
                                            <td>{{ $categoryLabels[data_get($item, 'category')] ?? str_replace('_', ' ', ucfirst(data_get($item, 'category', 'unknown'))) }}</td>
                                            <td>{{ (int) data_get($item, 'score', 0) }} pts</td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                        @if($evidence->category === 'suppressed_signals' && !empty(data_get($evidence->meta, 'suppressed')))
                            <div class="table-responsive mb-2">
                                <table class="table table-sm table-bordered mb-0">
                                    <thead>
                                    <tr>
                                        <th>Suppressed Evidence</th>
                                        <th>Category</th>
                                        <th>Original Score</th>
                                        <th>Reason</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach(data_get($evidence->meta, 'suppressed', []) as $suppressed)
                                        <tr>
                                            <td>{{ data_get($suppressed, 'title') ?: 'Unknown evidence' }}</td>
                                            <td>{{ $categoryLabels[data_get($suppressed, 'category')] ?? str_replace('_', ' ', ucfirst(data_get($suppressed, 'category', 'unknown'))) }}</td>
                                            <td>{{ (int) data_get($suppressed, 'score', 0) }} pts</td>
                                            <td>{{ data_get($suppressed, 'reason') ?: '-' }}</td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                        @if($evidence->category === 'low_loyalty_points' && !empty(data_get($evidence->meta, 'corporations')))
                            <div class="table-responsive mb-2">
                                <table class="table table-sm table-bordered mb-0">
                                    <thead>
                                    <tr>
                                        <th>Character</th>
                                        <th>Corporation</th>
                                        <th>LP</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach(data_get($evidence->meta, 'corporations', []) as $lpRow)
                                        <tr>
                                            <td>{!! $characterLink(data_get($lpRow, 'character_id'), data_get($lpRow, 'character_id')) !!}</td>
                                            <td>
                                                {{ data_get($lpRow, 'corporation_name') ?: data_get($lpRow, 'corporation_id') }}
                                                <div class="small text-muted">{{ data_get($lpRow, 'corporation_id') }}</div>
                                            </td>
                                            <td>{{ number_format((int) data_get($lpRow, 'amount', 0)) }}</td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                        @if(in_array($evidence->category, ['hostile_corporation_history', 'recent_neutral_corporation_history']) && !empty(data_get($evidence->meta, 'matches')))
                            <div class="table-responsive mb-2">
                                <table class="table table-sm table-bordered mb-0">
                                    <thead>
                                    <tr>
                                        <th>Character</th>
                                        <th>Corporation</th>
                                        <th>Start Date</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach(data_get($evidence->meta, 'matches', []) as $history)
                                        <tr>
                                            <td>{!! $characterLink(data_get($history, 'character_id'), data_get($history, 'character_id')) !!}</td>
                                            <td>{{ data_get($history, 'corporation_id') }}</td>
                                            <td>{{ data_get($history, 'start_date') ?: 'Unknown' }}</td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                        @if($evidence->category === 'shared_ip' && !empty(data_get($evidence->meta, 'shared_users')))
                            <div class="table-responsive mb-2">
                                <table class="table table-sm table-bordered mb-0">
                                    <thead>
                                    <tr>
                                        <th>SeAT User</th>
                                        <th>Shared IPs</th>
                                        <th>Account Flags</th>
                                        <th>Last Seen</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach(data_get($evidence->meta, 'shared_users', []) as $sharedUser)
                                        <tr>
                                            <td>
                                                <a href="{{ route('seatcore::configuration.users.edit', ['user_id' => $sharedUser['user_id']]) }}" target="_blank" rel="noopener noreferrer">
                                                    <strong>{{ $sharedUser['user_name'] ?: ('User #' . $sharedUser['user_id']) }}</strong>
                                                </a>
                                                <div class="small text-muted">User #{{ $sharedUser['user_id'] }}</div>
                                            </td>
                                            <td>
                                                @forelse($sharedUser['shared_ips'] ?? [] as $ip)
                                                    <span class="badge badge-light">{{ $ip }}</span>
                                                @empty
                                                    <span class="text-muted">No IP detail captured</span>
                                                @endforelse
                                            </td>
                                            <td>
                                                @if(!empty($sharedUser['active']))
                                                    <span class="badge badge-success">Active</span>
                                                @else
                                                    <span class="badge badge-secondary">Inactive</span>
                                                @endif
                                                @if(!empty($sharedUser['admin']))
                                                    <span class="badge badge-danger">Admin</span>
                                                @endif
                                            </td>
                                            <td>{{ $sharedUser['last_seen_at'] ?: 'Unknown' }}</td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                        @if($evidence->category === 'hostile_mail')
                            @php
                                $mailRows = collect(data_get($evidence->meta, 'latest_received', []))
                                    ->map(fn($row) => array_merge($row, ['direction' => 'Received']))
                                    ->merge(collect(data_get($evidence->meta, 'latest_sent', []))->map(fn($row) => array_merge($row, ['direction' => 'Sent'])));
                            @endphp
                            @if($mailRows->isNotEmpty())
                                <div class="table-responsive mb-2">
                                    <table class="table table-sm table-bordered mb-0">
                                        <thead>
                                        <tr>
                                            <th>Direction</th>
                                            <th>Subject</th>
                                            <th>Counterparty</th>
                                            <th>Sent</th>
                                            <th></th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @foreach($mailRows as $mail)
                                            <tr>
                                                <td>{{ data_get($mail, 'direction') }}</td>
                                                <td>{{ data_get($mail, 'subject') ?: 'No subject' }}</td>
                                                <td>
                                                    @if(data_get($mail, 'direction') === 'Received')
                                                        {{ data_get($mail, 'from') ?: data_get($mail, 'from_id', 'Unknown') }}
                                                    @else
                                                        {{ implode(', ', data_get($mail, 'recipients', [])) ?: 'Unknown' }}
                                                    @endif
                                                </td>
                                                <td>{{ data_get($mail, 'timestamp') ?: 'Unknown' }}</td>
                                                <td class="text-right">
                                                    @if(data_get($mail, 'character_id') && data_get($mail, 'mail_id'))
                                                        <a class="btn btn-xs btn-outline-primary" href="{{ route('seatcore::character.view.mail.read', ['character' => data_get($mail, 'character_id'), 'message_id' => data_get($mail, 'mail_id')]) }}" target="_blank" rel="noopener noreferrer">
                                                            <i class="fas fa-envelope-open-text"></i> Open Mail
                                                        </a>
                                                    @elseif(data_get($mail, 'character_id'))
                                                        <a class="btn btn-xs btn-outline-secondary" href="{{ route('seatcore::character.view.mail', ['character' => data_get($mail, 'character_id')]) }}" target="_blank" rel="noopener noreferrer">
                                                            <i class="fas fa-envelope"></i> Mail
                                                        </a>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        @endif
                        @if(in_array($evidence->category, ['hostile_wallet', 'hostile_wallet_direct']) && !empty(data_get($evidence->meta, 'latest_journal')))
                            <div class="table-responsive mb-2">
                                <table class="table table-sm table-bordered mb-0">
                                    <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Ref Type</th>
                                        <th>Parties</th>
                                        <th>Amount</th>
                                        <th>Reason</th>
                                        <th></th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach(data_get($evidence->meta, 'latest_journal', []) as $entry)
                                        <tr>
                                            <td>{{ data_get($entry, 'date') ?: 'Unknown' }}</td>
                                            <td>{{ data_get($entry, 'ref_type') ?: 'Unknown' }}</td>
                                            <td>
                                                {{ data_get($entry, 'first_party') ?: data_get($entry, 'first_party_id', 'Unknown') }}
                                                <span class="text-muted">→</span>
                                                {{ data_get($entry, 'second_party') ?: data_get($entry, 'second_party_id', 'Unknown') }}
                                            </td>
                                            <td>{{ number_format((float) data_get($entry, 'amount', 0), 2) }} ISK</td>
                                            <td>{{ data_get($entry, 'reason') ?: '-' }}</td>
                                            <td class="text-right">
                                                @if(data_get($entry, 'character_id'))
                                                    <a class="btn btn-xs btn-outline-primary" href="{{ route('seatcore::character.view.journal', ['character' => data_get($entry, 'character_id')]) }}" target="_blank" rel="noopener noreferrer">
                                                        <i class="fas fa-wallet"></i> Journal
                                                    </a>
                                                    @if(data_get($entry, 'first_party_id') && data_get($entry, 'second_party_id') && data_get($entry, 'ref_type'))
                                                        <a class="btn btn-xs btn-outline-secondary" href="{{ route('seatcore::character.view.intel.summary.journal.details', ['character' => data_get($entry, 'character_id'), 'first_party_id' => data_get($entry, 'first_party_id'), 'second_party_id' => data_get($entry, 'second_party_id'), 'ref_type' => data_get($entry, 'ref_type')]) }}" target="_blank" rel="noopener noreferrer">
                                                            <i class="fas fa-search"></i> Detail
                                                        </a>
                                                    @endif
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                        @if(in_array($evidence->category, ['hostile_wallet', 'hostile_market_transaction']) && !empty(data_get($evidence->meta, 'latest_transactions')))
                            <div class="table-responsive mb-2">
                                <table class="table table-sm table-bordered mb-0">
                                    <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Side</th>
                                        <th>Item</th>
                                        <th>Party</th>
                                        <th>Total</th>
                                        <th></th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach(data_get($evidence->meta, 'latest_transactions', []) as $transaction)
                                        <tr>
                                            <td>{{ data_get($transaction, 'date') ?: 'Unknown' }}</td>
                                            <td>{{ data_get($transaction, 'is_buy') ? 'Buy' : 'Sell' }}</td>
                                            <td>
                                                {{ data_get($transaction, 'type') ?: 'Unknown item' }}
                                                @if(data_get($transaction, 'quantity'))
                                                    <div class="small text-muted">Qty {{ number_format((int) data_get($transaction, 'quantity')) }}</div>
                                                @endif
                                            </td>
                                            <td>{{ data_get($transaction, 'party') ?: data_get($transaction, 'client_id', 'Unknown') }}</td>
                                            <td>{{ number_format((float) data_get($transaction, 'total', 0), 2) }} ISK</td>
                                            <td class="text-right">
                                                @if(data_get($transaction, 'character_id'))
                                                    <a class="btn btn-xs btn-outline-primary" href="{{ route('seatcore::character.view.transactions', ['character' => data_get($transaction, 'character_id')]) }}" target="_blank" rel="noopener noreferrer">
                                                        <i class="fas fa-exchange-alt"></i> Transactions
                                                    </a>
                                                    @if(data_get($transaction, 'client_id'))
                                                        <a class="btn btn-xs btn-outline-secondary" href="{{ route('seatcore::character.view.intel.summary.transactions.details', ['character' => data_get($transaction, 'character_id'), 'client_id' => data_get($transaction, 'client_id')]) }}" target="_blank" rel="noopener noreferrer">
                                                            <i class="fas fa-search"></i> Detail
                                                        </a>
                                                    @endif
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                        @if($evidence->category === 'hostile_contract' && !empty(data_get($evidence->meta, 'contracts')))
                            @if(data_get($evidence->meta, 'score_rule'))
                                <div class="alert alert-warning py-2 mb-2">
                                    <strong>Score rule:</strong> {{ data_get($evidence->meta, 'score_rule') }}
                                </div>
                            @endif
                            <div class="table-responsive mb-2">
                                <table class="table table-sm table-bordered mb-0">
                                    <thead>
                                    <tr>
                                        <th>Contract</th>
                                        <th>Character</th>
                                        <th>Hostile Match</th>
                                        <th>Parties</th>
                                        <th>Value</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach(data_get($evidence->meta, 'contracts', []) as $contract)
                                        @php
                                            $matchedContractLabel = data_get($contract, 'matched_entity_type') ? str_replace('_', ' ', ucfirst(data_get($contract, 'matched_entity_type'))) . ' #' . data_get($contract, 'matched_entity_id') : 'Unknown';
                                        @endphp
                                        <tr>
                                            <td>
                                                #{{ data_get($contract, 'contract_id') }}
                                                <div>{{ data_get($contract, 'title') ?: data_get($contract, 'type', 'Contract') }}</div>
                                                <div class="small text-muted">{{ data_get($contract, 'status') ?: 'Unknown status' }} / {{ data_get($contract, 'date_issued') ?: 'Unknown date' }}</div>
                                            </td>
                                            <td>
                                                {!! $characterLink(data_get($contract, 'character_id'), data_get($contract, 'character_name') ?: data_get($contract, 'character_id')) !!}
                                            </td>
                                            <td>
                                                <span class="badge badge-danger">{{ $matchedContractLabel }}</span>
                                                @if(data_get($contract, 'age_days') !== null)
                                                    <div class="small text-muted">{{ number_format((int) data_get($contract, 'age_days')) }} days old</div>
                                                @endif
                                            </td>
                                            <td class="small">
                                                Issuer: {{ data_get($contract, 'issuer_name') ?: data_get($contract, 'issuer_id', 'Unknown') }}<br>
                                                Issuer Corp: {{ data_get($contract, 'issuer_corporation_name') ?: data_get($contract, 'issuer_corporation_id', 'Unknown') }}<br>
                                                Assignee: {{ data_get($contract, 'assignee_name') ?: data_get($contract, 'assignee_id', '-') }}<br>
                                                Acceptor: {{ data_get($contract, 'acceptor_name') ?: data_get($contract, 'acceptor_id', '-') }}
                                            </td>
                                            <td class="small">
                                                Price {{ number_format((float) data_get($contract, 'price', 0), 2) }} ISK<br>
                                                Reward {{ number_format((float) data_get($contract, 'reward', 0), 2) }} ISK<br>
                                                Collateral {{ number_format((float) data_get($contract, 'collateral', 0), 2) }} ISK
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                        @if($evidence->category === 'hostile_killmail' && !empty(data_get($evidence->meta, 'matches')))
                            <div class="mb-2">
                                <span class="badge badge-danger">{{ data_get($evidence->meta, 'same_side_count', 0) }} same-side</span>
                                <span class="badge badge-danger">{{ data_get($evidence->meta, 'recent_same_side_count', 0) }} recent same-side</span>
                                <span class="badge badge-warning">{{ data_get($evidence->meta, 'opposed_count', 0) }} opposed</span>
                                <span class="badge badge-warning">{{ data_get($evidence->meta, 'recent_opposed_count', 0) }} recent opposed</span>
                                <span class="badge badge-secondary">{{ data_get($evidence->meta, 'total_count', 0) }} total</span>
                            </div>
                            @if(data_get($evidence->meta, 'score_rule'))
                                <div class="alert alert-warning py-2 mb-2">
                                    <strong>Score rule:</strong> {{ data_get($evidence->meta, 'score_rule') }}
                                </div>
                            @endif
                            <div class="table-responsive mb-2">
                                <table class="table table-sm table-bordered mb-0">
                                    <thead>
                                    <tr>
                                        <th>Killmail</th>
                                        <th>Relationship</th>
                                        <th>Monitored Character</th>
                                        <th>Hostile Entity</th>
                                        <th>Ships</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach(data_get($evidence->meta, 'matches', []) as $killmail)
                                        @php
                                            $relationshipLabels = [
                                                'same_side_attacker' => 'Same side attackers',
                                                'hostile_attacker' => 'Hostile attacker',
                                                'hostile_victim' => 'Hostile victim',
                                            ];
                                            $relationshipBadge = [
                                                'same_side_attacker' => 'danger',
                                                'hostile_attacker' => 'warning',
                                                'hostile_victim' => 'warning',
                                            ][data_get($killmail, 'relationship')] ?? 'secondary';
                                            $hostileLabel = data_get($killmail, 'hostile_character_name')
                                                ?: data_get($killmail, 'hostile_corporation_name')
                                                ?: data_get($killmail, 'hostile_alliance_name')
                                                ?: data_get($killmail, 'matched_entity_id')
                                                ?: 'Unknown hostile';
                                        @endphp
                                        <tr>
                                            <td>
                                                #{{ data_get($killmail, 'killmail_id') }}
                                                <div class="small text-muted">{{ data_get($killmail, 'killmail_time') ?: 'Unknown time' }}</div>
                                                @if(data_get($killmail, 'recency_bucket'))
                                                    <span class="badge badge-{{ data_get($killmail, 'recency_bucket') === 'recent' ? 'success' : 'secondary' }}">{{ ucfirst(data_get($killmail, 'recency_bucket')) }}</span>
                                                @endif
                                                @if(data_get($killmail, 'solar_system_name') || data_get($killmail, 'solar_system_id'))
                                                    <div class="small text-muted">{{ data_get($killmail, 'solar_system_name') ?: data_get($killmail, 'solar_system_id') }}</div>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge badge-{{ $relationshipBadge }}">{{ $relationshipLabels[data_get($killmail, 'relationship')] ?? data_get($killmail, 'relationship', 'Unknown') }}</span>
                                                @if(data_get($killmail, 'final_blow'))
                                                    <div class="small text-danger">Hostile final blow</div>
                                                @endif
                                                @if(data_get($killmail, 'damage_done') !== null)
                                                    <div class="small text-muted">Damage {{ number_format((int) data_get($killmail, 'damage_done')) }}</div>
                                                @endif
                                            </td>
                                            <td>
                                                {!! $characterLink(data_get($killmail, 'monitored_character_id'), data_get($killmail, 'monitored_character_name') ?: data_get($killmail, 'monitored_character_id')) !!}
                                                <div class="small text-muted">{{ ucfirst(data_get($killmail, 'monitored_side', 'unknown')) }}</div>
                                            </td>
                                            <td>
                                                @if(data_get($killmail, 'hostile_character_id'))
                                                    {!! $characterLink(data_get($killmail, 'hostile_character_id'), $hostileLabel) !!}
                                                @else
                                                    {{ $hostileLabel }}
                                                @endif
                                                <div class="small text-muted">
                                                    @if(data_get($killmail, 'hostile_corporation_name') || data_get($killmail, 'hostile_corporation_id'))
                                                        Corp: {{ data_get($killmail, 'hostile_corporation_name') ?: data_get($killmail, 'hostile_corporation_id') }}
                                                    @endif
                                                    @if(data_get($killmail, 'hostile_alliance_name') || data_get($killmail, 'hostile_alliance_id'))
                                                        <br>Alliance: {{ data_get($killmail, 'hostile_alliance_name') ?: data_get($killmail, 'hostile_alliance_id') }}
                                                    @endif
                                                </div>
                                            </td>
                                            <td>
                                                {{ data_get($killmail, 'monitored_ship_type_name') ?: data_get($killmail, 'monitored_ship_type_id', 'Unknown') }}
                                                <span class="text-muted">/</span>
                                                {{ data_get($killmail, 'hostile_ship_type_name') ?: data_get($killmail, 'hostile_ship_type_id', 'Unknown') }}
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                        @if($evidence->category === 'hostile_employment_overlap' && !empty(data_get($evidence->meta, 'matches')))
                            <div class="mb-2">
                                <span class="badge badge-danger">{{ data_get($evidence->meta, 'close_departure_count', 0) }} close departures</span>
                                <span class="badge badge-danger">{{ data_get($evidence->meta, 'same_time_count', 0) }} same-time</span>
                                <span class="badge badge-danger">{{ data_get($evidence->meta, 'recent_same_time_count', 0) }} recent same-time</span>
                                <span class="badge badge-warning">{{ data_get($evidence->meta, 'recent_different_time_count', 0) }} recent same-corp</span>
                                <span class="badge badge-warning">{{ data_get($evidence->meta, 'different_time_count', 0) }} historical-only</span>
                                <span class="badge badge-info">{{ data_get($evidence->meta, 'recent_count', 0) }} last 2 years</span>
                                <span class="badge badge-primary">{{ data_get($evidence->meta, 'aging_count', 0) }} 2-5 years old</span>
                                <span class="badge badge-secondary">{{ data_get($evidence->meta, 'old_count', 0) }} older than 5 years</span>
                            </div>
                            @if(data_get($evidence->meta, 'score_rule'))
                                <div class="alert alert-secondary py-2 mb-2">
                                    <strong>Score rule:</strong> {{ data_get($evidence->meta, 'score_rule') }}
                                </div>
                            @endif
                            <div class="table-responsive mb-2">
                                <table class="table table-sm table-bordered mb-0">
                                    <thead>
                                    <tr>
                                        <th>Monitored Character</th>
                                        <th>Hostile Character</th>
                                        <th>Shared Corporation</th>
                                        <th>Actual Overlap</th>
                                        <th>Monitored Character Was There</th>
                                        <th>Hostile Character Was There</th>
                                        <th>Recency</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach(data_get($evidence->meta, 'matches', []) as $match)
                                        <tr>
                                            <td>
                                                {!! $characterLink(data_get($match, 'character_id'), data_get($match, 'character_name') ?: data_get($match, 'character_id')) !!}
                                                <div class="small text-muted">{{ data_get($match, 'character_id') }}</div>
                                            </td>
                                            <td>
                                                {!! $characterLink(data_get($match, 'hostile_character_id'), data_get($match, 'hostile_character_name') ?: data_get($match, 'hostile_character_id')) !!}
                                                <div class="small text-muted">
                                                    {{ data_get($match, 'hostile_character_id') }}
                                                    @if(data_get($match, 'source_entity_type') && data_get($match, 'source_entity_id'))
                                                        / {{ ucfirst(data_get($match, 'source_entity_type')) }}
                                                        {{ data_get($match, 'source_entity_name') ?: data_get($match, 'source_entity_id') }}
                                                    @endif
                                                </div>
                                            </td>
                                            <td>
                                                {{ data_get($match, 'corporation_name') ?: data_get($match, 'corporation_id') }}
                                                <div class="small text-muted">{{ data_get($match, 'corporation_id') }}</div>
                                                @if(data_get($match, 'alliance_name') || data_get($match, 'alliance_id'))
                                                    <div class="small text-muted">
                                                        Alliance: {{ data_get($match, 'alliance_name') ?: data_get($match, 'alliance_id') }}
                                                    </div>
                                                @endif
                                            </td>
                                            <td>
                                                @if(data_get($match, 'same_time'))
                                                    <span class="badge badge-danger">Same time</span>
                                                    <div class="mt-1">
                                                        {{ data_get($match, 'overlap_start_date') ?: 'Unknown' }}
                                                        -
                                                        {{ data_get($match, 'overlap_end_date') ?: 'Current/Unknown' }}
                                                    </div>
                                                @else
                                                    <span class="badge badge-warning">No same-time overlap</span>
                                                    <div class="small text-muted mt-1">They were in the same corporation at different times.</div>
                                                @endif
                                                @if(data_get($match, 'close_departure'))
                                                    <div class="mt-1">
                                                        <span class="badge badge-danger">
                                                            Left within {{ data_get($match, 'departure_delta_days') }} day{{ (int) data_get($match, 'departure_delta_days') === 1 ? '' : 's' }}
                                                        </span>
                                                    </div>
                                                @endif
                                            </td>
                                            <td>
                                                {{ data_get($match, 'local_start_date') ?: 'Unknown' }}
                                                -
                                                {{ data_get($match, 'local_end_date') ?: 'Current/Unknown' }}
                                                @if(data_get($match, 'local_recent'))
                                                    <div><span class="badge badge-info">Monitored recent</span></div>
                                                @endif
                                            </td>
                                            <td>
                                                {{ data_get($match, 'hostile_start_date') ?: 'Unknown' }}
                                                -
                                                {{ data_get($match, 'hostile_end_date') ?: 'Current/Unknown' }}
                                                @if(data_get($match, 'hostile_recent'))
                                                    <div><span class="badge badge-warning">Hostile recent</span></div>
                                                @endif
                                            </td>
                                            <td>
                                                @php
                                                    $ageBucket = data_get($match, 'overlap_age_bucket', 'unknown');
                                                    $ageBadge = data_get(['recent' => 'info', 'aging' => 'secondary', 'old' => 'light', 'unknown' => 'secondary'], $ageBucket, 'secondary');
                                                @endphp
                                                <span class="badge badge-{{ $ageBadge }}">{{ ucfirst($ageBucket) }}</span>
                                                <div class="small text-muted">
                                                    Last relevant: {{ data_get($match, 'overlap_last_seen_date') ?: 'Unknown' }}
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
		                        @if(!empty($evidence->meta))
	                                <div class="mt-2 border-top pt-2">
	                                    <button class="btn btn-link btn-sm p-0 text-muted" type="button" data-toggle="collapse" data-target="#{{ $contextId }}" aria-expanded="false" aria-controls="{{ $contextId }}">
	                                        <i class="fas fa-chevron-right"></i> Captured context
	                                    </button>
	                                    <div id="{{ $contextId }}" class="collapse mt-2">
                                            @if($nestedMeta->isNotEmpty())
                                                <div class="accordion" id="spy-hunter-nested-context-{{ $report->id }}-{{ $evidence->id }}">
                                                    @foreach($nestedMeta as $nestedIndex => $nestedValue)
                                                        @php
                                                            $nestedKey = collect($evidence->meta ?: [])->filter(fn($value) => $value === $nestedValue)->keys()->first();
                                                            $nestedRows = collect($nestedValue)->take(10);
                                                            $nestedId = 'spy-hunter-nested-context-' . $report->id . '-' . $evidence->id . '-' . $nestedIndex;
                                                        @endphp
                                                        <div class="card mb-1">
                                                            <div class="card-header py-1 px-2" id="{{ $nestedId }}-heading">
                                                                <button class="btn btn-link btn-sm p-0" type="button" data-toggle="collapse" data-target="#{{ $nestedId }}" aria-expanded="false" aria-controls="{{ $nestedId }}">
                                                                    {{ str_replace('_', ' ', ucfirst((string) $nestedKey)) }} ({{ collect($nestedValue)->count() }})
                                                                </button>
                                                            </div>
                                                            <div id="{{ $nestedId }}" class="collapse" data-parent="#spy-hunter-nested-context-{{ $report->id }}-{{ $evidence->id }}">
                                                                <div class="card-body p-2">
                                                                    @foreach($nestedRows as $nestedRow)
                                                                        <div class="border rounded p-2 mb-1">
                                                                            @if(is_array($nestedRow) || is_object($nestedRow))
                                                                                <div class="row">
                                                                                    @foreach(collect($nestedRow)->take(8) as $nestedRowKey => $nestedRowValue)
                                                                                        <div class="col-md-3 col-sm-6 mb-1">
                                                                                            <small class="text-muted d-block">{{ str_replace('_', ' ', ucfirst((string) $nestedRowKey)) }}</small>
                                                                                            <span>{{ is_array($nestedRowValue) || is_object($nestedRowValue) ? json_encode($nestedRowValue, JSON_UNESCAPED_SLASHES) : $nestedRowValue }}</span>
                                                                                        </div>
                                                                                    @endforeach
                                                                                </div>
                                                                            @else
                                                                                {{ $nestedRow }}
                                                                            @endif
                                                                        </div>
                                                                    @endforeach
                                                                    @if(collect($nestedValue)->count() > 10)
                                                                        <div class="small text-muted">Showing first 10 of {{ collect($nestedValue)->count() }} captured rows.</div>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @else
                                                <div class="small text-muted mb-2">No additional nested context beyond the fields shown above.</div>
                                            @endif
	                                        <button class="btn btn-link btn-sm p-0 text-muted mt-2" type="button" data-toggle="collapse" data-target="#{{ $rawContextId }}" aria-expanded="false" aria-controls="{{ $rawContextId }}">
	                                            <i class="fas fa-code"></i> Developer raw context
	                                        </button>
	                                        <div id="{{ $rawContextId }}" class="collapse mt-2">
	                                            <pre class="bg-light border rounded p-2 mb-0 small" style="white-space: pre-wrap;">{{ json_encode($evidence->meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
	                                        </div>
	                                    </div>
	                                </div>
		                        @endif
	                        @if($evidence->score > 0)
	                            <form action="{{ route('seat-spy-hunter.reports.suppressions.store', $report) }}" method="POST" class="mt-2">
	                                {{ csrf_field() }}
	                                <input type="hidden" name="category" value="{{ $evidence->category }}">
	                                <div class="input-group input-group-sm">
	                                    <input type="text" name="reason" class="form-control" placeholder="False-positive reason">
	                                    <div class="input-group-append">
	                                        <button type="submit" class="btn btn-outline-secondary">
	                                            <i class="fas fa-ban"></i> Suppress Category
	                                        </button>
	                                    </div>
	                                </div>
	                            </form>
	                        @endif
                            </div>
                        </div>
	                    </div>
                @empty
                    <p class="text-muted mb-0">No evidence was recorded for this account.</p>
                @endforelse
                </div>
            </div>

            <div class="modal-footer">
                <a href="{{ route('seat-spy-hunter.characters.show', $report) }}" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-external-link-alt"></i> Open Account Page
                </a>
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
