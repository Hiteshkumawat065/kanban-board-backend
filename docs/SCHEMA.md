# Database Schema — Kanban

Detailed schema documentation. Every column, index, foreign key and the *why* behind it.

> Engine: **MySQL 8** (InnoDB) or **PostgreSQL 16**. Character set `utf8mb4` / collation `utf8mb4_unicode_ci`.

---

## Entity Relationship Diagram

```
┌──────────┐         ┌─────────────────────┐         ┌──────────────┐
│  users   │◀────────│  workspace_members  │────────▶│  workspaces  │
└──────────┘         └─────────────────────┘         └──────┬───────┘
     │                                                       │
     │                                                       │
     │                                                       ▼
     │                                            ┌─────────────────────┐
     │                                            │workspace_invitations│
     │                                            └─────────────────────┘
     │                                                       │
     │                                                       ▼
     │              ┌──────────────────┐         ┌──────────────┐
     ├──────────────│  board_members   │────────▶│    boards    │
     │              └──────────────────┘         └──────┬───────┘
     │                                                  │
     │                                                  ▼
     │                                            ┌──────────┐
     │                                            │  lists   │
     │                                            └────┬─────┘
     │                                                 │
     │                                                 ▼
     │                                            ┌──────────┐
     │  ┌──────────────────┐                      │  cards   │
     ├──│  card_assignees  │─────────────────────▶│          │
     │  └──────────────────┘                      └────┬─────┘
     │                                                 │
     │                                                 │
     │                       ┌─────────────────┐       │
     │                       │     labels      │◀──────┤
     │                       └─────────────────┘       │
     │                            ▲                    │
     │                            │                    │
     │                    ┌───────┴────────┐           │
     │                    │  card_label    │◀──────────┤
     │                    └────────────────┘           │
     │                                                 │
     │                       ┌─────────────────┐       │
     │  ┌──────────────────  │   checklists    │◀──────┤
     │  │                    └────────┬────────┘       │
     │  │                             │                │
     │  │                             ▼                │
     │  │                   ┌──────────────────┐       │
     │  │                   │ checklist_items  │       │
     │  │                   └──────────────────┘       │
     │  │                                              │
     │  │  ┌──────────────┐                            │
     ├──┼──│  comments    │◀───────────────────────────┤
     │  │  └──────────────┘                            │
     │  │                                              │
     │  │  ┌──────────────┐                            │
     ├──┼──│ attachments  │◀───────────────────────────┤
     │  │  └──────────────┘                            │
     │  │                                              │
     │  │  ┌──────────────┐                            │
     └──┴──│  activities  │◀───────────────────────────┘
           └──────────────┘
```

---

## Table Specifications

### 1. `users`

Laravel default + custom fields.

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| `id` | `bigint unsigned` (PK, AI) | NO | | |
| `name` | `varchar(120)` | NO | | |
| `email` | `varchar(180)` | NO | | **UNIQUE** |
| `email_verified_at` | `timestamp` | YES | NULL | |
| `password` | `varchar(255)` | NO | | Argon2id |
| `avatar_url` | `varchar(500)` | YES | NULL | |
| `timezone` | `varchar(64)` | NO | `UTC` | IANA TZ |
| `remember_token` | `varchar(100)` | YES | NULL | |
| `created_at` / `updated_at` | `timestamp` | YES | NULL | |

**Indexes**
- `users_email_unique` on `email`

---

### 2. `workspaces`

A workspace is the top-level **tenant** boundary. Everything else cascades from here.

| Column | Type | Null | Notes |
|---|---|---|---|
| `id` | `bigint unsigned` (PK) | NO | |
| `name` | `varchar(120)` | NO | |
| `slug` | `varchar(140)` | NO | **UNIQUE** — used in URLs |
| `description` | `text` | YES | |
| `owner_id` | `bigint unsigned` (FK → users.id) | NO | RESTRICT on delete |
| `avatar_url` | `varchar(500)` | YES | |
| `created_at` / `updated_at` | `timestamp` | YES | |
| `deleted_at` | `timestamp` | YES | Soft delete |

**Indexes**
- `workspaces_slug_unique` on `slug`
- `workspaces_owner_id_index` on `owner_id`

**Why slug?** SEO-friendly URLs like `/w/acme-corp/boards/sprint-12`.

---

### 3. `workspace_members`

Pivot — many-to-many between `users` and `workspaces` with extra `role` and `joined_at`.

