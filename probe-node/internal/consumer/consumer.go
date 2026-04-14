package consumer

import (
	"context"
	"fmt"
	"log"
	"strconv"
	"sync"
	"time"

	"github.com/easymonitordev/probe-node/internal/config"
	"github.com/easymonitordev/probe-node/pkg/types"
	"github.com/redis/go-redis/v9"
)

// Consumer reads check jobs from Redis Streams
type Consumer struct {
	client *redis.Client
	cfg    *config.Config
	mu     sync.RWMutex
	active bool
}

// NewConsumer creates a new stream consumer
func NewConsumer(client *redis.Client, cfg *config.Config) *Consumer {
	return &Consumer{
		client: client,
		cfg:    cfg,
		active: false,
	}
}

// EnsureConsumerGroup creates the consumer group if it doesn't exist
func (c *Consumer) EnsureConsumerGroup(ctx context.Context) error {
	err := c.client.XGroupCreateMkStream(
		ctx,
		c.cfg.CheckStream,
		c.cfg.ConsumerGroup,
		"0",
	).Err()

	if err != nil && err.Error() != "BUSYGROUP Consumer Group name already exists" {
		return fmt.Errorf("failed to create consumer group: %w", err)
	}

	return nil
}

// Start begins consuming check jobs from the stream
func (c *Consumer) Start(ctx context.Context, handler func(*types.CheckJob) error) error {
	c.mu.Lock()
	c.active = true
	c.mu.Unlock()

	defer func() {
		c.mu.Lock()
		c.active = false
		c.mu.Unlock()
	}()

	// Ensure consumer group exists
	if err := c.EnsureConsumerGroup(ctx); err != nil {
		return err
	}

	log.Printf("Consumer started: group=%s, consumer=%s, stream=%s",
		c.cfg.ConsumerGroup, c.cfg.ConsumerName, c.cfg.CheckStream)

	for {
		select {
		case <-ctx.Done():
			log.Println("Consumer shutting down")
			return ctx.Err()
		default:
			if err := c.consumeBatch(ctx, handler); err != nil {
				log.Printf("Error consuming batch: %v", err)
				// Brief pause before retrying
				time.Sleep(1 * time.Second)
			}
		}
	}
}

// consumeBatch reads and processes a batch of check jobs
func (c *Consumer) consumeBatch(ctx context.Context, handler func(*types.CheckJob) error) error {
	// XREADGROUP GROUP probes node-1 BLOCK 5000 COUNT 10 STREAMS checks >
	streams, err := c.client.XReadGroup(ctx, &redis.XReadGroupArgs{
		Group:    c.cfg.ConsumerGroup,
		Consumer: c.cfg.ConsumerName,
		Streams:  []string{c.cfg.CheckStream, ">"},
		Count:    int64(c.cfg.BatchSize),
		Block:    c.cfg.BlockTimeout,
	}).Result()

	if err != nil {
		if err == redis.Nil {
			// No messages available, this is normal
			return nil
		}
		return fmt.Errorf("xreadgroup failed: %w", err)
	}

	for _, stream := range streams {
		for _, message := range stream.Messages {
			job, err := c.parseCheckJob(message)
			if err != nil {
				log.Printf("Failed to parse check job %s: %v", message.ID, err)
				// Acknowledge the malformed message to prevent blocking
				c.acknowledge(ctx, message.ID)
				continue
			}

			// Process the check
			if err := handler(job); err != nil {
				log.Printf("Failed to process check job %s: %v", message.ID, err)
				// Don't acknowledge - allow retry
				continue
			}

			// Acknowledge successful processing
			if err := c.acknowledge(ctx, message.ID); err != nil {
				log.Printf("Failed to acknowledge message %s: %v", message.ID, err)
			}
		}
	}

	return nil
}

// parseCheckJob converts a Redis stream message to a CheckJob
func (c *Consumer) parseCheckJob(msg redis.XMessage) (*types.CheckJob, error) {
	checkID, err := strconv.ParseInt(msg.Values["check_id"].(string), 10, 64)
	if err != nil {
		return nil, fmt.Errorf("invalid check_id: %w", err)
	}

	url, ok := msg.Values["url"].(string)
	if !ok {
		return nil, fmt.Errorf("missing url field")
	}

	timeout := 30000 // Default 30 seconds
	if timeoutStr, ok := msg.Values["timeout"].(string); ok {
		if t, err := strconv.Atoi(timeoutStr); err == nil {
			timeout = t
		}
	}

	roundID := ""
	if r, ok := msg.Values["round_id"].(string); ok {
		roundID = r
	}

	return &types.CheckJob{
		ID:      msg.ID,
		CheckID: checkID,
		URL:     url,
		Timeout: timeout,
		RoundID: roundID,
	}, nil
}

// acknowledge marks a message as processed
func (c *Consumer) acknowledge(ctx context.Context, messageID string) error {
	return c.client.XAck(ctx, c.cfg.CheckStream, c.cfg.ConsumerGroup, messageID).Err()
}

// IsActive returns whether the consumer is currently active
func (c *Consumer) IsActive() bool {
	c.mu.RLock()
	defer c.mu.RUnlock()
	return c.active
}

// GetPendingCount returns the number of pending messages in the stream
func (c *Consumer) GetPendingCount(ctx context.Context) (int64, error) {
	pending, err := c.client.XPending(ctx, c.cfg.CheckStream, c.cfg.ConsumerGroup).Result()
	if err != nil {
		return 0, err
	}
	return pending.Count, nil
}