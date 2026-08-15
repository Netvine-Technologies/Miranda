<?php

namespace App\Http\Controllers;

use App\Models\ZoomCallLog;
use App\Services\ZoomPhone\ZoomPhoneService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ZoomPhoneController extends Controller
{
    public function index(Request $request, ZoomPhoneService $zoomPhoneService): View
    {
        return view('zoom-phone.index', [
            'smartEmbedEnabled' => (bool) config('zoom-phone.smart_embed_enabled'),
            'smartEmbedUrl' => (string) config('zoom-phone.smart_embed_url'),
            'apiConfigured' => $zoomPhoneService->configured(),
            'dialNumber' => trim((string) $request->query('number', '')),
            'callLogs' => ZoomCallLog::query()
                ->with('businessLead:id,name')
                ->latest('occurred_at')
                ->latest('id')
                ->paginate(50),
        ]);
    }

    public function storeEvent(Request $request, ZoomPhoneService $zoomPhoneService): JsonResponse
    {
        $data = $request->validate([
            'event' => ['required', 'array'],
            'event.type' => ['required', 'in:zp-call-log-completed-event,zp-save-log-event'],
            'event.data' => ['required', 'array'],
        ]);

        $callLog = $zoomPhoneService->storeSmartEmbedEvent($data['event'], $request->user()?->id);

        return response()->json([
            'saved' => true,
            'call_log_id' => $callLog->id,
            'business_lead_id' => $callLog->business_lead_id,
        ]);
    }

    public function sync(ZoomPhoneService $zoomPhoneService): RedirectResponse
    {
        if (! $zoomPhoneService->configured()) {
            return back()->withErrors(['zoom' => 'Add the Zoom Phone OAuth credentials before syncing call history.']);
        }

        try {
            $result = $zoomPhoneService->sync();
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withErrors(['zoom' => 'Zoom sync failed: '.$exception->getMessage()]);
        }

        return back()->with('status', "Zoom history synced: {$result['saved']} calls saved, {$result['matched']} matched to leads.");
    }
}
