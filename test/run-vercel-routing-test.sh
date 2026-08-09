#!/usr/bin/env bash
#
# Tests vercel.json against Vercel's own router.
#
#   ./test/run-vercel-routing-test.sh
#
# `vercel dev --local` runs the real routing code (pcre-to-regexp matching,
# phase ordering, and the fallback to the original request path when a route's
# dest misses) without a Vercel account or a deployment.
#
# WordPress never runs. The fixture replaces api/vercel.js with a stub handler,
# so the only question each probe answers is which side of the router served
# the response: a static file out of the repo, or the function. That is exactly
# what the static file disclosure was about, and it means the test doesn't
# depend on the bundled PHP binary, which does not run on every host.
#
# The fixture is a hardlink copy, so it costs no meaningful disk or time and
# picks up the working tree's vercel.json as-is.
set -uo pipefail

REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PORT="${PORT:-3998}"
# Alongside the repo, not in TMPDIR: hardlinks cannot cross filesystems.
FIXTURE="$(mktemp -d "$(dirname "$REPO")/.vercel-routing-XXXXXX")"
STUB_HEADER="x-routing-stub"
DEV_PID=""
FAILURES=0

port_pids() { ss -ltnpH "sport = :$PORT" 2>/dev/null | grep -o 'pid=[0-9]*' | cut -d= -f2; }

# `npx vercel dev` is three processes deep (subshell -> npm exec -> node) and
# spawns a child server per function. Killing the pid bash reports leaves the
# rest holding the port, and the next run then probes a stale server whose
# fixture has been deleted - every path 404s and every check lies.
kill_tree() {
    local pid=$1 child
    for child in $(pgrep -P "$pid" 2>/dev/null); do kill_tree "$child"; done
    kill "$pid" 2>/dev/null
}

cleanup() {
    [ -n "$DEV_PID" ] && kill_tree "$DEV_PID"
    for _ in $(seq 1 10); do
        [ -z "$(port_pids)" ] && break
        sleep 1
    done
    local stragglers; stragglers=$(port_pids)
    if [ -n "$stragglers" ]; then
        echo "warning: $PORT still held by $stragglers after TERM; sending KILL"
        kill -9 $stragglers 2>/dev/null
        sleep 1
    fi
    [ -n "$(port_pids)" ] && echo "warning: port $PORT is STILL held by $(port_pids)"
    rm -rf "$FIXTURE"
}
trap cleanup EXIT

# A busy port means vercel dev silently picks a different one, and every probe
# below would hit somebody else's server.
if [ -n "$(port_pids)" ]; then
    echo "port $PORT is already in use by pid(s) $(port_pids); free it or set PORT="
    exit 1
fi

echo "Building fixture in $FIXTURE"
# -l hardlinks; -T copies into the existing directory.
if ! cp -alT "$REPO" "$FIXTURE" 2>/dev/null; then
    rm -rf "$FIXTURE" && mkdir -p "$FIXTURE"
    cp -rT "$REPO" "$FIXTURE"
fi
rm -rf "$FIXTURE/.git" "$FIXTURE/.vercel"

# rm first: the fixture entry is a hardlink to the real file, and writing
# through it would edit the repo.
rm -f "$FIXTURE/api/vercel.js"
cat > "$FIXTURE/api/vercel.js" <<'STUB'
// Stands in for the WordPress function so routing can be tested without PHP.
// `vercel dev` invokes the entrypoint as a plain (req, res) function -
// NODEJS_AWS_HANDLER_NAME only renames the entrypoint at build time.
module.exports = (req, res) => {
    res.setHeader('x-routing-stub', '1');
    res.end(`function reached: ${req.url}`);
};
STUB

echo "Starting vercel dev --local on :$PORT"
(cd "$FIXTURE" && npx --yes vercel dev --local --listen "$PORT" > "$FIXTURE/dev.log" 2>&1) &
DEV_PID=$!

for _ in $(seq 1 60); do
    grep -q "Ready!" "$FIXTURE/dev.log" 2>/dev/null && break
    sleep 2
done
if ! grep -q "Ready!" "$FIXTURE/dev.log" 2>/dev/null; then
    echo "vercel dev did not start:"
    tail -20 "$FIXTURE/dev.log"
    exit 1
fi
# The Ready line carries the port it actually bound, which is not necessarily
# the one asked for.
if ! grep -q "Ready!.*:$PORT" "$FIXTURE/dev.log"; then
    echo "vercel dev is not on :$PORT:"
    grep "Ready!" "$FIXTURE/dev.log"
    exit 1
fi

