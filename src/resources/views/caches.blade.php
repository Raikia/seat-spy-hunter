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
        </div>
        <div class="card-body">
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
            </div>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                EveWho is only used here to cache current hostile corporation or alliance members. Corporation history comes from SeAT's normal ESI character jobs after those members are discovered. Hostile-member ESI refreshes are throttled to roughly once per month per character unless you force a refresh; forced refreshes run in small delayed batches.
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
