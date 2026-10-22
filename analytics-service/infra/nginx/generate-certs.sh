#!/bin/sh
set -e

CERT_DIR="$(cd "$(dirname "$0")" && pwd)/certs"
mkdir -p "$CERT_DIR"

if [ ! -f "$CERT_DIR/selfsigned.crt" ]; then
    openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
        -keyout "$CERT_DIR/selfsigned.key" \
        -out "$CERT_DIR/selfsigned.crt" \
        -subj "/C=BY/ST=Minsk/L=Minsk/O=Ecommerce/OU=Analytics/CN=localhost"
fi
