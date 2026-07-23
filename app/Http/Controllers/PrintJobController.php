<?php

namespace App\Http\Controllers;

use App\Models\PrintJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PrintJobController extends Controller
{
    public function next(): JsonResponse
    {
        $job = PrintJob::query()->where('status', 'queued')->orderBy('id')->first();
        if (!$job) return response()->json(['data' => null]);
        $job->update(['status' => 'printing']);
        return response()->json(['data' => $job->fresh()]);
    }

    public function update(Request $request, PrintJob $printJob): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['printed', 'failed'])],
            'failure_reason' => ['nullable', 'string', 'max:500'],
        ]);
        $printJob->update([
            'status' => $data['status'],
            'failure_reason' => $data['status'] === 'failed' ? ($data['failure_reason'] ?? 'Printer gagal.') : null,
            'printed_at' => $data['status'] === 'printed' ? now() : null,
        ]);
        return response()->json(['data' => $printJob->fresh()]);
    }

    public function retry(PrintJob $printJob): JsonResponse
    {
        abort_if($printJob->status !== 'failed', 422, 'Hanya print gagal yang dapat diulang.');
        $printJob->update(['status' => 'queued', 'failure_reason' => null]);
        return response()->json(['data' => $printJob->fresh()]);
    }
}
