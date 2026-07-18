@extends('web::layouts.grids.12')

@section('title', 'Spy Hunter Caches')
@section('page_header', 'Spy Hunter Caches')

@section('content')
    <div class="row">
        <div class="col-md-3 col-6 mb-3">
            <div class="small-box bg-info">
                <div class="inner">
                    <h3>{{ $summary['ip_count'] }}</h3>
                    <p>VPNAPI.io Cache</p>
                </div>
                <div class="icon"><i class="fas fa-shield-alt"></i></div>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-3">
            <div class="small-box bg-warning">
                <div class="inner">
                    <h3>{{ $summary['evewho_member_count'] }}</h3>
                    <p>EveWho Members</p>
                </div>
                <div class="icon"><i class="fas fa-history"></i></div>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-3">
            <div class="small-box bg-secondary">
                <div class="inner">
                    <h3>{{ $summary['vpn_pending'] }}</h3>
                    <p>VPN Pending</p>
                </div>
                <div class="icon"><i class="fas fa-tasks"></i></div>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-3">
            <div class="small-box bg-secondary">
                <div class="inner">
                    <h3>{{ $summary['evewho_pending'] }}</h3>
                    <p>EveWho Pending</p>
                </div>
                <div class="icon"><i class="fas fa-tasks"></i></div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-shield-alt mr-1"></i> VPNAPI.io / Manual IP Cache</h3>
            <div class="card-tools">
                <form method="POST" action="{{ route('seat-spy-hunter.caches.vpn.queue-login-ips') }}" class="d-inline">
                    {{ csrf_field() }}
                    <button type="submit" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-plus-circle"></i> Queue Login IPs
                    </button>
                </form>
                <form method="POST" action="{{ route('seat-spy-hunter.caches.vpn.process') }}" class="d-inline">
                    {{ csrf_field() }}
                    <button type="submit" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-play"></i> Run VPN Queue
                    </button>
                </form>
            </div>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                Queue Login IPs scans SeAT login history and adds uncached public IPs to the pending queue. Run VPN Queue dispatches the background worker that processes those pending lookups through VPNAPI.io. Cached IP results are kept indefinitely, and rate limits pause processing until the next UTC day.
            </div>
            <div class="row mb-3">
                <div class="col-md-2 col-6 mb-2">
                    <div class="border rounded p-2 h-100">
                        <small class="text-muted d-block">VPNAPI.io</small>
                        @if($loginIpQueueStats['configured'])
                            <span class="badge badge-success">Configured</span>
                        @else
                            <span class="badge badge-danger">Not configured</span>
                        @endif
                    </div>
                </div>
                <div class="col-md-2 col-6 mb-2">
                    <div class="border rounded p-2 h-100">
                        <small class="text-muted d-block">Distinct Login IPs</small>
                        <strong>{{ number_format($loginIpQueueStats['total']) }}</strong>
                    </div>
                </div>
                <div class="col-md-2 col-6 mb-2">
                    <div class="border rounded p-2 h-100">
                        <small class="text-muted d-block">Public</small>
                        <strong>{{ number_format($loginIpQueueStats['public']) }}</strong>
                    </div>
                </div>
                <div class="col-md-2 col-6 mb-2">
                    <div class="border rounded p-2 h-100">
                        <small class="text-muted d-block">Private / Reserved</small>
                        <strong>{{ number_format($loginIpQueueStats['private_or_reserved']) }}</strong>
                    </div>
                </div>
                <div class="col-md-2 col-6 mb-2">
                    <div class="border rounded p-2 h-100">
                        <small class="text-muted d-block">Cached / Queued</small>
                        <strong>{{ number_format($loginIpQueueStats['cached']) }} / {{ number_format($loginIpQueueStats['queued']) }}</strong>
                    </div>
                </div>
                <div class="col-md-2 col-6 mb-2">
                    <div class="border rounded p-2 h-100">
                        <small class="text-muted d-block">Queueable Now</small>
                        <strong>{{ number_format($loginIpQueueStats['queueable']) }}</strong>
                    </div>
                </div>
            </div>
            @if(!$loginIpQueueStats['has_login_history_table'])
                <div class="alert alert-warning">
                    SeAT's <code>user_login_histories</code> table was not found, so Spy Hunter cannot discover login IPs to queue.
                </div>
            @elseif($loginIpQueueStats['public'] === 0 && $loginIpQueueStats['total'] > 0)
                <div class="alert alert-warning">
                    Spy Hunter found login history, but none of the distinct login IPs are public routable IPs. Private, reserved, localhost, and Docker/internal addresses are skipped because VPNAPI.io cannot classify them usefully.
                </div>
            @endif
            <form method="GET" action="{{ route('seat-spy-hunter.caches') }}" class="form-row align-items-end mb-3">
                <div class="form-group col-md-9">
                    <label for="ip_search">Search IP Cache</label>
                    <input type="text" name="ip_search" id="ip_search" class="form-control" value="{{ $ipSearch }}" placeholder="IP address or provider">
                </div>
                <div class="form-group col-md-3">
                    <button type="submit" class="btn btn-secondary btn-block">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                </div>
            </form>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr>
                            <th>IP</th>
                            <th>Flags</th>
                            <th>Risk</th>
                            <th>Provider</th>
                            <th>Checked</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($ipRecords as $record)
                            <tr>
                                <td>{{ $record->ip }}</td>
                                <td>
                                    @foreach(['is_vpn' => 'VPN', 'is_proxy' => 'Proxy', 'is_tor' => 'Tor', 'is_hosting' => 'Hosting'] as $field => $label)
                                        @if($record->{$field})
                                            <span class="badge badge-warning">{{ $label }}</span>
                                        @endif
                                    @endforeach
                                    @if(!$record->is_vpn && !$record->is_proxy && !$record->is_tor && !$record->is_hosting)
                                        <span class="text-muted">None</span>
                                    @endif
                                </td>
                                <td>{{ $record->risk_score }}/100</td>
                                <td>{{ $record->provider ?: 'manual' }}</td>
                                <td>{{ $record->checked_at ? $record->checked_at->toDateTimeString() : 'Unknown' }}</td>
                                <td class="text-right">
                                    <form action="{{ route('seat-spy-hunter.caches.ip.destroy', $record) }}" method="POST">
                                        {{ csrf_field() }}
                                        {{ method_field('DELETE') }}
                                        <button type="submit" class="btn btn-outline-danger btn-sm">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-muted text-center py-4">No IP cache entries found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($ipRecords->hasPages())
            <div class="card-footer">{{ $ipRecords->links() }}</div>
        @endif
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-users mr-1"></i> EveWho Member Cache</h3>
            <div class="card-tools">
                <form method="POST" action="{{ route('seat-spy-hunter.caches.evewho.refresh-esi') }}" class="d-inline">
                    {{ csrf_field() }}
                    <button type="submit" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-sync-alt"></i> Force Monthly ESI Refresh
                    </button>
                </form>
                <form method="POST" action="{{ route('seat-spy-hunter.caches.evewho.clear') }}" class="d-inline" onsubmit="return confirm('Delete all cached EveWho members and queue configured hostile groups for re-sync?');">
                    {{ csrf_field() }}
                    {{ method_field('DELETE') }}
                    <button type="submit" class="btn btn-sm btn-outline-danger">
                        <i class="fas fa-trash"></i> Clear EveWho Cache
                    </button>
                </form>
            </div>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                EveWho is only used here to cache current hostile corporation or alliance members. Corporation history comes from SeAT's public CorporationHistory ESI job after those members are discovered. Normal refreshes trickle 5 stale members every 15 minutes and only requeue a member about once per month; forced refreshes run 10 members every 5 minutes.
            </div>
            <form method="GET" action="{{ route('seat-spy-hunter.caches') }}" class="form-row align-items-end mb-3">
                <div class="form-group col-md-9">
                    <label for="evewho_search">Search EveWho Cache</label>
                    <input type="text" name="evewho_search" id="evewho_search" class="form-control" value="{{ $eveWhoSearch }}" placeholder="Character, corporation, alliance, source, or ID">
                </div>
                <div class="form-group col-md-3">
                    <button type="submit" class="btn btn-secondary btn-block">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                </div>
            </form>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Character</th>
                            <th>Current Corp / Alliance</th>
                            <th>Last Seen</th>
                            <th>ESI Queued</th>
                            <th>Source</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($eveWhoMembers as $member)
                            @php
                                $localAffiliation = $eveWhoMemberAffiliations->get((int) $member->character_id, []);
                                $corporationId = $member->corporation_id ?: data_get($localAffiliation, 'corporation_id');
                                $corporationName = $member->corporation_name ?: data_get($localAffiliation, 'corporation_name');
                                $allianceId = $member->alliance_id ?: data_get($localAffiliation, 'alliance_id');
                                $allianceName = $member->alliance_name ?: data_get($localAffiliation, 'alliance_name');
                                $sourceKey = ($member->source_entity_type ?: 'unknown') . ':' . (int) $member->source_entity_id;
                                $sourceName = $sourceEntityNames->get($sourceKey);
                            @endphp
                            <tr>
                                <td>
                                    {{ $member->character_name ?: $member->character_id }}
                                    <div class="small text-muted">{{ $member->character_id }}</div>
                                </td>
                                <td>
                                    {{ $corporationName ?: ($corporationId ?: 'Unknown corporation') }}
                                    @if($corporationId)
                                        <div class="small text-muted">{{ $corporationId }}</div>
                                    @endif
                                    @if($allianceName || $allianceId)
                                        <div class="small">
                                            {{ $allianceName ?: $allianceId }}
                                            @if($allianceId)
                                                <span class="text-muted">({{ $allianceId }})</span>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    {{ $member->last_seen_at ? $member->last_seen_at->toDateTimeString() : 'Unknown' }}
                                </td>
                                <td>
                                    {{ $member->esi_queued_at ? $member->esi_queued_at->toDateTimeString() : 'Not queued yet' }}
                                </td>
                                <td>
                                    {{ $sourceName ?: ucfirst($member->source_entity_type ?: 'unknown') }}
                                    @if($member->source_entity_id)
                                        <div class="small text-muted">{{ ucfirst($member->source_entity_type ?: 'unknown') }} {{ $member->source_entity_id }}</div>
                                    @endif
                                </td>
                                <td class="text-right">
                                    <form action="{{ route('seat-spy-hunter.caches.evewho.destroy', $member) }}" method="POST">
                                        {{ csrf_field() }}
                                        {{ method_field('DELETE') }}
                                        <button type="submit" class="btn btn-outline-danger btn-sm">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-muted text-center py-4">No EveWho member cache entries found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($eveWhoMembers->hasPages())
            <div class="card-footer">{{ $eveWhoMembers->links() }}</div>
        @endif
    </div>
@endsection
