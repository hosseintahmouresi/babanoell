"""Disposable seven-service HTTP stack. Never reads deployed local configuration."""
from __future__ import annotations

import base64
import contextlib
import getpass
import hashlib
import hmac
import http.cookiejar
import json
import os
from pathlib import Path
import secrets
import shutil
import signal
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request


SERVICES = ("identity", "loans", "documents", "partners", "finance", "integrations", "panel")


def available_port():
    with socket.socket() as sock:
        sock.bind(("127.0.0.1", 0))
        return sock.getsockname()[1]


class Stack:
    def __init__(self, repository: Path):
        self.repository = repository
        self.temp = tempfile.TemporaryDirectory(prefix="bb-e2e-")
        self.root = Path(self.temp.name)
        self.processes = []
        self.named_processes = {}
        self.logs = []
        self.secret = secrets.token_hex(32)
        self.password = secrets.token_urlsafe(24)
        self.user = getpass.getuser()
        self.urls = {}
        ports = set()
        for name in SERVICES:
            port = available_port()
            while port in ports:
                port = available_port()
            ports.add(port)
            self.urls[name] = f"http://127.0.0.1:{port}"
        self.socket = str(self.root / "mysql.sock")
        self.php = os.environ.get("PHP_BIN") or shutil.which("php")
        if not self.php and Path("/workspace/.babanoell-dev/bin/php").exists():
            self.php = "/workspace/.babanoell-dev/bin/php"
        self.maria = Path(os.environ.get("MARIADB_BASEDIR", "/usr"))
        if not (self.maria / "sbin/mariadbd").exists():
            self.maria = Path("/workspace/.babanoell-dev/rootfs/usr")
        if not self.php or not (self.maria / "sbin/mariadbd").exists():
            raise RuntimeError("Install PHP/MySQL extensions and MariaDB, or set PHP_BIN and MARIADB_BASEDIR.")
        # Keep executor networking/runtime settings, but never inherit app credentials.
        app_prefixes = ("AUTH_", "LOANS_", "DOCUMENTS_", "PARTNERS_", "FINANCE_",
                        "INTEGRATIONS_", "PANEL_", "NOEEL_")
        self.env = {key: value for key, value in os.environ.items()
                    if key not in ("APP_ENV", "SERVICE_NAME") and not key.startswith(app_prefixes)}
        self.env["LD_LIBRARY_PATH"] = str(self.maria / "lib/x86_64-linux-gnu") + (
            os.pathsep + self.env["LD_LIBRARY_PATH"] if self.env.get("LD_LIBRARY_PATH") else ""
        )
        self.env["NO_PROXY"] = self.env.get("NO_PROXY", "") + ",127.0.0.1,localhost"
        self.env["no_proxy"] = self.env["NO_PROXY"]
        self.env["PHP_CLI_SERVER_WORKERS"] = "4"
        self.jar = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(
            urllib.request.ProxyHandler({}), urllib.request.HTTPCookieProcessor(self.jar)
        )

    def php_command(self):
        return [self.php, "-d", "date.timezone=Asia/Tehran", "-d", "pdo_mysql.default_socket=" + self.socket,
                "-d", "session.save_path=" + str(self.root / "sessions")]

    @contextlib.contextmanager
    def browser_session(self):
        previous = self.jar, self.opener
        self.jar = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(
            urllib.request.ProxyHandler({}), urllib.request.HTTPCookieProcessor(self.jar)
        )
        try:
            yield
        finally:
            self.jar, self.opener = previous

    def spawn(self, args, name, cwd=None):
        log = (self.root / (name + ".log")).open("w+")
        self.logs.append(log)
        process = subprocess.Popen(args, cwd=cwd, env=self.env, stdout=log,
                                   stderr=subprocess.STDOUT, start_new_session=True)
        self.processes.append(process)
        self.named_processes[name] = process
        return process

    def stop(self, name):
        process = self.named_processes[name]
        os.killpg(process.pid, signal.SIGTERM)
        process.wait(timeout=10)

    def restart(self, service):
        port = urllib.parse.urlsplit(self.urls[service]).port
        router = "router.php" if (self.root / service / "router.php").exists() else "public/index.php"
        self.spawn(self.php_command() + ["-S", f"127.0.0.1:{port}", "-t", "public", router],
                   service, self.root / service)
        for _ in range(100):
            try:
                status, _ = self.request(service, "/health", signed=False)
                if status == 200:
                    return
            except (OSError, urllib.error.URLError):
                pass
            time.sleep(0.1)
        raise RuntimeError(service + " restart failed")

    def command(self, args, name):
        result = subprocess.run(args, env=self.env, capture_output=True, text=True, timeout=120)
        if result.returncode:
            raise RuntimeError(f"{name} failed: {result.stderr[-2000:] or result.stdout[-2000:]}")
        return result.stdout

    def client(self):
        return [str(self.maria / "bin/mariadb"), "--no-defaults", "--socket=" + self.socket,
                "--user=" + self.user]

    def prepare(self):
        (self.root / "sessions").mkdir(mode=0o700)
        (self.root / "sql-tmp").mkdir(mode=0o700)
        init = [str(self.maria / "bin/mariadb-install-db"), "--no-defaults",
                "--basedir=" + str(self.maria), "--datadir=" + str(self.root / "data"),
                "--tmpdir=" + str(self.root / "sql-tmp"),
                "--auth-root-authentication-method=socket", "--skip-test-db"]
        self.command(init, "MariaDB initialization")
        db = self.spawn([str(self.maria / "sbin/mariadbd"), "--no-defaults",
                         "--basedir=" + str(self.maria), "--datadir=" + str(self.root / "data"),
                         "--socket=" + self.socket, "--pid-file=" + str(self.root / "mysql.pid"),
                         "--tmpdir=" + str(self.root / "sql-tmp"),
                         "--plugin-dir=" + str(self.maria / "lib/mysql/plugin"),
                         "--lc-messages-dir=" + str(self.maria / "share/mariadb"),
                         "--skip-networking", "--innodb-buffer-pool-size=128M",
                         "--user=" + self.user], "mysql")
        for _ in range(100):
            if subprocess.run(self.client() + ["-Nse", "SELECT 1"], env=self.env,
                              stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL).returncode == 0:
                break
            if db.poll() is not None:
                raise RuntimeError("Local MariaDB exited during startup")
            time.sleep(0.1)
        else:
            raise RuntimeError("Local MariaDB readiness timed out")
        for service in SERVICES:
            target = self.root / service
            source = self.repository / (service + ".noeel.ir") / "public_html"
            shutil.copytree(source, target, ignore=shutil.ignore_patterns(
                "storage", "backups", "vendor", "local.env.php", "service-auth.local.php", ".env"))
            if (source / "vendor").is_dir():
                (target / "vendor").symlink_to(source / "vendor", target_is_directory=True)
            (target / "storage").mkdir(mode=0o700)
            prefix = "AUTH" if service == "identity" else service.upper()
            config = {
                "APP_ENV": "local", "SERVICE_NAME": service,
                "NOEEL_SERVICE_SHARED_SECRET": self.secret, "NOEEL_SERVICE_AUTH_MODE": "required",
                prefix + "_DB_HOST": "localhost", prefix + "_DB_NAME": "test_" + service,
                prefix + "_DB_USER": self.user, prefix + "_DB_PORT": "3306",
            }
            config.update({prefix + "_" + name.upper() + "_BASE_URL": url
                           for name, url in self.urls.items()})
            if service == "identity":
                config.update({
                    "AUTH_ENVIRONMENT": "local", "AUTH_ALLOW_HTTP_LOGIN_IN_LOCAL": "true",
                    "AUTH_LOGIN_ENABLED": "true", "AUTH_SESSION_ISSUING_ENABLED": "true",
                    "AUTH_REFRESH_ENABLED": "true", "AUTH_TOKEN_PEPPER": secrets.token_hex(32),
                    "AUTH_LOGIN_RATE_LIMIT_MAX_ATTEMPTS": "100",
                })
            if service == "panel":
                config["PANEL_SESSION_ENCRYPTION_KEY"] = base64.urlsafe_b64encode(os.urandom(32)).decode().rstrip("=")
            if service == "finance":
                config.update({"FINANCE_GREENPAY_ENCRYPTION_KEY": secrets.token_hex(32),
                               "FINANCE_GREENPAY_TRANSFERS_ENABLED": "0"})
            if service == "integrations":
                config["INTEGRATIONS_GREENPAY_TRANSFERS_ENABLED"] = "0"
            encoded = base64.b64encode(json.dumps(config).encode()).decode()
            local = target / "config/local.env.php"
            local.write_text("<?php return json_decode(base64_decode('" + encoded + "'), true, 512, JSON_THROW_ON_ERROR);\n")
            local.chmod(0o600)
            (target / "storage/installed.lock").write_text("{}")
        fixture = self.root / "fixture.json"
        fixture.write_text(json.dumps({"root": str(self.root), "socket": self.socket,
                                       "db_user": self.user, "password": self.password}))
        fixture.chmod(0o600)
        result = self.command(self.php_command() + [str(Path(__file__).with_name("fixture.php")),
                                                    str(fixture), "prepare"], "fixture migrations")
        prepared = json.loads(result)
        self.accounts = prepared["accounts"]
        self.migrations = prepared["migrations"]
        self.first_due = prepared["first_due"]

    def start(self):
        for service in SERVICES:
            port = urllib.parse.urlsplit(self.urls[service]).port
            router = "router.php" if (self.root / service / "router.php").exists() else "public/index.php"
            self.spawn(self.php_command() + ["-S", f"127.0.0.1:{port}", "-t", "public", router],
                       service, self.root / service)
        for service in SERVICES:
            for _ in range(100):
                try:
                    status, _ = self.request(service, "/health", signed=False)
                    if status == 200:
                        break
                except (OSError, urllib.error.URLError):
                    pass
                time.sleep(0.1)
            else:
                raise RuntimeError(f"{service} readiness timed out")

    def request(self, service, path, method="GET", data=None, token=None, key=None,
                form=False, files=None, signed=True, body_auth=False):
        headers = {}
        body = None
        if files is not None:
            boundary = "test-" + secrets.token_hex(12)
            chunks = []
            for name, value in (data or {}).items():
                chunks.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{name}"\r\n\r\n{value}\r\n'.encode())
            for name, filename, mime, content in files:
                chunks.extend([f'--{boundary}\r\nContent-Disposition: form-data; name="{name}"; filename="{filename}"\r\nContent-Type: {mime}\r\n\r\n'.encode(), content, b"\r\n"])
            chunks.append(f"--{boundary}--\r\n".encode())
            body = b"".join(chunks)
            headers["Content-Type"] = "multipart/form-data; boundary=" + boundary
        elif data is not None:
            body = urllib.parse.urlencode(data, doseq=True).encode() if form else json.dumps(data).encode()
            headers["Content-Type"] = "application/x-www-form-urlencoded" if form else "application/json"
        if signed:
            stamp, rid = str(int(time.time())), "req_" + secrets.token_hex(12)
            canonical = "\n".join(("panel", stamp, rid, method, urllib.parse.urlsplit(path).path))
            headers.update({"X-Noel-Service": "panel", "X-Noel-Timestamp": stamp,
                            "X-Request-Id": rid, "X-Noel-Signature": "v1=" + hmac.new(
                                self.secret.encode(), canonical.encode(), hashlib.sha256).hexdigest()})
        if signed and body_auth:
            payload_canonical="\n".join(("v1","panel",stamp,rid,method,urllib.parse.urlsplit(path).path,hashlib.sha256(body or b"").hexdigest()))
            headers["X-Noel-Payload-Signature"]="v1="+hmac.new(self.secret.encode(),payload_canonical.encode(),hashlib.sha256).hexdigest()
        if token:
            headers["Authorization"] = "Bearer " + token
        if key:
            headers["Idempotency-Key"] = key
        request = urllib.request.Request(self.urls[service] + path, body, headers, method=method)
        try:
            response = self.opener.open(request, timeout=20)
        except urllib.error.HTTPError as error:
            response = error
        with response:
            raw = response.read()
            try:
                value = json.loads(raw)
            except (ValueError, UnicodeDecodeError):
                value = raw
            return response.status, value

    def fixture_regressions(self):
        return self.command(self.php_command() + [str(Path(__file__).with_name("fixture.php")),
                                                  str(self.root / "fixture.json"), "regressions"],
                            "native PDO regressions")

    def close(self):
        for process in reversed(self.processes):
            with contextlib.suppress(ProcessLookupError):
                os.killpg(process.pid, signal.SIGTERM)
            with contextlib.suppress(subprocess.TimeoutExpired):
                process.wait(timeout=10)
        for log in self.logs:
            log.close()
        self.temp.cleanup()
