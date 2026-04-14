package publisher

import (
	"context"
	"fmt"
	"log"
	"strconv"

	"github.com/easymonitordev/probe-node/internal/config"
	"github.com/easymonitordev/probe-node/pkg/types"
	"github.com/redis/go-redis/v9"
)

// Publisher publishes check results to Redis Streams
type Publisher struct {
	client *redis.Client
	cfg    *config.Config
}

// NewPublisher creates a new result publisher
func NewPublisher(client *redis.Client, cfg *config.Config) *Publisher {
	return &Publisher{
		client: client,
		cfg:    cfg,
	}
}

// Publish publishes a check result to the results stream
func (p *Publisher) Publish(ctx context.Context, result *types.CheckResult) error {
	// Build field map according to Redis Streams contract
	fields := map[string]interface{}{
		"check_id": strconv.FormatInt(result.CheckID, 10),
		"node":     result.NodeID,
		"ok":       formatBool(result.OK),
		"ms":       strconv.Itoa(result.ResponseTime),
	}

	// Echo round_id so the server can group per-probe results for quorum.
	if result.RoundID != "" {
		fields["round_id"] = result.RoundID
	}

	// Add optional fields
	if result.StatusCode > 0 {
		fields["status_code"] = strconv.Itoa(result.StatusCode)
	}

	if result.Error != "" {
		fields["error"] = result.Error
	}

	// XADD results * check_id=42 node=node-1 ok=1 ms=123
	entryID, err := p.client.XAdd(ctx, &redis.XAddArgs{
		Stream: p.cfg.ResultStream,
		Values: fields,
	}).Result()

	if err != nil {
		return fmt.Errorf("failed to publish result: %w", err)
	}

	log.Printf("Published result: check_id=%d, ok=%v, ms=%d, entry_id=%s",
		result.CheckID, result.OK, result.ResponseTime, entryID)

	return nil
}

// formatBool converts a boolean to "1" or "0" for Redis
func formatBool(b bool) string {
	if b {
		return "1"
	}
	return "0"
}

// GetStreamLength returns the length of the results stream
func (p *Publisher) GetStreamLength(ctx context.Context) (int64, error) {
	return p.client.XLen(ctx, p.cfg.ResultStream).Result()
}
