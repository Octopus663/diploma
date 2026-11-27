// Затримки при однаковій інтенсивності: постійна частота запитів RATE за секунду
// (змішаний профіль). Показує, як кожен режим тримає однакове навантаження.
//   RATE=200 DURATION=60s
import { loginAll, makeSummary, rng } from './lib/common.js';
import { request } from './mixed.js';

const RATE = parseInt(__ENV.RATE || '100', 10);
const DURATION = __ENV.DURATION || '60s';

export const options = {
    scenarios: {
        main: {
            executor: 'constant-arrival-rate',
            rate: RATE,
            timeUnit: '1s',
            duration: DURATION,
            preAllocatedVUs: Math.max(20, RATE),
            maxVUs: Math.max(100, RATE * 5),
        },
    },
    discardResponseBodies: true,
    summaryTrendStats: ['avg', 'min', 'med', 'max', 'p(90)', 'p(95)', 'p(99)'],
    thresholds: {
        'http_reqs{scenario:main}': ['count>=0'],
        'http_req_duration{scenario:main}': ['max>=0'],
        'http_req_failed{scenario:main}': ['rate<0.01'],
        'dropped_iterations': ['count>=0'],
    },
};

export function setup() {
    return loginAll();
}

export default function (data) {
    request(data, rng());
}

export const handleSummary = makeSummary(DURATION);
