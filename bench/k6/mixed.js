// Змішаний профіль, наближений до реальної роботи системи:
// 40% ping, 30% БД, 20% кеш, 10% обчислення.
import http from 'k6/http';
import { TARGET, authHeaders, buildOptions, loginAll, ok, rng, summary } from './lib/common.js';

export const options = buildOptions();

export function setup() {
    return loginAll();
}

export function request(data, next) {
    const params = authHeaders(data);
    const roll = next(100);

    if (roll <= 40) {
        return ok(http.get(`${TARGET}/api/ping`, { tags: { name: 'ping' } }), 'ping');
    }
    if (roll <= 55) {
        return ok(http.get(`${TARGET}/api/bench/db/orders/${next(100000)}`, Object.assign({ tags: { name: 'db-order' } }, params)), 'db-order');
    }
    if (roll <= 70) {
        return ok(http.get(`${TARGET}/api/bench/db/counterparties/${next(5000)}/orders?limit=20`, Object.assign({ tags: { name: 'db-counterparty' } }, params)), 'db-counterparty');
    }
    if (roll <= 86) {
        return ok(http.get(`${TARGET}/api/bench/cache/counterparties/${next(5000)}`, Object.assign({ tags: { name: 'cache-read' } }, params)), 'cache-read');
    }
    if (roll <= 90) {
        return ok(http.post(`${TARGET}/api/bench/cache/counterparties/${next(5000)}/hit`, null, Object.assign({ tags: { name: 'cache-write' } }, params)), 'cache-write');
    }
    return ok(http.get(`${TARGET}/api/bench/compute/report?rows=10000&seed=42`, Object.assign({ tags: { name: 'compute' } }, params)), 'compute');
}

export default function (data) {
    request(data, rng());
}

export const handleSummary = summary;
