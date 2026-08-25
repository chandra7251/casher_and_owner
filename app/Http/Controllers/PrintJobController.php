<?php

namespace App\Http\Controllers;

use App\Models\PrintJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PrintJobController extends Controller
{
    public function failed(): JsonResponse
    {
        return response()->json(['data' => PrintJob::query()->where('status', 'failed')->with('order:id,number')->latest()->limit(10)->get(['id', 'order_id', 'failure_reason'])]);
    }

    public function next(): JsonResponse
    {
        $job = DB::transaction(function () {
            $job = PrintJob::query()->where('status', 'queued')->orderBy('id')->lockForUpdate()->first();
            if (! $job) {
                return null;
            }
            $job->update(['status' => 'printing']);

            return $job;
        });
        if (! $job) {
            return response()->json(['data' => null]);
        }

        return response()->json(['data' => $job->fresh()]);
    }

    public function update(Request $request, PrintJob $printJob): JsonResponse
    {
        abort_if($printJob->status !== 'printing', 409, 'Print job belum diambil bridge.');
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
