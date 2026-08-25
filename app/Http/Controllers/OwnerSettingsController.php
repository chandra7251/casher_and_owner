<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCafeSettingRequest;
use App\Models\CafeSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class OwnerSettingsController extends Controller
{
    public function show(): Response|JsonResponse
    {
        $settings = CafeSetting::query()->firstOrFail();
        if (request()->expectsJson()) {
            return response()->json(['data' => $settings]);
        }

        return Inertia::render('Owner/Settings', ['settings' => $settings]);
    }

    public function update(UpdateCafeSettingRequest $request): JsonResponse
    {
        $settings = CafeSetting::query()->firstOrFail();
        $data = $request->validated();
        if ($request->hasFile('logo')) {
            if ($settings->logo_path) {
                Storage::disk('public')->delete($settings->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store('cafe', 'public');
        }
        unset($data['logo']);
        $settings->update($data);

        return response()->json(['data' => $settings->fresh()]);
    }
}
