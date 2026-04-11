package types

import "time"

// CheckJob represents a monitoring check job from the Redis stream
type CheckJob struct {
	ID      string // Stream entry ID
	CheckID int64
	URL     string
	Timeout int // Timeout in milliseconds
}

// CheckResult represents the result of a monitoring check
type CheckResult struct {
	CheckID      int64
	NodeID       string
	OK           bool
	ResponseTime int    // Response time in milliseconds
	StatusCode   int    // HTTP status code (0 for non-HTTP checks)
	Error        string // Error message if check failed
}

// CheckType represents the type of monitoring check
type CheckType int

const (
	CheckTypeHTTP CheckType = iota
	CheckTypeICMP
)

// Checker defines the interface for performing monitoring checks
type Checker interface {
	Check(url string, timeout time.Duration) (*CheckResult, error)
}