| Column | Type | Null | Notes |
|---|---|---|---|
| `id` | `bigint unsigned` (PK) | NO | |
| `workspace_id` | `bigint unsigned` (FK) | NO | CASCADE delete |
| `user_id` | `bigint unsigned` (FK) | NO | CASCADE delete |
| `role` | `enum('owner','admin','member')` | NO | default `member` |
| `joined_at` | `timestamp` | NO | |

**Indexes**
- **UNIQUE** `(workspace_id, user_id)` — prevents duplicate membership
- `(user_id)` index for fast "my workspaces" query

**Why a pivot table?** A user can be in N workspaces with different roles. Storing role here (not on user) supports multi-tenant correctly.

---

### 4. `workspace_invitations`

Pending email invitations.

| Column | Type | Null | Notes |
|---|---|---|---|
| `id` | `bigint unsigned` (PK) | NO | |
| `workspace_id` | `bigint unsigned` (FK) | NO | CASCADE delete |
| `email` | `varchar(180)` | NO | |
| `role` | `enum('admin','member')` | NO | default `member` |
| `token` | `varchar(64)` | NO | **UNIQUE** — random 32-byte hex |
| `invited_by` | `bigint unsigned` (FK → users) | NO | RESTRICT |
| `expires_at` | `timestamp` | NO | typically `now() + 7 days` |
| `accepted_at` | `timestamp` | YES | NULL until accepted |
| `created_at` / `updated_at` | `timestamp` | YES | |

**Indexes**
- `token_unique`
- `(workspace_id, email)` index — quick lookup
- `expires_at` — for cleanup cron

---

### 5. `boards`

| Column | Type | Null | Notes |
|---|---|---|---|
| `id` | `bigint unsigned` (PK) | NO | |
| `workspace_id` | `bigint unsigned` (FK) | NO | CASCADE delete |
| `name` | `varchar(160)` | NO | |
| `description` | `text` | YES | |
| `background_color` | `varchar(20)` | NO | default `#0079bf` |
| `background_url` | `varchar(500)` | YES | optional image |
| `visibility` | `enum('private','workspace','public')` | NO | default `workspace` |
| `position` | `int unsigned` | NO | default 0 |
| `created_by` | `bigint unsigned` (FK → users) | NO | |
| `archived_at` | `timestamp` | YES | |
| `created_at` / `updated_at` | `timestamp` | YES | |
| `deleted_at` | `timestamp` | YES | Soft delete |

**Indexes**
- `(workspace_id, archived_at)` — fast "active boards in workspace" listing
- `(created_by)`

---

### 6. `board_members`

Optional — for boards with `visibility='private'`. Grants per-board access.

| Column | Type | Null | Notes |
|---|---|---|---|
| `id` | `bigint unsigned` (PK) | NO | |
| `board_id` | `bigint unsigned` (FK) | NO | CASCADE |
| `user_id` | `bigint unsigned` (FK) | NO | CASCADE |
| `role` | `enum('admin','member','observer')` | NO | default `member` |

**Indexes**
- UNIQUE `(board_id, user_id)`

---

### 7. `lists`

Columns of a board ("To Do", "In Progress", "Done").

| Column | Type | Null | Notes |
|---|---|---|---|
| `id` | `bigint unsigned` (PK) | NO | |
| `board_id` | `bigint unsigned` (FK) | NO | CASCADE |
| `name` | `varchar(120)` | NO | |
| `color` | `varchar(20)` | YES | hex |
| `position` | `decimal(20,10)` | NO | **see Position Strategy below** |
| `archived_at` | `timestamp` | YES | |
| `created_at` / `updated_at` | `timestamp` | YES | |

**Indexes**
- `(board_id, position)` — composite, used in every load

