"""
Мінімальний експортер метрик контейнерів для Prometheus.

Читає потокову статистику Docker API (той самий джерело, що й `docker stats`) для
контейнерів проєкту compose і віддає її у форматі Prometheus на :9417/metrics.
Обрано замість cAdvisor, бо cAdvisor не працює з containerd image store,
який Docker Desktop використовує за замовчуванням.

Лише стандартна бібліотека Python.
"""

import http.client
import json
import os
import socket
import threading
import time
import urllib.parse
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

DOCKER_SOCK = os.environ.get("DOCKER_SOCK", "/var/run/docker.sock")
PROJECT = os.environ.get("COMPOSE_PROJECT", "diploma")
PORT = int(os.environ.get("PORT", "9417"))
REFRESH = float(os.environ.get("REFRESH", "3"))

metrics = {}  # container_id -> dict
lock = threading.Lock()
watched = set()


class UnixConnection(http.client.HTTPConnection):
    def __init__(self, timeout=None):
        super().__init__("localhost", timeout=timeout)

    def connect(self):
        self.sock = socket.socket(socket.AF_UNIX, socket.SOCK_STREAM)
        self.sock.connect(DOCKER_SOCK)


def docker_get(path, timeout=10):
    conn = UnixConnection(timeout=timeout)
    conn.request("GET", path)
    return conn, conn.getresponse()


def list_containers():
    filters = urllib.parse.quote(json.dumps({"label": [f"com.docker.compose.project={PROJECT}"]}))
    conn, resp = docker_get(f"/containers/json?filters={filters}")
    data = json.loads(resp.read())
    conn.close()
    return data


def cpu_percent(stats):
    cpu, pre = stats.get("cpu_stats", {}), stats.get("precpu_stats", {})
    delta = cpu.get("cpu_usage", {}).get("total_usage", 0) - pre.get("cpu_usage", {}).get("total_usage", 0)
    system = cpu.get("system_cpu_usage", 0) - pre.get("system_cpu_usage", 0)
    online = cpu.get("online_cpus") or len(cpu.get("cpu_usage", {}).get("percpu_usage") or []) or 1
    if delta > 0 and system > 0:
        return delta / system * online * 100.0
    return 0.0


def memory_bytes(stats):
    mem = stats.get("memory_stats", {})
    usage = mem.get("usage", 0)
    st = mem.get("stats", {})
    # Як у docker stats: без файлового кешу.
    cache = st.get("inactive_file", st.get("total_inactive_file", 0))
    return max(0, usage - cache), mem.get("limit", 0)


def watch(container):
    cid = container["Id"]
    labels = container.get("Labels", {})
    service = labels.get("com.docker.compose.service", container["Names"][0].lstrip("/"))
    try:
        conn, resp = docker_get(f"/containers/{cid}/stats?stream=true", timeout=None)
        while True:
            line = resp.readline()
            if not line:
                break
            s = json.loads(line)
            used, limit = memory_bytes(s)
            nets = s.get("networks") or {}
            with lock:
                metrics[cid] = {
                    "service": service,
                    "name": container["Names"][0].lstrip("/"),
                    "cpu": cpu_percent(s),
                    "cpu_total": s.get("cpu_stats", {}).get("cpu_usage", {}).get("total_usage", 0) / 1e9,
                    "mem": used,
                    "mem_limit": limit,
                    "rx": sum(n.get("rx_bytes", 0) for n in nets.values()),
                    "tx": sum(n.get("tx_bytes", 0) for n in nets.values()),
                    "pids": s.get("pids_stats", {}).get("current", 0),
                    "ts": time.time(),
                }
    except Exception as exc:  # контейнер зупинено або помилка з'єднання
        print(f"stop watching {service}: {exc}", flush=True)
    finally:
        with lock:
            metrics.pop(cid, None)
            watched.discard(cid)


def discover():
    while True:
        try:
            for c in list_containers():
                if c["Id"] not in watched:
                    watched.add(c["Id"])
                    threading.Thread(target=watch, args=(c,), daemon=True).start()
        except Exception as exc:
            print(f"discover error: {exc}", flush=True)
        time.sleep(REFRESH)


def render():
    rows = []
    gauges = [
        ("container_cpu_percent", "cpu", "CPU, % одного ядра"),
        ("container_cpu_seconds_total", "cpu_total", "Сумарний час CPU, с"),
        ("container_memory_bytes", "mem", "Пам'ять без файлового кешу, байт"),
        ("container_memory_limit_bytes", "mem_limit", "Ліміт пам'яті, байт"),
        ("container_network_rx_bytes_total", "rx", "Отримано по мережі, байт"),
        ("container_network_tx_bytes_total", "tx", "Надіслано по мережі, байт"),
        ("container_pids", "pids", "Кількість процесів/потоків"),
    ]
    with lock:
        snapshot = [m for m in metrics.values() if time.time() - m["ts"] < 10]
    for name, key, help_text in gauges:
        kind = "counter" if name.endswith("_total") else "gauge"
        rows.append(f"# HELP {name} {help_text}")
        rows.append(f"# TYPE {name} {kind}")
        for m in snapshot:
            rows.append(f'{name}{{service="{m["service"]}",name="{m["name"]}"}} {m[key]}')
    return "\n".join(rows) + "\n"


class Handler(BaseHTTPRequestHandler):
    def do_GET(self):
        body = render().encode() if self.path.startswith("/metrics") else b"ok\n"
        self.send_response(200)
        self.send_header("Content-Type", "text/plain; version=0.0.4; charset=utf-8")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def log_message(self, *args):
        pass


if __name__ == "__main__":
    threading.Thread(target=discover, daemon=True).start()
    print(f"exporter on :{PORT}, project={PROJECT}", flush=True)
    ThreadingHTTPServer(("0.0.0.0", PORT), Handler).serve_forever()
