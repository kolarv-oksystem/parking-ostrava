<?php

namespace App\Http\Controllers;

use App\Exceptions\ParkingApiException;
use App\Http\Requests\DeviceActionRequest;
use App\Http\Requests\InitParkingRequest;
use App\Http\Requests\SetFreeRequest;
use App\Services\ParkingStateService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class ParkingStateController extends Controller
{
    /** @var ParkingStateService */
    private $parkingStateService;

    public function __construct(ParkingStateService $parkingStateService)
    {
        $this->parkingStateService = $parkingStateService;
    }

    public function status(Request $request): JsonResponse
    {
        $deviceId = $request->query('device_id');

        return response()->json($this->parkingStateService->getStatus(
            is_string($deviceId) ? $deviceId : null
        ));
    }

    public function decrement(DeviceActionRequest $request): JsonResponse
    {
        return $this->runAction(function () use ($request) {
            return $this->parkingStateService->arrive(
                $request->input('device_id'),
                $request->input('name'),
                (float) $request->input('latitude'),
                (float) $request->input('longitude'),
                'public'
            );
        });
    }

    public function increment(DeviceActionRequest $request): JsonResponse
    {
        return $this->runAction(function () use ($request) {
            return $this->parkingStateService->leave(
                $request->input('device_id'),
                $request->input('name'),
                (float) $request->input('latitude'),
                (float) $request->input('longitude'),
                'public'
            );
        });
    }

    public function events(Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', 15);

        return response()->json([
            'events' => $this->parkingStateService->recentEvents($limit),
        ]);
    }

    public function setFree(SetFreeRequest $request): JsonResponse
    {
        return $this->runAction(function () use ($request) {
            return $this->parkingStateService->setFreeSpots(
                (int) $request->input('free_spots'),
                $request->input('password'),
                'manual'
            );
        });
    }

    public function nightReset(Request $request): JsonResponse
    {
        return $this->runAction(function () use ($request) {
            $actor = 'cron';
            if ($request->header('User-Agent')) {
                $actor = 'cron:' . substr($request->header('User-Agent'), 0, 40);
            }

            return $this->parkingStateService->nightReset($actor);
        });
    }

    public function initOrReseed(InitParkingRequest $request): JsonResponse
    {
        return $this->runAction(function () use ($request) {
            return $this->parkingStateService->initOrReseed(
                (int) $request->input('capacity_total'),
                $request->input('manual_password'),
                $request->filled('free_spots') ? (int) $request->input('free_spots') : null,
                'admin:init'
            );
        });
    }

    private function runAction(callable $callback): JsonResponse
    {
        try {
            return response()->json($callback());
        } catch (ParkingApiException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => $e->getClientCode(),
            ], 422);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Došlo k neočekávané chybě.',
                'error' => app()->environment('production') ? null : $e->getMessage(),
            ], 500);
        }
    }
}
