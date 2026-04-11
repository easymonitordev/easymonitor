package config

import (
	"os"
	"testing"
	"time"

	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/require"
)

func TestLoadFromEnv_Success(t *testing.T) {
	// Set required environment variables
	os.Setenv("NODE_ID", "test-node-1")
	os.Setenv("JWT_TOKEN", "test-token")
	defer os.Clearenv()

	cfg, err := LoadFromEnv()
	require.NoError(t, err)
	assert.Equal(t, "test-node-1", cfg.NodeID)
	assert.Equal(t, "test-token", cfg.JWTToken)
	assert.Equal(t, "redis://redis:6379/0", cfg.RedisURL) // Default
	assert.Equal(t, "checks", cfg.CheckStream)
	assert.Equal(t, "results", cfg.ResultStream)
}

func TestLoadFromEnv_MissingNodeID(t *testing.T) {
	os.Clearenv()
	os.Setenv("JWT_TOKEN", "test-token")

	_, err := LoadFromEnv()
	assert.Error(t, err)
	assert.Contains(t, err.Error(), "NODE_ID")
}

func TestLoadFromEnv_MissingJWTToken(t *testing.T) {
	os.Clearenv()
	os.Setenv("NODE_ID", "test-node-1")

	_, err := LoadFromEnv()
	assert.Error(t, err)
	assert.Contains(t, err.Error(), "JWT_TOKEN")
}

func TestLoadFromEnv_CustomValues(t *testing.T) {
	os.Setenv("NODE_ID", "custom-node")
	os.Setenv("JWT_TOKEN", "token123")
	os.Setenv("REDIS_URL", "rediss://redis.example.com:6380/2")
	os.Setenv("REDIS_PASSWORD", "mypassword")
	os.Setenv("DEFAULT_TIMEOUT", "60s")
	os.Setenv("BATCH_SIZE", "20")
	os.Setenv("MAX_CONCURRENCY", "5")
	os.Setenv("HEALTH_CHECK_PORT", "9090")
	defer os.Clearenv()

	cfg, err := LoadFromEnv()
	require.NoError(t, err)
	assert.Equal(t, "custom-node", cfg.NodeID)
	assert.Equal(t, "rediss://redis.example.com:6380/2", cfg.RedisURL)
	assert.Equal(t, "mypassword", cfg.RedisPassword)
	// Go normalizes 60s to 1m0s
	assert.Equal(t, 60*time.Second, cfg.DefaultTimeout)
	assert.Equal(t, 20, cfg.BatchSize)
	assert.Equal(t, 5, cfg.MaxConcurrency)
	assert.Equal(t, 9090, cfg.HealthCheckPort)
}