> **Why `decimal` for position?** See [Position Strategy](#position-strategy).

---

### 8. `cards`

The main entity. Holds task data.

| Column | Type | Null | Notes |
|---|---|---|---|
| `id` | `bigint unsigned` (PK) | NO | |
| `list_id` | `bigint unsigned` (FK) | NO | CASCADE |
| `board_id` | `bigint unsigned` (FK, denormalized) | NO | for query speed |
| `title` | `varchar(255)` | NO | |
| `description` | `mediumtext` | YES | Markdown supported |
| `position` | `decimal(20,10)` | NO | |
| `cover_color` | `varchar(20)` | YES | |
| `cover_url` | `varchar(500)` | YES | optional cover image |
| `due_date` | `timestamp` | YES | |
| `started_at` | `timestamp` | YES | |
| `completed_at` | `timestamp` | YES | NULL = not done |
| `created_by` | `bigint unsigned` (FK → users) | NO | |
| `archived_at` | `timestamp` | YES | |
| `created_at` / `updated_at` | `timestamp` | YES | |
| `deleted_at` | `timestamp` | YES | Soft delete |

**Indexes**
- `(list_id, position)` — for column rendering
- `(board_id, archived_at)` — for full board load
- `(due_date)` — for calendar view
- `(created_by)`

> **Why denormalize `board_id` on cards?** Saves an extra JOIN on every read. Worth the 8 bytes of storage.

---

### 9. `card_assignees`

| Column | Type | Null | Notes |
|---|---|---|---|
| `id` | `bigint unsigned` (PK) | NO | |
| `card_id` | `bigint unsigned` (FK) | NO | CASCADE |
| `user_id` | `bigint unsigned` (FK) | NO | CASCADE |
| `assigned_by` | `bigint unsigned` (FK → users) | NO | |
| `assigned_at` | `timestamp` | NO | |

**Indexes**
- UNIQUE `(card_id, user_id)`
- `(user_id)` — "cards assigned to me"

---

### 10. `labels`

Per-board colored tags.

| Column | Type | Null | Notes |
|---|---|---|---|
| `id` | `bigint unsigned` (PK) | NO | |
| `board_id` | `bigint unsigned` (FK) | NO | CASCADE |
| `name` | `varchar(60)` | YES | nullable — color-only label allowed |
| `color` | `varchar(20)` | NO | hex |
| `created_at` / `updated_at` | `timestamp` | YES | |

**Indexes**
- `(board_id)`

---

### 11. `card_label`

Pivot.

| Column | Type | Null | Notes |
|---|---|---|---|
| `card_id` | `bigint unsigned` (FK) | NO | CASCADE |
| `label_id` | `bigint unsigned` (FK) | NO | CASCADE |

**Primary Key**: composite `(card_id, label_id)` — no separate `id`.

---

### 12. `checklists`

| Column | Type | Null | Notes |
|---|---|---|---|
| `id` | `bigint unsigned` (PK) | NO | |
| `card_id` | `bigint unsigned` (FK) | NO | CASCADE |
| `title` | `varchar(160)` | NO | |
| `position` | `decimal(20,10)` | NO | |
| `created_at` / `updated_at` | `timestamp` | YES | |

---

### 13. `checklist_items`

| Column | Type | Null | Notes |
|---|---|---|---|
| `id` | `bigint unsigned` (PK) | NO | |
| `checklist_id` | `bigint unsigned` (FK) | NO | CASCADE |
| `title` | `varchar(255)` | NO | |
| `is_completed` | `boolean` | NO | default false |
| `position` | `decimal(20,10)` | NO | |
| `completed_by` | `bigint unsigned` (FK → users) | YES | |
| `completed_at` | `timestamp` | YES | |
| `created_at` / `updated_at` | `timestamp` | YES | |

---

### 14. `comments`

| Column | Type | Null | Notes |
|---|---|---|---|
| `id` | `bigint unsigned` (PK) | NO | |
| `card_id` | `bigint unsigned` (FK) | NO | CASCADE |
| `user_id` | `bigint unsigned` (FK) | NO | RESTRICT — keep history if user deleted? Use SET NULL if so |
| `body` | `text` | NO | Markdown |
| `edited_at` | `timestamp` | YES | |
| `created_at` / `updated_at` | `timestamp` | YES | |
| `deleted_at` | `timestamp` | YES | Soft delete |

**Indexes**
- `(card_id, created_at)` — for chronological feed

---

### 15. `attachments`

| Column | Type | Null | Notes |
|---|---|---|---|
| `id` | `bigint unsigned` (PK) | NO | |
| `card_id` | `bigint unsigned` (FK) | NO | CASCADE |
| `uploaded_by` | `bigint unsigned` (FK → users) | NO | |
| `original_name` | `varchar(255)` | NO | |
| `filename` | `varchar(255)` | NO | stored name (UUID) |
| `disk` | `varchar(40)` | NO | `r2`, `s3`, `local` |
| `path` | `varchar(500)` | NO | |
| `mime_type` | `varchar(120)` | NO | |
| `size_bytes` | `bigint unsigned` | NO | |
| `created_at` / `updated_at` | `timestamp` | YES | |

---

### 16. `activities`

Polymorphic audit log. Records every important action.

| Column | Type | Null | Notes |
|---|---|---|---|
| `id` | `bigint unsigned` (PK) | NO | |
| `workspace_id` | `bigint unsigned` (FK) | NO | CASCADE |
| `board_id` | `bigint unsigned` (FK) | YES | nullable for workspace-level activity |
| `card_id` | `bigint unsigned` (FK) | YES | nullable |
| `user_id` | `bigint unsigned` (FK) | NO | who did it |
| `action` | `varchar(60)` | NO | e.g., `card.created`, `card.moved`, `member.invited` |
| `subject_type` | `varchar(120)` | YES | polymorphic — model class |
| `subject_id` | `bigint unsigned` | YES | polymorphic |
| `metadata` | `json` | YES | extra context (old/new values) |
| `created_at` | `timestamp` | YES | |

**Indexes**
- `(board_id, created_at)` — for board activity feed
- `(card_id, created_at)` — for card history
- `(user_id, created_at)` — for "my activity"
- `(subject_type, subject_id)` — polymorphic lookup

> No `updated_at` — activities are immutable.

---

### 17. `notifications` (Laravel default)

Standard table created by `php artisan notifications:table`. Stores in-app notifications.

---

## Position Strategy

Drag-and-drop reordering needs a strategy. Options:

| Strategy | Pros | Cons |
|---|---|---|
| `int` with full re-index on every move | Simple | O(n) writes — kills performance |
| `int` with gaps (10, 20, 30...) | Fewer rewrites | Eventually runs out of gaps |
| **`decimal(20,10)` with midpoint** ⭐ | O(1) writes, virtually infinite gaps | Slightly larger storage |

### Midpoint Algorithm
When inserting between items with positions `A` and `B`, new position = `(A + B) / 2`.

```
Initial:   To Do=1.0      Doing=2.0      Done=3.0

Move Done between To Do and Doing:
           To Do=1.0      Done=1.5       Doing=2.0
```

Run a **normalize job** weekly to reset positions to `1, 2, 3...` and avoid float drift.

---

## Foreign Key Behaviour Summary

| FK | On Delete | Why |
|---|---|---|
| `workspaces.owner_id → users.id` | **RESTRICT** | Can't delete owner with active workspaces |
| `workspace_members.*` | **CASCADE** | Removing workspace removes memberships |
| `boards.workspace_id` | **CASCADE** | Boards belong to workspace |
| `boards.created_by` | **RESTRICT** (or SET NULL) | Preserve history |
| `lists.board_id` | **CASCADE** | |
| `cards.list_id` | **CASCADE** | |
| `cards.board_id` | **CASCADE** | |
| `comments.user_id` | **SET NULL** | Keep comment text if user deleted |
| `activities.*` | **CASCADE** on workspace; SET NULL on user | Audit trail should survive user deletion |

---

## Soft Deletes

These tables use soft deletes (`deleted_at`):
- `workspaces`
- `boards`
- `cards`
- `comments`

Allows "Recently deleted" restore feature.

---

## Indexing Cheat Sheet (Performance)

Most expensive queries and the indexes that support them:

| Query | Index Used |
|---|---|
| Load full board (lists + cards) | `lists(board_id, position)` + `cards(list_id, position)` |
| My active workspaces | `workspace_members(user_id)` |
| My assigned cards | `card_assignees(user_id)` |
| Card due today | `cards(due_date)` |
| Recent activity on board | `activities(board_id, created_at)` |
| Search by slug | `workspaces(slug)` unique |

Run `EXPLAIN` on every query in production before shipping.

---

## Sample Queries

### Load entire board efficiently (no N+1)

```php
$board = Board::with([
    'lists' => fn ($q) => $q->orderBy('position'),
    'lists.cards' => fn ($q) => $q->orderBy('position')
        ->whereNull('archived_at'),
    'lists.cards.assignees:id,name,avatar_url',
    'lists.cards.labels:id,name,color',
])->findOrFail($boardId);
```

### Move card (atomic, transactional)

```php
DB::transaction(function () use ($card, $newListId, $newPosition) {
    $card->update([
        'list_id'  => $newListId,
        'position' => $newPosition,
    ]);
    Activity::log('card.moved', $card, [
        'from_list' => $card->getOriginal('list_id'),
        'to_list'   => $newListId,
    ]);
    broadcast(new CardMoved($card))->toOthers();
});
```

---

## Data Constraints (Application Level)

Some rules cannot be expressed at DB level — enforce in Models/Policies:

1. Only `owner` role can delete a workspace.
2. Cannot demote the **last owner** of a workspace.
3. Workspace must have at least 1 owner at all times.
4. Cards cannot move between boards (only between lists *within* a board).
5. Labels are scoped to their board — cannot attach a label from board A to a card in board B.
6. Maximum 50 lists per board, 1000 cards per board (free tier limits).
