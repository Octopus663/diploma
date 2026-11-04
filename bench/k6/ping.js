// Сценарій 1: легкий JSON без БД і кешу (накладні витрати фреймворку).
import http from 'k6/http';
import { TARGET, buildOptions, ok, summary } from './lib/common.js';

export const options = buildOptions();

export default function () {
    ok(http.get(`${TARGET}/api/ping`, { tags: { name: 'ping' } }), 'ping');
}

export const handleSummary = summary;
