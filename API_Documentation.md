# Islamic Content Platform — API Documentation

**Version:** v1
**Base URL:** `http://localhost/api/v1`
**Format:** JSON (UTF-8)
**Intended audience:** Frontend developers & AI (FastAPI) developers.

---

## 1. Overview

The platform exposes a REST API that lets admins upload documents, run AI operations (summarization / question generation), approve AI outputs, and publish quizzes that the public can take by a unique token.

Two consumer types:

- **Frontend (admin dashboard + public quiz page):** consumes all endpoints below, authenticating with a Bearer token.
- **AI service (FastAPI):** receives document text via an internal endpoint and returns structured output that is stored verbatim (see section 9).

---

## 2. Authentication

All admin endpoints require a token issued at login.

- **Login** returns `data.token` (Sanctum plain-text token).
- Send it in every protected request as:

```
Authorization: Bearer <token>
```

- Public quiz endpoints do **not** require authentication.
- Super-admin-only endpoints additionally require the logged-in user to have `role = super_admin`.

---

## 3. Standard Response Envelope

Every endpoint responds with the same envelope:

```json
{
  "success": true,
  "data": { ... },
  "message": "تم تنفيذ الطلب بنجاح."
}
```

| Field     | Type    | Description                                |
|-----------|---------|--------------------------------------------|
| `success` | boolean | `true` on success, `false` on error        |
| `data`    | any     | Payload (`null` when there is nothing)     |
| `message` | string  | Human-readable message (Arabic)            |

### 3.1 Pagination

Collection endpoints paginate (15 per page). `data` then has the Laravel paginator shape:

```json
{
  "current_page": 1,
  "data": [ ... ],
  "first_page_url": "...",
  "from": 1,
  "last_page": 3,
  "last_page_url": "...",
  "links": [ ... ],
  "next_page_url": "...",
  "path": "http://localhost/api/v1/documents",
  "per_page": 15,
  "prev_page_url": null,
  "to": 15,
  "total": 42
}
```

Use `?page=N` to navigate pages.

### 3.2 Validation errors (HTTP 422)

