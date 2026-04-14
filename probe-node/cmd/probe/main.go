package main

import (
	"context"
	"fmt"
	"log"
	"net/http"
	"os"
	"os/signal"
	"strings"
	"sync"
	"syscall"
	"time"

	"github.com/easymonitordev/probe-node/internal/auth"
	"github.com/easymonitordev/probe-node/internal/checker"
	"github.com/easymonitordev/probe-node/internal/config"
	"github.com/easymonitordev/probe-node/internal/consumer"
	"github.com/easymonitordev/probe-node/internal/publisher"
	"github.com/easymonitordev/probe-node/pkg/types"
	"github.com/redis/go-redis/v9"
)

var (
	version   = "dev"
	buildTime = "unknown"
)

func main() {
	log.Printf("EasyMonitor Probe Node starting (version=%s, build=%s)", version, buildTime)

	// Load configuration
	cfg, err := config.LoadFromEnv()
	if err != nil {
		log.Fatalf("Failed to load configuration: %v", err)
	}

	log.Printf("Configuration loaded: node_id=%s, redis=%s", cfg.NodeID, cfg.RedisURL)

	// Validate JWT token structure (no signature verification needed on probe)
	claims, err := auth.ValidateTokenStructure(cfg.JWTToken)
	if err != nil {
		log.Fatalf("JWT token validation failed: %v", err)
	}

	log.Printf("JWT token validated: node_id=%s, tags=%v, expires=%v", claims.NodeID, claims.Tags, claims.ExpiresAt.Time)

	// Verify node ID matches token
	if claims.NodeID != cfg.NodeID {
		log.Fatalf("Node ID mismatch: config=%s, token=%s", cfg.NodeID, claims.NodeID)
	}

	// Create Redis client
	// Parse Redis URL to get options
	redisOpts, err := redis.ParseURL(cfg.RedisURL)
	if err != nil {
		log.Fatalf("Failed to parse Redis URL: %v", err)
	}

	// Override with explicit password if provided
	if cfg.RedisPassword != "" {
		redisOpts.Password = cfg.RedisPassword
	}

	redisClient := redis.NewClient(redisOpts)

	// Test Redis connection
	ctx := context.Background()
	if err := redisClient.Ping(ctx).Err(); err != nil {
		log.Fatalf("Failed to connect to Redis: %v", err)
	}

	log.Println("Connected to Redis successfully")

	// Ensure consumer group exists
	err = ensureConsumerGroup(ctx, redisClient, cfg.CheckStream, cfg.ConsumerGroup)
	if err != nil {
		log.Fatalf("Failed to ensure consumer group exists: %v", err)
	}
	log.Printf("Consumer group '%s' ready", cfg.ConsumerGroup)

	// Create components
	cons := consumer.NewConsumer(redisClient, cfg)
	pub := publisher.NewPublisher(redisClient, cfg)
	httpChecker := checker.NewHTTPChecker()
	icmpChecker := checker.NewICMPChecker()

	// Create check handler
	checkHandler := createCheckHandler(cfg, pub, httpChecker, icmpChecker)

	// Setup graceful shutdown
	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()

	var wg sync.WaitGroup

	// Start health check server
	wg.Add(1)
	go func() {
		defer wg.Done()
		startHealthCheckServer(ctx, cfg, cons)
	}()

	// Start consumer
	wg.Add(1)
	go func() {
		defer wg.Done()
		if err := cons.Start(ctx, checkHandler); err != nil {
			if err != context.Canceled {
				log.Printf("Consumer error: %v", err)
			}
		}
	}()

	// Wait for interrupt signal
	sigChan := make(chan os.Signal, 1)
	signal.Notify(sigChan, os.Interrupt, syscall.SIGTERM)

	<-sigChan
	log.Println("Shutdown signal received, gracefully shutting down...")

	// Cancel context to stop all goroutines
	cancel()

	// Wait for all goroutines with timeout
	done := make(chan struct{})
	go func() {
		wg.Wait()
		close(done)
	}()

	select {
	case <-done:
		log.Println("Graceful shutdown completed")
	case <-time.After(30 * time.Second):
		log.Println("Shutdown timeout exceeded, forcing exit")
	}

	// Close Redis connection
	if err := redisClient.Close(); err != nil {
		log.Printf("Error closing Redis connection: %v", err)
	}
}

