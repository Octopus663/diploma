// Сценарій 1а: той самий легкий запит, але з автентифікацією Sanctum.
import http from 'k6/http';
import { TARGET, authHeaders, buildOptions, loginAll, ok, summary } from './lib/common.js';

export const options = buildOptions();

export function setup() {
    return loginAll();
}

export default function (data) {
    ok(http.get(`${TARGET}/api/me`, Object.assign({ tags: { name: 'me' } }, authHeaders(data))), 'me');
}

export const handleSummary = summary;
