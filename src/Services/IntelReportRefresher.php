<?php

namespace Raikia\SeatSpyHunter\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Raikia\SeatSpyHunter\Models\CharacterIntelReport;
use Raikia\SeatSpyHunter\Models\FalsePositiveSuppression;
use Raikia\SeatSpyHunter\Models\IntelEntity;
use Raikia\SeatSpyHunter\Models\IpIntelligence;
use Raikia\SeatSpyHunter\Models\VpnLookupQueue;
use Seat\Eveapi\Models\RefreshToken;
use Seat\Eveapi\Models\Character\CharacterInfo;
use Seat\Eveapi\Models\Wallet\CharacterWalletJournal;
use Seat\Eveapi\Models\Wallet\CharacterWalletTransaction;
use Seat\Web\Models\User;

class IntelReportRefresher
{
    private $analyzer;
    private $hostileContacts;
    private $eveWho;
    private $settings;
    private $ipIntelligence;

    public function __construct(CharacterRiskAnalyzer $analyzer, HostileContactResolver $hostileContacts, EveWhoService $eveWho, IntelSettings $settings, IpIntelligenceService $ipIntelligence)
    {
        $this->analyzer = $analyzer;
        $this->hostileContacts = $hostileContacts;
        $this->eveWho = $eveWho;
        $this->settings = $settings;
        $this->ipIntelligence = $ipIntelligence;
    }

    public function refresh(): int
    {
        return DB::transaction(function () {
            $hostileEntityIds = $this->hostileContacts->hostileEntityIds();
            $characters = $this->monitoredCharacters();
            $charactersByUser = $characters
                ->filter(fn ($character) => optional($character->user)->id)
                ->groupBy(fn ($character) => optional($character->user)->id);
            $previousReviews = CharacterIntelReport::query()
                ->with('evidence')
                ->get(['id', 'account_user_id', 'user_id', 'review_status', 'review_notes', 'reviewed_by', 'reviewed_at'])
                ->keyBy(fn ($report) => (int) ($report->account_user_id ?: $report->user_id));
            $count = 0;

            $this->eveWho->queueConfiguredHostiles();
            CharacterIntelReport::query()->delete();

            foreach ($charactersByUser as $userId => $userCharacters) {
                $user = User::find((int) $userId);

                if (!$user) {
                    continue;
                }

                $analyses = $userCharacters->map(function ($character) use ($hostileEntityIds) {
                    return [
                        'character' => $character,
                        'analysis' => $this->analyzer->analyze($character, $hostileEntityIds),
                    ];
                })->reject(fn ($row) => $row['analysis']['ignored'])->values();

                if ($analyses->isEmpty()) {
                    continue;
                }

                $scoredCharacters = $analyses->pluck('character')->values();
                $characterEvidence = $this->accountScopedCharacterEvidence($analyses);
                $accountEvidence = $this->accountEvidence($user, $scoredCharacters, $hostileEntityIds);
                $evidence = $characterEvidence->merge($accountEvidence)->values();
                $suppressedCategories = $this->suppressedCategories((int) $user->id);
                $suppressedEvidence = $evidence->filter(fn ($row) => $suppressedCategories->has($row['category']))->values();
                $evidence = $evidence->reject(fn ($row) => $suppressedCategories->has($row['category']))->values();
                $metrics = $this->accountMetrics($analyses);
                $mitigation = $accountEvidence->sum(fn ($row) => (int) data_get($row, 'meta.mitigation_score', 0));
                $score = min(100, max(0, (int) $evidence->sum('score') - $mitigation));
                $primaryCharacter = $this->primaryCharacter($user, $scoredCharacters);
                $affiliation = optional($primaryCharacter)->affiliation;
                $previousReview = $previousReviews->get((int) $user->id);
                $newEvidenceSinceReview = $this->newEvidenceSinceReviewEvidence($previousReview, $evidence);
                $reviewWorkflow = $this->reviewWorkflowAttributes($previousReview, (bool) $newEvidenceSinceReview);

                if ($newEvidenceSinceReview) {
                    $evidence->push($newEvidenceSinceReview);
                }

                $riskSignalCount = $evidence
                    ->reject(fn ($row) => in_array($row['category'], ['account_connectors', 'multi_character_account', 'new_evidence_since_review']))
                    ->where('score', '>', 0)
                    ->count();

                $reportAttributes = [
                    'character_id' => $primaryCharacter ? (int) $primaryCharacter->character_id : null,
                    'character_name' => $user->name,
                    'corporation_id' => $primaryCharacter ? $this->corporationId($primaryCharacter) : null,
                    'corporation_name' => $primaryCharacter ? $this->corporationName($primaryCharacter, $affiliation) : null,
                    'alliance_id' => $this->allianceId($affiliation),
                    'alliance_name' => $this->allianceName($affiliation),
                    'user_id' => (int) $user->id,
                    'score' => $score,
                    'rating' => RiskRating::fromScore($score),
                    'evidence_count' => $riskSignalCount,
                    'hostile_contact_count' => $metrics['hostile_contact_count'],
                    'hostile_mail_count' => $metrics['hostile_mail_count'],
                    'hostile_wallet_count' => $metrics['hostile_wallet_count'],
                    'shared_ip_user_count' => $metrics['shared_ip_user_count'],
                    'vpn_ip_count' => $metrics['vpn_ip_count'],
                    'skillpoints' => $scoredCharacters->sum(fn ($character) => (int) optional($character->skillpoints)->total_sp),
                    'birthday' => $this->oldestBirthday($scoredCharacters),
                    'last_analyzed_at' => now(),
                    'review_status' => $reviewWorkflow['review_status'],
                    'review_notes' => $reviewWorkflow['review_notes'],
                    'reviewed_by' => $reviewWorkflow['reviewed_by'],
                    'reviewed_at' => $reviewWorkflow['reviewed_at'],
                ];

                if (Schema::hasColumn('seat_spy_hunter_character_reports', 'account_user_id')) {
                    $reportAttributes['account_user_id'] = (int) $user->id;
                }

                $report = CharacterIntelReport::create($reportAttributes);

                $report->evidence()->create($this->accountCharactersEvidence($user, $scoredCharacters));

                foreach ($evidence as $row) {
                    $report->evidence()->create($row);
                }

                if ($suppressedEvidence->isNotEmpty()) {
                    $report->evidence()->create($this->suppressedEvidenceContext($suppressedEvidence, $suppressedCategories));
                }

                $count++;
            }

            return $count;
        });
    }

