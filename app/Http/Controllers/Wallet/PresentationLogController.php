<?php

namespace App\Http\Controllers\Wallet;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PresentationLogController extends Controller
{
    public function index(Request $request): Response
    {
        $logs = $request->user()
            ->presentationLogs()
            ->with('credential:id,type,issuer')
            ->latest()
            ->paginate(20);

        return Inertia::render('wallet/history', [
            'logs' => $logs,
        ]);
    }
}
