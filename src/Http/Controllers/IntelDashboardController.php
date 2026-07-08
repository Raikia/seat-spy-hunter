<?php

namespace Raikia\SeatSpyHunter\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Raikia\SeatSpyHunter\Jobs\RefreshIntelReportsJob;
use Raikia\SeatSpyHunter\Models\CharacterIntelReport;
use Raikia\SeatSpyHunter\Models\FalsePositiveSuppression;
use Seat\Web\Http\Controllers\Controller;

class IntelDashboardController extends Controller
{
    public function index(Request $request)
    {
        $rating = $request->get('rating');
        $search = $request->get('search');
        $evidenceCategory = $request->get('evidence_category');
        $suppressed = $request->get('suppressed');
        $evidenceCategories = $this->evidenceCategories();

        $reports = CharacterIntelReport::query()
            ->with('evidence')
            ->with('suppressions')
            ->withCount(['evidence as total_evidence_count'])
            ->when($rating, function ($query) use ($rating) {
                $query->where('rating', $rating);
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
            ->orderByDesc('score')
            ->orderBy('character_name')
            ->get();

        $lastAnalyzedAt = CharacterIntelReport::query()->max('last_analyzed_at');
        $summary = [
            'total' => CharacterIntelReport::query()->count(),
            'critical' => CharacterIntelReport::query()->where('rating', 'critical')->count(),
            'high' => CharacterIntelReport::query()->where('rating', 'high')->count(),
            'watch' => CharacterIntelReport::query()->where('rating', 'watch')->count(),
            'clear' => CharacterIntelReport::query()->where('rating', 'clear')->count(),
            'last_analyzed_at' => $lastAnalyzedAt ? Carbon::parse($lastAnalyzedAt)->toDateTimeString() : null,
        ];

        return view('seat-spy-hunter::index', compact('reports', 'summary', 'rating', 'search', 'evidenceCategory', 'evidenceCategories', 'suppressed'));
    }

    private function evidenceCategories(): array
    {
        return [
            'hostile_employment_overlap' => 'Hostile Employment Overlap',
            'hostile_contacts' => 'Hostile Contacts',
            'hostile_mail' => 'Hostile Mail',
            'hostile_wallet_direct' => 'Direct Wallet Dealings',
            'hostile_market_transaction' => 'Market Transactions',
            'new_evidence_since_review' => 'New Evidence Since Review',
            'suppressed_signals' => 'Suppressed Evidence',
            'hostile_corporation_history' => 'Hostile Corp History',
            'hostile_asset_location' => 'Hostile Asset Location',
            'shared_ip' => 'Shared IP',
            'vpn_ip' => 'VPN / Proxy',
            'low_account_skillpoints' => 'Low Account SP',
            'no_pve_wallet_history' => 'No PvE Wallet',
            'limited_recent_wallet_activity' => 'Low Wallet Activity',
            'stable_wallet_balance' => 'Stable Wallet',
            'thin_seat_footprint' => 'Thin SeAT Footprint',
            'no_productive_footprint' => 'No PvE/Indy/Market',
            'age_skill_mismatch' => 'Age vs SP',
            'low_assets' => 'Low Assets',
            'corporation_history_churn' => 'Corp Churn',
            'quiet_corporation_history' => 'Quiet Corp History',
            'recent_neutral_corporation_history' => 'Recent Neutral Corp',
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

    public function refresh()
    {
        RefreshIntelReportsJob::dispatch();

        return redirect()->route('seat-spy-hunter.index')->with('success', 'Spy Hunter report refresh queued. The dashboard will update after the worker finishes processing it.');
    }

    public function updateReview(Request $request, CharacterIntelReport $report)
    {
        $data = $request->validate([
            'review_status' => 'required|in:new,reviewing,cleared,watchlisted,escalated',
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
