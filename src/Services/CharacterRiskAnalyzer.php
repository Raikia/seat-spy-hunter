<?php

namespace Raikia\SeatSpyHunter\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Raikia\SeatSpyHunter\Models\IgnoredCharacter;
use Seat\Eveapi\Models\RefreshToken;
use Seat\Eveapi\Models\Character\CharacterInfo;
use Seat\Eveapi\Models\Contacts\CharacterContact;
use Seat\Eveapi\Models\Mail\MailHeader;
use Seat\Eveapi\Models\Wallet\CharacterWalletJournal;
use Seat\Eveapi\Models\Wallet\CharacterWalletTransaction;
use Seat\Web\Models\User;
use Seat\Web\Models\UserLoginHistory;

class CharacterRiskAnalyzer
{
    private $settings;
    private $ipIntelligence;

    public function __construct(IntelSettings $settings, IpIntelligenceService $ipIntelligence)
    {
        $this->settings = $settings;
        $this->ipIntelligence = $ipIntelligence;
    }

    public function analyze(CharacterInfo $character, Collection $hostileEntityIds): array
    {
        if (IgnoredCharacter::query()->where('character_id', $character->character_id)->exists()) {
            return [
                'ignored' => true,
                'score' => 0,
                'rating' => RiskRating::fromScore(0),
                'evidence' => [],
                'metrics' => [],
            ];
        }

        $evidence = collect();
        $metrics = [
            'hostile_contact_count' => 0,
            'hostile_mail_count' => 0,
            'hostile_wallet_count' => 0,
            'shared_ip_user_count' => 0,
            'vpn_ip_count' => 0,
        ];

        $this->addHostileContactEvidence($character, $hostileEntityIds, $evidence, $metrics);
        $this->addHostileMailEvidence($character, $hostileEntityIds, $evidence, $metrics);
        $this->addHostileWalletEvidence($character, $hostileEntityIds, $evidence, $metrics);
        $this->addLoginEvidence($character, $evidence, $metrics);
        $this->addTokenEvidence($character, $evidence);
        $this->addSparseDataEvidence($character, $evidence);
        $this->addHistoryEvidence($character, $evidence);

        $score = min(100, (int) $evidence->sum('score'));

        return [
            'ignored' => false,
            'score' => $score,
            'rating' => RiskRating::fromScore($score),
            'evidence' => $evidence->values()->all(),
            'metrics' => $metrics,
        ];
    }

    private function addHostileContactEvidence(CharacterInfo $character, Collection $hostileEntityIds, Collection $evidence, array &$metrics): void
    {
        if ($hostileEntityIds->isEmpty()) {
            return;
        }

        $matches = CharacterContact::query()
            ->where('character_id', $character->character_id)
            ->whereIn('contact_id', $hostileEntityIds->all())
            ->with('entity')
            ->get();

        $metrics['hostile_contact_count'] = $matches->count();

        if ($matches->isNotEmpty()) {
            $names = $matches->map(function ($contact) {
                return sprintf('%s (%s, standing %s)',
                    optional($contact->entity)->name ?: $contact->contact_id,
                    $contact->contact_type,
                    $contact->standing
                );
            })->take(5)->implode(', ');

            $watchedCount = $matches->where('is_watched', true)->count();
            $blockedCount = $matches->where('is_blocked', true)->count();

            $evidence->push([
                'category' => 'hostile_contacts',
                'score' => min(35, $this->settings->hostileInteractionScore() + (($matches->count() - 1) * 5)),
                'title' => 'Has hostile or negative contacts',
                'details' => sprintf('%s has %d contact match%s against configured hostile entities or monitored negative standings: %s. Watched contacts are a stronger active-intel signal than passive negative standings.',
                    $character->name,
                    $matches->count(),
                    $matches->count() === 1 ? '' : 'es',
                    $names
                ),
                'meta' => [
                    'contacts' => $matches->map(function ($contact) {
                        return [
                            'id' => (int) $contact->contact_id,
                            'name' => optional($contact->entity)->name,
                            'type' => $contact->contact_type,
                            'standing' => (float) $contact->standing,
                            'watched' => (bool) $contact->is_watched,
                            'blocked' => (bool) $contact->is_blocked,
                        ];
                    })->values()->all(),
                    'direction' => [
                        'relationship' => 'character_contact_list',
                        'watched_count' => $watchedCount,
                        'blocked_count' => $blockedCount,
                        'interpretation' => $watchedCount > 0 ? 'active_watchlist' : 'stored_contact',
                    ],
                ],
            ]);
        }
    }

