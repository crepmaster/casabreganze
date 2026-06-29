# Blog2Social API — support ticket: enabling **direct** (non-draft) TikTok video publishing

> **Update — partially solved on our side.** TikTok **draft** publishing now works for us via a complete
> `share_settings` object (`mode:1`, `status_privacy:SELF_ONLY`). The remaining ask is **direct/public
> publishing** (`mode:0`), which still fails with `DEFAULT`. The findings below are reproducible evidence.
>
> Raw captures: [`diagnostics/`](diagnostics/) — `b2s_debug_*` (no share_settings → DEFAULT),
> `b2s_expA_draft_*` (partial → MISMATCH), `b2s_expB_full_direct_*` (full mode:0 → DEFAULT),
> `b2s_expC_full_draft_*` / `b2s_expD_draft_oob_*` (full mode:1 → **published OK**).
> **Before sending:** the `service_token` is never printed in the logs, but rotate it anyway since it was
> used in a shared test environment.

## 0. TL;DR of what we found (please confirm / correct)

| `share_settings` sent | `b2s_error_code` | Outcome |
|---|---|---|
| *(none)* | `DEFAULT` | fail |
| `{mode:1}` (partial) | `NETWORK_36_SHARE_SETTINGS_MISMATCH` | fail — object must be complete |
| `{mode:0, status_privacy:SELF_ONLY, allow_comment, promotion_*}` (full, **direct**) | `DEFAULT` | fail |
| `{mode:1, status_privacy:SELF_ONLY, allow_comment, promotion_*}` (full, **draft**) | — | **`state:0` published ✅** |

So **draft (`mode:1`) works**; **direct (`mode:0`) is refused with `DEFAULT`**. Our questions are now about
(a) enabling direct posting, (b) which `status_privacy` values are permitted, and (c) exposing the real
TikTok error instead of `DEFAULT`.

---

## 1. Subject (for the ticket form)

> TikTok video upload finishes processing but final `/video/check` returns `state:1` with `b2s_error_code:"DEFAULT"` — no actionable error message exposed by the API. Need the underlying TikTok error and the correct flow for direct vs. draft publishing.

## 2. Account / context

| Field | Value |
|---|---|
| Plan | Business (service_token-based API access) |
| Network | **TikTok**, `network_id: 36` |
| Connected account | `client_user_network_id: 2179158`, display name **NotreAfrik**, `type: profile` |
| Connection `instant_sharing` | **0** (as reported by `/user/auth/list`) |
| API host | `https://api.blog2social.com/rest/v1.0` |
| Upload host | `https://api-upload.blog2social.com/api/rest/v1.0` |
| Test timestamp (UTC) | `2026-06-23T12:25:17Z` |
| Failing `video_token` | `71a423365f3e1e536b94faea66dc9ccd66dcecf35c981316d0dbf3de40ac4ad63ae90e2159063153191782217518` |
| Test video | MP4, 24 102 187 bytes (~24 MB), publicly reachable (HTTP 200), H.264/AAC |

## 3. One-paragraph summary

Every step of the documented TikTok video flow succeeds — auth, post creation (`/network/post/create` returns `video_upload_type:1`), and the full chunked physical upload (all 6 chunks return `{"error":0}`). The job then polls `/video/check` in `state:2` (processing) for ~95 s and finally flips to **`state:1`** with **`b2s_error_code:"DEFAULT"`**. `DEFAULT` carries no detail, so we cannot tell whether this is a permission/scope issue, a content rejection, or a format issue on TikTok's side. **We need the real TikTok-side error for this `video_token`, and confirmation of the correct flow.**

## 4. The key anomaly we'd like you to explain

The connection is listed as `instant_sharing: 0`:

```json
// POST /user/auth/list  →
[{"client_user_network_id":2179158,"network_id":36,"type":"profile",
  "display_name":"NotreAfrik","instant_sharing":0,"name":"TikTok"}]
```

…yet the **final upload chunk response** reports `instant_sharing: 1` for the same network:

```json
// POST /video/upload  (chunk 6/6) →
{"error":0,"networks":[{"error":0,"network_id":36,
  "client_user_network_id":2179158,"instant_sharing":1}]}
```

So the upload pipeline appears to attempt a **direct/instant publish** (`instant_sharing:1`) on a connection that is registered as **not authorized for it** (`instant_sharing:0`). Is this mismatch the cause of the `DEFAULT` failure? If so, **how do we either (a) enable direct publishing for this TikTok connection, or (b) force draft mode** so the video lands in the TikTok inbox for manual confirmation?

## 5. Exact flow used (reproducible)

All requests send a browser `User-Agent` and `Accept: application/json`. `client_user_network_id` is included **inside each `b2s_posts` entry** (omitting it there returns HTTP 500).

