FROM alpine:3.21
ARG TARGETARCH=amd64
ARG MINIO_RELEASE=RELEASE.2025-09-07T16-13-09Z
# hadolint ignore=DL3018,DL4006
RUN apk add --no-cache ca-certificates curl \
    && url="https://github.com/minio/minio/releases/download/${MINIO_RELEASE}/minio.linux-${TARGETARCH}.${MINIO_RELEASE}" \
    && curl -fsSL -o /usr/local/bin/minio "$url" \
    && curl -fsSL "$url.sha256sum" | awk '{ print $1 "  /usr/local/bin/minio" }' | sha256sum -c - \
    && chmod +x /usr/local/bin/minio
EXPOSE 9000 9001
ENTRYPOINT ["minio"]
