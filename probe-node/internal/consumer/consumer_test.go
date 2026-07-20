package consumer

import (
	"testing"

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
}
