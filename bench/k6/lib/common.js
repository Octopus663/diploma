// Спільні налаштування сценаріїв k6.
//
// Змінні оточення:
//   TARGET    базова адреса застосунку (типово http://fpm:8000 у мережі compose)
//   VUS       кількість віртуальних користувачів у вимірюваній фазі (типово 10)
//   DURATION  тривалість вимірювання (типово 60s)
//   WARMUP    тривалість прогріву перед вимірюванням (типово 30s, 0s щоб вимкнути)
//   OUT       файл для JSON-підсумку (типово /results/summary.json)
//   LABEL     довільна мітка прогону (потрапляє в підсумок)
//   TOKENS    JSON-файл з токенами (php artisan bench:tokens); без нього токени беруться через API
import http from 'k6/http';
import { check, fail } from 'k6';

export const TARGET = __ENV.TARGET || 'http://fpm:8000';
export const VUS = parseInt(__ENV.VUS || '10', 10);
export const DURATION = __ENV.DURATION || '60s';
export const WARMUP = __ENV.WARMUP || '30s';
const OUT = __ENV.OUT || '/results/summary.json';

const BENCH_USERS = 20;
const TOKENS = __ENV.TOKENS ? JSON.parse(open(__ENV.TOKENS)) : null;

function seconds(value) {
    const m = String(value).match(/^(\d+(?:\.\d+)?)(ms|s|m|h)?$/);
    if (!m) return 0;
    const n = parseFloat(m[1]);
    return { ms: n / 1000, s: n, m: n * 60, h: n * 3600 }[m[2] || 's'];
}

/**
 * Два сценарії: прогрів (результати відкидаються) і вимірювання з постійною кількістю VU.
 * Метрики вимірювальної фази мають тег scenario=main.
 */
export function buildOptions(extraThresholds = {}) {
    const scenarios = {};
    const warmup = seconds(WARMUP);

    if (warmup > 0) {
        scenarios.warmup = {
            executor: 'constant-vus',
            vus: Math.min(VUS, 10),
            duration: WARMUP,
            gracefulStop: '5s',
            tags: { phase: 'warmup' },
        };
    }

    scenarios.main = {
        executor: 'constant-vus',
        vus: VUS,
        duration: DURATION,
        startTime: warmup > 0 ? `${warmup + 5}s` : '0s',
        gracefulStop: '10s',
        tags: { phase: 'main' },
    };

    return {
        scenarios,
        discardResponseBodies: true,
        summaryTrendStats: ['avg', 'min', 'med', 'max', 'p(90)', 'p(95)', 'p(99)'],
        thresholds: Object.assign({
            // Ці записи потрібні, щоб метрики фази main потрапили в підсумок.
            'http_reqs{scenario:main}': ['count>=0'],
            'http_req_duration{scenario:main}': ['max>=0'],
            'http_req_failed{scenario:main}': ['rate<0.01'],
            'checks{scenario:main}': ['rate>=0'],
            'iterations{scenario:main}': ['count>=0'],
        }, extraThresholds),
    };
}

/**
 * Отримати токени Sanctum для тестових користувачів bench01..bench20.
 */
export function loginAll() {
    if (TOKENS) {
        return { tokens: TOKENS.tokens };
    }
    const tokens = [];
    for (let i = 1; i <= BENCH_USERS; i++) {
        const email = `bench${String(i).padStart(2, '0')}@diploma.test`;
        const res = http.post(`${TARGET}/api/auth/token`, { email, password: 'password', device_name: 'k6' }, {
            headers: { Accept: 'application/json' },
            responseType: 'text',
            tags: { phase: 'setup' },
        });
        if (res.status !== 201) {
            fail(`Не вдалося отримати токен для ${email}: ${res.status} ${res.body}`);
        }
        tokens.push(res.json('token'));
    }
    return { tokens };
}

export function authHeaders(data) {
    const token = data.tokens[(__VU - 1) % data.tokens.length];
    return { headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' } };
}

/**
 * Детермінований генератор псевдовипадкових чисел (однакова послідовність запитів
 * для кожного режиму при тій самій кількості VU).
 */
export function rng() {
    let state = (__VU * 1000003 + __ITER * 7919) >>> 0;
    return function next(max) {
        state = (Math.imul(state, 1103515245) + 12345) >>> 0;
        return 1 + (state % max);
    };
}

export function ok(res, name) {
    return check(res, { [`${name}: status 2xx`]: (r) => r.status >= 200 && r.status < 300 });
}

/**
 * Підсумок: повний JSON для аналізу + короткий текст у консоль.
 * duration: тривалість вимірювальної фази (для розрахунку RPS).
 */
export function makeSummary(duration) {
    return (data) => summarize(data, duration);
}

export function summary(data) {
    return summarize(data, DURATION);
}

function summarize(data, duration) {
    const main = (name) => data.metrics[`${name}{scenario:main}`];
    const reqs = main('http_reqs');
    const dur = main('http_req_duration');
    const failed = main('http_req_failed');
    const rps = reqs ? reqs.values.count / seconds(duration) : 0;

    data.diploma = {
        label: __ENV.LABEL || '',
        target: TARGET,
        vus: VUS,
        duration_s: seconds(duration),
        warmup_s: seconds(WARMUP),
        requests: reqs ? reqs.values.count : 0,
        rps,
        latency_ms: dur ? dur.values : {},
        error_rate: failed ? failed.values.rate : 0,
    };

    const line = `${__ENV.LABEL || ''} VUS=${VUS} RPS=${rps.toFixed(1)} `
        + `p50=${dur ? dur.values.med.toFixed(1) : '-'}ms p95=${dur ? dur.values['p(95)'].toFixed(1) : '-'}ms `
        + `p99=${dur ? dur.values['p(99)'].toFixed(1) : '-'}ms errors=${((failed ? failed.values.rate : 0) * 100).toFixed(2)}%\n`;

    return { [OUT]: JSON.stringify(data, null, 2), stdout: line };
}
