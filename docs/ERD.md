# Entity-Relationship Diagram

> Status: **DRAFT — awaiting approval** (Phase 4 checkpoint). Migrations will be
> written only after this schema is approved.

## Diagram

```mermaid
erDiagram
    USERS ||--o{ POSTS : "writes"
    USERS ||--o{ COMMENTS : "writes (when logged in)"
    USERS ||--o{ ACTIVITY_LOGS : "performs"
    CATEGORIES ||--o{ POSTS : "groups"
    POSTS ||--o{ COMMENTS : "receives"
    POSTS }o--o{ TAGS : "post_tag"
    COMMENTS ||--o{ COMMENTS : "parent / replies"

    USERS {
        bigint id PK
        string name
        string email UK
        timestamp email_verified_at "nullable"
        string password "bcrypt hash"
        string role "enum: admin | author"
        string remember_token
        timestamps created_updated
    }

    CATEGORIES {
        bigint id PK
        string name
        string slug UK
        text description "nullable"
        timestamps created_updated
    }

    POSTS {
        bigint id PK
        bigint user_id FK "restrict on delete"
        bigint category_id FK "nullable, null on delete"
        string title
        string slug UK
        text excerpt "nullable"
        longtext body "Markdown source"
        string cover_image "nullable, storage path"
        string status "enum: draft | published | scheduled"
        timestamptz published_at "nullable, indexed"
        bigint view_count "default 0"
        timestamptz deleted_at "soft delete"
        timestamps created_updated
    }

    TAGS {
        bigint id PK
        string name
        string slug UK
        timestamps created_updated
    }

    POST_TAG {
        bigint post_id PK_FK "cascade on delete"
        bigint tag_id PK_FK "cascade on delete"
    }

    COMMENTS {
        bigint id PK
        bigint post_id FK "cascade on delete"
        bigint parent_id FK "nullable, self-reference, cascade"
        bigint user_id FK "nullable, null on delete"
        string author_name
        string author_email
        text body
        string status "enum: pending | approved | rejected"
        timestamps created_updated
    }

    ACTIVITY_LOGS {
        bigint id PK
        bigint user_id FK "nullable, null on delete"
        string action "e.g. post.published, comment.approved"
        string subject_type "polymorphic"
        bigint subject_id "polymorphic"
        timestamp created_at "no updated_at"
    }
```

## Rationale, relationship by relationship

| Relationship | Eloquent methods | Why this shape |
|---|---|---|
| User 1—N Post | `User::posts()` hasMany / `Post::user()` belongsTo | One author writes many posts. FK is `restrictOnDelete`: you cannot delete a user who still owns content — protects against accidental content loss. |
| Category 1—N Post | `Category::posts()` hasMany / `Post::category()` belongsTo | The brief fixes one category per post. FK nullable + `nullOnDelete`: deleting a category leaves posts standing, just uncategorized. |
| Post N—M Tag | `Post::tags()` / `Tag::posts()` belongsToMany via `post_tag` | Classic many-to-many. Composite primary key `(post_id, tag_id)` prevents duplicate attachments at the DB level; both FKs cascade because a pivot row is meaningless without either side. |
| Post 1—N Comment | `Post::comments()` hasMany / `Comment::post()` belongsTo | Cascade delete: comments have no life without their post. |
| Comment 1—N Comment | `Comment::replies()` hasMany / `Comment::parent()` belongsTo (self) | Self-referencing FK gives threaded replies. One level is rendered nested; deeper levels flatten (UI decision, not schema). |
| User 1—N Comment (nullable) | `User::comments()` hasMany | Guests comment with name+email; a logged-in user's id is stored so their comments can be styled/trusted differently. `nullOnDelete` keeps the comment if the account goes. |
| User → Comments **through** Posts | `User::commentsOnPosts()` **hasManyThrough** | "All comments my posts received" — powers the admin dashboard and demonstrates `hasManyThrough` with a real need. |
| ActivityLog → subject | `ActivityLog::subject()` **morphTo**; `Post/Comment::activityLogs()` morphMany | The **polymorphic** demo with a genuine job: one audit table records admin actions on any model, feeding the dashboard's "recent activity" list. |

## Field decisions worth defending

- **`posts.status` enum + `published_at`** — "scheduled" is an explicit status (as the
  brief requires); a scheduled post becomes `published` when the custom Artisan command
  `posts:publish-scheduled` (run by the scheduler container every minute) sees
  `published_at <= now()`. A post is publicly visible only when
  `status = published AND published_at <= now()` — that compound rule lives in one
  query scope, `Post::published()`.
- **`body` stores Markdown, not HTML.** Rendering happens through an accessor that
  converts Markdown with HTML stripped — that is what will make `{!! !!}` output safe
  (documented when we build it).
- **`view_count` on the row** — simplest honest counter (atomic `increment()`, no
  `updated_at` bump). A separate views table would allow analytics but is out of scope.
- **Soft deletes only on `posts`** — trash/restore is a real editorial workflow.
  Categories/tags/comments delete hard; their FKs define the blast radius instead.
- **PostgreSQL types**: enums stored as `string` columns with a CHECK constraint via
  PHP-enum casts (portable, migration-friendly) rather than native PG enum types
  (painful to alter).

## Indexes (performance requirement from the brief)

| Table | Index | Serves |
|---|---|---|
| posts | `slug` unique | route-model binding by slug |
| posts | `(status, published_at)` composite | the `published()` scope on every public page |
| posts | `user_id`, `category_id` | FK joins + admin filters |
| post_tag | PK `(post_id, tag_id)` + `tag_id` | both directions of the N-M |
| comments | `(post_id, status)` | "approved comments of this post" |
| comments | `parent_id`, `user_id` | reply threads, author lookups |
| activity_logs | `(subject_type, subject_id)` | morphTo lookups |
| categories/tags | `slug` unique | archive pages by slug |

## Migration order (FK-safe)

1. `create_categories_table`
2. `create_posts_table` (needs users ✓ skeleton, categories)
3. `create_tags_table`
4. `create_post_tag_table`
5. `create_comments_table`
6. `create_activity_logs_table`
7. `add_role_to_users_table` (extends the skeleton's users table)
