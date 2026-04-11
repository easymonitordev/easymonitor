package checker

import (
	"fmt"
	"os/exec"
	"regexp"
	"strconv"
	"strings"
	"time"

	"github.com/easymonitordev/probe-node/pkg/types"
)

// ICMPChecker performs ICMP (ping) checks
type ICMPChecker struct{}

// NewICMPChecker creates a new ICMP checker
func NewICMPChecker() *ICMPChecker {
	return &ICMPChecker{}
}

// Check performs an ICMP ping check on the given host
func (i *ICMPChecker) Check(checkID int64, nodeID, host string, timeout time.Duration) *types.CheckResult {
	result := &types.CheckResult{
		CheckID: checkID,
		NodeID:  nodeID,
		OK:      false,
	}

	// Remove protocol prefix if present
	host = strings.TrimPrefix(host, "http://")
	host = strings.TrimPrefix(host, "https://")

	// Extract hostname (remove path)
	if idx := strings.Index(host, "/"); idx != -1 {
		host = host[:idx]
	}

	// Use ping command (works on Linux and macOS)
	// -c 1: send 1 packet
	// -W timeout: timeout in seconds
	timeoutSec := int(timeout.Seconds())
	if timeoutSec < 1 {
		timeoutSec = 1
	}

	cmd := exec.Command("ping", "-c", "1", "-W", strconv.Itoa(timeoutSec), host)

	start := time.Now()
	output, err := cmd.CombinedOutput()
	elapsed := time.Since(start)

	if err != nil {
		result.Error = fmt.Sprintf("ping failed: %v", err)
		result.ResponseTime = int(elapsed.Milliseconds())
		return result
	}

	// Parse response time from output
	// Example: "time=14.2 ms"
	re := regexp.MustCompile(`time[=\s]+(\d+\.?\d*)\s*ms`)
	matches := re.FindStringSubmatch(string(output))

	if len(matches) > 1 {
		if ms, err := strconv.ParseFloat(matches[1], 64); err == nil {
			result.ResponseTime = int(ms)
			result.OK = true
		}
	}

	if !result.OK {
		result.Error = "failed to parse ping response"
		result.ResponseTime = int(elapsed.Milliseconds())
	}

	return result
}
