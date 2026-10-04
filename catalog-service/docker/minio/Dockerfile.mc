FROM alpine:3.21

RUN apk add --no-cache ca-certificates curl \
    && curl -fsSL -o /usr/local/bin/mc \
        https://github.com/minio/mc/releases/download/RELEASE.2025-08-13T08-35-41Z/mc.linux-amd64.RELEASE.2025-08-13T08-35-41Z \
    && chmod +x /usr/local/bin/mc

ENTRYPOINT ["mc"]
