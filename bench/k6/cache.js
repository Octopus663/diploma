// Сценарій 3: читання (80%) і запис (20%) агрегатів у Redis.
// Перед прогоном: php artisan bench:warm-cache
import http from 'k6/http';
import { TARGET, authHeaders, buildOptions, loginAll, ok, rng, summary } from './lib/common.js';

export const options = buildOptions();

const COUNTERPARTIES = parseInt(__ENV.COUNTERPARTIES || '5000', 10);

export function setup() {
    return loginAll();
}

export default function (data) {
    const next = rng();
    const id = next(COUNTERPARTIES);
    const params = authHeaders(data);

    if (next(10) <= 8) {
        ok(http.get(`${TARGET}/api/bench/cache/counterparties/${id}`, Object.assign({ tags: { name: 'cache-read' } }, params)), 'cache-read');
    } else {
        ok(http.post(`${TARGET}/api/bench/cache/counterparties/${id}/hit`, null, Object.assign({ tags: { name: 'cache-write' } }, params)), 'cache-write');
    }
}

export const handleSummary = summary;
