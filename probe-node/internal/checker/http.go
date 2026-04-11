package checker

import (
	"context"
	"crypto/tls"
	"fmt"
	"net/http"
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
		result.Error = err.Error()
		return result
	}
	defer resp.Body.Close()

	result.StatusCode = resp.StatusCode

	// Consider 2xx and 3xx as successful
	if resp.StatusCode >= 200 && resp.StatusCode < 400 {
		result.OK = true
	} else {
		result.Error = fmt.Sprintf("HTTP status: %d %s", resp.StatusCode, resp.Status)
	}

	return result
}