# Installation

## Requirements

- Docker
- Docker Compose

## Deploy

```bash
docker compose up --build -d
```

## Verify

```bash
curl http://localhost:3000/api/email
```

An empty database returns HTTP 429 until emails are imported through `http://localhost:3000/admin/`.

## Backup

Stop the container and copy `data/emails.sqlite`.