    private function accountMetrics($analyses): array
    {
        return [
            'hostile_contact_count' => $analyses->sum(fn ($row) => $row['analysis']['metrics']['hostile_contact_count'] ?? 0),
            'hostile_mail_count' => $analyses->sum(fn ($row) => $row['analysis']['metrics']['hostile_mail_count'] ?? 0),
            'hostile_wallet_count' => $analyses->sum(fn ($row) => $row['analysis']['metrics']['hostile_wallet_count'] ?? 0),
            'shared_ip_user_count' => $analyses->pluck('analysis.metrics.shared_ip_user_count')->max() ?: 0,
            'vpn_ip_count' => $analyses->pluck('analysis.metrics.vpn_ip_count')->max() ?: 0,
        ];
    }

    private function reviewWorkflowAttributes(?CharacterIntelReport $previousReview, bool $hasNewEvidence): array
    {
        if (!$previousReview) {
            return [
                'review_status' => 'new',
                'review_notes' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
            ];
        }

        if ($previousReview->review_status === 'permanently_cleared') {
            return [
                'review_status' => 'permanently_cleared',
                'review_notes' => $previousReview->review_notes,
                'reviewed_by' => $previousReview->reviewed_by,
                'reviewed_at' => $previousReview->reviewed_at,
            ];
        }

        if ($hasNewEvidence && $this->settings->reopenReviewOnNewEvidence() && in_array($previousReview->review_status, ['cleared', 'watchlisted'], true)) {
            return [
                'review_status' => 'reviewing',
                'review_notes' => $previousReview->review_notes,
                'reviewed_by' => null,
                'reviewed_at' => null,
            ];
        }

        return [
            'review_status' => $previousReview->review_status,
            'review_notes' => $previousReview->review_notes,
            'reviewed_by' => $previousReview->reviewed_by,
            'reviewed_at' => $previousReview->reviewed_at,
        ];
    }

    private function suppressedCategories(int $accountUserId)
    {
        return FalsePositiveSuppression::query()
            ->active()
            ->where('account_user_id', $accountUserId)
            ->get(['category', 'reason'])
            ->keyBy('category');
    }

    private function suppressedEvidenceContext($suppressedEvidence, $suppressedCategories): array
    {
        return [
            'category' => 'suppressed_signals',
            'score' => 0,
            'title' => 'False-positive suppressions applied',
            'details' => sprintf('%d evidence categor%s suppressed for this account.',
                $suppressedEvidence->count(),
                $suppressedEvidence->count() === 1 ? 'y was' : 'ies were'
            ),
            'meta' => [
                'suppressed' => $suppressedEvidence->map(fn ($row) => [
                    'category' => $row['category'],
                    'title' => $row['title'],
                    'score' => $row['score'],
                    'reason' => optional($suppressedCategories->get($row['category']))->reason,
                ])->values()->all(),
            ],
        ];
    }

    private function newEvidenceSinceReviewEvidence(?CharacterIntelReport $previousReport, $currentEvidence): ?array
    {
        if (!$previousReport || !$previousReport->reviewed_at) {
            return null;
        }

        $ignoredCategories = ['account_characters', 'account_connectors', 'suppressed_signals', 'new_evidence_since_review'];
        $previousFingerprints = $previousReport->evidence
            ->reject(fn ($row) => in_array($row->category, $ignoredCategories))
            ->map(fn ($row) => $this->evidenceFingerprint([
                'category' => $row->category,
                'title' => $row->title,
                'details' => $row->details,
                'meta' => $row->meta,
            ]))
            ->unique()
            ->flip();

        $newRows = $currentEvidence
            ->reject(fn ($row) => in_array($row['category'], $ignoredCategories))
            ->reject(fn ($row) => $previousFingerprints->has($this->evidenceFingerprint($row)))
            ->values();

        if ($newRows->isEmpty()) {
            return null;
        }

        return [
            'category' => 'new_evidence_since_review',
            'score' => 0,
            'title' => 'New evidence since last review',
            'details' => sprintf('%d evidence item%s changed or appeared since this account was last reviewed.',
                $newRows->count(),
                $newRows->count() === 1 ? '' : 's'
            ),
            'meta' => [
                'reviewed_at' => $this->dateString($previousReport->reviewed_at),
                'new_items' => $newRows->map(fn ($row) => [
                    'category' => $row['category'],
                    'title' => $row['title'],
                    'score' => $row['score'],
                ])->values()->all(),
            ],
        ];
    }

    private function evidenceFingerprint(array $row): string
    {
        return sha1(json_encode([
            'category' => $row['category'] ?? null,
            'title' => $row['title'] ?? null,
            'details' => $row['details'] ?? null,
            'meta' => $this->stableEvidenceMeta($row['meta'] ?? []),
        ], JSON_UNESCAPED_SLASHES));
    }

    private function stableEvidenceMeta($meta)
    {
        $meta = is_array($meta) ? $meta : [];

        foreach (['latest_received', 'latest_sent', 'latest_journal', 'latest_transactions', 'login_ips'] as $volatileKey) {
            unset($meta[$volatileKey]);
        }

        ksort($meta);

        return $meta;
    }

    private function accountScopedCharacterEvidence($analyses)
    {
        $evidence = $analyses->pluck('analysis.evidence')->flatten(1)->values();
        $accountSignalCategories = ['shared_ip', 'vpn_ip'];
        $replacedByAccountCategories = ['low_skills', 'few_trained_skills'];
        $accountSignals = collect();

        foreach ($accountSignalCategories as $category) {
            $signal = $evidence
                ->where('category', $category)
                ->sortByDesc('score')
                ->first();

            if ($signal) {
                $accountSignals->push($signal);
            }
        }

        return $evidence
            ->reject(fn ($row) => in_array($row['category'], array_merge($accountSignalCategories, $replacedByAccountCategories)))
            ->merge($accountSignals)
            ->values();
    }

    private function primaryCharacter(User $user, $characters): ?CharacterInfo
    {
        return $characters->firstWhere('character_id', $user->main_character_id)
            ?: $characters->sortByDesc(fn ($character) => (int) optional($character->skillpoints)->total_sp)->first();
    }

