FROM alpine:3.21

RUN apk add --no-cache ca-certificates curl \
    && curl -fsSL -o /usr/local/bin/minio \
        https://github.com/minio/minio/releases/download/RELEASE.2025-09-07T16-13-09Z/minio.linux-amd64.RELEASE.2025-09-07T16-13-09Z \
    && chmod +x /usr/local/bin/minio

EXPOSE 9000 9001
ENTRYPOINT ["minio"]