Form validation does **not** use the envelope; it returns Laravel's default format:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "category": ["The selected category is invalid."]
  }
}
```

---

## 4. HTTP Status Codes

| Code | Meaning                                                   |
|------|-----------------------------------------------------------|
| 200  | OK                                                        |
| 201  | Created                                                    |
| 401  | Unauthenticated / wrong credentials                       |
| 403  | Authenticated but not allowed (e.g., not super_admin)     |
| 404  | Not found                                                  |
| 409  | Conflict (e.g., email already submitted a quiz)           |
| 410  | Gone (quiz exists but is inactive)                        |
| 422  | Validation error                                          |
| 429  | Monthly AI operations limit exceeded                      |
| 500  | Internal error (e.g., AI service unreachable)             |

---

## 5. Public Endpoints

### 5.1 `POST /login` — Admin login

**Auth:** none

**Request body (JSON):**

| Field      | Type   | Rules                  |
|------------|--------|------------------------|
| `email`    | string | required, valid email  |
| `password` | string | required               |

**Response 200:**

```json
{
  "success": true,
  "data": {
    "token": "1|abc123...",
    "user": { "id": 1, "name": "Super Admin", "email": "superadmin@ertiqaa.com", "role": "super_admin" }
  },
  "message": "تم تسجيل الدخول بنجاح."
}
```

**Errors:** `401` invalid credentials · `403` account disabled (`is_active = false`).

---

### 5.2 `GET /quizzes/{token}` — Public quiz view

**Auth:** none

Returns the quiz questions so participants can take the test. The participant must **not** receive the correct answers here — only the questions.

**Response 200:**

```json
{
  "success": true,
  "data": {
    "id": 3,
    "unique_token": "a1b2c3...32chars",
    "questions": [
      { "question": "ما هي أركان الإسلام؟" },
      { "question": "كم عدد الصلوات المفروضة؟" }
    ]
  },
  "message": "تم جلب الاختبار بنجاح."
}
```

**Errors:** `404` token not found · `410` quiz inactive.

---

### 5.3 `POST /quizzes/{token}/submit` — Submit quiz answers

**Auth:** none

The frontend returns the participant's answers together with the **correct answers it received from... the participant must not be able to forge the score.** Score is computed server-side by comparing `participant_answer` with `correct_answer` (case-insensitive, trimmed). Note: the current contract trusts the submitted `correct_answer`; see section 9.4 for the recommended tamper-proof flow.

**Request body (JSON):**

| Field         | Type   | Rules                            |
|---------------|--------|----------------------------------|
| `name`        | string | required, max 255                |
| `email`       | string | required, valid email, max 255   |
| `answers`     | array  | required, min 1                  |
| `answers[].question_text`      | string | required, max 1000 |
| `answers[].participant_answer` | string | required, max 1000 |
| `answers[].correct_answer`     | string | required, max 1000 |

**Response 201:**

```json
{
  "success": true,
  "data": { "score": 4, "total": 5, "submission_id": 12 },
  "message": "تم إرسال إجاباتك بنجاح."
}
```

**Errors:** `404` token not found · `410` inactive · `409` this email already submitted this quiz.

---

## 6. Authenticated Admin Endpoints

> All require `Authorization: Bearer <token>`.

### 6.1 Documents

#### `GET /documents` — List documents

**Auth:** any admin

Response: paginated documents, each with nested `uploader` and `operations`.

```json
{
  "current_page": 1,
  "data": [
    {
      "id": 1,
      "uploader_id": 1,
      "source_type": "text",
      "extracted_text": "نص الدرس...",
      "created_at": "2026-08-05T16:58:29.000000Z",
      "updated_at": "2026-08-05T16:58:29.000000Z",
      "uploader": { "id": 1, "name": "Super Admin", "email": "superadmin@ertiqaa.com" },
      "operations": [
        { "id": 2, "document_id": 1, "category": "aqeedah", "operation_type": "summarization", "status": "completed" }
      ]
    }
  ],
  "total": 1
}
```

#### `POST /documents` — Upload a document

**Auth:** any admin · **Content-Type:** `multipart/form-data`

| Field          | Type   | Rules                                              |
|----------------|--------|----------------------------------------------------|
| `source_type`  | string | required, `text` or `pdf`                          |
| `text_content` | string | required if `source_type = text`; max 200000       |
| `file`         | file   | required if `source_type = pdf`; `mimes:pdf`, max 20480 KB (20 MB) |

- For `pdf`: text is extracted server-side (`pdftotext`); the file is not stored permanently.
- All text is sanitized (`strip_tags`).

**Response 201:** the created document object.

**Errors:** `422` validation / PDF contains no extractable text.

#### `GET /documents/{document}` — Show a document

**Auth:** any admin

Returns the document with nested `uploader` and full `operations`.

#### `PUT /documents/{document}` — Update a document

**Auth:** any admin · **Content-Type:** `application/json`

| Field             | Type   | Rules                                 |
|-------------------|--------|---------------------------------------|
| `source_type`     | string | optional, `text` or `pdf`             |
| `extracted_text`  | string | optional, max 200000 (sanitized)      |

**Response 200:** updated document.

#### `DELETE /documents/{document}` — Delete a document

**Auth:** any admin

**Response 200:** `{ "success": true, "data": null, "message": "تم حذف المستند بنجاح." }`

---

### 6.2 `POST /operations` — Run an AI operation

**Auth:** any admin · **Content-Type:** `application/json`

| Field            | Type   | Rules                                                      |
|------------------|--------|------------------------------------------------------------|
| `document_id`    | integer| required, must exist                                        |
| `category`       | string | required, `aqeedah` \| `fiqh` \| `seerah` \| `tazkiyah`     |
| `operation_type` | string | required, `summarization` \| `question_generation`         |

**Behavior:**
1. Checks the monthly limit from `global_settings.ai_operations_monthly_limit` (0 = unlimited). Operations counted are those created in the current calendar month.
2. Creates an `Operation` with `status = pending`.
3. Synchronously calls the internal AI service (`POST {FASTAPI_INTERNAL_URL}/process`, 60s timeout) — see section 9.
4. On success: stores the entire response body as `ai_outputs.raw_json`, sets `status = completed`.
5. On failure: sets `status = failed`, returns 500 — **no `ai_outputs` row is created**.

**Response 201:**

```json
{
  "success": true,
  "data": {
    "id": 5,
    "operation_id": 5,
    "raw_json": { "type": "summarization", "summary": "...", "key_points": ["..."] },
    "created_at": "...",
    "updated_at": "..."
  },
  "message": "تم تنفيذ العملية بنجاح."
}
```

**Errors:** `429` monthly limit exceeded · `500` AI service unreachable/timeout/failure.

---

### 6.3 `POST /approved-versions` — Approve an AI output

**Auth:** any admin · **Content-Type:** `application/json`

| Field            | Type   | Rules                                  |
|------------------|--------|----------------------------------------|
| `ai_output_id`   | integer| required, must exist                   |
| `edited_content` | object | required, the admin-edited JSON        |

Creates a **new** `ApprovedVersion` inside a DB transaction. The original `ai_outputs.raw_json` is **never** overwritten. `approver_id` is set to the authenticated user.

**Response 201:**

```json
{
  "success": true,
  "data": {
    "id": 1,
    "ai_output_id": 5,
    "edited_content": { "summary": "النسخة المعدّلة..." },
    "approver_id": 1,
    "created_at": "...",
    "updated_at": "..."
  },
  "message": "تم اعتماد النسخة بنجاح."
}
```

---

### 6.4 Quiz management

#### `POST /quizzes` — Publish a quiz from an approved version

**Auth:** any admin · **Content-Type:** `application/json`

| Field                | Type   | Rules                       |
|----------------------|--------|-----------------------------|
| `approved_version_id`| integer| required, must exist        |

The server generates a unique 32-char `unique_token` and sets `is_active = true`.

**Response 201:**

```json
{
  "success": true,
  "data": {
    "id": 3,
    "approved_version_id": 1,
    "unique_token": "f3Kd...32chars",
    "is_active": true,
    "created_at": "...",
    "updated_at": "..."
  },
  "message": "تم إنشاء الاختبار بنجاح."
}
```

**Note for frontend:** the shareable quiz URL is `FRONTEND_URL/quizzes/{unique_token}`.

#### `GET /quizzes/{quiz}/results` — Quiz results (admin)

**Auth:** any admin

Returns the quiz with nested `approved_version.ai_output.operation` and all `submissions` (each with `answers`).

```json
{
  "success": true,
  "data": {
    "id": 3,
    "approved_version_id": 1,
    "unique_token": "...",
    "is_active": true,
    "approved_version": {
      "id": 1,
      "ai_output_id": 5,
      "edited_content": { ... },
      "ai_output": {
        "id": 5,
        "raw_json": { ... },
        "operation": { "id": 5, "category": "aqeedah", "operation_type": "question_generation", "status": "completed" }
      }
    },
    "submissions": [
      {
        "id": 12,
        "quiz_id": 3,
        "name": "أحمد",
        "email": "ahmed@example.com",
        "score": 4,
        "answers": [
          {
            "id": 41,
            "submission_id": 12,
            "question_text": "...",
            "participant_answer": "...",
            "correct_answer": "...",
            "is_correct": true
          }
        ]
      }
    ]
  }
}
```

---

## 7. Super-Admin Only Endpoints

> Require `Authorization: Bearer <token>` **and** `role = super_admin`.
> Non-super admins receive **403**.

### 7.1 Sub-admins

#### `GET /sub-admins` — List sub-admins

Paginated list of users with `role = sub_admin`.

```json
{ "id": 2, "name": "علي", "email": "ali@example.com", "is_active": true, "created_at": "..." }
```

#### `POST /sub-admins` — Create a sub-admin

| Field       | Type    | Rules                              |
|-------------|---------|------------------------------------|
| `name`      | string  | required, max 255                  |
| `email`     | string  | required, valid email, unique      |
| `password`  | string  | required, min 8, max 255           |
| `is_active` | boolean | optional (default `true`)          |

**Response 201:** `{ id, name, email, is_active }`

#### `GET /sub-admins/{sub_admin}` — Show a sub-admin

#### `PUT /sub-admins/{sub_admin}` — Update a sub-admin

| Field       | Type    | Rules                                       |
|-------------|---------|---------------------------------------------|
| `name`      | string  | optional, max 255                           |
| `email`     | string  | optional, valid email, unique (excl. self)  |
| `password`  | string  | optional, min 8                             |
| `is_active` | boolean | optional                                    |

#### `DELETE /sub-admins/{sub_admin}` — Delete a sub-admin

Cannot delete your own account. **Errors:** `404` if target is not a sub-admin · `422` self-deletion.

---

### 7.2 `PUT /settings` — Update global settings

| Field      | Type  | Rules                                  |
|------------|-------|----------------------------------------|
| `settings` | object| required, min 1 entry; values are strings, max 255 |

Settings are upserted by key.

```json
{
  "settings": {
    "ai_operations_monthly_limit": "50"
  }
}
```

**Response 200:**

```json
{
  "success": true,
  "data": { "ai_operations_monthly_limit": "50" },
  "message": "تم تحديث الإعدادات بنجاح."
}
```

---

## 8. Enum Values (used across endpoints)

| Field              | Allowed values                                          |
|--------------------|---------------------------------------------------------|
| `role`             | `super_admin`, `sub_admin`                              |
| `is_active`        | `true`, `false`                                         |
| `source_type`      | `text`, `pdf`                                           |
| `category`         | `aqeedah`, `fiqh`, `seerah`, `tazkiyah`                 |
| `operation_type`   | `summarization`, `question_generation`                  |
| `operation.status` | `pending`, `completed`, `failed`                        |
| `setting_key`      | `ai_operations_monthly_limit` (extensible)              |

---

## 9. AI Service (FastAPI) Integration Contract

### 9.1 Endpoint called by Laravel

```
POST {FASTAPI_INTERNAL_URL}/process
Content-Type: application/json
Timeout: 60 seconds (synchronous)
```

`FASTAPI_INTERNAL_URL` is configured in Laravel's `.env` (e.g. `http://fastapi-service:8000`).

