<?php

declare(strict_types=1);

namespace App\Features\AgeVerification\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AgeVerificationController extends Controller
{
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        session(['adult_consent' => 'accepted']);

        if ($user = $request->user()) {
            $user->is_adult_verified = true;
            $user->save();
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back();
    }
}
