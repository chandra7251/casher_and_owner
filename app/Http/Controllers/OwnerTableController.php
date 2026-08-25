<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateTableRequest;
use App\Models\AuditLog;
use App\Models\Table;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OwnerTableController extends Controller
{
    public function index(): Response|JsonResponse
    {
        $tables = Table::query()->orderBy('name')->get(['id', 'name', 'status']);
        if (request()->expectsJson()) {
            return response()->json(['data' => $tables]);
        }

        return Inertia::render('Owner/Tables', ['tables' => $tables]);
    }

    public function update(UpdateTableRequest $request, Table $table): JsonResponse
    {
        $updated = DB::transaction(function () use ($request, $table) {
            $before = $table->only(['name', 'status']);
            $table->update($request->validated());
            $fresh = $table->fresh();
            AuditLog::create([
                'user_id' => $request->user()->id,
                'action' => 'table_updated',
                'auditable_type' => Table::class,
                'auditable_id' => $fresh->id,
                'before' => $before,
                'after' => $fresh->only(['name', 'status']),
            ]);

            return $fresh;
        });

        return response()->json(['data' => $updated]);
    }
}
