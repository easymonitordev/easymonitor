package checker

import (
	"net"
	"testing"
	"time"

	"github.com/stretchr/testify/assert"
)

func TestTCPChecker_Check_Success(t *testing.T) {
	listener, err := net.Listen("tcp", "127.0.0.1:0")
	assert.NoError(t, err)
	defer listener.Close()

	go func() {
		for {
			conn, err := listener.Accept()
			if err != nil {
				return
			}
			conn.Close()
		}
	}()

	checker := NewTCPChecker()
	result := checker.Check(1, "test-node", listener.Addr().String(), 5*time.Second)

	assert.True(t, result.OK)
	assert.Equal(t, int64(1), result.CheckID)
	assert.Equal(t, "test-node", result.NodeID)
	assert.Equal(t, 0, result.StatusCode)
	assert.GreaterOrEqual(t, result.ResponseTime, 0)
	assert.Empty(t, result.Error)
}

func TestTCPChecker_Check_ConnectionRefused(t *testing.T) {
	// Grab a free port, then close the listener so nothing accepts on it.
	listener, err := net.Listen("tcp", "127.0.0.1:0")
	assert.NoError(t, err)
	addr := listener.Addr().String()
	listener.Close()

	checker := NewTCPChecker()
	result := checker.Check(2, "test-node", addr, 2*time.Second)

	assert.False(t, result.OK)
	assert.Contains(t, result.Error, "tcp connect failed")
}

func TestTCPChecker_Check_InvalidHost(t *testing.T) {
	checker := NewTCPChecker()
	result := checker.Check(3, "test-node", "unresolvable.invalid:80", 2*time.Second)

	assert.False(t, result.OK)
	assert.NotEmpty(t, result.Error)
}
