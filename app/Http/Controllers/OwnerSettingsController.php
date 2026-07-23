<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCafeSettingRequest;
use App\Models\CafeSetting;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class OwnerSettingsController extends Controller
{
    public function show(): Response|JsonResponse
    {
        $settings = CafeSetting::query()->firstOrFail();
        if (request()->expectsJson()) return response()->json(['data' => $settings]);
        return Inertia::render('Owner/Settings', ['settings' => $settings]);
    }

    public function update(UpdateCafeSettingRequest $request): JsonResponse
    {
        $settings = CafeSetting::query()->firstOrFail();
        $settings->update($request->validated());
        return response()->json(['data' => $settings->fresh()]);
    }
}
