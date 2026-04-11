# EasyMonitor Probe Node

A lightweight, production-ready monitoring probe written in Go that connects to EasyMonitor via Redis Streams to perform uptime and performance checks.

## Features

- ✅ **HTTP/HTTPS Monitoring** - Check website availability and response times
- ✅ **ICMP (Ping) Monitoring** - Monitor server reachability
- ✅ **Redis Streams Integration** - Scalable job distribution and result collection
- ✅ **JWT Authentication** - Secure probe-to-server communication
- ✅ **Multi-Architecture** - Supports amd64 and arm64
- ✅ **Health Checks** - Built-in health and readiness endpoints
- ✅ **Graceful Shutdown** - Clean termination handling
- ✅ **Production Ready** - Comprehensive tests and error handling

## Quick Start

### Using Docker Compose (Recommended)

The probe node is already integrated into the main EasyMonitor setup:

```bash
# 1. Generate JWT token
docker exec php php artisan probe:generate-token

# 2. Start the probe node
docker compose up -d probe

# 3. Check probe health
curl http://localhost:8080/health
```

### Standalone Deployment

#### 1. Generate JWT Token

```bash
# From your EasyMonitor Laravel application
php artisan probe:generate-token --node-id=production-probe-1 --tags=us-east-1,production
```

#### 2. Configure Environment

Create a `.env` file or export environment variables:

```bash
export NODE_ID="production-probe-1"
export REDIS_URL="rediss://your-redis-host.com:6380/0"  # Use rediss:// for TLS
export REDIS_PASSWORD="your-redis-password"
export JWT_TOKEN="your-generated-jwt-token"
```

**Note:** JWT_SECRET is NOT needed on the probe - only the server uses it to generate tokens!

#### 3. Run with Docker

```bash
docker run -d \
  --name easymonitor-probe \
  -e NODE_ID="production-probe-1" \
  -e REDIS_URL="rediss://your-redis-host.com:6380/0" \
  -e REDIS_PASSWORD="your-redis-password" \
  -e JWT_TOKEN="your-jwt-token" \
  -p 8080:8080 \
  easymonitor/probe-node:latest
```

#### 4. Run Binary

```bash
# Download latest release
wget https://github.com/easymonitordev/probe-node/releases/latest/download/probe-node-linux-amd64

# Make executable
chmod +x probe-node-linux-amd64

# Run
./probe-node-linux-amd64
```

## Configuration

All configuration is done via environment variables:

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `NODE_ID` | ✅ Yes | - | Unique identifier for this probe node |
| `JWT_TOKEN` | ✅ Yes | - | JWT token for authentication (from server) |
| `REDIS_URL` | No | `redis://redis:6379/0` | Redis connection URI (use `rediss://` for TLS) |
| `REDIS_PASSWORD` | No | - | Redis password (if required) |
| `DEFAULT_TIMEOUT` | No | `30s` | Default timeout for checks |
| `BATCH_SIZE` | No | `10` | Number of checks to process per batch |
| `MAX_CONCURRENCY` | No | `10` | Maximum concurrent checks |
| `HEALTH_CHECK_PORT` | No | `8080` | Port for health check endpoints |
| `PROBE_TAGS` | No | - | Comma-separated tags for this probe |

## Health Endpoints

The probe exposes several health check endpoints:

- **`GET /health`** - Overall health status
- **`GET /ready`** - Readiness check for load balancers
- **`GET /version`** - Version and build information

## Architecture

### How It Works

1. **Consumer** - Reads check jobs from Redis Stream `checks` using consumer groups
2. **Checker** - Performs HTTP or ICMP checks based on the URL scheme
3. **Publisher** - Publishes results to Redis Stream `results`
4. **Acknowledgment** - Acknowledges processed messages to prevent reprocessing

### Redis Streams Contract

#### Input Stream: `checks`

```
check_id: 42              # Monitor ID
url: https://example.com  # URL to check
timeout: 30000            # Timeout in milliseconds
```

