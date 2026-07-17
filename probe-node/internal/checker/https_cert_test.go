package checker

import (
	"net/http"
	"net/http/httptest"
	"testing"
	"time"

	"github.com/stretchr/testify/assert"
)

func TestHTTPChecker_Check_CapturesCertificateExpiry(t *testing.T) {
	server := httptest.NewTLSServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusOK)
	}))
	defer server.Close()

	// The test server uses a self-signed certificate, so use its trusting
	// client transport instead of the default verifying one.
	checker := &HTTPChecker{client: server.Client()}
	result := checker.Check(1, "test-node", server.URL, 5*time.Second)

	assert.True(t, result.OK)
	assert.Greater(t, result.CertExpiresAt, time.Now().Unix())
}

func TestHTTPChecker_Check_NoCertificateForPlainHTTP(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusOK)
	}))
	defer server.Close()

	checker := NewHTTPChecker()
	result := checker.Check(2, "test-node", server.URL, 5*time.Second)

	assert.True(t, result.OK)
	assert.Equal(t, int64(0), result.CertExpiresAt)
	assert.Empty(t, result.CertIssuer)
}