### 9.2 Request body (Laravel → FastAPI)

```json
{
  "operation_id": 5,
  "document_id": 1,
  "category": "aqeedah",
  "operation_type": "summarization",
  "text": "نص المستند الكامل المستخرج..."
}
```

| Field            | Type   | Description                                      |
|------------------|--------|--------------------------------------------------|
| `operation_id`   | int    | Backend operation row id (for tracing)           |
| `document_id`    | int    | Source document id                               |
| `category`       | string | one of the category enums                        |
| `operation_type` | string | `summarization` or `question_generation`         |
| `text`           | string | Full extracted document text (max 200000 chars)  |

### 9.3 Response (FastAPI → Laravel)

Return **HTTP 200** with a JSON body. The **entire body** is stored verbatim in `ai_outputs.raw_json` and returned to the frontend. Recommended structures:

**`summarization`:**

```json
{
  "type": "summarization",
  "title": "عنوان مختصر",
  "summary": "ملخص النص...",
  "key_points": ["نقطة 1", "نقطة 2"]
}
```

**`question_generation`:**

```json
{
  "type": "question_generation",
  "questions": [
    {
      "question": "ما هي أركان الإسلام؟",
      "correct_answer": "خمسة أركان",
      "options": ["خمسة أركان", "أربعة أركان", "ثلاثة أركان", "ستة أركان"]
    }
  ]
}
```

