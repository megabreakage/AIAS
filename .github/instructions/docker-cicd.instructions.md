---
description: "Use when writing Dockerfiles, docker-compose configurations, CI/CD pipelines, GitHub Actions workflows, deployment scripts, or cloud infrastructure configs. Covers containerization, multi-stage builds, and automated pipelines."
applyTo: ["**/Dockerfile*", "**/docker-compose*", "**/.github/workflows/**", "**/*.yml", "**/*.yaml"]
---
# Docker & CI/CD Standards

## Docker
- Multi-stage builds — separate build and runtime stages
- Alpine-based images for minimal size
- Non-root user for runtime container
- `.dockerignore` to exclude vendor, node_modules, tests
- Health checks in Dockerfiles and compose

## Compose
- Named volumes for persistent data
- Networks for service isolation
- Environment files (`.env`) — never hardcode secrets
- Depends-on with health check conditions

## CI/CD Pipeline Stages
1. **Lint**: Pint (PHP), ESLint (JS/TS), ktlint (Kotlin)
2. **Test**: Parallel PEST, Jest, Android unit tests
3. **Build**: Docker image, frontend assets, APK/AAB
4. **Deploy**: Cloud-native deployment (Laravel Cloud, K8s)

## Security
- Scan images for vulnerabilities (Trivy/Snyk)
- Pin dependency versions — no `latest` tags
- Secrets via environment variables or vault — never in code
- Separate credentials per environment