// createCheckHandler creates the handler function for processing check jobs
func createCheckHandler(
	cfg *config.Config,
	pub *publisher.Publisher,
	httpChecker *checker.HTTPChecker,
	icmpChecker *checker.ICMPChecker,
) func(*types.CheckJob) error {
	// Create semaphore for concurrency control
	sem := make(chan struct{}, cfg.MaxConcurrency)

	return func(job *types.CheckJob) error {
		// Acquire semaphore
		sem <- struct{}{}
		defer func() { <-sem }()

		log.Printf("Processing check: check_id=%d, url=%s", job.CheckID, job.URL)

		// Determine check type and timeout
		timeout := time.Duration(job.Timeout) * time.Millisecond
		if timeout == 0 {
			timeout = cfg.DefaultTimeout
		}

		var result *types.CheckResult

		// Determine check type based on URL
		if strings.HasPrefix(job.URL, "icmp://") {
			// ICMP check
			host := strings.TrimPrefix(job.URL, "icmp://")
			result = icmpChecker.Check(job.CheckID, cfg.NodeID, host, timeout)
		} else {
			// HTTP/HTTPS check (default)
			result = httpChecker.Check(job.CheckID, cfg.NodeID, job.URL, timeout)
		}

		// Propagate round_id so the server can group this result with other
		// probes' results for the same dispatched check (quorum).
		result.RoundID = job.RoundID

		// Publish result
		ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
		defer cancel()

		if err := pub.Publish(ctx, result); err != nil {
			return fmt.Errorf("failed to publish result: %w", err)
		}

		return nil
	}
}

// startHealthCheckServer starts the HTTP health check endpoint
func startHealthCheckServer(ctx context.Context, cfg *config.Config, cons *consumer.Consumer) {
	mux := http.NewServeMux()

	// Health check endpoint
	mux.HandleFunc("/health", func(w http.ResponseWriter, r *http.Request) {
		if cons.IsActive() {
			w.WriteHeader(http.StatusOK)
			fmt.Fprintf(w, `{"status":"healthy","node_id":"%s"}`, cfg.NodeID)
		} else {
			w.WriteHeader(http.StatusServiceUnavailable)
			fmt.Fprintf(w, `{"status":"unhealthy","node_id":"%s"}`, cfg.NodeID)
		}
	})

	// Ready check endpoint
	mux.HandleFunc("/ready", func(w http.ResponseWriter, r *http.Request) {
		if cons.IsActive() {
			w.WriteHeader(http.StatusOK)
			fmt.Fprintf(w, `{"status":"ready","node_id":"%s"}`, cfg.NodeID)
		} else {
			w.WriteHeader(http.StatusServiceUnavailable)
			fmt.Fprintf(w, `{"status":"not_ready","node_id":"%s"}`, cfg.NodeID)
		}
	})

	// Version endpoint
	mux.HandleFunc("/version", func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusOK)
		fmt.Fprintf(w, `{"version":"%s","build_time":"%s"}`, version, buildTime)
	})

	server := &http.Server{
		Addr:    fmt.Sprintf(":%d", cfg.HealthCheckPort),
		Handler: mux,
	}

	log.Printf("Health check server listening on :%d", cfg.HealthCheckPort)

	go func() {
		if err := server.ListenAndServe(); err != nil && err != http.ErrServerClosed {
			log.Printf("Health check server error: %v", err)
		}
	}()

	<-ctx.Done()

	shutdownCtx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
	defer cancel()

	if err := server.Shutdown(shutdownCtx); err != nil {
		log.Printf("Health check server shutdown error: %v", err)
	}
}

// ensureConsumerGroup creates the consumer group if it doesn't exist
func ensureConsumerGroup(ctx context.Context, client *redis.Client, stream, group string) error {
	// Try to create the consumer group with MKSTREAM option.
	// Start from "$" so a newly-joined probe only picks up checks dispatched
	// after it comes online, not the entire stream backlog. On restart the
	// group already exists (BUSYGROUP) and resumes from last-delivered-id.
	err := client.XGroupCreateMkStream(ctx, stream, group, "$").Err()
	if err != nil {
		// If the group already exists, that's fine
		if err.Error() == "BUSYGROUP Consumer Group name already exists" {
			return nil
		}
		return fmt.Errorf("failed to create consumer group: %w", err)
	}
	return nil
}
