FROM golang:1.25-alpine AS build

WORKDIR /src

COPY go.mod go.sum ./
RUN go mod download

COPY . .

RUN CGO_ENABLED=0 go build -o /out/analytics ./cmd/api

FROM alpine:3.20

WORKDIR /app

COPY --from=build /out/analytics /usr/local/bin/analytics

EXPOSE 8080

CMD ["analytics"]
