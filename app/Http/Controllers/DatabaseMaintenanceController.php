<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Throwable;

class DatabaseMaintenanceController extends Controller
{
    /**
     * Spustí migrace aplikace (--force pro produkci).
     * Volitelně ?seed=1 nebo JSON { "seed": true } pro --seed.
     */
    public function migrate(Request $request): JsonResponse
    {
        $options = ['--force' => true];
        if ($request->boolean('seed')) {
            $options['--seed'] = true;
        }

        try {
            $exitCode = Artisan::call('migrate', $options);

            return response()->json([
                'exit_code' => $exitCode,
                'output' => trim(Artisan::output()),
            ], $exitCode === 0 ? 200 : 500);
        } catch (Throwable $e) {
            return response()->json([
                'exit_code' => 1,
                'message' => $e->getMessage(),
                'output' => trim(Artisan::output()),
            ], 500);
        }
    }

    /**
     * Stav migrací (migrate:status).
     */
    public function migrateStatus(): JsonResponse
    {
        try {
            $exitCode = Artisan::call('migrate:status');

            return response()->json([
                'exit_code' => $exitCode,
                'output' => trim(Artisan::output()),
            ], $exitCode === 0 ? 200 : 500);
        } catch (Throwable $e) {
            return response()->json([
                'exit_code' => 1,
                'message' => $e->getMessage(),
                'output' => trim(Artisan::output()),
            ], 500);
        }
    }

    /**
     * Vrátí poslední dávku migrací (výchozí step=1).
     * JSON body: { "step": 1 } volitelné.
     */
    public function migrateRollback(Request $request): JsonResponse
    {
        $step = (int) $request->input('step', 1);
        $step = max(1, min($step, 100));

        try {
            $exitCode = Artisan::call('migrate:rollback', [
                '--force' => true,
                '--step' => $step,
            ]);

            return response()->json([
                'exit_code' => $exitCode,
                'output' => trim(Artisan::output()),
            ], $exitCode === 0 ? 200 : 500);
        } catch (Throwable $e) {
            return response()->json([
                'exit_code' => 1,
                'message' => $e->getMessage(),
                'output' => trim(Artisan::output()),
            ], 500);
        }
    }

    /**
     * Vyčistí cache, config, view, route (užitečné po deployi / při Dingo apod.).
     */
    public function clearCaches(): JsonResponse
    {
        $commands = ['cache:clear', 'config:clear', 'view:clear', 'route:clear'];
        $results = [];

        try {
            foreach ($commands as $command) {
                $exitCode = Artisan::call($command);
                $results[$command] = [
                    'exit_code' => $exitCode,
                    'output' => trim(Artisan::output()),
                ];
            }

            $failed = collect($results)->contains(function ($r) {
                return $r['exit_code'] !== 0;
            });

            return response()->json(['commands' => $results], $failed ? 500 : 200);
        } catch (Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'commands' => $results,
            ], 500);
        }
    }
}