    private function oldestBirthday($characters): ?string
    {
        $birthday = $characters->pluck('birthday')->filter()->sort()->first();

        return $birthday ?: null;
    }

    private function accountCharactersEvidence(User $user, $characters): array
    {
        return [
            'category' => 'account_characters',
            'score' => 0,
            'title' => 'Monitored characters on this SeAT account',
            'details' => sprintf('%s has %d monitored character%s included in this account-level score.',
                $user->name,
                $characters->count(),
                $characters->count() === 1 ? '' : 's'
            ),
            'meta' => [
                'user_id' => (int) $user->id,
                'user_name' => $user->name,
                'login_ips' => $this->accountLoginIps($user),
                'characters' => $characters->map(function ($character) use ($user) {
                    $affiliation = $character->affiliation;

                    return [
                        'character_id' => (int) $character->character_id,
                        'name' => $character->name,
                        'main' => (int) $user->main_character_id === (int) $character->character_id,
                        'corporation_id' => $this->corporationId($character),
                        'corporation_name' => $this->corporationName($character, $affiliation),
                        'alliance_id' => $this->allianceId($affiliation),
                        'alliance_name' => $this->allianceName($affiliation),
                        'skillpoints' => optional($character->skillpoints)->total_sp,
                        'birthday' => $this->dateString($character->birthday),
                    ];
                })->values()->all(),
            ],
        ];
    }

    private function accountLoginIps(User $user): array
    {
        if (!Schema::hasTable('user_login_histories')) {
            return [];
        }

        $rows = DB::table('user_login_histories')
            ->where('user_id', $user->id)
            ->whereNotNull('source')
            ->where('source', '<>', '')
            ->select('source', DB::raw('count(*) as login_count'), DB::raw('max(created_at) as last_seen_at'))
            ->groupBy('source')
            ->orderByDesc('last_seen_at')
            ->get();

        $this->ipIntelligence->queuePublicIps($rows->pluck('source'));
        $ipIntelligence = IpIntelligence::query()
            ->whereIn('ip', $rows->pluck('source')->all())
            ->get()
            ->keyBy('ip');
        $queuedIps = VpnLookupQueue::query()
            ->whereIn('ip', $rows->pluck('source')->all())
            ->get()
            ->keyBy('ip');

        return $rows->map(function ($row) use ($ipIntelligence, $queuedIps) {
                $record = $ipIntelligence->get($row->source);
                $queued = $queuedIps->get($row->source);

                return [
                'ip' => $row->source,
                'login_count' => (int) $row->login_count,
                'last_seen_at' => $this->dateString($row->last_seen_at),
                'public' => $this->isPublicIp((string) $row->source),
                'intelligence' => $record ? [
                    'is_vpn' => (bool) $record->is_vpn,
                    'is_proxy' => (bool) $record->is_proxy,
                    'is_tor' => (bool) $record->is_tor,
                    'is_hosting' => (bool) $record->is_hosting,
                    'risk_score' => (int) $record->risk_score,
                    'provider' => $record->provider,
                    'checked_at' => $this->dateString($record->checked_at),
                    'suspicious' => $record->isSuspicious(),
                ] : null,
                'queue' => $queued ? [
                    'status' => $queued->status,
                    'attempts' => (int) $queued->attempts,
                    'available_at' => $this->dateString($queued->available_at),
                    'looked_up_at' => $this->dateString($queued->looked_up_at),
                    'last_error' => $queued->last_error,
                ] : null,
            ];
            })
            ->values()
            ->all();
    }

    private function accountEvidence(User $user, $characters, $hostileEntityIds)
    {
        $characterIds = $characters->pluck('character_id')->map(fn ($id) => (int) $id)->values();
        $evidence = collect();

        $connectorEvidence = $this->connectorEvidence($user);
        if ($connectorEvidence) {
            $evidence->push($connectorEvidence);
        }

        $this->addEsiCoverageHealthEvidence($user, $characters, $evidence);

        if ($characters->count() > 1) {
            $mitigation = min(20, ($characters->count() - 1) * 8);
            $evidence->push([
                'category' => 'multi_character_account',
                'score' => 0,
                'title' => 'Multiple monitored characters linked',
                'details' => sprintf('%s has %d monitored characters linked to the same SeAT account, reducing account-level concern by %d points.',
                    $user->name,
                    $characters->count(),
                    $mitigation
                ),
                'meta' => [
                    'character_count' => $characters->count(),
                    'mitigation_score' => $mitigation,
                ],
            ]);
        }

        $totalSkillpoints = $characters->sum(fn ($character) => (int) optional($character->skillpoints)->total_sp);
        if ($totalSkillpoints > 0 && $totalSkillpoints < 10000000) {
            $evidence->push([
                'category' => 'low_account_skillpoints',
                'score' => 15,
                'title' => 'Low account skillpoint footprint',
                'details' => sprintf('%s has %s total monitored skillpoints, below the 10,000,000 account threshold.',
                    $user->name,
                    number_format($totalSkillpoints)
                ),
                'meta' => [
                    'skillpoints' => $totalSkillpoints,
                    'threshold' => 10000000,
                ],
            ]);
        }

        $this->addCorporationHistoryEvidence($user, $characterIds, $hostileEntityIds, $evidence);
        $this->addEmploymentOverlapEvidence($user, $characterIds, $evidence);
        $this->addLowAssetValueEvidence($user, $characterIds, $evidence);
        $this->addFootprintEvidence($user, $characters, $characterIds, $hostileEntityIds, $evidence);
        $this->addWalletAccountEvidence($user, $characterIds, $evidence);

        return $evidence;
    }

