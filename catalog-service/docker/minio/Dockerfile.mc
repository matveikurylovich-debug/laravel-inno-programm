FROM alpine:3.21
ARG TARGETARCH=amd64
ARG MC_RELEASE=RELEASE.2025-08-13T08-35-41Z
# hadolint ignore=DL3018,DL4006
RUN apk add --no-cache ca-certificates curl \
    && url="https://github.com/minio/mc/releases/download/${MC_RELEASE}/mc.linux-${TARGETARCH}.${MC_RELEASE}" \
    && curl -fsSL -o /usr/local/bin/mc "$url" \
    && curl -fsSL "$url.sha256sum" | awk '{ print $1 "  /usr/local/bin/mc" }' | sha256sum -c - \
    && chmod +x /usr/local/bin/mc
ENTRYPOINT ["mc"]
