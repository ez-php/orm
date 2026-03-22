<?php

declare(strict_types=1);

/**
 * Performance benchmark for EzPhp\Orm\QueryBuilder.
 *
 * Measures the overhead of constructing complex fluent query chains,
 * including SELECT clauses, WHERE conditions, JOINs, ORDER BY, and LIMIT.
 * No database connection is used — only SQL string construction is measured.
 *
 * Exits with code 1 if the per-build time exceeds the defined threshold,
 * allowing CI to detect performance regressions automatically.
 *
 * Usage:
 *   php benchmarks/query-builder.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use EzPhp\Database\Database;
use EzPhp\Orm\QueryBuilder;

const ITERATIONS = 5000;
const THRESHOLD_MS = 2.0; // per-build upper bound in milliseconds

// ── Setup a DatabaseInterface instance backed by SQLite in-memory ─────────────
// QueryBuilder construction and clause chaining are pure CPU operations.

$db = new Database('sqlite::memory:', '', '');

// ── Warm-up ───────────────────────────────────────────────────────────────────

$qb = new QueryBuilder($db, 'users');
$qb->select('id', 'name', 'email')
   ->where('active', '=', 1)
   ->where('age', '>', 18)
   ->orderBy('created_at', 'DESC')
   ->limit(25)
   ->offset(0);

// ── Benchmark ─────────────────────────────────────────────────────────────────

$start = hrtime(true);

for ($i = 0; $i < ITERATIONS; $i++) {
    $qb = (new QueryBuilder($db, 'users'))
        ->select('u.id', 'u.name', 'u.email', 'p.bio')
        ->leftJoin('profiles p', 'p.user_id', '=', 'u.id')
        ->where('u.active', '=', 1)
        ->where('u.age', '>', 18)
        ->whereNotNull('u.email')
        ->orderBy('u.created_at', 'DESC')
        ->limit(25)
        ->offset($i % 100);
}

$end = hrtime(true);

$totalMs = ($end - $start) / 1_000_000;
$perBuild = $totalMs / ITERATIONS;

echo sprintf(
    "QueryBuilder Build Benchmark\n" .
    "  Clauses per build    : select + leftJoin + 3×where + orderBy + limit + offset\n" .
    "  Iterations           : %d\n" .
    "  Total time           : %.2f ms\n" .
    "  Per build            : %.3f ms\n" .
    "  Threshold            : %.1f ms\n",
    ITERATIONS,
    $totalMs,
    $perBuild,
    THRESHOLD_MS,
);

if ($perBuild > THRESHOLD_MS) {
    echo sprintf(
        "FAIL: %.3f ms exceeds threshold of %.1f ms\n",
        $perBuild,
        THRESHOLD_MS,
    );
    exit(1);
}

echo "PASS\n";
exit(0);