    private function addEsiCoverageHealthEvidence(User $user, $characters, $evidence): void
    {
        $characterIds = $characters->pluck('character_id')->map(fn ($id) => (int) $id)->filter()->values();
        if ($characterIds->isEmpty()) {
            return;
        }

        $scopeGroups = $this->esiCoverageScopeGroups();
        $requiredScopes = collect($scopeGroups)->flatten()->unique()->values();
        $tokens = RefreshToken::withTrashed()
            ->whereIn('character_id', $characterIds->all())
            ->get()
            ->keyBy('character_id');

        $rows = $characters->map(function ($character) use ($tokens, $scopeGroups, $requiredScopes) {
            $token = $tokens->get((int) $character->character_id);
            $scopes = collect($token ? ($token->scopes ?: []) : [])->map(fn ($scope) => (string) $scope)->values();
            $missingScopes = $requiredScopes->diff($scopes)->values();
            $missingScopeGroups = collect($scopeGroups)
                ->filter(fn ($groupScopes) => collect($groupScopes)->diff($scopes)->isNotEmpty())
                ->keys()
                ->values();
            $status = 'healthy';
            $issues = [];

            if (!$token) {
                $status = 'missing_token';
                $issues[] = 'No token';
            } else {
                if ($token->deleted_at) {
                    $status = 'deleted_token';
                    $issues[] = 'Deleted token';
                }

                if (blank($token->refresh_token)) {
                    $status = 'missing_refresh_token';
                    $issues[] = 'Missing refresh token';
                }

                if ($token->updated_at && $token->updated_at->lt(now()->subDays(30))) {
                    $status = $status === 'healthy' ? 'stale_token' : $status;
                    $issues[] = 'Stale token';
                }
            }

            if ($missingScopeGroups->isNotEmpty()) {
                $status = $status === 'healthy' ? 'scope_gaps' : $status;
                $issues[] = 'Missing ' . $missingScopeGroups->implode(', ');
            }

            return [
                'character_id' => (int) $character->character_id,
                'character_name' => $character->name,
                'status' => $status,
                'issues' => $issues,
                'scope_count' => $scopes->count(),
                'scopes_profile' => $token ? $token->scopes_profile : null,
                'missing_scope_groups' => $missingScopeGroups->all(),
                'missing_scopes' => $missingScopes->all(),
                'has_refresh_token' => $token ? filled($token->refresh_token) : false,
                'access_token_current' => $token && $token->expires_on ? $token->expires_on->gt(now()) : false,
                'expires_on' => $token ? $this->dateString($token->expires_on) : null,
                'updated_at' => $token ? $this->dateString($token->updated_at) : null,
                'deleted_at' => $token ? $this->dateString($token->deleted_at) : null,
            ];
        })->values();

        $issueRows = $rows->filter(fn ($row) => !empty($row['issues']));
        $healthyRows = $rows->where('status', 'healthy');
        $coveragePercent = $rows->isNotEmpty() ? (int) round(($healthyRows->count() / $rows->count()) * 100) : 0;

        $evidence->push([
            'category' => 'esi_coverage_health',
            'score' => 0,
            'title' => 'ESI coverage health',
            'details' => sprintf('%s has %d of %d monitored character%s with complete current ESI coverage for Spy Hunter review data.',
                $user->name,
                $healthyRows->count(),
                $rows->count(),
                $rows->count() === 1 ? '' : 's'
            ),
            'meta' => [
                'coverage_percent' => $coveragePercent,
                'healthy_count' => $healthyRows->count(),
                'issue_count' => $issueRows->count(),
                'character_count' => $rows->count(),
                'required_scope_groups' => array_keys($scopeGroups),
                'characters' => $rows->all(),
            ],
        ]);
    }

    private function esiCoverageScopeGroups(): array
    {
        return [
            'Contacts' => ['esi-characters.read_contacts.v1'],
            'Mail' => ['esi-mail.read_mail.v1'],
            'Wallet' => ['esi-wallet.read_character_wallet.v1'],
            'Assets' => ['esi-assets.read_assets.v1'],
            'Skills' => ['esi-skills.read_skills.v1'],
            'Contracts' => ['esi-contracts.read_character_contracts.v1'],
            'Industry' => ['esi-industry.read_character_jobs.v1'],
            'Market' => ['esi-markets.read_character_orders.v1'],
            'Loyalty Points' => ['esi-characters.read_loyalty.v1'],
            'Fittings' => ['esi-fittings.read_fittings.v1'],
            'Killmails' => ['esi-killmails.read_killmails.v1'],
        ];
    }

    private function connectorEvidence(User $user): ?array
    {
        if (!Schema::hasTable('seat_connector_users')) {
            return null;
        }

        $connectors = DB::table('seat_connector_users')
            ->where('user_id', $user->id)
            ->orderBy('connector_type')
            ->get(['connector_type', 'connector_name', 'name_override', 'connector_id', 'unique_id', 'updated_at'])
            ->map(function ($connector) {
                return [
                    'type' => $connector->connector_type,
                    'name' => $connector->name_override ?: $connector->connector_name,
                    'connector_id' => $connector->connector_id,
                    'unique_id' => $connector->unique_id,
                    'updated_at' => $connector->updated_at,
                ];
            })
            ->values();

        return [
            'category' => 'account_connectors',
            'score' => 0,
            'title' => 'External connector registrations',
            'details' => sprintf('%s has %d external connector registration%s.',
                $user->name,
                $connectors->count(),
                $connectors->count() === 1 ? '' : 's'
            ),
            'meta' => [
                'connectors' => $connectors->all(),
            ],
        ];
    }

