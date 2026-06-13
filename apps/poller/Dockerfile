# syntax=docker/dockerfile:1.7

FROM golang:1.22-alpine AS dev
WORKDIR /app
RUN apk add --no-cache ca-certificates tzdata
CMD ["go", "run", "./cmd/poller"]

FROM golang:1.22-alpine AS builder
WORKDIR /src
RUN apk add --no-cache ca-certificates tzdata
COPY go.mod go.sum* ./
RUN go mod download
COPY . .
RUN CGO_ENABLED=0 GOOS=linux go build -trimpath -ldflags="-s -w" -o /bin/poller ./cmd/poller

FROM alpine:3.20 AS prod
WORKDIR /app
RUN apk add --no-cache ca-certificates tzdata
COPY --from=builder /bin/poller /usr/local/bin/poller
USER 65532:65532
ENTRYPOINT ["poller"]