### 9.4 Important notes for AI developers

- **Quiz questions must live under the `questions` key.** The public quiz endpoint reads `approved_version.edited_content["questions"]`. When admins edit and approve a quiz, they submit `edited_content` containing `{ "questions": [ ... ] }`.
- **`raw_json` must be valid JSON** (the DB column is JSON-typed).
- **Tamper-proofing:** the current implementation compares the participant's answer against the `correct_answer` **submitted by the frontend**. Recommended improvement: store a checksum/hash of the questions in the quiz and reject submissions whose answers don't match the approved version. Coordinate with the backend team before relying on score integrity in production.
- **Failures:** if FastAPI returns any non-2xx status, times out (60s), or is unreachable, Laravel returns `500` to the frontend with message `تعذّر الاتصال بخدمة الذكاء الاصطناعي...` and marks the operation `failed`. No partial data is stored.
- **Rate/limit:** Laravel enforces the monthly operation limit **before** calling FastAPI, so FastAPI should not need its own quota logic.

---

## 10. CORS

- Only `FRONTEND_URL` (default `http://localhost:3000`) is allowed to call the API from a browser.
- `supports_credentials = true`, so you may send cookies/credentials; token auth is the primary mechanism.

---

## 11. Quick Examples (curl)

**Login:**

```bash
curl -X POST http://localhost/api/v1/login \
  -H "Content-Type: application/json" \
  -d '{"email":"superadmin@ertiqaa.com","password":"superadmin"}'
```

**Create a text document:**

```bash
curl -X POST http://localhost/api/v1/documents \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"source_type":"text","text_content":"محتوى الدرس"}'
```

**Run an AI operation:**

```bash
curl -X POST http://localhost/api/v1/operations \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"document_id":1,"category":"aqeedah","operation_type":"summarization"}'
```

**Submit a quiz (public):**

```bash
curl -X POST http://localhost/api/v1/quizzes/<token>/submit \
  -H "Content-Type: application/json" \
  -d '{
        "name":"أحمد",
        "email":"ahmed@example.com",
        "answers":[
          {"question_text":"س1","participant_answer":"ج1","correct_answer":"ج1"}
        ]
      }'
```
