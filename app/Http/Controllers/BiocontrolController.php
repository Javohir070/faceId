<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Services\Hikvision\HikvisionDeviceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class BiocontrolController extends Controller
{
    public function __construct(
        private readonly HikvisionDeviceService $deviceService,
    ) {}

    public function index(Device $device): JsonResponse
    {
        if (! $device->hasIsapiCredentials()) {
            abort(503, 'Qurilma ISAPI ma’lumotlari to‘liq emas.');
        }

        try {
            $info = $this->deviceService->getDeviceInfo($device);

            return response()->json([
                'status' => 'ok',
                'device_id' => $device->id,
                'device_name' => $device->name,
                'isapi_base_url' => $device->isapiBaseUrl(),
                'info' => $info,
            ]);
        } catch (\Throwable $exception) {
            Log::error('Biocontrol diagnostika xatoligi', [
                'device_id' => $device->id,
                'message' => $exception->getMessage(),
            ]);

            abort(502, 'Qurilma bilan bog‘lanib bo‘lmadi.');
        }
    }

    public function httpHost(Device $device): JsonResponse
    {
        if (! $device->hasIsapiCredentials()) {
            abort(503, 'Qurilma ISAPI ma’lumotlari to‘liq emas.');
        }

        try {
            $configured = $this->deviceService->setupWebhook($device);

            return response()->json([
                'status' => $configured ? 'ok' : 'failed',
                'device_id' => $device->id,
                'webhook_url' => config('hikvision.webhook_url') ?: url(config('hikvision.webhook_path')),
            ], $configured ? 200 : 502);
        } catch (\Throwable $exception) {
            Log::error('Biocontrol webhook sozlash xatoligi', [
                'device_id' => $device->id,
                'message' => $exception->getMessage(),
            ]);

            abort(502, 'Webhook sozlab bo‘lmadi.');
        }
    }
}
