<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use App\Services\Admin\AdminCommunityChatService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class BusinessController extends Controller
{
    public function __construct(
        private readonly AdminCommunityChatService $service,
    ) {}

    public function index(Request $request): View
    {
        $search = (string) $request->string('q');

        return view('admin.businesses.index', [
            'businesses' => $this->service->businessesIndex($search !== '' ? $search : null),
            'filters' => ['q' => $search],
        ]);
    }

    public function show(Profile $business): View
    {
        abort_unless($business->isBusiness(), 404);

        return view('admin.businesses.show', $this->service->businessDetail($business));
    }
}