1. `POST /user/auth/list` → confirms connected networks.
2. `POST /network/post/create` with:
   ```json
   {
     "service_token": "<redacted>",
     "access_token": "<redacted>",
     "client_user_network_id": 2179158,
     "b2s_posts": [{
       "client_user_network_id": 2179158,
       "title": "...",
       "message": "Visa : ce qui change pour les voyageurs au Congo-Brazzaville. #Congo #Brazzaville #Sassou #Afrique #Visa #Voyage",
       "postFormat": 2,
       "mediaObjects": [{"type": "VIDEO", "url": "https://notreafrik.com/.../03-sassou-visa.mp4"}]
     }]
   }
   ```
   → `{"error":0,"network_id":36,"video_token":"71a4...7518","video_upload_type":1}`
3. Because `video_upload_type == 1`, upload the physical file to
   `POST /video/upload` in chunks (4 MiB each, size divisible by 8; fields `video_token`,
   `max_count_chunks`, `current_chunk`, multipart `chunk`). All 6 chunks → `{"error":0}`.
4. Poll `POST /video/check?video_token=…` every 6 s.

## 6. Raw API exchange (verbatim, redacted)

```
# create
POST /network/post/create →
[{"error":0,"network_id":36,"type":0,"client_user_network_id":2179158,"extra":[],
  "video_token":"71a4...7518","video_upload_type":1}]

# upload (24,102,187 bytes → 6 chunks of 4,194,304)
POST /video/upload  chunk 1/6 → {"error":0}
POST /video/upload  chunk 2/6 → {"error":0}
POST /video/upload  chunk 3/6 → {"error":0}
POST /video/upload  chunk 4/6 → {"error":0}
POST /video/upload  chunk 5/6 → {"error":0}
POST /video/upload  chunk 6/6 → {"error":0,"networks":[{"error":0,"network_id":36,
                                  "client_user_network_id":2179158,"instant_sharing":1}]}

# check (polled every 6s)
POST /video/check → [{"network_id":36,"network_type":0,"state":2,"client_user_network_id":2179158}]   ×16  (~95s "processing")
POST /video/check → [{"network_id":36,"network_type":0,"state":1,"client_user_network_id":2179158,
                      "b2s_error_code":"DEFAULT"}]   ← FINAL
```

Full untruncated log: [`diagnostics/b2s_debug_20260623T122517Z.log`](diagnostics/b2s_debug_20260623T122517Z.log).

## 7. What we have already ruled out

- **Auth / tokens** — `accounts`, `create`, and all uploads succeed with the same tokens; the access_token is the durable one that carries the connected network (not a freshly minted empty one).
- **Request shape** — `client_user_network_id` is inside each `b2s_posts` entry (HTTP 500 otherwise); `postFormat:2`; `mediaObjects[].type:"VIDEO"`.
- **Upload transport** — all chunks return `{"error":0}`; the anti-bot HTML challenge on the upload host is handled via a browser User-Agent + per-chunk retry.
- **Video format** — an independent control clip (Big Buck Bunny, 10 s, ~1 MB, standard H.264/AAC) produced the **identical** `state:1 / DEFAULT` outcome, so this is not specific to our MP4 or its size.
- **Network reachability** — the source MP4 returns HTTP 200 publicly.

## 8. Questions for support (please answer point by point)

1. **Direct publish:** with a *complete* `share_settings` and `mode:0` (direct) + `status_privacy:SELF_ONLY`, we still get `DEFAULT` (see `b2s_expB_full_direct_*`). What is required to enable **direct** (non-draft) TikTok publishing for this account — an audited TikTok app, the `video.publish` scope, a Business account, or a specific re-connection flow?
2. **Privacy levels:** which `status_privacy` values are accepted for this connection? Is `SELF_ONLY` the only one until the app is audited (i.e. `PUBLIC_TO_EVERYONE` requires audit)?
3. **`share_settings` contract:** can you document the required keys and accepted values? We inferred that all 5 (`mode`, `status_privacy`, `allow_comment`, `promotion_option_organic`, `promotion_option_branded`) must be present, else `NETWORK_36_SHARE_SETTINGS_MISMATCH`. Is `mode` 0=direct / 1=draft correct?
4. **`instant_sharing` mismatch:** the connection reports `instant_sharing:0` but the upload step reports `instant_sharing:1` for the same network — is that expected, and does it relate to the direct-publish refusal?
5. **Error transparency:** can the API expose (now or planned) the underlying TikTok error message instead of the opaque `DEFAULT`? For `video_token` `71a4...7518` (the no-share-settings run), what was the actual TikTok-side error?

## 9. How to reproduce on your side

CLI used (no secrets in code — env-driven):

```bash
B2S_SERVICE_TOKEN=… B2S_ACCESS_TOKEN=… python3 publish.py post --debug \
  --account 2179158 \
  --video "https://notreafrik.com/wp-content/uploads/2026/06/03-sassou-visa.mp4" \
  --caption "Visa : ce qui change … #Congo #Visa #Voyage"
```

`--debug` prints every raw API response (create / each upload chunk / each check poll), which is exactly the capture in §6.