    private function addWalletAccountEvidence(User $user, $characterIds, $evidence): void
    {
        if ($characterIds->isEmpty()) {
            return;
        }

        $pveWalletCount = CharacterWalletJournal::query()
            ->whereIn('character_id', $characterIds->all())
            ->where(function ($query) {
                $query->where('ref_type', 'like', '%bounty%')
                    ->orWhere('ref_type', 'like', '%mission%');
            })
            ->count();

        if ($pveWalletCount === 0) {
            $evidence->push([
                'category' => 'no_pve_wallet_history',
                'score' => 10,
                'title' => 'No bounty or mission wallet history',
                'details' => sprintf('%s has no monitored character wallet journal entries with bounty or mission reward references.', $user->name),
                'meta' => [
                    'ref_type_patterns' => ['%bounty%', '%mission%'],
                ],
            ]);
        }

        $recentSince = now()->subDays(30);
        $recentJournalCount = CharacterWalletJournal::query()
            ->whereIn('character_id', $characterIds->all())
            ->where('date', '>=', $recentSince)
            ->count();
        $recentTransactionCount = CharacterWalletTransaction::query()
            ->whereIn('character_id', $characterIds->all())
            ->where('date', '>=', $recentSince)
            ->count();
        $recentWalletActivity = $recentJournalCount + $recentTransactionCount;

        if ($recentWalletActivity <= 2) {
            $evidence->push([
                'category' => 'limited_recent_wallet_activity',
                'score' => 10,
                'title' => 'Limited recent wallet activity',
                'details' => sprintf('%s has only %d wallet journal or market transaction rows across monitored characters in the last 30 days.',
                    $user->name,
                    $recentWalletActivity
                ),
                'meta' => [
                    'since' => $recentSince->toDateString(),
                    'journal_count' => $recentJournalCount,
                    'transaction_count' => $recentTransactionCount,
                ],
            ]);
        }

        $balanceStats = CharacterWalletJournal::query()
            ->whereIn('character_id', $characterIds->all())
            ->whereNotNull('balance')
            ->where('date', '>=', now()->subDays(90))
            ->selectRaw('count(*) as samples, min(balance) as min_balance, max(balance) as max_balance')
            ->first();

        if ($balanceStats && (int) $balanceStats->samples >= 5) {
            $minBalance = (float) $balanceStats->min_balance;
            $maxBalance = (float) $balanceStats->max_balance;
            $movement = $maxBalance - $minBalance;
            $movementRatio = $maxBalance > 0 ? $movement / $maxBalance : 0;

            if ($movement < 1000000 || $movementRatio < 0.05) {
                $evidence->push([
                    'category' => 'stable_wallet_balance',
                    'score' => 8,
                    'title' => 'Wallet balance shows little movement',
                    'details' => sprintf('%s has a narrow wallet balance range across recent journal samples.', $user->name),
                    'meta' => [
                        'samples' => (int) $balanceStats->samples,
                        'min_balance' => $minBalance,
                        'max_balance' => $maxBalance,
                        'movement' => $movement,
                        'movement_ratio' => $movementRatio,
                        'window_days' => 90,
                    ],
                ]);
            }
        }
    }

    private function addCorporationHistoryEvidence(User $user, $characterIds, $hostileEntityIds, $evidence): void
    {
        if ($characterIds->isEmpty() || !Schema::hasTable('character_corporation_histories')) {
            return;
        }

        $histories = DB::table('character_corporation_histories')
            ->whereIn('character_id', $characterIds->all())
            ->where('is_deleted', false)
            ->orderByDesc('start_date')
            ->get(['character_id', 'corporation_id', 'start_date']);

        $hostileCorpIds = $hostileEntityIds->map(fn ($id) => (int) $id)->flip();
        $hostileHistories = $histories->filter(fn ($row) => $hostileCorpIds->has((int) $row->corporation_id));
        $monitoredCorpIds = $this->monitoredCorporationIds()->flip();

        if ($hostileHistories->isNotEmpty()) {
            $evidence->push([
                'category' => 'hostile_corporation_history',
                'score' => min(30, 18 + (($hostileHistories->count() - 1) * 4)),
                'title' => 'Past corporation overlap with hostile or negative contacts',
                'details' => sprintf('%s has monitored characters with prior corporation history matching configured hostile or monitored negative-contact entities.', $user->name),
                'meta' => [
                    'matches' => $hostileHistories->take(10)->map(fn ($row) => [
                        'character_id' => (int) $row->character_id,
                        'corporation_id' => (int) $row->corporation_id,
                        'start_date' => $this->dateString($row->start_date),
                    ])->values()->all(),
                ],
            ]);
        }

        $recentUnmonitoredHistories = $histories->filter(function ($row) use ($monitoredCorpIds) {
            return $row->start_date
                && $row->start_date >= now()->subDays(180)->toDateString()
                && !$monitoredCorpIds->has((int) $row->corporation_id);
        });

        if ($recentUnmonitoredHistories->isNotEmpty()) {
            $evidence->push([
                'category' => 'recent_neutral_corporation_history',
                'score' => min(16, 8 + (($recentUnmonitoredHistories->count() - 1) * 2)),
                'title' => 'Recent non-monitored corporation history',
                'details' => sprintf('%s has recent corporation-history entries outside monitored corporations and known monitored-alliance member corporations.', $user->name),
                'meta' => [
                    'matches' => $recentUnmonitoredHistories->take(10)->map(fn ($row) => [
                        'character_id' => (int) $row->character_id,
                        'corporation_id' => (int) $row->corporation_id,
                        'start_date' => $this->dateString($row->start_date),
                    ])->values()->all(),
                    'window_days' => 180,
                ],
            ]);
        }

        $historyCount = $histories->count();
        $uniqueCorporationCount = $histories->pluck('corporation_id')->filter()->unique()->count();
        $firstHistoryDate = $histories->pluck('start_date')->filter()->sort()->first();
        $historyAgeDays = $firstHistoryDate ? max(1, now()->diffInDays($firstHistoryDate)) : null;
        $historyAgeYears = $historyAgeDays ? max(1, $historyAgeDays / 365) : 1;
        $expectedCorporations = max(2, (int) ceil($historyAgeYears * 2));
        $excessCorporations = max(0, $uniqueCorporationCount - $expectedCorporations);
        $recentHistoryCount = $histories->filter(function ($row) {
            return $row->start_date && $row->start_date >= now()->subDays(180)->toDateString();
        })->count();

        if ($excessCorporations > 0 || $recentHistoryCount >= 3) {
            $recentScore = $recentHistoryCount >= 3 ? min(4, $recentHistoryCount - 2) : 0;
            $evidence->push([
                'category' => 'corporation_history_churn',
                'score' => min(20, 6 + ($excessCorporations * 2) + $recentScore),
                'title' => 'Frequent corporation changes',
                'details' => sprintf('%s has %d unique corporations across %s years of corporation history; rule of thumb allows about %d.',
                    $user->name,
                    $uniqueCorporationCount,
                    number_format($historyAgeYears, 1),
                    $expectedCorporations
                ),
                'meta' => [
                    'history_count' => $historyCount,
                    'unique_corporation_count' => $uniqueCorporationCount,
                    'history_age_days' => $historyAgeDays,
                    'history_age_years' => $historyAgeYears,
                    'expected_corporations' => $expectedCorporations,
                    'excess_corporations' => $excessCorporations,
                    'recent_history_count' => $recentHistoryCount,
                    'window_days' => 180,
                ],
            ]);
        }

        if ($historyCount > 0 && $recentHistoryCount === 0) {
            $evidence->push([
                'category' => 'quiet_corporation_history',
                'score' => 0,
                'title' => 'No recent corporation movement',
                'details' => sprintf('%s has no corporation-history changes in the last 180 days. This is context only and does not add suspicion points.', $user->name),
                'meta' => [
                    'history_count' => $historyCount,
                    'unique_corporation_count' => $uniqueCorporationCount,
                    'recent_history_count' => 0,
                    'window_days' => 180,
                ],
            ]);
        }
    }