    private function addHostileMailEvidence(CharacterInfo $character, Collection $hostileEntityIds, Collection $evidence, array &$metrics): void
    {
        if ($hostileEntityIds->isEmpty()) {
            return;
        }

        $sentByHostiles = MailHeader::query()
            ->whereIn('from', $hostileEntityIds->all())
            ->whereHas('recipients', function ($query) use ($character) {
                $query->where('recipient_id', $character->character_id);
            });

        $sentToHostiles = MailHeader::query()
            ->where('from', $character->character_id)
            ->whereHas('recipients', function ($query) use ($hostileEntityIds) {
                $query->whereIn('recipient_id', $hostileEntityIds->all());
            });

        $receivedCount = (clone $sentByHostiles)->count();
        $sentCount = (clone $sentToHostiles)->count();

        $total = $receivedCount + $sentCount;
        $metrics['hostile_mail_count'] = $total;

        if ($total > 0) {
            $latestReceived = (clone $sentByHostiles)->with('sender')->orderByDesc('timestamp')->take(5)->get();
            $latestSent = (clone $sentToHostiles)->with('recipients.entity')->orderByDesc('timestamp')->take(5)->get();

            $direction = $sentCount > 0 && $receivedCount > 0 ? 'bidirectional' : ($sentCount > 0 ? 'outbound' : 'inbound');

            $evidence->push([
                'category' => 'hostile_mail',
                'score' => min(35, 15 + ($total * 3) + ($sentCount > 0 ? 5 : 0)),
                'title' => 'Mail interaction with hostile entities',
                'details' => sprintf('%s has %d mail interaction%s involving hostile or negative-contact entities. Direction: %s.',
                    $character->name,
                    $total,
                    $total === 1 ? '' : 's',
                    $direction
                ),
                'meta' => [
                    'direction' => $direction,
                    'received' => $receivedCount,
                    'sent' => $sentCount,
                    'latest_received' => $latestReceived->map(function ($mail) use ($character) {
                        return [
                            'character_id' => (int) $character->character_id,
                            'mail_id' => $mail->mail_id,
                            'from' => optional($mail->sender)->name ?: $mail->from,
                            'from_id' => $mail->from,
                            'subject' => $mail->subject,
                            'timestamp' => $this->dateTimeString($mail->timestamp),
                        ];
                    })->values()->all(),
                    'latest_sent' => $latestSent->map(function ($mail) use ($character) {
                        return [
                            'character_id' => (int) $character->character_id,
                            'mail_id' => $mail->mail_id,
                            'subject' => $mail->subject,
                            'timestamp' => $this->dateTimeString($mail->timestamp),
                            'recipients' => $mail->recipients->map(function ($recipient) {
                                return optional($recipient->entity)->name ?: $recipient->recipient_id;
                            })->values()->all(),
                        ];
                    })->values()->all(),
                ],
            ]);
        }
    }

