package checker

import (
	"net/http"
	"net/http/httptest"
	"testing"
	"time"

	"github.com/stretchr/testify/assert"
)

func TestHTTPChecker_Check_Success(t *testing.T) {
	// Create test server
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		assert.Equal(t, "EasyMonitor-Probe/1.0", r.Header.Get("User-Agent"))
		w.WriteHeader(http.StatusOK)
		w.Write([]byte("OK"))
	}))
	defer server.Close()

	checker := NewHTTPChecker()
	result := checker.Check(1, "test-node", server.URL, 5*time.Second)

	assert.True(t, result.OK)
	assert.Equal(t, int64(1), result.CheckID)
	assert.Equal(t, "test-node", result.NodeID)
	assert.Equal(t, 200, result.StatusCode)
	assert.Greater(t, result.ResponseTime, 0)
	assert.Empty(t, result.Error)
}

func TestHTTPChecker_Check_NotFound(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusNotFound)
	}))
	defer server.Close()

	checker := NewHTTPChecker()
	result := checker.Check(2, "test-node", server.URL, 5*time.Second)

	assert.False(t, result.OK)
	assert.Equal(t, 404, result.StatusCode)
	assert.Contains(t, result.Error, "404")
}

func TestHTTPChecker_Check_Timeout(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		time.Sleep(2 * time.Second)
		w.WriteHeader(http.StatusOK)
	}))
	defer server.Close()

	checker := NewHTTPChecker()
	result := checker.Check(3, "test-node", server.URL, 100*time.Millisecond)

	assert.False(t, result.OK)
	assert.NotEmpty(t, result.Error)
	assert.Contains(t, result.Error, "timed out")
}

func TestHumanizeHTTPError_DNSNotFound(t *testing.T) {
	checker := NewHTTPChecker()
	result := checker.Check(10, "test-node", "https://this-host-does-not-exist-easymonitor.invalid", 5*time.Second)

	assert.False(t, result.OK)
	assert.Contains(t, result.Error, "DNS lookup failed")
}

func TestHumanizeHTTPError_ConnectionRefused(t *testing.T) {
	// 127.0.0.1 on a port nothing is listening on.
	checker := NewHTTPChecker()
	result := checker.Check(11, "test-node", "http://127.0.0.1:1", 2*time.Second)

	assert.False(t, result.OK)
	// Could be "Connection refused" on Linux, other messages on other OSes — be lenient.
	assert.NotContains(t, result.Error, "context deadline")
	assert.NotEmpty(t, result.Error)
}

func TestHTTPChecker_Check_InvalidURL(t *testing.T) {
	checker := NewHTTPChecker()
	result := checker.Check(4, "test-node", "://invalid-url", 5*time.Second)

	assert.False(t, result.OK)
	assert.NotEmpty(t, result.Error)
}

func TestHTTPChecker_Check_Redirect(t *testing.T) {
	redirectServer := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusOK)
	}))
	defer redirectServer.Close()

	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		http.Redirect(w, r, redirectServer.URL, http.StatusMovedPermanently)
	}))
	defer server.Close()

	checker := NewHTTPChecker()
	result := checker.Check(5, "test-node", server.URL, 5*time.Second)

	assert.True(t, result.OK)
	assert.Equal(t, 200, result.StatusCode)
}