    private function addEmploymentOverlapEvidence(User $user, $characterIds, $evidence): void
    {
        $overlaps = $this->eveWho->hostileEmploymentOverlaps($characterIds);

        if ($overlaps->isEmpty()) {
            return;
        }

        $sameTime = $overlaps->where('same_time', true);
        $recent = $overlaps->where('overlap_age_bucket', 'recent');
        $aging = $overlaps->where('overlap_age_bucket', 'aging');
        $old = $overlaps->where('overlap_age_bucket', 'old');
        $unknownAge = $overlaps->where('overlap_age_bucket', 'unknown');
        $score = min(45,
            ($recent->where('same_time', true)->count() * 12)
            + ($recent->where('same_time', false)->count() * 7)
            + ($aging->where('same_time', true)->count() * 6)
            + ($aging->where('same_time', false)->count() * 3)
            + ($old->where('same_time', true)->count() * 2)
            + ($old->where('same_time', false)->count() * 1)
            + ($unknownAge->where('same_time', true)->count() * 4)
            + ($unknownAge->where('same_time', false)->count() * 2)
        );
        $score = max($sameTime->isNotEmpty() ? 4 : 2, $score);

        $evidence->push([
            'category' => 'hostile_employment_overlap',
            'score' => $score,
            'title' => $sameTime->isNotEmpty() ? 'Employment overlap with hostile characters' : 'Historical corporation overlap with hostile characters',
            'details' => sprintf('%s has employment history overlapping SeAT ESI histories for characters currently cached from hostile EveWho groups%s.',
                $user->name,
                $sameTime->isNotEmpty() ? ', including same-time corporation membership' : ''
            ),
            'meta' => [
                'same_time_count' => $sameTime->count(),
                'different_time_count' => $overlaps->count() - $sameTime->count(),
                'recent_count' => $recent->count(),
                'aging_count' => $aging->count(),
                'old_count' => $old->count(),
                'unknown_age_count' => $unknownAge->count(),
                'recency_window_days' => 730,
                'matches' => $overlaps->take(20)->values()->all(),
            ],
        ]);
    }

    private function addFootprintEvidence(User $user, $characters, $characterIds, $hostileEntityIds, $evidence): void
    {
        if ($characterIds->isEmpty()) {
            return;
        }

        $counts = [
            'assets' => $this->countCharacterRows('character_assets', $characterIds),
            'saved_fittings' => $this->countCharacterRows('character_fittings', $characterIds),
            'lossmails' => $this->countCharacterLossmails($characterIds),
            'wallet_journal' => $this->countCharacterRows('character_wallet_journals', $characterIds),
            'wallet_transactions' => $this->countCharacterRows('character_wallet_transactions', $characterIds),
            'industry_jobs' => $this->countCharacterRows('character_industry_jobs', $characterIds),
            'mining' => $this->countCharacterRows('character_minings', $characterIds),
            'market_orders' => $this->countCharacterRows('character_orders', $characterIds),
        ];

        $activityCount = array_sum($counts);
        $oldestBirthday = $characters->pluck('birthday')->filter()->sort()->first();
        $oldestAgeDays = $oldestBirthday ? now()->diffInDays($oldestBirthday) : null;
        $totalSkillpoints = $characters->sum(fn ($character) => (int) optional($character->skillpoints)->total_sp);

        if ($oldestAgeDays !== null && $oldestAgeDays >= 180 && $activityCount <= 10) {
            $evidence->push([
                'category' => 'thin_seat_footprint',
                'score' => 14,
                'title' => 'Thin SeAT footprint for account age',
                'details' => sprintf('%s has an older monitored character history but only %d visible SeAT activity rows across key datasets.',
                    $user->name,
                    $activityCount
                ),
                'meta' => [
                    'oldest_age_days' => $oldestAgeDays,
                    'activity_count' => $activityCount,
                    'counts' => $counts,
                ],
            ]);
        }

        $productiveFootprint = $counts['wallet_journal'] + $counts['wallet_transactions'] + $counts['industry_jobs'] + $counts['mining'] + $counts['market_orders'];
        if ($productiveFootprint <= 3) {
            $evidence->push([
                'category' => 'no_productive_footprint',
                'score' => 12,
                'title' => 'Little PvE, industry, or market footprint',
                'details' => sprintf('%s has only %d wallet, mining, industry, or market rows across monitored characters.',
                    $user->name,
                    $productiveFootprint
                ),
                'meta' => [
                    'productive_footprint' => $productiveFootprint,
                    'counts' => $counts,
                ],
            ]);
        }

        if ($oldestAgeDays !== null && $oldestAgeDays >= 365 && $totalSkillpoints > 0 && $totalSkillpoints < 15000000) {
            $evidence->push([
                'category' => 'age_skill_mismatch',
                'score' => 12,
                'title' => 'Character age and skillpoints do not line up',
                'details' => sprintf('%s has monitored characters at least %d days old but only %s total monitored SP.',
                    $user->name,
                    $oldestAgeDays,
                    number_format($totalSkillpoints)
                ),
                'meta' => [
                    'oldest_age_days' => $oldestAgeDays,
                    'skillpoints' => $totalSkillpoints,
                    'threshold' => 15000000,
                ],
            ]);
        }

        if ($counts['assets'] <= 5) {
            $evidence->push([
                'category' => 'low_assets',
                'score' => $counts['assets'] === 0 ? 12 : 8,
                'title' => 'No or low visible assets',
                'details' => sprintf('%s has %d visible asset row%s across monitored characters.',
                    $user->name,
                    $counts['assets'],
                    $counts['assets'] === 1 ? '' : 's'
                ),
                'meta' => [
                    'asset_count' => $counts['assets'],
                ],
            ]);
        }

        if (Schema::hasTable('character_fittings') && Schema::hasColumn('character_fittings', 'character_id') && $counts['saved_fittings'] === 0) {
            $evidence->push([
                'category' => 'no_saved_fittings',
                'score' => 12,
                'title' => 'No saved fittings on account characters',
                'details' => sprintf('%s has no saved fittings across monitored characters on this SeAT account.', $user->name),
                'meta' => [
                    'saved_fitting_count' => 0,
                    'character_count' => $characterIds->count(),
                ],
            ]);
        }

        if ($counts['lossmails'] === 0 && (Schema::hasTable('killmail_victims') || Schema::hasTable('character_killmails'))) {
            $evidence->push([
                'category' => 'no_lossmails',
                'score' => 12,
                'title' => 'No recorded lossmails',
                'details' => sprintf('%s has no recorded lossmails across monitored characters. This can indicate a thin or carefully-managed public footprint.', $user->name),
                'meta' => [
                    'lossmail_count' => 0,
                    'character_count' => $characterIds->count(),
                ],
            ]);
        }

        $this->addLowLoyaltyPointEvidence($user, $characterIds, $evidence);

        $this->addAssetLocationRiskEvidence($user, $characterIds, $hostileEntityIds, $evidence);
    }

