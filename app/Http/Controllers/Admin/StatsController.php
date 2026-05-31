<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\PlatformStatsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class StatsController extends Controller
{
    public function __construct(private readonly PlatformStatsService $stats) {}

    public function index(Request $request): View
    {
        $range = (string) $request->string('range', '30d');

        if (! in_array($range, PlatformStatsService::RANGES, true)) {
            $range = '30d';
        }

        return view('admin.stats.index', [
            'summary' => $this->stats->summary($range),
            'ranges' => PlatformStatsService::RANGES,
            'range' => $range,
        ]);
    }
}
