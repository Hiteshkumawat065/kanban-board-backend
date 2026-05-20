# API Reference

All endpoints are JSON. Base URL: `https://your-domain.com/api/v1`

## Authentication

All endpoints except `register` and `login` require a Sanctum bearer token:

```
Authorization: Bearer <your-token-here>
```

---

## POST `/auth/register`

```json
// Request
{
  "name": "Aamir Khan",
  "email": "aamir@example.com",
  "password": "password",
  "password_confirmation": "password"
}

// Response 201
{
  "user": { "id": 1, "name": "Aamir Khan", "email": "aamir@example.com", "avatar_url": "..." },
  "token": "1|abc..."
}
```

## POST `/auth/login`

```json
{ "email": "demo@kanban.test", "password": "password" }
```

## GET `/auth/me`

Returns current authenticated user.

---

## GET `/workspaces`

List workspaces I'm a member of.

```json
{
  "data": [
    {
      "id": 1,
      "name": "Acme Corp",
      "slug": "acme-corp",
      "description": "...",
      "boards_count": 3,
      "members_count": 5,
      "role": "owner",
      "created_at": "2026-05-18T10:00:00+00:00"
    }
  ]
}
```

## POST `/workspaces`

```json
{ "name": "My Team", "description": "..." }
```

## POST `/workspaces/{workspace}/invite`

```json
{ "email": "newbie@example.com", "role": "member" }
```

---

## GET `/boards/{board}`

Full board with lists and cards eager-loaded (single query — no N+1).

```json
{
  "data": {
    "id": 12,
    "name": "Sprint Board",
    "visibility": "workspace",
    "background_color": "#0079bf",
    "lists": [
      {
        "id": 100,
        "name": "To Do",
        "position": 1,
        "cards": [
          {
            "id": 500,
            "title": "Design login page",
            "due_date": "2026-05-25T00:00:00+00:00",
            "is_overdue": false,
            "assignees": [...],
            "labels": [...]
          }
        ]
      }
    ],
    "labels": [...]
  }
}
```

---

## PATCH `/cards/{card}/move`

Move a card to another list or reorder within the same list.

```json
// Request
{
  "list_id": 102,
  "position": 0
}

// Response — also broadcasts CardMoved event on private channel board.{boardId}
```

Position is a **zero-based index** in the destination list.

---

## Examples — curl

```bash
# Login
curl -X POST https://kanban.app/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"demo@kanban.test","password":"password"}'

# Create workspace
curl -X POST https://kanban.app/api/v1/workspaces \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name":"Engineering"}'

# Move card
curl -X PATCH https://kanban.app/api/v1/cards/500/move \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"list_id": 102, "position": 0}'
```

---

## Rate Limits

- Auth endpoints: **5 requests / minute / IP**
- All other authenticated endpoints: **60 requests / minute / user**

Rate-limit headers returned on every response:
```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 58
```

## Error Format

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email field is required."]
  }
}
```

Status codes used:
- `200` OK
- `201` Created
- `204` No Content (delete)
- `401` Unauthenticated (no/invalid token)
- `403` Unauthorized (policy denied)
- `404` Not Found
- `422` Validation failed
- `429` Too Many Requests
