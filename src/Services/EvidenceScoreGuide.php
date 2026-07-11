<?php

namespace Raikia\SeatSpyHunter\Services;

class EvidenceScoreGuide
{
    public function __construct(private IntelSettings $settings)
    {
    }

    public function rows(): array
    {
        $hostileBase = $this->settings->hostileInteractionScore();

        return [
            $this->row('hostile_employment_overlap', 'Hostile Employment Overlap', '2-100', 'Employment history overlap between account characters and current members of configured hostile groups.', '100 if both left the same corp within 10 days and that happened in the last 2 years. Older close departures score 20. 45 for same-time overlap in the last 2 years. 25 when both were in the same corp during the last 2 years but not at the same time. Otherwise capped at 10.'),
            $this->row('hostile_contacts', 'Hostile Contacts', sprintf('%d-40', min(40, $hostileBase + 2)), 'Positive standings toward configured hostile entities or entities marked negative by monitored groups.', sprintf('Base %d, plus 5 per extra match and +2/+5/+10 based on positive standing strength. Capped at 40.', $hostileBase)),
            $this->row('hostile_mail', 'Hostile Mail', '8-38', 'EVE mail between account characters and hostile entities.', 'Base 15 + 3 per interaction, +5 for outbound mail. Recent mail within 180 days adds 6. Evidence older than 2 years is halved.'),
            $this->row('hostile_wallet_direct', 'Direct Wallet Dealings', '10-42', 'Non-market wallet journal activity involving hostile entities.', 'Base 22 + 4 per direct journal match, +5 for outbound transfers. Recent activity within 180 days adds 6. Evidence older than 2 years is halved. Market transactions are excluded here.'),
            $this->row('hostile_market_transaction', 'Market Transactions', '1-10', 'Market transactions with hostile entities.', 'Low-context signal: 2 + transaction count, freshness-adjusted, capped at 10. This is intentionally much lower than direct wallet dealings.'),
            $this->row('hostile_killmail', 'Pre-Join Kills vs Monitored', '10-45', 'Account characters appearing as attackers on killmails where the victim belonged to a monitored group, before the character or account joined a monitored group.', 'Recent kills within 2 years score 25 + 5 per match, capped at 45. Older kills score 10 + 3 per match, capped at 25.'),
            $this->row('prejoin_killmail_cluster', 'Pre-Join Killmail Clustering', '16-45', 'Repeated pre-join killmail activity where an account character appears with configured hostile attackers and the same non-NPC same-side group.', 'Only fires when the killmail has a configured hostile attacker, NPC parties are ignored, and the same player outside group appears across at least 2 days or at least 3 killmails before the character/account joined. Hostile clusters score 28 + 3 per killmail + 2 per active day, capped at 45. Non-monitored outside clusters score 16 + 2 per killmail + 1 per active day, capped at 30.'),
            $this->row('prejoin_monitored_lossmail', 'Pre-Join Monitored Loss', '10', 'Account characters killed by monitored group members before joining.', 'Low-severity context only; can suggest prior friction but is not suspicious by itself.'),
            $this->row('hostile_contract', 'Hostile Contracts', '6-38', 'Character contracts involving hostile entities.', 'Base 18 + 4 per directly assigned/accepted contract, +8 when any contract is within 2 years. Evidence older than 2 years is halved and capped at 18.'),
            $this->row('hostile_corporation_history', 'Hostile Corp History', '18-30', 'Corporation history directly matching hostile or monitored-negative entities.', '18 + 4 per extra history row, capped at 30. NPC corporations are ignored.'),
            $this->row('post_leave_hostile_join', 'Post-Leave Hostile Join', '25-55', 'A linked account character joined a hostile corporation within 30 days after leaving a monitored corporation.', '25 + 3 per match when the hostile join happens within 30 days, capped at 35. If any move happens within 7 days, score becomes 35 + 5 per fast move, capped at 55.'),
            $this->row('recent_neutral_corporation_history', 'Recent Outside Corp', '8-16', 'Recent corporation-history entries outside monitored corporations and known monitored-alliance member corporations.', '8 + 2 per extra recent outside-corp entry in the last 180 days, capped at 16. NPC corporations are ignored.'),
            $this->row('corporation_history_churn', 'Corp Churn', '10', 'Unique corporation count is high relative to account age, or at least 3 corp changes occurred in the last 180 days.', 'Fixed 10. Rule of thumb is roughly 2 unique corps per year of history.'),
            $this->row('quiet_corporation_history', 'Quiet Corp History', '0', 'No corporation-history movement in the last 180 days.', 'Context only. This intentionally adds no points.'),
            $this->row('hostile_asset_location', 'Hostile Asset Location', '12-20', 'Assets at locations whose IDs match configured hostile entities.', '10 + 2 per matching location, capped at 20.'),
            $this->row('shared_ip', 'Shared IP', '30', 'Public login IPs shared with other SeAT user accounts.', 'Fixed 30. Private, reserved, localhost, and Docker/internal IPs are skipped.'),
            $this->row('vpn_ip', 'VPN / Proxy', '20', 'Public login IPs marked by VPNAPI.io or manual intelligence as VPN, proxy, Tor, or hosting.', 'Fixed 20. VPNAPI.io results are cached indefinitely by IP.'),
            $this->row('missing_token', 'Missing Token', '20', 'A visible account character has no SeAT refresh token.', 'Fixed 20.'),
            $this->row('deleted_token', 'Deleted Token', '22', 'A character token exists but is soft-deleted.', 'Fixed 22.'),
            $this->row('missing_refresh_token', 'Missing Refresh Token', '18', 'A token record exists but has no refresh token value.', 'Fixed 18.'),
            $this->row('stale_token', 'Stale Token', '10', 'Token record has not updated in more than 30 days.', 'Fixed 10. This does not mean the current access token is expired; it means SeAT has not refreshed the token recently.'),
            $this->row('no_login_history', 'No Login History', '8', 'Owning SeAT user has no public login IP history.', 'Fixed 8.'),
            $this->row('low_account_skillpoints', 'Low Account SP', '15', 'All linked account characters total less than 10,000,000 SP.', 'Fixed 15.'),
            $this->row('low_skills', 'Low Character SP', '15', 'Individual character is below the configured low-SP threshold.', sprintf('Fixed 15. Current threshold: %s SP.', number_format($this->settings->lowSkillpointThreshold()))),
            $this->row('new_character', 'New Character', '10', 'Individual character is inside the configured new-character age window.', sprintf('Fixed 10. Current window: %d days.', $this->settings->newCharacterDays())),
            $this->row('sparse_activity', 'Sparse Activity', '12', 'Character is older than 30 days but has very little visible SeAT activity.', 'Fixed 12 when key activity rows total 3 or fewer.'),
            $this->row('few_trained_skills', 'Few Skills', '8', 'Character has 25 or fewer trained skill rows visible in SeAT.', 'Fixed 8.'),
            $this->row('no_pve_wallet_history', 'No PvE Wallet', '10', 'No bounty or mission wallet journal references across account characters.', 'Fixed 10.'),
            $this->row('limited_recent_wallet_activity', 'Low Wallet Activity', '10', 'Very little wallet journal or market transaction activity in the last 30 days.', 'Fixed 10 when the account has 2 or fewer recent wallet rows.'),
            $this->row('stable_wallet_balance', 'Stable Wallet', '8', 'Wallet balance range barely changes over recent samples.', 'Fixed 8 when at least 5 samples in 90 days move less than 1,000,000 ISK or under 5%.'),
            $this->row('thin_seat_footprint', 'Thin SeAT Footprint', '14', 'Older account history with very few rows across key SeAT datasets.', 'Fixed 14 when oldest linked character is at least 180 days old and key activity rows total 10 or fewer.'),
            $this->row('no_productive_footprint', 'No PvE/Indy/Market', '12', 'Little wallet, mining, industry, or market activity.', 'Fixed 12 when productive footprint is 3 rows or fewer.'),
            $this->row('no_saved_fittings', 'No Saved Fittings', '12', 'No saved fittings on any linked account character.', 'Fixed 12.'),
            $this->row('no_lossmails', 'No Lossmails', '12', 'No recorded lossmails across linked account characters.', 'Fixed 12.'),
            $this->row('low_loyalty_points', 'Low Loyalty Points', '8', 'No non-Paragon LP balance reaches 2,000 LP.', 'Fixed 8. Paragon is ignored.'),
            $this->row('age_skill_mismatch', 'Age vs SP', '12', 'Oldest linked character is at least 365 days old but total linked SP is below 15,000,000.', 'Fixed 12.'),
            $this->row('low_assets', 'Low Assets', '8-12', 'No or very few visible asset rows.', '12 for zero asset rows, 8 for 1-5 asset rows.'),
            $this->row('low_asset_value', 'Low Asset Value', '14-18', 'Estimated visible priced assets are below 500,000,000 ISK.', '18 below 100,000,000 ISK. 14 from 100,000,000 up to 500,000,000 ISK.'),
            $this->row('account_connectors', 'Connectors', '0', 'Shows external connector registrations such as Discord or TeamSpeak.', 'Context only. Adds no suspicion points.'),
            $this->row('account_characters', 'Account Characters', '0', 'Lists linked characters and which ones caused the account to be monitored.', 'Context only. Adds no suspicion points.'),
            $this->row('multi_character_account', 'Linked Characters Mitigation', '-8 to -20', 'Multiple characters on the same SeAT account reduce concern.', 'Subtracts 8 points for each linked character after the first, capped at 20.'),
            $this->row('esi_coverage_health', 'ESI Coverage Health', '0', 'Shows token and scope coverage for data Spy Hunter relies on.', 'Context only. Adds no suspicion points.'),
            $this->row('risk_confidence', 'Risk Confidence', '0', 'Explains how much trust to put in the score based on data coverage and visible activity.', 'Context only. Adds no suspicion points.'),
            $this->row('new_evidence_since_review', 'New Evidence Since Review', '0', 'Flags evidence categories that appeared after a prior review.', 'Context only. Adds no suspicion points.'),
            $this->row('suppressed_signals', 'Suppressed Evidence', '0', 'Shows categories suppressed as false positives.', 'Context only. Suppressed categories are removed before scoring.'),
        ];
    }

