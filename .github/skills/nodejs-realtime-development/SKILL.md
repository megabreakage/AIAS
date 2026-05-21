---
name: nodejs-realtime-development
description: "Build Node.js backend services, WebSocket real-time systems, and API microservices. Use when creating Express/Fastify APIs, Socket.io/ws WebSocket servers, queue workers, real-time tracking, telemetry pipelines, or event-driven architectures. Triggers: Node.js, WebSocket, real-time, Socket.io, Express, live tracking."
---
# Node.js & Real-Time Development

## When to Use
- Building Node.js API services (Express/Fastify)
- WebSocket servers for real-time communication
- Live tracking and telemetry systems
- Queue workers and event-driven pipelines
- Microservice architecture patterns

## Architecture

```
src/
├── config/                 # Environment, database, Redis config
├── controllers/            # Route handlers
├── middleware/              # Auth, validation, error handling
├── models/                 # Database models (Prisma/TypeORM)
├── services/               # Business logic
├── repositories/           # Data access layer
├── events/                 # Event handlers and emitters
├── websocket/              # WebSocket handlers and rooms
├── jobs/                   # Background job processors
├── types/                  # TypeScript interfaces
└── utils/                  # Shared utilities
```

## WebSocket Patterns

### Socket.io Server Setup
```typescript
const io = new Server(httpServer, {
  cors: { origin: process.env.ALLOWED_ORIGINS?.split(",") },
  pingInterval: 25000,
  pingTimeout: 60000,
});

io.use(authenticateSocket);  // JWT verification middleware

io.on("connection", (socket) => {
  const userId = socket.data.userId;
  socket.join(`user:${userId}`);

  socket.on("subscribe:tracking", (trackingId: string) => {
    socket.join(`tracking:${trackingId}`);
  });
});
```

### Event-Driven Broadcasting
```typescript
// Emit to specific room
io.to(`tracking:${trackingId}`).emit("location:update", {
  lat: payload.latitude,
  lng: payload.longitude,
  timestamp: Date.now(),
});
```

## API Patterns
- Repository pattern for data access
- Service layer for business logic
- DTOs for request/response shaping
- Zod for request validation
- Centralized error handling middleware

## Security
- JWT/OAuth2 for API + WebSocket auth
- Rate limiting per IP and per user
- Input validation at every boundary
- Helmet for HTTP security headers
- CORS configured per environment

## Real-Time Best Practices
- Heartbeat/ping-pong for connection health
- Reconnection with exponential backoff (client)
- Room-based broadcasting for scoped updates
- Redis adapter for multi-instance scaling
- Message acknowledgment for critical events

## Docker
```dockerfile
FROM node:22-alpine
WORKDIR /app
COPY package*.json ./
RUN npm ci --only=production
COPY dist/ ./dist/
EXPOSE 3000
CMD ["node", "dist/main.js"]
```
