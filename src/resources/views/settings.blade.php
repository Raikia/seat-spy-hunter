@extends('web::layouts.grids.12')

@section('title', 'Spy Hunter Settings')
@section('page_header', 'Spy Hunter Settings')

@section('content')
    <div class="row">
        <div class="col-md-3 col-6 mb-3">
            <div class="small-box bg-info">
                <div class="inner">
                    <h3>{{ $monitoredEntities->count() }}</h3>
                    <p>Monitored Groups</p>
                </div>
                <div class="icon"><i class="fas fa-bullseye"></i></div>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-3">
            <div class="small-box bg-danger">
                <div class="inner">
                    <h3>{{ $hostileEntities->count() }}</h3>
                    <p>Hostile Entities</p>
                </div>
                <div class="icon"><i class="fas fa-user-secret"></i></div>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-3">
            <div class="small-box bg-secondary">
                <div class="inner">
                    <h3>{{ $vpnQueueSummary->get('pending', 0) }}</h3>
                    <p>VPN Queue</p>
                </div>
                <div class="icon"><i class="fas fa-network-wired"></i></div>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-3">
            <div class="small-box bg-warning">
                <div class="inner">
                    <h3>{{ $eveWhoQueueSummary->get('pending', 0) }}</h3>
                    <p>EveWho Queue</p>
                </div>
                <div class="icon"><i class="fas fa-history"></i></div>
            </div>
        </div>
    </div>

    <form action="{{ route('seat-spy-hunter.settings.general') }}" method="POST">
        {{ csrf_field() }}
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-sliders-h mr-1"></i> Scoring & Providers</h3>
                <div class="card-tools">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-save"></i> Save Settings
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-7">
                        <h6 class="text-muted text-uppercase small mb-3">Risk weights</h6>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="low_skillpoint_threshold">
                                    Low Skillpoint Threshold
                                    <i class="fas fa-info-circle text-muted ml-1" data-toggle="tooltip" title="Characters below this total SP threshold receive the low-skillpoint signal. This applies to individual character analysis before account-level aggregation."></i>
                                </label>
                                <input type="number" name="low_skillpoint_threshold" id="low_skillpoint_threshold" class="form-control" min="0" value="{{ $settings->lowSkillpointThreshold() }}">
                            </div>
                            <div class="form-group col-md-6">
                                <label for="new_character_days">
                                    New Character Days
                                    <i class="fas fa-info-circle text-muted ml-1" data-toggle="tooltip" title="Characters younger than this many days receive the new-character signal. Use this to tune how much very young alts stand out."></i>
                                </label>
                                <input type="number" name="new_character_days" id="new_character_days" class="form-control" min="1" value="{{ $settings->newCharacterDays() }}">
                            </div>
                            <div class="form-group col-md-4">
                                <label for="hostile_interaction_score">
                                    Hostile Interaction
                                    <i class="fas fa-info-circle text-muted ml-1" data-toggle="tooltip" title="Base score used when positive contacts, mail, or direct wallet activity match hostile entities. Repeated matches and outbound direction can add more points."></i>
                                </label>
                                <input type="number" name="hostile_interaction_score" id="hostile_interaction_score" class="form-control" min="0" max="100" value="{{ $settings->hostileInteractionScore() }}">
                            </div>
                            <div class="form-group col-md-4">
                                <label for="shared_ip_score">
                                    Shared IP
                                    <i class="fas fa-info-circle text-muted ml-1" data-toggle="tooltip" title="Base score used when a SeAT user account shares public login IPs with other SeAT user accounts. More distinct shared users can increase the score."></i>
                                </label>
                                <input type="number" name="shared_ip_score" id="shared_ip_score" class="form-control" min="0" max="100" value="{{ $settings->sharedIpScore() }}">
                            </div>
                            <div class="form-group col-md-4">
                                <label for="vpn_score">
                                    VPN / Proxy
                                    <i class="fas fa-info-circle text-muted ml-1" data-toggle="tooltip" title="Base score used when a public login IP is marked as VPN, proxy, Tor, hosting, Private Relay, or risk score 50+ in the IP intelligence cache."></i>
                                </label>
                                <input type="number" name="vpn_score" id="vpn_score" class="form-control" min="0" max="100" value="{{ $settings->vpnScore() }}">
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <h6 class="text-muted text-uppercase small mb-3">Review workflow</h6>
                        <div class="border rounded p-3 mb-3">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" name="reopen_review_on_new_evidence" value="1" class="custom-control-input" id="reopen_review_on_new_evidence" {{ $settings->reopenReviewOnNewEvidence() ? 'checked' : '' }}>
                                <label class="custom-control-label" for="reopen_review_on_new_evidence">
                                    Reopen reviewed accounts when new evidence appears
                                </label>
                            </div>
                            <div class="small text-muted mt-2">
                                When enabled, a report refresh that finds new evidence after the last review moves the account back to Reviewing and clears the previous reviewed timestamp.
                            </div>
                        </div>

                        <h6 class="text-muted text-uppercase small mb-3">IP intelligence</h6>
                        <div class="form-row">
                            <div class="form-group col-md-5">
                                <label for="ip_provider">Provider</label>
                                <input type="text" name="ip_provider" id="ip_provider" class="form-control" value="{{ $settings->ipProvider() }}" placeholder="vpnapi.io">
                            </div>
                            <div class="form-group col-md-7">
                                <label for="ip_provider_key">Provider Key</label>
                                <input type="password" name="ip_provider_key" id="ip_provider_key" class="form-control" value="{{ $settings->ipProviderKey() }}">
                            </div>
                        </div>
                        <div class="border rounded p-3 bg-light">
                            <div class="d-flex justify-content-between flex-wrap">
                                <span><i class="fas fa-cloud-download-alt text-muted mr-1"></i> VPNAPI.io</span>
                                <span class="text-muted">uncached public IPs only</span>
                            </div>
                            @if($settings->ipProviderLimitedUntil() && $settings->ipProviderLimitedUntil()->isFuture())
                                <div class="text-warning mt-2">
                                    <i class="fas fa-pause-circle"></i>
                                    Paused until {{ $settings->ipProviderLimitedUntil()->toDateTimeString() }}
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer text-right">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fas fa-save"></i> Save Settings
                </button>
            </div>
        </div>
    </form>

    <div class="card mb-4">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-crosshairs mr-1"></i> Hunting Scope</h3>
        </div>
        <div class="card-body">
            <ul class="nav nav-tabs" id="seat-spy-hunter-entity-tabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="seat-spy-hunter-monitored-tab" data-toggle="pill" href="#seat-spy-hunter-monitored" role="tab">
                        Monitored Groups <span class="badge badge-info ml-1">{{ $monitoredEntities->count() }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="seat-spy-hunter-hostile-tab" data-toggle="pill" href="#seat-spy-hunter-hostile" role="tab">
                        Hostile Entities <span class="badge badge-danger ml-1">{{ $hostileEntities->count() }}</span>
                    </a>
                </li>
            </ul>
            <div class="tab-content pt-3">
                <div class="tab-pane fade show active" id="seat-spy-hunter-monitored" role="tabpanel">
                    <div class="row">
                        <div class="col-lg-5">
                            @include('seat-spy-hunter::partials.entity-form', ['category' => 'monitored'])
                        </div>
                        <div class="col-lg-7 table-responsive">
                            @include('seat-spy-hunter::partials.entity-table', ['entities' => $monitoredEntities])
                        </div>
                    </div>
                </div>
                <div class="tab-pane fade" id="seat-spy-hunter-hostile" role="tabpanel">
                    <div class="row">
                        <div class="col-lg-5">
                            @include('seat-spy-hunter::partials.entity-form', ['category' => 'hostile'])
                        </div>
                        <div class="col-lg-7 table-responsive">
                            @include('seat-spy-hunter::partials.entity-table', ['entities' => $hostileEntities])
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="seat-spy-hunter-maintenance">
        <div class="card mb-2">
            <div class="card-header">
                <h3 class="card-title">
                    <a data-toggle="collapse" href="#seat-spy-hunter-ignore-collapse" aria-expanded="false">
                        <i class="fas fa-user-slash mr-1"></i> Ignored Characters
                    </a>
                </h3>
                <div class="card-tools">
                    <span class="badge badge-secondary">{{ $ignoredCharacters->count() }}</span>
                </div>
            </div>
            <div id="seat-spy-hunter-ignore-collapse" class="collapse" data-parent="#seat-spy-hunter-maintenance">
                <div class="card-body">
                    <form action="{{ route('seat-spy-hunter.settings.ignored-characters.store') }}" method="POST" class="mb-3">
                        {{ csrf_field() }}
                        <input type="hidden" name="name" class="seat-spy-hunter-ignored-character-name">
                        <div class="form-row align-items-end">
                            <div class="form-group col-md-5">
                                <label>Character</label>
                                <select name="character_id" class="form-control seat-spy-hunter-ignored-character-select" data-search-url="{{ route('seat-spy-hunter.settings.search.entities') }}" required style="width: 100%;"></select>
                            </div>
                            <div class="form-group col-md-5">
                                <label>Reason</label>
                                <input type="text" name="reason" class="form-control">
                            </div>
                            <div class="form-group col-md-2">
                                <button type="submit" class="btn btn-secondary btn-block">
                                    <i class="fas fa-plus"></i> Add
                                </button>
                            </div>
                        </div>
                    </form>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <tbody>
                                @forelse($ignoredCharacters as $character)
                                    <tr>
                                        <td>{{ $character->name ?: $character->character_id }}</td>
                                        <td class="text-muted">{{ $character->reason }}</td>
                                        <td class="text-right">
                                            <form action="{{ route('seat-spy-hunter.settings.ignored-characters.destroy', $character) }}" method="POST">
                                                {{ csrf_field() }}
                                                {{ method_field('DELETE') }}
                                                <button type="submit" class="btn btn-link btn-sm text-danger">Remove</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td class="text-muted">No ignored characters configured.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-2">
            <div class="card-header">
                <h3 class="card-title">
                    <a data-toggle="collapse" href="#seat-spy-hunter-ip-collapse" aria-expanded="false">
                        <i class="fas fa-shield-alt mr-1"></i> IP Risk Cache
                    </a>
                </h3>
                <div class="card-tools">
                    <span class="badge badge-secondary">VPN pending {{ $vpnQueueSummary->get('pending', 0) }}</span>
                    <span class="badge badge-success">VPN complete {{ $vpnQueueSummary->get('complete', 0) }}</span>
                </div>
            </div>
            <div id="seat-spy-hunter-ip-collapse" class="collapse" data-parent="#seat-spy-hunter-maintenance">
                <div class="card-body">
                    <form action="{{ route('seat-spy-hunter.settings.ip-intelligence.store') }}" method="POST" class="mb-3">
                        {{ csrf_field() }}
                        <div class="form-row align-items-end">
                            <div class="form-group col-md-3">
                                <label>IP</label>
                                <input type="text" name="ip" class="form-control" required>
                            </div>
                            <div class="form-group col-md-2">
                                <label>Risk Score</label>
                                <input type="number" name="risk_score" class="form-control" min="0" max="100" value="75" required>
                            </div>
                            <div class="form-group col-md-3">
                                <label>Provider</label>
                                <input type="text" name="provider" class="form-control" value="manual">
                            </div>
                            <div class="form-group col-md-3">
                                @foreach(['is_vpn' => 'VPN', 'is_proxy' => 'Proxy', 'is_tor' => 'Tor', 'is_hosting' => 'Hosting'] as $field => $label)
                                    <div class="custom-control custom-checkbox custom-control-inline">
                                        <input type="checkbox" name="{{ $field }}" value="1" class="custom-control-input" id="{{ $field }}">
                                        <label class="custom-control-label" for="{{ $field }}">{{ $label }}</label>
                                    </div>
                                @endforeach
                            </div>
                            <div class="form-group col-md-1">
                                <button type="submit" class="btn btn-secondary btn-block">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                    </form>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>IP</th>
                                    <th>Score</th>
                                    <th>Provider</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($ipRecords as $record)
                                    <tr>
                                        <td>{{ $record->ip }}</td>
                                        <td>{{ $record->risk_score }}/100</td>
                                        <td class="text-muted">{{ $record->provider ?: 'manual' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-muted">No IP intelligence records cached.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title">
                    <a data-toggle="collapse" href="#seat-spy-hunter-queue-collapse" aria-expanded="false">
                        <i class="fas fa-tasks mr-1"></i> Lookup Queues
                    </a>
                </h3>
                <div class="card-tools">
                    <span class="badge badge-info">EveWho pending {{ $eveWhoQueueSummary->get('pending', 0) }}</span>
                    <span class="badge badge-success">EveWho complete {{ $eveWhoQueueSummary->get('complete', 0) }}</span>
                </div>
            </div>
            <div id="seat-spy-hunter-queue-collapse" class="collapse" data-parent="#seat-spy-hunter-maintenance">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="border rounded p-3 h-100">
                                <h6>VPNAPI.io</h6>
                                <div class="small text-muted mb-2">Scheduled daily at 00:07 UTC, up to 1,000 uncached IPs.</div>
                                <div class="d-flex justify-content-between"><span>Pending</span><strong>{{ $vpnQueueSummary->get('pending', 0) }}</strong></div>
                                <div class="d-flex justify-content-between"><span>Complete</span><strong>{{ $vpnQueueSummary->get('complete', 0) }}</strong></div>
                                <code class="small d-block mt-2">php artisan seat-spy-hunter:vpn-lookup --limit=1000</code>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="border rounded p-3 h-100">
                                <h6>EveWho</h6>
                                <div class="small text-muted mb-2">Scheduled every 5 minutes, 10 hostile member-list pages per run. Newly discovered members queue SeAT ESI character jobs for corporation history.</div>
                                <div class="d-flex justify-content-between"><span>Pending</span><strong>{{ $eveWhoQueueSummary->get('pending', 0) }}</strong></div>
                                <div class="d-flex justify-content-between"><span>Complete</span><strong>{{ $eveWhoQueueSummary->get('complete', 0) }}</strong></div>
                                <code class="small d-block mt-2">php artisan seat-spy-hunter:evewho-sync --limit=10</code>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

	@push('javascript')
	    <script>
	        $(function () {
	            $('[data-toggle="tooltip"]').tooltip();

	            function selectedName(selection) {
                if (!selection) {
                    return '';
                }

                return selection.name || selection.text || '';
            }

            $('.seat-spy-hunter-entity-select').each(function () {
                var $select = $(this);

                $select.select2({
                    ajax: {
                        url: $select.data('search-url'),
                        dataType: 'json',
                        delay: 250,
                        data: function (params) {
                            return {
                                q: params.term || ''
                            };
                        },
                        processResults: function (data) {
                            return data;
                        }
                    },
                    minimumInputLength: 2,
                    placeholder: $select.data('placeholder'),
                    width: '100%',
                    allowClear: true
                }).on('select2:select', function (event) {
                    $select.closest('form').find('.seat-spy-hunter-selected-name').val(selectedName(event.params.data));
                }).on('select2:clear', function () {
                    $select.closest('form').find('.seat-spy-hunter-selected-name').val('');
                });
            });

            $('.seat-spy-hunter-hostile-entity-select').each(function () {
                var $select = $(this);
                var $form = $select.closest('form');
                var $type = $form.find('.seat-spy-hunter-hostile-type');

                $select.select2({
                    ajax: {
                        url: $select.data('search-url'),
                        dataType: 'json',
                        delay: 250,
                        data: function (params) {
                            return {
                                q: params.term || '',
                                type: $type.val()
                            };
                        },
                        processResults: function (data) {
                            return data;
                        }
                    },
                    minimumInputLength: 2,
                    placeholder: $select.data('placeholder'),
                    width: '100%',
                    allowClear: true
                }).on('select2:select', function (event) {
                    $form.find('.seat-spy-hunter-selected-name').val(selectedName(event.params.data));
                }).on('select2:clear', function () {
                    $form.find('.seat-spy-hunter-selected-name').val('');
                });

                $type.on('change', function () {
                    $select.val(null).trigger('change');
                    $form.find('.seat-spy-hunter-selected-name').val('');
                });
            });

            $('.seat-spy-hunter-ignored-character-select').each(function () {
                var $select = $(this);
                var $form = $select.closest('form');

                $select.select2({
                    ajax: {
                        url: $select.data('search-url'),
                        dataType: 'json',
                        delay: 250,
                        data: function (params) {
                            return {
                                q: params.term || '',
                                type: 'character'
                            };
                        },
                        processResults: function (data) {
                            return data;
                        }
                    },
                    minimumInputLength: 2,
                    placeholder: 'Search characters...',
                    width: '100%',
                    allowClear: true
                }).on('select2:select', function (event) {
                    $form.find('.seat-spy-hunter-ignored-character-name').val(selectedName(event.params.data));
                }).on('select2:clear', function () {
                    $form.find('.seat-spy-hunter-ignored-character-name').val('');
                });
            });
        });
    </script>
@endpush
