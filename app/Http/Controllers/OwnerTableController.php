<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateTableRequest;
use App\Models\Table;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class OwnerTableController extends Controller
{
    public function index(): Response|JsonResponse
    {
        $tables = Table::query()->orderBy('name')->get(['id', 'name', 'status']);
        if (request()->expectsJson()) return response()->json(['data' => $tables]);
        return Inertia::render('Owner/Tables', ['tables' => $tables]);
    }

    public function update(UpdateTableRequest $request, Table $table): JsonResponse
    {
        $table->update($request->validated());
        return response()->json(['data' => $table->fresh()]);
    }
}
