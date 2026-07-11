<?php

namespace Raikia\SeatSpyHunter\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Raikia\SeatSpyHunter\Jobs\RefreshIntelReportsJob;
use Raikia\SeatSpyHunter\Models\CharacterIntelReport;
use Raikia\SeatSpyHunter\Models\FalsePositiveSuppression;
use Raikia\SeatSpyHunter\Services\EvidenceScoreGuide;
use Seat\Web\Http\Controllers\Controller;

class IntelDashboardController extends Controller
{
    public function index(Request $request)
    {
        $rating = $request->get('rating');
        $search = $request->get('search');
        $evidenceCategory = $request->get('evidence_category');
        $suppressed = $request->get('suppressed');
        $reviewStatus = $request->get('review_status', 'active');
        $evidenceCategories = $this->evidenceCategories();

        $reports = CharacterIntelReport::query()
            ->with('evidence')
            ->with('suppressions')
            ->withCount(['evidence as total_evidence_count'])
            ->when($rating, function ($query) use ($rating) {
                $query->where('rating', $rating);
            })
            ->when($reviewStatus === 'active', function ($query) {
                $query->whereNotIn('review_status', ['cleared', 'permanently_cleared']);
            })
            ->when(in_array($reviewStatus, ['new', 'reviewing', 'watchlisted', 'concerned', 'escalated', 'cleared', 'permanently_cleared'], true), function ($query) use ($reviewStatus) {
                $query->where('review_status', $reviewStatus);
            })
            ->when($evidenceCategory && array_key_exists($evidenceCategory, $evidenceCategories), function ($query) use ($evidenceCategory) {
                $query->whereHas('evidence', function ($evidence) use ($evidenceCategory) {
                    $evidence->where('category', $evidenceCategory);
                });
            })
            ->when($suppressed === 'with', function ($query) {
                $query->whereHas('evidence', function ($evidence) {
                    $evidence->where('category', 'suppressed_signals');
                });
            })
            ->when($suppressed === 'without', function ($query) {
                $query->whereDoesntHave('evidence', function ($evidence) {
                    $evidence->where('category', 'suppressed_signals');
                });
            })
            ->when($search, function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('character_name', 'like', '%' . $search . '%')
                        ->orWhere('corporation_name', 'like', '%' . $search . '%')
                        ->orWhere('alliance_name', 'like', '%' . $search . '%');

                    if (Schema::hasColumn('seat_spy_hunter_character_reports', 'account_user_id')) {
                        $inner->orWhere('account_user_id', $search);
                    }

                    $inner->orWhere('user_id', $search);
                });
            })
            ->orderByRaw("case review_status when 'escalated' then 0 when 'concerned' then 1 when 'watchlisted' then 2 else 3 end")
            ->orderByDesc('score')
            ->orderBy('character_name')
            ->get();

        $lastAnalyzedAt = CharacterIntelReport::query()->max('last_analyzed_at');
        $activeReports = CharacterIntelReport::query()
            ->whereNotIn('review_status', ['cleared', 'permanently_cleared']);
        $summary = [
            'total' => CharacterIntelReport::query()->count(),
            'critical' => (clone $activeReports)->where('rating', 'critical')->count(),
            'high' => (clone $activeReports)->where('rating', 'high')->count(),
            'watchlisted' => CharacterIntelReport::query()->where('review_status', 'watchlisted')->count(),
            'concerned' => CharacterIntelReport::query()->where('review_status', 'concerned')->count(),
            'escalated' => CharacterIntelReport::query()->where('review_status', 'escalated')->count(),
            'last_analyzed_at' => $lastAnalyzedAt ? Carbon::parse($lastAnalyzedAt)->toDateTimeString() : null,
        ];

        return view('seat-spy-hunter::index', compact('reports', 'summary', 'rating', 'search', 'evidenceCategory', 'evidenceCategories', 'suppressed', 'reviewStatus'));
    }

    private function evidenceCategories(): array
    {
        return [
            'hostile_employment_overlap' => 'Hostile Employment Overlap',
            'hostile_contacts' => 'Hostile Contacts',
            'hostile_mail' => 'Hostile Mail',
            'hostile_wallet_direct' => 'Direct Wallet Dealings',
            'hostile_market_transaction' => 'Market Transactions',
            'hostile_killmail' => 'Pre-Join Kills vs Monitored',
            'prejoin_killmail_cluster' => 'Pre-Join Killmail Clustering',
            'prejoin_monitored_lossmail' => 'Pre-Join Monitored Loss',
            'hostile_contract' => 'Hostile Contracts',
            'risk_confidence' => 'Risk Confidence',
            'new_evidence_since_review' => 'New Evidence Since Review',
            'suppressed_signals' => 'Suppressed Evidence',
            'esi_coverage_health' => 'ESI Coverage Health',
            'hostile_corporation_history' => 'Hostile Corp History',
            'post_leave_hostile_join' => 'Post-Leave Hostile Join',
            'hostile_asset_location' => 'Hostile Asset Location',
            'shared_ip' => 'Shared IP',
            'vpn_ip' => 'VPN / Proxy',
            'low_account_skillpoints' => 'Low Account SP',
            'no_pve_wallet_history' => 'No PvE Wallet',
            'limited_recent_wallet_activity' => 'Low Wallet Activity',
            'stable_wallet_balance' => 'Stable Wallet',
            'thin_seat_footprint' => 'Thin SeAT Footprint',
            'no_productive_footprint' => 'No PvE/Indy/Market',
            'no_saved_fittings' => 'No Saved Fittings',
            'no_lossmails' => 'No Lossmails',
            'low_loyalty_points' => 'Low Loyalty Points',
            'age_skill_mismatch' => 'Age vs SP',
            'low_assets' => 'Low Assets',
            'low_asset_value' => 'Low Asset Value',
            'corporation_history_churn' => 'Corp Churn',
            'quiet_corporation_history' => 'Quiet Corp History',
            'recent_neutral_corporation_history' => 'Recent Outside Corp',
            'missing_token' => 'Missing Token',
            'deleted_token' => 'Deleted Token',
            'missing_refresh_token' => 'Missing Refresh Token',
            'stale_token' => 'Stale Token',
            'no_login_history' => 'No Login History',
            'sparse_activity' => 'Sparse Activity',
            'few_trained_skills' => 'Few Skills',
            'low_skills' => 'Low SP',
            'new_character' => 'New Character',
        ];
    }

    public function show(CharacterIntelReport $report)
    {
        $report->load('evidence', 'suppressions');

        return view('seat-spy-hunter::show', compact('report'));
    }

    public function help(EvidenceScoreGuide $guide)
    {
        return view('seat-spy-hunter::help', [
            'evidenceRows' => $guide->rows(),
            'ratings' => $guide->ratings(),
            'confidenceLevels' => $guide->confidenceLevels(),
        ]);
    }

    public function refresh()
    {
        RefreshIntelReportsJob::dispatch();

        return redirect()->route('seat-spy-hunter.index')->with('success', 'Spy Hunter report refresh queued. The dashboard will update after the worker finishes processing it.');
    }

    public function updateReview(Request $request, CharacterIntelReport $report)
    {
        $data = $request->validate([
            'review_status' => 'required|in:new,reviewing,cleared,permanently_cleared,watchlisted,concerned,escalated',
            'review_notes' => 'nullable|string|max:5000',
        ]);

        $report->update([
            'review_status' => $data['review_status'],
            'review_notes' => $data['review_notes'] ?? null,
            'reviewed_by' => optional($request->user())->id,
            'reviewed_at' => now(),
        ]);

        return redirect()->route('seat-spy-hunter.index')->with('success', 'Spy Hunter review updated.');
    }

    public function bulkUpdateReview(Request $request)
    {
        $data = $request->validate([
            'report_ids' => 'required|array|min:1',
            'report_ids.*' => 'integer|exists:seat_spy_hunter_character_reports,id',
            'review_status' => 'required|in:new,reviewing,cleared,permanently_cleared,watchlisted,concerned,escalated',
            'review_notes' => 'nullable|string|max:5000',
        ]);

        $updated = CharacterIntelReport::query()
            ->whereIn('id', $data['report_ids'])
            ->update([
                'review_status' => $data['review_status'],
                'review_notes' => $data['review_notes'] ?? null,
                'reviewed_by' => optional($request->user())->id,
                'reviewed_at' => now(),
            ]);

        return redirect()->back()->with('success', sprintf('Updated %d Spy Hunter review%s.', $updated, $updated === 1 ? '' : 's'));
    }

    public function storeSuppression(Request $request, CharacterIntelReport $report)
    {
        $data = $request->validate([
            'category' => 'required|string|max:64',
            'reason' => 'nullable|string|max:2000',
        ]);

        FalsePositiveSuppression::query()->updateOrCreate([
            'account_user_id' => $report->account_user_id ?: $report->user_id,
            'category' => $data['category'],
        ], [
            'reason' => $data['reason'] ?? null,
            'created_by' => optional($request->user())->id,
            'expires_at' => null,
        ]);

        return redirect()->route('seat-spy-hunter.index')->with('success', 'False-positive suppression saved. Refresh reports to re-score.');
    }

    public function destroySuppression(FalsePositiveSuppression $suppression)
    {
        $suppression->delete();

        return redirect()->route('seat-spy-hunter.index')->with('success', 'False-positive suppression removed. Refresh reports to re-score.');
    }
}
