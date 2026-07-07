@php
    $accountUserId = $report->account_user_id ?: $report->user_id;
    $badge = ['critical' => 'danger', 'high' => 'warning', 'watch' => 'info', 'clear' => 'success'][$report->rating] ?? 'secondary';
    $categoryLabels = [
        'hostile_contacts' => 'Hostile Contacts',
        'account_characters' => 'Account Characters',
        'account_connectors' => 'Connectors',
        'multi_character_account' => 'Linked Characters',
        'low_account_skillpoints' => 'Low Account SP',
        'no_pve_wallet_history' => 'No PvE Wallet',
        'limited_recent_wallet_activity' => 'Low Wallet Activity',
        'stable_wallet_balance' => 'Stable Wallet',
        'hostile_corporation_history' => 'Hostile Corp History',
        'recent_neutral_corporation_history' => 'Recent Neutral Corp',
        'corporation_history_churn' => 'Corp Churn',
        'quiet_corporation_history' => 'Quiet Corp History',
        'shared_user_agent' => 'Shared Browser',
        'thin_seat_footprint' => 'Thin Footprint',
        'no_productive_footprint' => 'No PvE/Indy/Market',
        'age_skill_mismatch' => 'Age vs SP',
        'low_assets' => 'Low Assets',
        'hostile_asset_location' => 'Hostile Asset Location',
        'hostile_employment_overlap' => 'Hostile Employment Overlap',
        'hostile_mail' => 'Hostile Mail',
        'hostile_wallet' => 'Hostile Wallet',
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
        'multi_character_account' => 'success',
        'low_account_skillpoints' => 'info',
        'no_pve_wallet_history' => 'info',
        'limited_recent_wallet_activity' => 'info',
        'stable_wallet_balance' => 'info',
        'hostile_corporation_history' => 'danger',
        'recent_neutral_corporation_history' => 'warning',
        'corporation_history_churn' => 'warning',
        'quiet_corporation_history' => 'secondary',
        'shared_user_agent' => 'warning',
        'thin_seat_footprint' => 'info',
        'no_productive_footprint' => 'info',
        'age_skill_mismatch' => 'info',
        'low_assets' => 'info',
        'hostile_asset_location' => 'warning',
        'hostile_employment_overlap' => 'danger',
        'hostile_mail' => 'danger',
        'hostile_wallet' => 'danger',
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
    $reviewBadge = ['new' => 'secondary', 'reviewing' => 'info', 'cleared' => 'success', 'watchlisted' => 'warning', 'escalated' => 'danger'][$report->review_status] ?? 'secondary';
@endphp

<div class="modal fade" id="intel-report-modal-{{ $report->id }}" tabindex="-1" role="dialog" aria-labelledby="intel-report-modal-title-{{ $report->id }}" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="intel-report-modal-title-{{ $report->id }}">{{ $report->character_name ?: ('User #' . $accountUserId) }}</h5>
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
                            <span class="badge badge-{{ $reviewBadge }}">{{ ucfirst($report->review_status ?: 'new') }}</span>
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
	                                    @foreach(['new' => 'New', 'reviewing' => 'Reviewing', 'cleared' => 'Cleared', 'watchlisted' => 'Watchlisted', 'escalated' => 'Escalated'] as $value => $label)
	                                        <option value="{{ $value }}" {{ $report->review_status === $value ? 'selected' : '' }}>{{ $label }}</option>
	                                    @endforeach
	                                </select>
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
                    <div class="col-md-3 col-6 mb-3">
                        <div class="border rounded p-3 h-100">
                            <small class="text-muted d-block">Risk</small>
                            <span class="badge badge-{{ $badge }}">{{ ucfirst($report->rating) }}</span>
                            <strong class="d-block h4 mb-0 mt-2">{{ $report->score }}/100</strong>
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
                            <span class="small text-muted">Total monitored SP</span>
                        </div>
                    </div>
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
                                        <span class="badge badge-{{ $categoryBadges[$evidence->category] ?? 'secondary' }}">{{ $categoryLabels[$evidence->category] ?? str_replace('_', ' ', ucfirst($evidence->category)) }}</span>
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

                @php($accountCharacters = data_get(optional($report->evidence->firstWhere('category', 'account_characters'))->meta, 'characters', []))
                @php($accountLoginIps = data_get(optional($report->evidence->firstWhere('category', 'account_characters'))->meta, 'login_ips', []))
                @php($accountConnectors = data_get(optional($report->evidence->firstWhere('category', 'account_connectors'))->meta, 'connectors', []))
                @if(!empty($accountCharacters))
                    <h6 class="mb-3">Monitored Characters</h6>
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
                                        <a href="{{ route('seatcore::character.view.sheet', ['character' => $character['character_id']]) }}">
                                            {{ $character['name'] ?: $character['character_id'] }}
                                        </a>
                                        <span class="text-muted small">({{ $character['character_id'] }})</span>
                                        @if(!empty($character['main']))
                                            <span class="badge badge-info">Main</span>
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
                            <tr>
                                <td>{{ $loginIp['ip'] }}</td>
                                <td>
                                    @if(!empty($loginIp['public']))
                                        <span class="badge badge-info">Public</span>
                                    @else
                                        <span class="badge badge-secondary">Private / Reserved</span>
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
                @forelse($report->evidence as $evidence)
                    @continue(in_array($evidence->category, ['account_characters', 'account_connectors']))
                    <div class="border rounded p-3 mb-3">
                        <div class="d-flex justify-content-between align-items-start flex-wrap">
                            <div class="mb-2">
                                <span class="badge badge-{{ $categoryBadges[$evidence->category] ?? 'secondary' }}">{{ $categoryLabels[$evidence->category] ?? str_replace('_', ' ', ucfirst($evidence->category)) }}</span>
                                <h6 class="mb-1 mt-2">{{ $evidence->title }}</h6>
                            </div>
                            <span class="badge badge-light">{{ $evidence->score }} pts</span>
                        </div>
                        @if($evidence->details)
                            <p class="mb-2">{{ $evidence->details }}</p>
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
                                                <a href="{{ route('seatcore::configuration.users.edit', ['user_id' => $sharedUser['user_id']]) }}">
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
                        @if($evidence->category === 'hostile_employment_overlap' && !empty(data_get($evidence->meta, 'matches')))
                            <div class="mb-2">
                                <span class="badge badge-danger">{{ data_get($evidence->meta, 'same_time_count', 0) }} same-time</span>
                                <span class="badge badge-warning">{{ data_get($evidence->meta, 'different_time_count', 0) }} historical-only</span>
                            </div>
                            <div class="table-responsive mb-2">
                                <table class="table table-sm table-bordered mb-0">
                                    <thead>
                                    <tr>
                                        <th>Monitored Character</th>
                                        <th>Hostile Character</th>
                                        <th>Corporation</th>
                                        <th>Monitored Dates</th>
                                        <th>Hostile Dates</th>
                                        <th>Timing</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach(data_get($evidence->meta, 'matches', []) as $match)
                                        <tr>
                                            <td>
                                                <a href="{{ route('seatcore::character.view.sheet', ['character' => data_get($match, 'character_id')]) }}">
                                                    {{ data_get($match, 'character_name') ?: data_get($match, 'character_id') }}
                                                </a>
                                                <div class="small text-muted">{{ data_get($match, 'character_id') }}</div>
                                            </td>
                                            <td>
                                                <a href="{{ route('seatcore::character.view.sheet', ['character' => data_get($match, 'hostile_character_id')]) }}">
                                                    {{ data_get($match, 'hostile_character_name') ?: data_get($match, 'hostile_character_id') }}
                                                </a>
                                                <div class="small text-muted">
                                                    {{ data_get($match, 'hostile_character_id') }}
                                                    @if(data_get($match, 'source_entity_type') && data_get($match, 'source_entity_id'))
                                                        / {{ ucfirst(data_get($match, 'source_entity_type')) }} {{ data_get($match, 'source_entity_id') }}
                                                    @endif
                                                </div>
                                            </td>
                                            <td>
                                                {{ data_get($match, 'corporation_name') ?: data_get($match, 'corporation_id') }}
                                                <div class="small text-muted">{{ data_get($match, 'corporation_id') }}</div>
                                            </td>
                                            <td>
                                                {{ data_get($match, 'local_start_date') ?: 'Unknown' }}
                                                -
                                                {{ data_get($match, 'local_end_date') ?: 'Current/Unknown' }}
                                            </td>
                                            <td>
                                                {{ data_get($match, 'hostile_start_date') ?: 'Unknown' }}
                                                -
                                                {{ data_get($match, 'hostile_end_date') ?: 'Current/Unknown' }}
                                            </td>
                                            <td>
                                                @if(data_get($match, 'same_time'))
                                                    <span class="badge badge-danger">Same time</span>
                                                @else
                                                    <span class="badge badge-warning">Different time</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
	                        @if(!empty($evidence->meta))
	                            <details>
	                                <summary class="small text-muted">Show captured context</summary>
	                                <pre class="bg-light border rounded p-2 mt-2 mb-0 small" style="white-space: pre-wrap;">{{ json_encode($evidence->meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
	                            </details>
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
                @empty
                    <p class="text-muted mb-0">No evidence was recorded for this account.</p>
                @endforelse
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