    public function ratings(): array
    {
        return [
            ['rating' => 'Clear', 'range' => '0-24', 'class' => 'success', 'description' => 'Low score after mitigations and suppressions. Still review the evidence if confidence is low.'],
            ['rating' => 'Watch', 'range' => '25-49', 'class' => 'info', 'description' => 'Enough weak or contextual signals to keep an eye on the account.'],
            ['rating' => 'High', 'range' => '50-79', 'class' => 'warning', 'description' => 'Multiple meaningful signals or one strong signal. Director review is recommended.'],
            ['rating' => 'Critical', 'range' => '80-100', 'class' => 'danger', 'description' => 'Very strong evidence, multiple high-value signals, or a 100-point close-departure employment overlap.'],
        ];
    }

    public function confidenceLevels(): array
    {
        return [
            ['level' => 'High', 'class' => 'success', 'rule' => 'At least 75% of linked characters have complete required ESI scope coverage and the account has at least 25 visible data rows.'],
            ['level' => 'Medium', 'class' => 'warning', 'rule' => 'At least 50% ESI coverage, or at least 10 visible data rows. The score is useful but may miss hidden activity.'],
            ['level' => 'Low', 'class' => 'danger', 'rule' => 'Less than 50% ESI coverage and fewer than 10 visible data rows. A quiet report should not be treated as clean by itself.'],
        ];
    }

    private function row(string $category, string $label, string $points, string $meaning, string $rule): array
    {
        return compact('category', 'label', 'points', 'meaning', 'rule');
    }
}
