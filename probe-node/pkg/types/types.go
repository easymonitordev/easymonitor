package types

import "time"

// CheckJob represents a monitoring check job from the Redis stream
type CheckJob struct {
	ID             string // Stream entry ID
	CheckID        int64
	URL            string
	Timeout        int    // Timeout in milliseconds
	RoundID        string // Groups results from all probes for the same dispatched check
	CheckType      string // Explicit check type ("http", "icmp", "tcp"); empty on jobs from pre-v0.2.0 servers
	AssertionType  string // Optional response-body assertion ("keyword_present", "keyword_absent"); empty = none
	AssertionValue string // The keyword the assertion looks for
}

// Assertion types the probe knows how to evaluate. The server only sends
// assertion fields when an assertion is configured on the monitor.
const (
	AssertionKeywordPresent = "keyword_present"
	AssertionKeywordAbsent  = "keyword_absent"
)

// KnownAssertionType reports whether this probe can evaluate the given
// assertion type. The empty string (no assertion) is always known.
func KnownAssertionType(assertionType string) bool {
	switch assertionType {
	case "", "none", AssertionKeywordPresent, AssertionKeywordAbsent:
		return true
	default:
		return false
	}
}

// CheckResult represents the result of a monitoring check
type CheckResult struct {
	CheckID       int64
	NodeID        string
	RoundID       string // Echoed back from the job so the server can group per-probe results
	OK            bool
	ResponseTime  int    // Response time in milliseconds
	StatusCode    int    // HTTP status code (0 for non-HTTP checks)
	Error         string // Error message if check failed
	CertExpiresAt int64  // TLS certificate NotAfter as unix seconds (0 when no TLS)
	CertIssuer    string // TLS certificate issuer (empty when no TLS)
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
