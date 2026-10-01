<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Keyset ("cursor") pagination — the kind doc 05 requires and the API did not have.
 *
 * There was no pagination at all. Most list endpoints were capped at a fixed 20–50 with no way to
 * reach anything older, and three were capped at nothing: `GET /jobs` loaded every job a customer
 * had ever created with four relations eager-loaded, and `GET /jobs/{job}/messages` returned a
 * whole thread with its media on every open, on 3G.
 *
 * Cursor, not offset, and doc 05 says why: offset pagination over a feed that is still growing
 * shows the same row on two pages. A keyset cursor names a position — "everything strictly before
 * this row" — so a page is stable no matter what arrives while the reader is scrolling.
 *
 * The cursor is `(timestamp, id)`, not `id` alone: UUIDv7 ids are time-ordered but the rows here
 * are ordered by an explicit timestamp column, and two rows can share a timestamp to the
 * microsecond. Compared as a row value, which Postgres does natively and an index can serve:
 *
 *     WHERE (created_at, id) < (?, ?)
 *
 * It is base64url of `<timestamp>|<id>`, which is opaque on purpose: the encoding is ours to change
 * and nothing should parse it. A malformed or stale cursor is treated as absent rather than an
 * error — a client that kept one across a deploy gets the first page, not a 422 in the middle of a
 * scroll.
 */
final class Cursor
{
    /** The most rows an endpoint will ever return in one page, whatever the caller asks for. */
    public const MAX_LIMIT = 200;

    public static function encode(string $timestamp, string $id): string
    {
        return rtrim(strtr(base64_encode($timestamp.'|'.$id), '+/', '-_'), '=');
    }

    /**
     * @return array{0: string, 1: string}|null the (timestamp, id) pair, or null if there is
     *                                          nothing usable to decode
     */
    public static function decode(?string $raw): ?array
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $decoded = base64_decode(strtr($raw, '-_', '+/'), true);

        if ($decoded === false || ! str_contains($decoded, '|')) {
            return null;
        }

        [$timestamp, $id] = explode('|', $decoded, 2);

        // Both halves are VALIDATED, not just non-empty. They go into a row comparison with
        // explicit `::timestamptz` and `::uuid` casts, so anything that is not those two things
        // would be a Postgres cast error — a 500 in the middle of someone's scroll — rather than
        // the "treated as absent" this promises. A cursor arrives from a client and may be stale,
        // truncated by a URL, or simply made up.
        if (! Str::isUuid($id) || strtotime($timestamp) === false) {
            return null;
        }

        return [$timestamp, $id];
    }

    /**
     * Narrow a query to the rows strictly BEFORE the cursor, in (timestamp, id) order.
     *
     * The SQL fragment is a compile-time constant and the column name comes from the caller's own
     * code, never a request; the two values are bound (rule #7).
     *
     * The casts are not decoration. PDO sends both parameters as untyped text, and inside a ROW
     * comparison Postgres has no single column to infer each side from — it answers "could not
     * determine data type of parameter" rather than guessing. Naming the types settles it, and they
     * are the same everywhere this is used: every table here has a `timestamptz` ordering column
     * and a `uuid` primary key.
     *
     * @param  Builder<covariant Model>|QueryBuilder  $query
     */
    public static function applyBefore(Builder|QueryBuilder $query, string $column, ?string $raw, string $idColumn = 'id'): void
    {
        $pair = self::decode($raw);

        if ($pair === null) {
            return;
        }

        $query->whereRaw("({$column}, {$idColumn}) < (?::timestamptz, ?::uuid)", $pair);
    }

    /**
     * How many rows to return: what was asked for, clamped into [1, MAX_LIMIT], falling back to
     * the endpoint's own default when nothing (or nonsense) was asked for.
     *
     * Additive-only (rule #4) depends on this being lenient: `?limit=banana` has to mean "the
     * default", because an older build that has never heard of `limit` must keep working exactly as
     * it did, and a newer one must not be able to break itself.
     */
    public static function limit(mixed $requested, int $default): int
    {
        if (! is_numeric($requested)) {
            return min($default, self::MAX_LIMIT);
        }

        return max(1, min((int) $requested, self::MAX_LIMIT));
    }

    /**
     * A cursor for a list ordered by a BUCKET before its timestamp.
     *
     * `/provider/site-visits` is the case: scheduled visits come before completed ones, and only
     * then does the date decide. A two-part cursor cannot page that — a keyset comparison has to
     * compare exactly what the ordering compares, or the boundary between "the last scheduled
     * visit" and "the first completed one" falls in the wrong place and a page is silently skipped.
     * So the bucket travels in the tuple.
     */
    public static function encodeBucketed(int $bucket, string $timestamp, string $id): string
    {
        return rtrim(strtr(base64_encode($bucket.'|'.$timestamp.'|'.$id), '+/', '-_'), '=');
    }

    /**
     * Narrow a query to the rows strictly BEFORE the cursor, in (bucket, timestamp, id) order.
     *
     * `$bucketExpression` is the SAME expression the ordering uses, passed by the caller's own code
     * and never from a request; all three values are bound (rule #7). Note the direction: these
     * lists are ASCENDING (soonest first), so "before" here means `>`, the rows still to come.
     *
     * @param  Builder<covariant Model>|QueryBuilder  $query
     */
    public static function applyAfterBucketed(
        Builder|QueryBuilder $query,
        string $bucketExpression,
        string $column,
        ?string $raw,
        string $idColumn = 'id',
    ): void {
        $parts = self::decodeBucketed($raw);

        if ($parts === null) {
            return;
        }

        $query->whereRaw(
            "({$bucketExpression}, {$column}, {$idColumn}) > (?::int, ?::timestamptz, ?::uuid)",
            $parts,
        );
    }

    /**
     * @return array{0: int, 1: string, 2: string}|null
     */
    public static function decodeBucketed(?string $raw): ?array
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $decoded = base64_decode(strtr($raw, '-_', '+/'), true);

        if ($decoded === false) {
            return null;
        }

        $parts = explode('|', $decoded, 3);

        if (count($parts) !== 3) {
            return null;
        }

        [$bucket, $timestamp, $id] = $parts;

        // Validated for the same reason the two-part cursor is: these go into a row comparison with
        // explicit casts, so anything else would be a Postgres error mid-scroll rather than the
        // "treated as absent" this promises.
        if (! ctype_digit($bucket) || ! Str::isUuid($id) || strtotime($timestamp) === false) {
            return null;
        }

        return [(int) $bucket, $timestamp, $id];
    }
}
