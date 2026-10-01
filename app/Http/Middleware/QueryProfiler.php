<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class QueryProfiler
{
    protected int $queryCount = 0;

    protected float $queryTimeMs = 0.0;

    protected array $slowQueries = [];

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('app.query_profile.enabled')) {
            return $next($request);
        }

        $slowThreshold = (float) config('app.query_profile.slow_ms', 100);
        $startedAt = microtime(true);

        DB::listen(function ($query) use ($slowThreshold) {
            $this->queryCount++;
            $this->queryTimeMs += $query->time;

            if ($query->time >= $slowThreshold) {
                $this->slowQueries[] = [
                    'ms' => $query->time,
                    'sql' => $query->sql,
                ];
            }
        });

        $response = $next($request);
        $totalMs = (microtime(true) - $startedAt) * 1000;

        $response->headers->set('X-Query-Count', (string) $this->queryCount);
        $response->headers->set('X-Query-Time-Ms', number_format($this->queryTimeMs, 1, '.', ''));
        $response->headers->set('X-Response-Time-Ms', number_format($totalMs, 1, '.', ''));
        $response->headers->set(
            'Access-Control-Expose-Headers',
            'X-Query-Count, X-Query-Time-Ms, X-Response-Time-Ms'
        );

        Log::info('query-profile', [
            'method' => $request->method(),
            'path' => $request->path(),
            'status' => $response->getStatusCode(),
            'queries' => $this->queryCount,
            'db_ms' => round($this->queryTimeMs, 1),
            'total_ms' => round($totalMs, 1),
            'slow' => $this->slowQueries,
        ]);

        return $response;
    }
}
