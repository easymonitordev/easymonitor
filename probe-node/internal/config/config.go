package config

import (
	"fmt"
	"os"
	"strconv"
	"time"
)

// Config holds all configuration for the probe node
type Config struct {
	// Node identification
	NodeID     string
	ProbeTags  []string

	// Redis connection
	RedisURL      string
	RedisPassword string
	RedisDB       int

	// JWT authentication
	JWTToken string

	// Stream configuration
	CheckStream      string
	ResultStream     string
	ConsumerGroup    string
	ConsumerName     string
	BlockTimeout     time.Duration
	BatchSize        int

	// Check configuration
	DefaultTimeout time.Duration
	MaxConcurrency int

	// Health check
	HealthCheckPort int
}

// LoadFromEnv loads configuration from environment variables
func LoadFromEnv() (*Config, error) {
	cfg := &Config{
		// Defaults
		CheckStream:      "checks",
		ResultStream:     "results",
		ConsumerGroup:    "probes",
		BlockTimeout:     5 * time.Second,
		BatchSize:        10,
		DefaultTimeout:   30 * time.Second,
		MaxConcurrency:   10,
		HealthCheckPort:  8080,
		RedisDB:          0,
	}

	// Required fields
	cfg.NodeID = os.Getenv("NODE_ID")
	if cfg.NodeID == "" {
		return nil, fmt.Errorf("NODE_ID environment variable is required")
	}

	cfg.RedisURL = os.Getenv("REDIS_URL")
	if cfg.RedisURL == "" {
		cfg.RedisURL = "redis://redis:6379/0" // Default for local Docker setup
	}

	cfg.JWTToken = os.Getenv("JWT_TOKEN")
	if cfg.JWTToken == "" {
		return nil, fmt.Errorf("JWT_TOKEN environment variable is required")
	}

	// Optional fields
	if tags := os.Getenv("PROBE_TAGS"); tags != "" {
		// Simple comma-separated parsing (can be enhanced)
		cfg.ProbeTags = []string{tags}
	}

	if password := os.Getenv("REDIS_PASSWORD"); password != "" {
		cfg.RedisPassword = password
	}

	if dbStr := os.Getenv("REDIS_DB"); dbStr != "" {
		db, err := strconv.Atoi(dbStr)
		if err != nil {
			return nil, fmt.Errorf("invalid REDIS_DB: %w", err)
		}
		cfg.RedisDB = db
	}

	if timeout := os.Getenv("DEFAULT_TIMEOUT"); timeout != "" {
		d, err := time.ParseDuration(timeout)
		if err != nil {
			return nil, fmt.Errorf("invalid DEFAULT_TIMEOUT: %w", err)
		}
		cfg.DefaultTimeout = d
	}

	if batchSize := os.Getenv("BATCH_SIZE"); batchSize != "" {
		size, err := strconv.Atoi(batchSize)
		if err != nil {
			return nil, fmt.Errorf("invalid BATCH_SIZE: %w", err)
		}
		cfg.BatchSize = size
	}

	if maxConc := os.Getenv("MAX_CONCURRENCY"); maxConc != "" {
		conc, err := strconv.Atoi(maxConc)
		if err != nil {
			return nil, fmt.Errorf("invalid MAX_CONCURRENCY: %w", err)
		}
		cfg.MaxConcurrency = conc
	}

	if port := os.Getenv("HEALTH_CHECK_PORT"); port != "" {
		p, err := strconv.Atoi(port)
		if err != nil {
			return nil, fmt.Errorf("invalid HEALTH_CHECK_PORT: %w", err)
		}
		cfg.HealthCheckPort = p
	}

	cfg.ConsumerName = fmt.Sprintf("%s-%d", cfg.NodeID, os.Getpid())

	return cfg, nil
}