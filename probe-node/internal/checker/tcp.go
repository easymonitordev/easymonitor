package checker

import (
	"fmt"
	"net"
	"time"

	"github.com/easymonitordev/probe-node/pkg/types"
)

// TCPChecker performs TCP port connection checks
type TCPChecker struct{}

// NewTCPChecker creates a new TCP checker
func NewTCPChecker() *TCPChecker {
	return &TCPChecker{}
}

// Check attempts a TCP connection to hostPort (e.g. "db.example.com:5432").
// Success means the connection was established; the connect latency is
// recorded as the response time.
func (t *TCPChecker) Check(checkID int64, nodeID, hostPort string, timeout time.Duration) *types.CheckResult {
	result := &types.CheckResult{
		CheckID: checkID,
		NodeID:  nodeID,
		OK:      false,
	}

	start := time.Now()
	conn, err := net.DialTimeout("tcp", hostPort, timeout)
	elapsed := time.Since(start)

	result.ResponseTime = int(elapsed.Milliseconds())

	if err != nil {
		result.Error = fmt.Sprintf("tcp connect failed: %v", err)
		return result
	}

	_ = conn.Close()
	result.OK = true

	return result
}
