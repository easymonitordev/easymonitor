# Build stage
FROM golang:1.23-alpine AS builder

# Install build dependencies
RUN apk add --no-cache git make ca-certificates tzdata

WORKDIR /build

# Copy go mod files
COPY go.mod go.sum ./
RUN go mod download

# Copy source code
COPY . .

# Build binary with version info
ARG VERSION=dev
ARG BUILD_TIME
RUN CGO_ENABLED=0 GOOS=linux go build \
    -ldflags "-X main.version=${VERSION} -X main.buildTime=${BUILD_TIME} -s -w" \
    -o probe-node \
    ./cmd/probe

# Final stage
FROM alpine:3.19

# Install runtime dependencies (iputils for ping)
RUN apk add --no-cache ca-certificates tzdata iputils

# Create non-root user
RUN addgroup -g 1000 probe && \
    adduser -D -u 1000 -G probe probe

WORKDIR /app

# Copy binary from builder
COPY --from=builder /build/probe-node .

# Change ownership
RUN chown -R probe:probe /app

# Switch to non-root user
USER probe

# Expose health check port
EXPOSE 8080

# Health check
HEALTHCHECK --interval=30s --timeout=5s --start-period=5s --retries=3 \
    CMD wget --no-verbose --tries=1 --spider http://localhost:8080/health || exit 1

ENTRYPOINT ["/app/probe-node"]
