# Build stage — always runs on BUILDPLATFORM (the native runner arch) and
# cross-compiles to the TARGETPLATFORM using Go's built-in cross-compiler.
# This avoids QEMU emulation entirely and is ~10x faster under buildx.
FROM --platform=$BUILDPLATFORM golang:1.24-alpine AS builder

# Install build dependencies
RUN apk add --no-cache git ca-certificates tzdata

WORKDIR /build

# Cache module downloads separately from source code.
COPY go.mod go.sum ./
RUN go mod download

# Copy source code
COPY . .

# buildx injects TARGETOS and TARGETARCH for the platform being built.
# CGO_ENABLED=0 is required for static binaries that run in alpine/scratch.
ARG TARGETOS
ARG TARGETARCH
ARG VERSION=dev
ARG BUILD_TIME

RUN CGO_ENABLED=0 GOOS=${TARGETOS} GOARCH=${TARGETARCH} go build \
    -ldflags "-X main.version=${VERSION} -X main.buildTime=${BUILD_TIME} -s -w" \
    -o probe-node \
    ./cmd/probe

# Final stage — minimal runtime image for the target platform.
FROM alpine:3.23

# Install runtime dependencies (iputils for ping).
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
