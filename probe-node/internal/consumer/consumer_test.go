package consumer

import (
	"testing"
	"time"

	"github.com/redis/go-redis/v9"
	"github.com/stretchr/testify/assert"
)

func TestParseCheckJob_WithCheckType(t *testing.T) {
	c := &Consumer{}

	job, err := c.parseCheckJob(redis.XMessage{
		ID: "1-0",
		Values: map[string]interface{}{
			"check_id":   "42",
			"url":        "tcp://db.example.com:5432",
			"timeout":    "5000",
			"round_id":   "round-abc",
			"check_type": "tcp",
		},
	})

	assert.NoError(t, err)
	assert.Equal(t, int64(42), job.CheckID)
	assert.Equal(t, "tcp://db.example.com:5432", job.URL)
	assert.Equal(t, 5000, job.Timeout)
	assert.Equal(t, "round-abc", job.RoundID)
	assert.Equal(t, "tcp", job.CheckType)
}

func TestParseCheckJob_WithoutCheckType(t *testing.T) {
	c := &Consumer{}

	job, err := c.parseCheckJob(redis.XMessage{
		ID: "1-0",
		Values: map[string]interface{}{
			"check_id": "7",
			"url":      "https://example.com",
		},
	})

	assert.NoError(t, err)
	assert.Equal(t, "", job.CheckType)
	assert.Equal(t, 30000, job.Timeout)
	assert.Equal(t, "", job.AssertionType)
	assert.Equal(t, "", job.AssertionValue)
}

func TestParseCheckJob_WithAssertion(t *testing.T) {
	c := &Consumer{}

	job, err := c.parseCheckJob(redis.XMessage{
		ID: "1-0",
		Values: map[string]interface{}{
			"check_id":        "42",
			"url":             "https://example.com/health",
			"check_type":      "http",
			"assertion_type":  "keyword_present",
			"assertion_value": "healthy",
		},
	})

	assert.NoError(t, err)
	assert.Equal(t, "keyword_present", job.AssertionType)
	assert.Equal(t, "healthy", job.AssertionValue)
}

func TestIsStale(t *testing.T) {
	now := time.UnixMilli(1793183658800)
	maxAge := 2 * time.Minute

	assert.False(t, isStale("1793183658800-0", now, maxAge), "just dispatched")
	assert.False(t, isStale("1793183538800-0", now, maxAge), "exactly at the limit")
	assert.True(t, isStale("1793183538799-0", now, maxAge), "one millisecond past the limit")
	assert.True(t, isStale("1792933675765-0", now, maxAge), "days old")
	assert.False(t, isStale("1793183718800-0", now, maxAge), "stamped ahead of now")
	assert.False(t, isStale("1792933675765-0", now, 0), "disabled")
	assert.False(t, isStale("not-an-id", now, maxAge), "unparseable IDs are processed")
	assert.False(t, isStale("12345", now, maxAge), "IDs without a sequence are processed")
}
