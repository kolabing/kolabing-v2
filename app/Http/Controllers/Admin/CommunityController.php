<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Services\Admin\AdminCommunityChatService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class CommunityController extends Controller
{
    public function __construct(
        private readonly AdminCommunityChatService $service,
    ) {}

    public function index(Request $request): View
    {
        $search = (string) $request->string('q');

        return view('admin.communities.index', [
            'communities' => $this->service->communitiesIndex($search !== '' ? $search : null),
            'filters' => ['q' => $search],
        ]);
    }

    public function show(Community $community): View
    {
        return view('admin.communities.show', $this->service->communityDetail($community));
    }
}