    private function addHostileWalletEvidence(CharacterInfo $character, Collection $hostileEntityIds, Collection $evidence, array &$metrics): void
    {
        if ($hostileEntityIds->isEmpty()) {
            return;
        }

        $journalQuery = CharacterWalletJournal::query()
            ->where('character_id', $character->character_id)
            ->where(function ($query) use ($hostileEntityIds) {
                $query->whereIn('first_party_id', $hostileEntityIds->all())
                    ->orWhereIn('second_party_id', $hostileEntityIds->all());
            });

        $transactionQuery = CharacterWalletTransaction::query()
            ->where('character_id', $character->character_id)
            ->whereIn('client_id', $hostileEntityIds->all());

        $journal = (clone $journalQuery)->count();
        $transactions = (clone $transactionQuery)->count();

        $total = $journal + $transactions;
        $metrics['hostile_wallet_count'] = $total;

        if ($total > 0) {
            $latestJournal = (clone $journalQuery)->with('first_party', 'second_party')->orderByDesc('date')->take(5)->get();
            $latestTransactions = (clone $transactionQuery)->with('party', 'type')->orderByDesc('date')->take(5)->get();

            $outgoingJournal = (clone $journalQuery)
                ->where('first_party_id', $character->character_id)
                ->whereIn('second_party_id', $hostileEntityIds->all())
                ->count();
            $incomingJournal = (clone $journalQuery)
                ->where('second_party_id', $character->character_id)
                ->whereIn('first_party_id', $hostileEntityIds->all())
                ->count();
            $journalDirection = $outgoingJournal > 0 && $incomingJournal > 0 ? 'bidirectional' : ($outgoingJournal > 0 ? 'outbound' : 'inbound_or_indirect');

            if ($journal > 0) {
                $evidence->push([
                    'category' => 'hostile_wallet_direct',
                    'score' => min(40, 22 + ($journal * 4) + ($outgoingJournal > 0 ? 5 : 0)),
                    'title' => 'Direct wallet dealings with hostile entities',
                    'details' => sprintf('%s has %d wallet journal match%s involving hostile entities. Direction: %s. Direct donations, trades, contracts, or transfers are stronger concern than open-market activity.',
                        $character->name,
                        $journal,
                        $journal === 1 ? '' : 'es',
                        $journalDirection
                    ),
                    'meta' => [
                        'character_id' => (int) $character->character_id,
                        'direction' => $journalDirection,
                        'journal' => $journal,
                        'outgoing_journal' => $outgoingJournal,
                        'incoming_journal' => $incomingJournal,
                        'latest_journal' => $latestJournal->map(function ($entry) {
                            return [
                                'character_id' => (int) $entry->character_id,
                                'journal_id' => $entry->id,
                                'date' => $this->dateTimeString($entry->date),
                                'amount' => (float) $entry->amount,
                                'ref_type' => $entry->ref_type,
                                'first_party_id' => $entry->first_party_id,
                                'second_party_id' => $entry->second_party_id,
                                'first_party' => optional($entry->first_party)->name ?: $entry->first_party_id,
                                'second_party' => optional($entry->second_party)->name ?: $entry->second_party_id,
                                'reason' => $entry->reason,
                            ];
                        })->values()->all(),
                    ],
                ]);
            }

            if ($transactions > 0) {
                $buyCount = (clone $transactionQuery)->where('is_buy', true)->count();
                $sellCount = (clone $transactionQuery)->where('is_buy', false)->count();

                $evidence->push([
                    'category' => 'hostile_market_transaction',
                    'score' => min(8, 2 + $transactions),
                    'title' => 'Market transactions with hostile entities',
                    'details' => sprintf('%s has %d market transaction match%s involving hostile entities. This is a low concern contextual signal because open-market trades are often incidental.',
                        $character->name,
                        $transactions,
                        $transactions === 1 ? '' : 'es'
                    ),
                    'meta' => [
                        'character_id' => (int) $character->character_id,
                        'transactions' => $transactions,
                        'buy_count' => $buyCount,
                        'sell_count' => $sellCount,
                        'latest_transactions' => $latestTransactions->map(function ($transaction) {
                            return [
                                'character_id' => (int) $transaction->character_id,
                                'transaction_id' => $transaction->transaction_id,
                                'date' => $this->dateTimeString($transaction->date),
                                'total' => (float) $transaction->unit_price * (int) $transaction->quantity,
                                'unit_price' => (float) $transaction->unit_price,
                                'quantity' => (int) $transaction->quantity,
                                'is_buy' => (bool) $transaction->is_buy,
                                'client_id' => $transaction->client_id,
                                'party' => optional($transaction->party)->name ?: $transaction->client_id,
                                'type' => optional($transaction->type)->typeName,
                            ];
                        })->values()->all(),
                    ],
                ]);
            }
        }
    }

