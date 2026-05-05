# syntax=docker/dockerfile:1.7

FROM golang:alpine AS dev
WORKDIR /app
RUN apk add --no-cache ca-certificates git tzdata curl
CMD ["go", "run", "./cmd/poller"]

FROM golang:alpine AS builder
WORKDIR /src
RUN apk add --no-cache ca-certificates git tzdata
COPY apps/poller/go.mod apps/poller/go.sum* ./
RUN if [ -f go.mod ]; then go mod download; fi
COPY apps/poller .
RUN CGO_ENABLED=0 GOOS=linux go build -trimpath -ldflags="-s -w" -o /bin/poller ./cmd/poller

FROM alpine:latest AS prod
WORKDIR /app
RUN apk add --no-cache ca-certificates tzdata
COPY --from=builder /bin/poller /usr/local/bin/poller
USER 65532:65532
ENTRYPOINT ["poller"]