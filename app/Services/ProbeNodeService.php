<?php

declare(strict_types=1);

namespace App\Services;

use Firebase\JWT\JWT;

/**
 * Service for managing probe node authentication and JWT tokens
 *
 * This service handles generation and validation of JWT tokens for probe nodes
 * that connect to the monitoring system via Redis Streams.
 */
class ProbeNodeService
{
    /**
     * Generate a JWT token for a probe node
     *
     * @param  string  $nodeId  Unique identifier for the probe node
     * @param  array<string>  $tags  Optional tags for the probe node
     * @param  int  $expiresInDays  Number of days until token expires (default: 365)
     * @return string JWT token
     */
    public function generateToken(string $nodeId, array $tags = [], int $expiresInDays = 365): string
    {
        $secret = config('app.jwt_secret');

        if (empty($secret)) {
            throw new \RuntimeException('JWT_SECRET is not configured');
        }

        $now = now();

        $payload = [
            'node_id' => $nodeId,
            'tags' => $tags,
            'iat' => $now->timestamp,
            'nbf' => $now->timestamp,
            'exp' => $now->addDays($expiresInDays)->timestamp,
        ];

        return JWT::encode($payload, $secret, 'HS256');
    }

    /**
     * Validate a JWT token and return the payload
     *
     * @param  string  $token  The JWT token to validate
     * @return object Decoded token payload
     *
     * @throws \Exception If token is invalid or expired
     */
    public function validateToken(string $token): object
    {
        $secret = config('app.jwt_secret');

        if (empty($secret)) {
            throw new \RuntimeException('JWT_SECRET is not configured');
        }

        return JWT::decode($token, new \Firebase\JWT\Key($secret, 'HS256'));
    }

    /**
     * Extract node ID from a JWT token
     *
     * @param  string  $token  The JWT token
     * @return string The node ID
     */
    public function getNodeIdFromToken(string $token): string
    {
        $payload = $this->validateToken($token);

        return $payload->node_id;
    }
}