# Blog2Social API — support ticket: TikTok video publish ends in `state:1 / b2s_error_code:"DEFAULT"`

> Ready-to-send dossier. Raw capture: [`diagnostics/b2s_debug_20260623T122517Z.log`](diagnostics/b2s_debug_20260623T122517Z.log).
> **Before sending:** the `service_token` is never printed in the log, but rotate it anyway since it was used in a shared test environment.

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

1. For `video_token` `71a4...7518`, what is the **actual TikTok-side error** returned to your servers? `b2s_error_code:"DEFAULT"` exposes nothing to us.
2. Why does the connection report `instant_sharing:0` while the upload step reports `instant_sharing:1` for the same network? Is the pipeline attempting a direct publish the account isn't entitled to?
3. What is required to make TikTok publishing succeed for this account — a **Business TikTok account**, the `video.publish` scope, an approved app, or a specific re-connection flow?
4. Is there a supported **draft / manual-confirmation mode** (video delivered to the TikTok inbox) we can request via the API instead of direct publish? If so, which field/value?
5. Does the API expose (now or planned) the underlying network error message via any endpoint, so integrators can self-diagnose instead of receiving `DEFAULT`?

## 9. How to reproduce on your side

CLI used (no secrets in code — env-driven):

```bash
B2S_SERVICE_TOKEN=… B2S_ACCESS_TOKEN=… python3 publish.py post --debug \
  --account 2179158 \
  --video "https://notreafrik.com/wp-content/uploads/2026/06/03-sassou-visa.mp4" \
  --caption "Visa : ce qui change … #Congo #Visa #Voyage"
```

`--debug` prints every raw API response (create / each upload chunk / each check poll), which is exactly the capture in §6.