#### Output Stream: `results`

```
check_id: 42                    # Monitor ID
node: production-probe-1        # Probe node ID
ok: 1                           # 1 = up, 0 = down
ms: 123                         # Response time in ms
status_code: 200                # HTTP status code (optional)
error: "connection timeout"     # Error message (optional)
```

## Development

### Prerequisites

- Go 1.23+
- Redis 7+
- Make (optional)

### Build

```bash
# Build for current platform
make build

# Build for Linux amd64
make build-linux

# Build for Linux arm64
make build-linux-arm

# Build Docker image
make docker-build
```

### Test

```bash
# Run all tests
make test

# Run tests with coverage
make test-coverage

# Run linters
make lint
```

### Local Development

```bash
# Install dependencies
make deps

# Run locally (requires Redis and JWT token)
make run
```

## Deployment

### Kubernetes

```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: easymonitor-probe
spec:
  replicas: 3
  selector:
    matchLabels:
      app: easymonitor-probe
  template:
    metadata:
      labels:
        app: easymonitor-probe
    spec:
      containers:
      - name: probe
        image: easymonitor/probe-node:latest
        env:
        - name: NODE_ID
          valueFrom:
            fieldRef:
              fieldPath: metadata.name
        - name: REDIS_URL
          value: "redis-service:6379"
        - name: JWT_SECRET
          valueFrom:
            secretKeyRef:
              name: easymonitor-secrets
              key: jwt-secret
        - name: JWT_TOKEN
          valueFrom:
            secretKeyRef:
              name: easymonitor-secrets
              key: jwt-token
        ports:
        - containerPort: 8080
          name: health
        livenessProbe:
          httpGet:
            path: /health
            port: 8080
          initialDelaySeconds: 5
          periodSeconds: 30
        readinessProbe:
          httpGet:
            path: /ready
            port: 8080
          initialDelaySeconds: 3
          periodSeconds: 10
```

### Systemd Service

```ini
[Unit]
Description=EasyMonitor Probe Node
After=network.target

[Service]
Type=simple
User=easymonitor
WorkingDirectory=/opt/easymonitor-probe
EnvironmentFile=/etc/easymonitor-probe/.env
ExecStart=/opt/easymonitor-probe/probe-node
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

## Monitoring the Probe

### Metrics

The probe logs structured data that can be ingested by log aggregation systems:

```json
{
  "timestamp": "2025-01-05T12:34:56Z",
  "level": "info",
  "message": "Processing check",
  "check_id": 42,
  "url": "https://example.com"
}
```

### Recommended Monitoring

- Monitor health endpoint: `GET /health` should return 200
- Watch log output for errors
- Monitor Redis Stream lengths to detect backlogs
- Track check processing latency

## Troubleshooting

### Probe won't start

```bash
# Check JWT token validation
docker logs probe | grep "JWT"

# Verify Redis connection
docker exec probe wget -O- http://localhost:8080/health
```

### Checks not being processed

```bash
# Check Redis Stream length
docker exec redis redis-cli XLEN checks

# Check consumer group
docker exec redis redis-cli XPENDING checks probes
```

### High memory usage

Reduce `MAX_CONCURRENCY` to limit simultaneous checks:

```bash
docker compose up -d probe -e MAX_CONCURRENCY=5
```

## Security

- **JWT Tokens**: Rotate tokens regularly using `php artisan probe:generate-token`
- **TLS**: Use Redis with TLS in production
- **Network**: Restrict probe network access to Redis only
- **Updates**: Keep probe image updated for security patches

## License

This project is open-source and part of the EasyMonitor project.

## Contributing

Contributions are welcome! Please ensure:

1. All tests pass (`make test`)
2. Code is formatted (`make lint`)
3. New features include tests
4. Documentation is updated

## Support

- GitHub Issues: https://github.com/easymonitordev/probe-node/issues
- Documentation: https://docs.easymonitor.com
