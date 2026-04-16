package checker

import (
	"context"
	"crypto/tls"
	"errors"
	"fmt"
	"net"
	"net/http"
	"net/url"
	"strings"
	"time"

	"github.com/easymonitordev/probe-node/pkg/types"
)

// HTTPChecker performs HTTP/HTTPS checks
type HTTPChecker struct {
	client *http.Client
}

// NewHTTPChecker creates a new HTTP checker
func NewHTTPChecker() *HTTPChecker {
	return &HTTPChecker{
		client: &http.Client{
			CheckRedirect: func(req *http.Request, via []*http.Request) error {
				// Allow up to 10 redirects
				if len(via) >= 10 {
					return fmt.Errorf("stopped after 10 redirects")
				}
				return nil
			},
			Transport: &http.Transport{
				TLSClientConfig: &tls.Config{
					InsecureSkipVerify: false, // Verify SSL certificates
				},
				MaxIdleConns:        100,
				MaxIdleConnsPerHost: 10,
				IdleConnTimeout:     90 * time.Second,
			},
		},
	}
}

// Check performs an HTTP check on the given URL
func (h *HTTPChecker) Check(checkID int64, nodeID, url string, timeout time.Duration) *types.CheckResult {
	result := &types.CheckResult{
		CheckID: checkID,
		NodeID:  nodeID,
		OK:      false,
	}

	// Create request with timeout context
	ctx, cancel := context.WithTimeout(context.Background(), timeout)
	defer cancel()

	req, err := http.NewRequestWithContext(ctx, http.MethodGet, url, nil)
	if err != nil {
		result.Error = fmt.Sprintf("failed to create request: %v", err)
		return result
	}

	// Set user agent
	req.Header.Set("User-Agent", "EasyMonitor-Probe/1.0")

	// Measure response time
	start := time.Now()
	resp, err := h.client.Do(req)
	elapsed := time.Since(start)

	result.ResponseTime = int(elapsed.Milliseconds())

	if err != nil {
		result.Error = humanizeHTTPError(err, timeout)
		return result
	}
	defer resp.Body.Close()

	result.StatusCode = resp.StatusCode

	// Consider 2xx and 3xx as successful
	if resp.StatusCode >= 200 && resp.StatusCode < 400 {
		result.OK = true
	} else {
		result.Error = fmt.Sprintf("HTTP %d %s", resp.StatusCode, http.StatusText(resp.StatusCode))
	}

	return result
}

// humanizeHTTPError turns a raw Go HTTP error into a short, readable message
// suitable for display to end users. Falls back to the original error string
// when no specific classification matches.
func humanizeHTTPError(err error, timeout time.Duration) string {
	// Context timeout (the per-check deadline elapsed).
	if errors.Is(err, context.DeadlineExceeded) {
		return fmt.Sprintf("Request timed out after %s", formatDuration(timeout))
	}

	// DNS resolution failures.
	var dnsErr *net.DNSError
	if errors.As(err, &dnsErr) {
		if dnsErr.IsNotFound {
			return fmt.Sprintf("DNS lookup failed: host %q not found", dnsErr.Name)
		}
		return fmt.Sprintf("DNS lookup failed for %q: %s", dnsErr.Name, dnsErr.Err)
	}

	// TLS handshake / certificate issues. The stdlib doesn't expose a single
	// typed error for these, so fall back to substring matching which is
	// stable across versions.
	raw := err.Error()
	if strings.Contains(raw, "tls: ") || strings.Contains(raw, "x509:") {
		if strings.Contains(raw, "certificate has expired") {
			return "TLS certificate has expired"
		}
		if strings.Contains(raw, "certificate is valid for") {
			return "TLS certificate does not match hostname"
		}
		if strings.Contains(raw, "unknown authority") || strings.Contains(raw, "signed by unknown authority") {
			return "TLS certificate signed by unknown authority"
		}
		if strings.Contains(raw, "handshake failure") {
			return "TLS handshake failed"
		}
		return "TLS error: " + trimAfter(raw, ": ")
	}

	// Connection-level failures (refused, reset, unreachable).
	var netOpErr *net.OpError
	if errors.As(err, &netOpErr) {
		switch {
		case strings.Contains(netOpErr.Error(), "connection refused"):
			return "Connection refused"
		case strings.Contains(netOpErr.Error(), "connection reset"):
			return "Connection reset by peer"
		case strings.Contains(netOpErr.Error(), "no route to host"):
			return "No route to host"
		case strings.Contains(netOpErr.Error(), "network is unreachable"):
			return "Network unreachable"
		}
	}

	// Redirect loops (hit the 10-redirect ceiling).
	if strings.Contains(raw, "stopped after 10 redirects") {
		return "Too many redirects (>10)"
	}

	// url.Error wraps most net errors; unwrap one layer so the URL isn't
	// repeated in the message (the monitor already shows its URL).
	var urlErr *url.Error
	if errors.As(err, &urlErr) && urlErr.Err != nil {
		return urlErr.Err.Error()
	}

	return raw
}

// formatDuration renders a timeout like "30s" or "1m30s".
func formatDuration(d time.Duration) string {
	if d < time.Minute {
		return fmt.Sprintf("%ds", int(d.Seconds()))
	}
	return d.Truncate(time.Second).String()
}

// trimAfter returns s with everything up to and including the first occurrence
// of sep removed. Falls back to s if sep isn't found.
func trimAfter(s, sep string) string {
	if i := strings.Index(s, sep); i >= 0 {
		return strings.TrimSpace(s[i+len(sep):])
	}
	return s
}