FROM golang:1.25-alpine AS build

WORKDIR /src

COPY go.mod go.sum ./
RUN go mod download

COPY . .

RUN CGO_ENABLED=0 go build -trimpath -ldflags="-s -w" -o /out/analytics ./cmd/api

FROM alpine:3.24

WORKDIR /app

COPY --from=build /out/analytics /usr/local/bin/analytics

USER 65532:65532

EXPOSE 8080

ENTRYPOINT ["analytics"]
