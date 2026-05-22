# BE-2026-05-22 · Draft Durability + Edit Reopen

**From**: `B3` in the mobile QA list

The mobile side now invalidates and reloads the user's kolab list after save/publish actions. Remaining failures here are expected to be persistence or response-shape problems on the API side.

---

## Required outcome

When a business or community saves a draft:

1. the draft is durably persisted
2. it appears in the correct draft list on reload
3. reopening the draft returns a complete editable payload
4. publishing the draft updates the same record instead of creating a ghost or duplicate

---

## Endpoints to verify

- `POST /api/v1/kolabs`
- `PUT /api/v1/kolabs/{id}`
- `GET /api/v1/kolabs/me?status=draft`
- `GET /api/v1/kolabs/{id}`
- publish/close endpoints for state transitions

---

## Backend requirements

### 1. Draft status must persist exactly

`status=draft` writes must survive app restart and list refresh. No optimistic-only success responses.

### 2. My-kolabs list must return draft records consistently

`GET /api/v1/kolabs/me` must:

- include draft records
- respect status filters
- return enough shape for cards/list rows to render immediately

### 3. Show payload must be edit-safe

`GET /api/v1/kolabs/{id}` for a draft must return the full editable structure, including:

- media
- availability
- past events
- offer contract fields
- any community-targeting metadata

### 4. Publish must update the draft, not fork it

Publishing an existing draft must transition that same record to `published`.

---

## Acceptance

- saving draft returns a record that later appears in `GET /kolabs/me?status=draft`
- reloading the app still shows the saved draft
- opening the draft loads a complete editable form state
- publishing the draft transitions the same id to `published`
- no duplicate draft/published records are created for one author action

---

## Mobile reference

Frontend expectations already live in:

- `kolabing-app/lib/features/kolab/providers/kolab_form_provider.dart`
- `kolabing-app/lib/features/kolab/providers/my_kolabs_provider.dart`
- `kolabing-app/lib/features/kolab/screens/kolab_flow_screen.dart`
- `kolabing-app/lib/features/business/screens/my_kollabs_screen.dart`
- `kolabing-app/lib/features/community/screens/my_opportunities_screen.dart`
