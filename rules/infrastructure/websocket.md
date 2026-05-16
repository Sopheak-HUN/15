# Skill: Scalable WebSocket Implementation (Multi-Instance)

## Context
Use this skill when implementing real-time features (Notifications, Live Dashboards, Chat) that must scale across multiple server instances (Cluster). This ensures that messages are broadcasted correctly to all connected clients regardless of which server node they are connected to.

## Guidelines

### 1. Broadcasting Infrastructure
- **Broadcaster**: Use **Laravel Reverb** (recommended for Laravel 11+) or **Redis** as the broadcasting driver.
- **Shared Store**: A centralized **Redis** instance must be used as the Pub/Sub backend to synchronize events across all application nodes.
- **Sticky Sessions**: Ensure the Load Balancer (e.g., Nginx, HAProxy) is configured with sticky sessions (Session Affinity) if using long-polling fallbacks, though pure WebSockets are preferred.

### 2. Multi-Tenant Scoping
- **Channel Isolation**: All WebSocket channels MUST be prefixed with the tenant `handle` to prevent cross-tenant message leakage.
- **Pattern**: `{tenant_handle}.{channel_name}.{resource_id}`
- **Example**: `client-a.orders.123`

### 3. Authentication & Security
- **Private Channels**: Use **Laravel Passport** to authorize access to private and presence channels.
- **Authorization Logic**: Channel authorization MUST verify that the authenticated user belongs to the tenant specified in the channel prefix.
- **TLS/SSL**: All WebSocket traffic (`wss://`) must be encrypted in transit.

### 4. Event Implementation
- **ShouldBroadcast**: Events must implement the `ShouldBroadcast` or `ShouldBroadcastNow` interface.
- **Queueing**: Always use a queue worker to handle broadcasting to prevent API latency.
- **Payloads**: Keep broadcast payloads lightweight. Send the `id` of the updated resource and let the client decide if it needs to fetch the full data via a REST API call.

## Best Practices
- **Presence Channels**: Use Presence channels for "Who is online" features, ensuring the data is stored in the shared Redis instance.
- **Rate Limiting**: Apply rate limiting to client-side event emission (client-to-server) to prevent DoS attacks on the WebSocket server.
- **Connection Monitoring**: Implement a "Heartbeat" or "Ping/Pong" mechanism to detect and clean up stale connections in the cluster.

## Troubleshooting
- **Missing Events**: If an event is triggered on Server A but not received by a client on Server B, verify that both servers are connected to the same Redis instance and using the same `REDIS_PREFIX`.
- **Auth Failures**: Ensure the `BroadcastServiceProvider` is correctly configured and the CSRF/Passport tokens are being sent in the `Echo` configuration.
- **Connection Drops**: Check for Load Balancer timeouts (e.g., `proxy_read_timeout` in Nginx) and increase them for WebSocket paths.
