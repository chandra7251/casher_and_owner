<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangeTableStatusRequest;
use App\Models\AuditLog;
use App\Models\Table;
use Illuminate\Http\JsonResponse;

class TableStatusController
{
    public function update(ChangeTableStatusRequest $request, Table $table): JsonResponse
    {
        $data = $request->validated();
        abort_if($data['status'] === 'available' && $table->orders()->whereIn('status', ['draft', 'awaiting_payment'])->exists(), 422, 'Meja masih memiliki order aktif.');
        $before = $table->only(['status']);
        $table->update($data);
        $fresh = $table->fresh();
        AuditLog::create(['user_id' => $request->user()->id, 'action' => 'table_status_updated', 'auditable_type' => Table::class, 'auditable_id' => $fresh->id, 'before' => $before, 'after' => $fresh->only(['status'])]);

        return response()->json(['data' => $fresh]);
    }
}