    private function addLoginEvidence(CharacterInfo $character, Collection $evidence, array &$metrics): void
    {
        $user = $character->user;

        if (!$user || !$user->id) {
            return;
        }

        $ips = UserLoginHistory::query()
            ->where('user_id', $user->id)
            ->pluck('source')
            ->filter()
            ->filter(function ($ip) {
                return $this->isPublicIp($ip);
            })
            ->unique()
            ->values();

        if ($ips->isEmpty()) {
            $evidence->push([
                'category' => 'no_login_history',
                'score' => 8,
                'title' => 'No SeAT login history for owning user',
                'details' => sprintf('%s is linked to a user, but that user has no recorded login IP history.', $character->name),
                'meta' => ['user_id' => $user->id],
            ]);

            return;
        }

        $sharedUserIds = UserLoginHistory::query()
            ->whereIn('source', $ips->all())
            ->where('user_id', '<>', $user->id)
            ->pluck('user_id')
            ->filter()
            ->unique()
            ->values();

        if ($sharedUserIds->isNotEmpty()) {
            $sharedUsers = $this->sharedIpUsers($sharedUserIds, $ips);
            $sharedUserIds = collect($sharedUsers)->pluck('user_id')->values();
            $metrics['shared_ip_user_count'] = $sharedUserIds->count();

            if ($sharedUserIds->isNotEmpty()) {
                $evidence->push([
                    'category' => 'shared_ip',
                    'score' => min(35, $this->settings->sharedIpScore() + (($sharedUserIds->count() - 1) * 5)),
                    'title' => 'Shares login IPs with other SeAT users',
                    'details' => sprintf('%s has used IP addresses also seen on %d other SeAT user account%s.',
                        $character->name,
                        $sharedUserIds->count(),
                        $sharedUserIds->count() === 1 ? '' : 's'
                    ),
                    'meta' => [
                        'user_ids' => $sharedUserIds->all(),
                        'ip_count' => $ips->count(),
                        'ips' => $ips->take(10)->values()->all(),
                        'shared_users' => $sharedUsers,
                    ],
                ]);
            }
        }

        $suspiciousIps = $this->ipIntelligence->suspiciousForIps($ips);
        $metrics['vpn_ip_count'] = $suspiciousIps->count();

        if ($suspiciousIps->isNotEmpty()) {
            $labels = $suspiciousIps->map(function ($record) {
                return sprintf('%s (%s)', $record->ip, $record->provider ?: 'manual');
            })->take(5)->implode(', ');

            $evidence->push([
                'category' => 'vpn_ip',
                'score' => min(45, $this->settings->vpnScore() + (($suspiciousIps->count() - 1) * 5)),
                'title' => 'Uses IPs marked as VPN/proxy/hosting',
                'details' => sprintf('%s has login IP intelligence matches: %s.', $character->name, $labels),
                'meta' => [
                    'ips' => $suspiciousIps->map(function ($record) {
                        return [
                            'ip' => $record->ip,
                            'risk_score' => (int) $record->risk_score,
                            'vpn' => (bool) $record->is_vpn,
                            'proxy' => (bool) $record->is_proxy,
                            'tor' => (bool) $record->is_tor,
                            'hosting' => (bool) $record->is_hosting,
                            'provider' => $record->provider,
                            'checked_at' => $this->dateTimeString($record->checked_at),
                        ];
                    })->values()->all(),
                ],
            ]);
        }
    }

    private function sharedIpUsers(Collection $sharedUserIds, Collection $ips): array
    {
        $sharedIpRows = UserLoginHistory::query()
            ->whereIn('user_id', $sharedUserIds->all())
            ->whereIn('source', $ips->all())
            ->get(['user_id', 'source', 'created_at'])
            ->groupBy('user_id');

        return User::query()
            ->whereIn('id', $sharedUserIds->all())
            ->orderBy('id')
            ->get()
            ->map(function ($user) use ($sharedIpRows) {
                $sharedIps = $sharedIpRows->get($user->id, collect());

                return [
                    'user_id' => (int) $user->id,
                    'user_name' => $user->name,
                    'active' => (bool) $user->active,
                    'admin' => (bool) $user->admin,
                    'shared_ips' => $sharedIps->pluck('source')->filter()->unique()->values()->all(),
                    'last_seen_at' => $this->dateTimeString(optional($sharedIps->sortByDesc('created_at')->first())->created_at),
                ];
            })
            ->values()
            ->all();
    }

