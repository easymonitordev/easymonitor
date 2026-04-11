package auth

import (
	"testing"
	"time"

	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/require"
)

func TestGenerateToken(t *testing.T) {
	secret := "test-secret-key-123"
	nodeID := "test-node-1"
	tags := []string{"us-east-1", "production"}

	token, err := GenerateToken(nodeID, tags, secret, 1*time.Hour)
	require.NoError(t, err)
	assert.NotEmpty(t, token)
}

func TestValidateToken_Success(t *testing.T) {
	secret := "test-secret-key-123"
	nodeID := "test-node-1"
	tags := []string{"us-east-1", "production"}

	token, err := GenerateToken(nodeID, tags, secret, 1*time.Hour)
	require.NoError(t, err)

	claims, err := ValidateToken(token, secret)
	require.NoError(t, err)
	assert.Equal(t, nodeID, claims.NodeID)
	assert.Equal(t, tags, claims.Tags)
}

func TestValidateToken_ExpiredToken(t *testing.T) {
	secret := "test-secret-key-123"
	nodeID := "test-node-1"
	tags := []string{"us-east-1"}

	// Generate token that expires immediately
	token, err := GenerateToken(nodeID, tags, secret, -1*time.Second)
	require.NoError(t, err)

	_, err = ValidateToken(token, secret)
	assert.Error(t, err)
	assert.Contains(t, err.Error(), "expired")
}

func TestValidateToken_InvalidSecret(t *testing.T) {
	secret := "test-secret-key-123"
	wrongSecret := "wrong-secret"
	nodeID := "test-node-1"

	token, err := GenerateToken(nodeID, nil, secret, 1*time.Hour)
	require.NoError(t, err)

	_, err = ValidateToken(token, wrongSecret)
	assert.Error(t, err)
}

func TestValidateToken_MalformedToken(t *testing.T) {
	secret := "test-secret-key-123"
	malformedToken := "not.a.valid.jwt.token"

	_, err := ValidateToken(malformedToken, secret)
	assert.Error(t, err)
}

func TestValidateToken_EmptyToken(t *testing.T) {
	secret := "test-secret-key-123"

	_, err := ValidateToken("", secret)
	assert.Error(t, err)
}
