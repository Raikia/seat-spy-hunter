<?php

namespace Raikia\SeatSpyHunter\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Raikia\SeatSpyHunter\Models\CharacterIntelReport;
use Raikia\SeatSpyHunter\Models\FalsePositiveSuppression;
use Raikia\SeatSpyHunter\Services\IntelReportRefresher;
use Seat\Web\Http\Controllers\Controller;

class IntelDashboardController extends Controller
{
    public function index(Request $request)
    {
        $rating = $request->get('rating');
        $search = $request->get('search');

        $reports = CharacterIntelReport::query()
            ->with('evidence')
            ->with('suppressions')
            ->withCount(['evidence as total_evidence_count'])
            ->when($rating, function ($query) use ($rating) {
                $query->where('rating', $rating);
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
            ->paginate(50)
            ->appends($request->only('rating', 'search'));

        $lastAnalyzedAt = CharacterIntelReport::query()->max('last_analyzed_at');
        $summary = [
            'total' => CharacterIntelReport::query()->count(),
            'critical' => CharacterIntelReport::query()->where('rating', 'critical')->count(),
            'high' => CharacterIntelReport::query()->where('rating', 'high')->count(),
            'watch' => CharacterIntelReport::query()->where('rating', 'watch')->count(),
            'clear' => CharacterIntelReport::query()->where('rating', 'clear')->count(),
            'last_analyzed_at' => $lastAnalyzedAt ? Carbon::parse($lastAnalyzedAt)->toDateTimeString() : null,
        ];

        return view('seat-spy-hunter::index', compact('reports', 'summary', 'rating', 'search'));
    }

    public function show(CharacterIntelReport $report)
    {
        $report->load('evidence', 'suppressions');

        return view('seat-spy-hunter::show', compact('report'));
    }

    public function refresh(IntelReportRefresher $refresher)
    {
        $count = $refresher->refresh();

        return redirect()->route('seat-spy-hunter.index')->with('success', $count . ' account spy hunter report' . ($count === 1 ? '' : 's') . ' refreshed.');
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