    private function isPublicIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    private function addTokenEvidence(CharacterInfo $character, Collection $evidence): void
    {
        $token = RefreshToken::withTrashed()->where('character_id', $character->character_id)->first();

        if (!$token) {
            $evidence->push([
                'category' => 'missing_token',
                'score' => 20,
                'title' => 'Character is visible but has no SeAT token',
                'details' => sprintf('%s is in a monitored group but no refresh token was found for this character.', $character->name),
                'meta' => ['character_id' => (int) $character->character_id],
            ]);

            return;
        }

        if ($token->deleted_at) {
            $evidence->push([
                'category' => 'deleted_token',
                'score' => 22,
                'title' => 'Token has been deleted',
                'details' => sprintf('%s has a soft-deleted SeAT token. Removed tokens can indicate a character pulled access after joining.', $character->name),
                'meta' => [
                    'deleted_at' => $this->dateTimeString($token->deleted_at),
                    'user_id' => $token->user_id,
                ],
            ]);
        }

        if (blank($token->refresh_token)) {
            $evidence->push([
                'category' => 'missing_refresh_token',
                'score' => 18,
                'title' => 'Refresh token is missing',
                'details' => sprintf('%s has a token record but no refresh token value. SeAT cannot renew ESI access without it.', $character->name),
                'meta' => [
                    'expires_on' => $this->dateTimeString($token->expires_on),
                    'user_id' => $token->user_id,
                ],
            ]);
        }

        if ($token->updated_at && $token->updated_at->lt(now()->subDays(30))) {
            $evidence->push([
                'category' => 'stale_token',
                'score' => 10,
                'title' => 'Token has not refreshed recently',
                'details' => sprintf('%s has not had token activity since %s.',
                    $character->name,
                    $token->updated_at->toDateString()
                ),
                'meta' => [
                    'updated_at' => $this->dateTimeString($token->updated_at),
                    'days_stale' => $token->updated_at->diffInDays(now()),
                    'scopes_profile' => $token->scopes_profile,
                    'scope_count' => is_array($token->scopes) ? count($token->scopes) : 0,
                ],
            ]);
        }
    }

    private function addSparseDataEvidence(CharacterInfo $character, Collection $evidence): void
    {
        $counts = [
            'assets' => $character->assets()->count(),
            'contacts' => $character->contacts()->count(),
            'mails' => $character->mails()->count(),
            'wallet_journal' => $character->wallet_journal()->count(),
            'wallet_transactions' => $character->wallet_transactions()->count(),
            'skills' => $character->skills()->count(),
            'contracts' => $character->contracts()->count(),
            'orders' => $character->orders()->count(),
        ];

        $activityCount = $counts['assets'] + $counts['contacts'] + $counts['mails'] + $counts['wallet_journal'] + $counts['wallet_transactions'] + $counts['contracts'] + $counts['orders'];
        $birthday = $character->birthday ? Carbon::parse($character->birthday) : null;
        $ageDays = $birthday ? $birthday->diffInDays(now()) : null;

        if ($ageDays !== null && $ageDays > 30 && $activityCount <= 3) {
            $evidence->push([
                'category' => 'sparse_activity',
                'score' => 12,
                'title' => 'Sparse SeAT data footprint',
                'details' => sprintf('%s is %d days old but has very little activity visible in SeAT.', $character->name, $ageDays),
                'meta' => [
                    'age_days' => $ageDays,
                    'activity_count' => $activityCount,
                    'counts' => $counts,
                ],
            ]);
        }

        if ($counts['skills'] > 0 && $counts['skills'] <= 25) {
            $evidence->push([
                'category' => 'few_trained_skills',
                'score' => 8,
                'title' => 'Very few trained skill records',
                'details' => sprintf('%s has only %d trained skill rows visible in SeAT.', $character->name, $counts['skills']),
                'meta' => ['counts' => $counts],
            ]);
        }
    }

    private function addHistoryEvidence(CharacterInfo $character, Collection $evidence): void
    {
        $skillpoints = optional($character->skillpoints)->total_sp;

        if ($skillpoints !== null && (int) $skillpoints <= $this->settings->lowSkillpointThreshold()) {
            $evidence->push([
                'category' => 'low_skills',
                'score' => 15,
                'title' => 'Low skillpoint history',
                'details' => sprintf('%s has %s skillpoints, below the configured %s threshold.',
                    $character->name,
                    number_format((int) $skillpoints),
                    number_format($this->settings->lowSkillpointThreshold())
                ),
                'meta' => ['skillpoints' => (int) $skillpoints],
            ]);
        }

        if (!$character->birthday) {
            return;
        }

        $birthday = Carbon::parse($character->birthday);
        $ageDays = $birthday->diffInDays(now());

        if ($ageDays <= $this->settings->newCharacterDays()) {
            $evidence->push([
                'category' => 'new_character',
                'score' => 10,
                'title' => 'Recently created character',
                'details' => sprintf('%s is %d day%s old, within the configured %d-day watch window.',
                    $character->name,
                    $ageDays,
                    $ageDays === 1 ? '' : 's',
                    $this->settings->newCharacterDays()
                ),
                'meta' => ['birthday' => $birthday->toDateString(), 'age_days' => $ageDays],
            ]);
        }
    }

    private function dateTimeString($value): ?string
    {
        if (!$value) {
            return null;
        }

        return $value instanceof Carbon
            ? $value->toDateTimeString()
            : Carbon::parse($value)->toDateTimeString();
    }
}