# Fields are pipe-separated, not space-separated: a response with no
# Content-Type gives curl an empty %{content_type}, and space-separated fields
# would collapse and shift every later column.
probe() {
    # probe <path> -> prints "<status>|<content-type>|<size>|<stub?>"
    local body; body="$FIXTURE/.probe-body"
    local out
    out=$(curl -s --path-as-is --max-time 20 -o "$body" \
        -w '%{http_code}|%{content_type}|%{size_download}' \
        -D "$FIXTURE/.probe-headers" "http://localhost:$PORT$1?cb=$RANDOM$$")
    local stub=no
    grep -qi "^$STUB_HEADER:" "$FIXTURE/.probe-headers" && stub=yes
    echo "$out|$stub"
}

field() { echo "$1" | cut -d'|' -f"$2"; }

# function <path> — must be handled by the function, not the filesystem.
expect_function() {
    local r; r=$(probe "$1")
    local status; status=$(field "$r" 1)
    if [ "$(field "$r" 4)" = "yes" ]; then
        echo "  ok   $1 — function"
    elif [ "$status" = "000" ]; then
        # No response at all. Says nothing about routing - the harness is broken.
        echo "  FAIL $1 — no response (timeout); the stub function is not answering"
        FAILURES=$((FAILURES + 1))
    else
        echo "  FAIL $1 — served statically: $r"
        FAILURES=$((FAILURES + 1))
    fi
}

# static <path> <content-type-substring> — must come off the filesystem.
expect_static() {
    local r; r=$(probe "$1")
    local status type stub cache
    status=$(field "$r" 1)
    type=$(field "$r" 2)
    stub=$(field "$r" 4)
    cache=$(tr -d '\r' < "$FIXTURE/.probe-headers" | grep -i '^cache-control:' | head -n1)
    if [ "$stub" = "yes" ]; then
        echo "  FAIL $1 — went to the function, losing CDN caching"
        FAILURES=$((FAILURES + 1))
    elif [ "$status" != "200" ]; then
        echo "  FAIL $1 — expected 200, got $status"
        FAILURES=$((FAILURES + 1))
    elif ! printf '%s' "$type" | grep -qi "$2"; then
        echo "  FAIL $1 — expected $2, got $type"
        FAILURES=$((FAILURES + 1))
    elif [ -z "$cache" ]; then
        echo "  FAIL $1 — 200 but no Cache-Control; the header route stopped matching"
        FAILURES=$((FAILURES + 1))
    else
        echo "  ok   $1 — static, $type, ${cache#*: }"
    fi
}

# denied <path> — must not return the file's bytes, whoever serves it.
denied() {
    local r; r=$(probe "$1")
    local size; size=$(field "$r" 3)
    local real; real=$(wc -c < "$REPO/${2}" 2>/dev/null || echo -1)
    if [ "$size" = "$real" ]; then
        echo "  FAIL $1 — returned $size bytes, the exact size of $2"
        FAILURES=$((FAILURES + 1))
    elif [ "$(field "$r" 4)" = "yes" ]; then
        echo "  ok   $1 — function"
    else
        echo "  ok   $1 — $r"
    fi
}

echo
echo "Source files (must never be served from the filesystem):"
for p in /wp/wp-config.php /wp/wp-settings.php /wp/index.php \
         /wp/wp-content/plugins/sqlite-database-integration/load.php \
         /util/sqliteS3.js /util/install.js /util/directory.js \
         /api/index.js /test/proxy.js /package.json /serverless.yml /netlify.toml; do
    expect_function "$p"
done

echo
echo "The dest-miss fallback (a matched route whose dest does not resolve):"
# Vercel falls back to resolving the original request path. Under the old
# config these returned the real source.
denied /util/sqliteS3.js       util/sqliteS3.js
denied /wp/util/sqliteS3.js    util/sqliteS3.js
denied /test/test-key.pem      test/test-key.pem
denied /test/proxy.js          test/proxy.js

echo
echo "Path traversal (routes match before the path is resolved):"
denied "/wp-content/../util/sqliteS3.js"    util/sqliteS3.js
denied "/wp-includes/../../util/install.js" util/install.js

echo
echo "Assets (must stay on the CDN):"
expect_static /wp-includes/css/classic-themes.css         'text/css'
expect_static /wp-admin/js/password-strength-meter.min.js 'javascript'
expect_static /wp-includes/images/w-logo-blue-white-bg.png 'image/png'

echo
echo "Pages (must reach the function):"
for p in / /wp-login.php /wp-admin/ /?p=1 /some/permalink/; do
    expect_function "$p"
done

echo
if [ "$FAILURES" -ne 0 ]; then
    echo "$FAILURES check(s) failed"
    exit 1
fi
echo "all checks passed"