    private function addLowAssetValueEvidence(User $user, $characterIds, $evidence): void
    {
        $threshold = 500000000;

        if ($characterIds->isEmpty() || !Schema::hasTable('character_assets') || !Schema::hasColumn('character_assets', 'type_id')) {
            return;
        }

        $hasMarketPrices = Schema::hasTable('market_prices');
        $hasSdeTypes = Schema::hasTable('invTypes');
        $quantityColumn = Schema::hasColumn('character_assets', 'quantity') ? 'quantity' : null;

        $query = DB::table('character_assets')
            ->whereIn('character_assets.character_id', $characterIds->all())
            ->select('character_assets.type_id');

        if ($quantityColumn) {
            $query->selectRaw('sum(greatest(character_assets.quantity, 1)) as quantity');
        } else {
            $query->selectRaw('count(*) as quantity');
        }

        $marketPriceColumns = $hasMarketPrices
            ? collect(['average_price', 'adjusted_price', 'average', 'sell_price', 'buy_price'])
                ->filter(fn ($column) => Schema::hasColumn('market_prices', $column))
                ->values()
            : collect();

        if ($hasMarketPrices) {
            $query->leftJoin('market_prices', 'market_prices.type_id', '=', 'character_assets.type_id')
                ->addSelect($marketPriceColumns
                    ->map(fn ($column) => DB::raw(sprintf('max(market_prices.%s) as %s', $column, $column)))
                    ->all());
        }

        if ($hasSdeTypes) {
            $query->leftJoin('invTypes', 'invTypes.typeID', '=', 'character_assets.type_id')
                ->addSelect([
                    DB::raw('max(invTypes.typeName) as type_name'),
                    DB::raw('max(invTypes.basePrice) as base_price'),
                ]);
        }

        $rows = $query
            ->groupBy('character_assets.type_id')
            ->get()
            ->map(function ($row) use ($marketPriceColumns) {
                $priceCandidates = $marketPriceColumns
                    ->mapWithKeys(fn ($column) => [$column => (float) ($row->{$column} ?? 0)])
                    ->merge(['base_price' => (float) ($row->base_price ?? 0)])
                    ->all();
                $priceSource = collect($priceCandidates)->filter(fn ($price) => $price > 0)->keys()->first();
                $unitPrice = $priceSource ? $priceCandidates[$priceSource] : 0.0;
                $quantity = max(1, (int) $row->quantity);

                return [
                    'type_id' => (int) $row->type_id,
                    'type_name' => $row->type_name ?? null,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'price_source' => $priceSource,
                    'estimated_value' => $unitPrice * $quantity,
                ];
            });

        if ($rows->isEmpty()) {
            return;
        }

        $pricedRows = $rows->filter(fn ($row) => (float) $row['unit_price'] > 0);

        if ($pricedRows->isEmpty()) {
            return;
        }

        $estimatedValue = (float) $pricedRows->sum('estimated_value');

        if ($estimatedValue >= $threshold) {
            return;
        }

        $evidence->push([
            'category' => 'low_asset_value',
            'score' => $estimatedValue < 100000000 ? 18 : 14,
            'title' => 'Low total asset value',
            'details' => sprintf('%s has about %s ISK in priced visible assets across monitored characters, below the %s ISK threshold.',
                $user->name,
                number_format($estimatedValue, 0),
                number_format($threshold)
            ),
            'meta' => [
                'estimated_asset_value' => $estimatedValue,
                'threshold' => $threshold,
                'priced_type_count' => $pricedRows->count(),
                'unpriced_type_count' => $rows->count() - $pricedRows->count(),
                'top_assets' => $pricedRows
                    ->sortByDesc('estimated_value')
                    ->take(10)
                    ->values()
                    ->all(),
            ],
        ]);
    }

    private function addLowLoyaltyPointEvidence(User $user, $characterIds, $evidence): void
    {
        if ($characterIds->isEmpty() || !Schema::hasTable('character_loyalty_points')) {
            return;
        }

        $rows = DB::table('character_loyalty_points')
            ->whereIn('character_id', $characterIds->all())
            ->get(['character_id', 'corporation_id', 'amount']);

        if ($rows->isEmpty()) {
            return;
        }

        $corporationNames = $this->corporationNames($rows->pluck('corporation_id')->filter()->unique()->values());
        $nonParagonRows = $rows
            ->reject(function ($row) use ($corporationNames) {
                $name = strtolower((string) $corporationNames->get((int) $row->corporation_id, ''));

                return str_contains($name, 'paragon');
            })
            ->values();

        if ($nonParagonRows->isEmpty()) {
            return;
        }

        $maxLp = (int) $nonParagonRows->max('amount');

        if ($maxLp >= 2000) {
            return;
        }

        $evidence->push([
            'category' => 'low_loyalty_points',
            'score' => 8,
            'title' => 'Very low non-Paragon loyalty points',
            'details' => sprintf('%s has LP records, but no non-Paragon corporation balance at or above 2,000 LP.', $user->name),
            'meta' => [
                'threshold' => 2000,
                'max_non_paragon_lp' => $maxLp,
                'corporations' => $nonParagonRows
                    ->sortByDesc('amount')
                    ->take(10)
                    ->map(fn ($row) => [
                        'character_id' => (int) $row->character_id,
                        'corporation_id' => (int) $row->corporation_id,
                        'corporation_name' => $corporationNames->get((int) $row->corporation_id),
                        'amount' => (int) $row->amount,
                    ])
                    ->values()
                    ->all(),
            ],
        ]);
    }

