<?php

namespace Tests\Support;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/** Stored UC38 browser fixtures. No production create workflow or response mapper is an oracle. */
final class ReviewReadBrowserFixtures
{
    public static function assertScenario(array $manifest, mixed $optIn): void
    {
        if (($manifest['scenario'] ?? null) !== 'ReviewRead' || $optIn !== '1') {
            throw new RuntimeException('Review browser requires its own scenario and explicit opt-in.');
        }
    }

    public static function allowsRequest(string $method, string $path): bool
    {
        return in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)
            || ($method === 'POST' && in_array($path, ['/login', '/logout'], true));
    }

    public static function create(Application $app, array $manifest, string $password): array
    {
        self::assertScenario($manifest, getenv('STAYHUB_TV3_AC_REVIEW_BROWSER_OPT_IN'));
        if (strlen($password) < 24 || DB::connection()->transactionLevel() !== 0) {
            throw new RuntimeException('Review browser needs a strong temporary password and committed fixture rows.');
        }
        $base = DB::transaction(function () use ($password) {
            $fixture = (new ReviewReadAcceptanceFixtures())->create();
            foreach ($fixture->actors as $actor) {
                DB::table('users')->where('id', $actor->id)->update(['password' => Hash::make($password)]);
            }
            return $fixture;
        });
        $actors = [];
        foreach ($base->actors as $label => $actor) {
            $actors[$label] = ['id' => $actor->id, 'email' => $actor->email,
                'role' => $actor->role->value, 'status' => $actor->status->value];
        }
        $orders = $summaries = [];
        foreach (['H_A', 'H_B', 'H_EMPTY', 'P_A1', 'P_A2', 'P_A0'] as $scope) {
            $orders[$scope] = ReviewReadAcceptanceFixtures::order($scope);
            $summaries[$scope] = ReviewReadAcceptanceFixtures::summary($scope);
        }
        return ['actors' => $actors, 'properties' => $base->properties, 'rows' => $base->rows,
            'orders' => $orders, 'summaries' => $summaries,
            'notice' => 'Committed synthetic UC38 data. No Booking integration or manual browser evidence.'];
    }

    public static function canonical(mixed $value): mixed
    {
        if (!is_array($value)) return $value;
        if (!array_is_list($value)) ksort($value);
        foreach ($value as &$item) $item = self::canonical($item);
        return $value;
    }

    /** Exact projection/order/summary and pagination, preserving raw ISO strings and nulls. */
    public static function verify(array $response, array $expected): void
    {
        if (array_keys($response) !== ['data', 'links', 'meta', 'summary']) throw new RuntimeException('Review HTTP envelope differs.');
        if (self::canonical($response['data']) !== self::canonical($expected['data'])) {
            throw new RuntimeException('Review HTTP projection/order differs from independent fixture oracle.');
        }
        if (self::canonical($response['summary']) !== self::canonical($expected['summary'])) {
            throw new RuntimeException('Review HTTP summary differs from independent fixture oracle.');
        }
        $page = $expected['page']; $size = $expected['size']; $total = $expected['total'];
        $last = max(1, (int) ceil($total / $size)); $count = count($expected['data']);
        $meta = ['current_page' => $page, 'from' => $count === 0 ? null : ($page - 1) * $size + 1,
            'last_page' => $last, 'path' => $expected['path'], 'per_page' => $size,
            'to' => $count === 0 ? null : ($page - 1) * $size + $count, 'total' => $total];
        if (array_keys($response['meta']) !== ['current_page', 'from', 'last_page', 'links', 'path', 'per_page', 'to', 'total']) {
            throw new RuntimeException('Review pagination meta envelope differs.');
        }
        foreach ($meta as $field => $value) {
            if (($response['meta'][$field] ?? null) !== $value) throw new RuntimeException('Review pagination differs: '.$field);
        }
        $prefix = $expected['path'].'?page=';
        $links = ['first' => $prefix.'1', 'last' => $prefix.$last,
            'prev' => $page > 1 ? $prefix.($page - 1) : null, 'next' => $page < $last ? $prefix.($page + 1) : null];
        if ($response['links'] !== $links || !is_array($response['meta']['links']) || count($response['meta']['links']) < 3) {
            throw new RuntimeException('Review pagination links differ.');
        }
        foreach ($response['meta']['links'] as $link) {
            if (($link['label'] ?? null) === '...') {
                if ($link !== ['url' => null, 'label' => '...', 'active' => false]) throw new RuntimeException('Invalid ellipsis.');
                continue;
            }
            if (array_keys($link) !== ['url', 'label', 'page', 'active'] || !is_string($link['label'])
                || !is_bool($link['active']) || ($link['page'] !== null && !is_int($link['page']))
                || $link['url'] !== ($link['page'] === null ? null : $prefix.$link['page'])
                || $link['active'] !== (ctype_digit($link['label']) && (int) $link['label'] === $page)) {
                throw new RuntimeException('Review page link projection differs.');
            }
        }
    }

    public static function audit(Application $app, array $manifest): array
    {
        $business = (new ReviewReadAcceptanceFixtures())->fingerprint();
        // Only session rows may change during this read-only scenario. Protect every other base table.
        unset($business['sessions']);
        return ['business' => $business, 'session_users' => DB::table('sessions')->whereNotNull('user_id')
            ->selectRaw('user_id, COUNT(*) AS count')->groupBy('user_id')->orderBy('user_id')->get()->toArray()];
    }

    public static function verifyAudit(array $before, array $after): void
    {
        if (($before['business'] ?? []) === [] || ($after['business'] ?? []) === []
            || self::canonical($before['business']) !== self::canonical($after['business'])
            || ($after['session_users'] ?? null) !== []) {
            throw new RuntimeException('Review browser changed protected data or retained authenticated sessions.');
        }
    }
}
