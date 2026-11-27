// Сценарій 2: складні вибірки через Eloquent ORM.
import http from 'k6/http';
import { TARGET, authHeaders, buildOptions, loginAll, ok, rng, summary } from './lib/common.js';

export const options = buildOptions();

const ORDERS = parseInt(__ENV.ORDERS || '100000', 10);
const COUNTERPARTIES = parseInt(__ENV.COUNTERPARTIES || '5000', 10);

export function setup() {
    return loginAll();
}

export default function (data) {
    const next = rng();
    const params = authHeaders(data);

    if (__ITER % 2 === 0) {
        ok(http.get(`${TARGET}/api/bench/db/orders/${next(ORDERS)}`, Object.assign({ tags: { name: 'db-order' } }, params)), 'db-order');
    } else {
        ok(http.get(`${TARGET}/api/bench/db/counterparties/${next(COUNTERPARTIES)}/orders?limit=20`, Object.assign({ tags: { name: 'db-counterparty' } }, params)), 'db-counterparty');
    }
}

export const handleSummary = summary;