    private function addAssetLocationRiskEvidence(User $user, $characterIds, $hostileEntityIds, $evidence): void
    {
        if ($characterIds->isEmpty() || $hostileEntityIds->isEmpty() || !Schema::hasTable('character_assets') || !Schema::hasColumn('character_assets', 'location_id')) {
            return;
        }

        $hostileLocationAssets = DB::table('character_assets')
            ->whereIn('character_id', $characterIds->all())
            ->whereIn('location_id', $hostileEntityIds->all())
            ->select('location_id', DB::raw('count(*) as asset_count'))
            ->groupBy('location_id')
            ->orderByDesc('asset_count')
            ->get();

        if ($hostileLocationAssets->isEmpty()) {
            return;
        }

        $assetCount = $hostileLocationAssets->sum('asset_count');
        $evidence->push([
            'category' => 'hostile_asset_location',
            'score' => min(20, 10 + ($hostileLocationAssets->count() * 2)),
            'title' => 'Assets located at hostile-matching locations',
            'details' => sprintf('%s has %d asset row%s at locations matching configured hostile entity IDs.',
                $user->name,
                $assetCount,
                $assetCount === 1 ? '' : 's'
            ),
            'meta' => [
                'locations' => $hostileLocationAssets->map(fn ($row) => [
                    'location_id' => (int) $row->location_id,
                    'asset_count' => (int) $row->asset_count,
                ])->values()->all(),
            ],
        ]);
    }

    private function monitoredCharacters()
    {
        $entities = IntelEntity::query()
            ->where('category', IntelEntity::CATEGORY_MONITORED)
            ->get();

        $corporationIds = $entities->where('entity_type', 'corporation')->pluck('entity_id')->map(fn ($id) => (int) $id)->all();
        $allianceIds = $entities->where('entity_type', 'alliance')->pluck('entity_id')->map(fn ($id) => (int) $id)->all();

        return CharacterInfo::query()
            ->with('skillpoints', 'user', 'affiliation.corporation', 'affiliation.alliance')
            ->when(!empty($corporationIds) || !empty($allianceIds), function ($query) use ($corporationIds, $allianceIds) {
                $query->where(function ($inner) use ($corporationIds, $allianceIds) {
                    if (!empty($corporationIds)) {
                        $inner->where(function ($corporationQuery) use ($corporationIds) {
                            $corporationQuery->whereIn('corporation_id', $corporationIds)
                                ->orWhereHas('affiliation', function ($affiliationQuery) use ($corporationIds) {
                                    $affiliationQuery->whereIn('corporation_id', $corporationIds);
                                });
                        });
                    }

                    if (!empty($allianceIds)) {
                        $inner->orWhereHas('affiliation', function ($affiliationQuery) use ($allianceIds) {
                            $affiliationQuery->whereIn('alliance_id', $allianceIds);
                        });
                    }
                });
            })
            ->when(empty($corporationIds) && empty($allianceIds), function ($query) {
                $query->where('character_id', 0);
            })
            ->orderBy('name')
            ->get();
    }

    private function monitoredCorporationIds()
    {
        $entities = IntelEntity::query()
            ->where('category', IntelEntity::CATEGORY_MONITORED)
            ->get();

        $corporationIds = $entities
            ->where('entity_type', 'corporation')
            ->pluck('entity_id')
            ->map(fn ($id) => (int) $id);
        $allianceIds = $entities
            ->where('entity_type', 'alliance')
            ->pluck('entity_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values();

        if ($allianceIds->isEmpty()) {
            return $corporationIds->filter()->unique()->values();
        }

        $allianceCorporationIds = collect();

        if (Schema::hasTable('alliance_members') && Schema::hasColumn('alliance_members', 'alliance_id') && Schema::hasColumn('alliance_members', 'corporation_id')) {
            $allianceCorporationIds = DB::table('alliance_members')
                ->whereIn('alliance_id', $allianceIds->all())
                ->pluck('corporation_id')
                ->map(fn ($id) => (int) $id);
        }

        if ($allianceCorporationIds->isEmpty() && Schema::hasTable('character_affiliations')) {
            $allianceCorporationIds = DB::table('character_affiliations')
                ->whereIn('alliance_id', $allianceIds->all())
                ->whereNotNull('corporation_id')
                ->pluck('corporation_id')
                ->map(fn ($id) => (int) $id);
        }

        return $corporationIds
            ->merge($allianceCorporationIds)
            ->filter()
            ->unique()
            ->values();
    }

    private function corporationId(CharacterInfo $character): ?int
    {
        return $character->corporation_id ? (int) $character->corporation_id : optional($character->affiliation)->corporation_id;
    }

    private function corporationName(CharacterInfo $character, $affiliation): ?string
    {
        return $character->corporation_name
            ?: optional(optional($affiliation)->corporation)->name;
    }

    private function allianceId($affiliation): ?int
    {
        return optional($affiliation)->alliance_id ? (int) $affiliation->alliance_id : null;
    }

    private function allianceName($affiliation): ?string
    {
        return optional(optional($affiliation)->alliance)->name;
    }

    private function countCharacterRows(string $table, $characterIds): int
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'character_id')) {
            return 0;
        }

        return (int) DB::table($table)
            ->whereIn('character_id', $characterIds->all())
            ->count();
    }

    private function countCharacterLossmails($characterIds): int
    {
        if (Schema::hasTable('killmail_victims') && Schema::hasColumn('killmail_victims', 'character_id')) {
            return (int) DB::table('killmail_victims')
                ->whereIn('character_id', $characterIds->all())
                ->count();
        }

        return $this->countCharacterRows('character_killmails', $characterIds);
    }

    private function corporationNames($corporationIds)
    {
        $corporationIds = collect($corporationIds)->filter()->map(fn ($id) => (int) $id)->unique()->values();

        if ($corporationIds->isEmpty() || !Schema::hasTable('corporation_infos')) {
            return collect();
        }

        return DB::table('corporation_infos')
            ->whereIn('corporation_id', $corporationIds->all())
            ->pluck('name', 'corporation_id');
    }

    private function dateString($value): ?string
    {
        if (!$value) {
            return null;
        }

        return method_exists($value, 'toDateString') ? $value->toDateString() : (string) $value;
    }

    private function isPublicIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }
}
