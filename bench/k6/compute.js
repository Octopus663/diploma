// Сценарій 4: генерація, агрегування і серіалізація великого масиву (CPU-bound).
import http from 'k6/http';
import { TARGET, authHeaders, buildOptions, loginAll, ok, summary } from './lib/common.js';

export const options = buildOptions();

const ROWS = parseInt(__ENV.ROWS || '10000', 10);

export function setup() {
    return loginAll();
}

export default function (data) {
    ok(http.get(`${TARGET}/api/bench/compute/report?rows=${ROWS}&seed=42`, Object.assign({ tags: { name: 'compute' } }, authHeaders(data))), 'compute');
}

export const handleSummary = summary;
