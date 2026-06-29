#!/usr/bin/env python3
"""
Blog2Social publisher — utilitaire CLI robuste pour publier (notamment des vidéos)
via l'API Blog2Social. Piloté par variables d'environnement, aucun secret en dur.

Découvertes intégrées (validées en test réel) :
- Auth : POST /user/auth avec le service_token suffit à minter un access_token (longue durée).
  ⚠️ La connexion d'un réseau (ex. TikTok) est liée à CET access_token précis. Un token
  re-minté à neuf = utilisateur VIDE. D'où B2S_ACCESS_TOKEN : fournis le token durable qui
  porte tes réseaux connectés, sinon le script en mint un (utile seulement pour `connect`).
- Création : /network/post/create exige `b2s_posts` ET `client_user_network_id` DANS chaque
  entrée de `b2s_posts` (pas seulement au niveau racine) — sinon HTTP 500.
- Vidéo : la réponse renvoie `video_upload_type`. Pour TikTok = 1 → upload du FICHIER physique
  en chunks requis (l'URL seule ne suffit pas : la « pull-from-URL » TikTok exige un domaine
  vérifié). Chunks : taille divisible par 8, toutes égales sauf la dernière. Hôte d'upload séparé.
- L'hôte d'upload filtre par User-Agent : sans UA navigateur, il sert une page de challenge HTML
  (chunks perdus). On envoie donc un UA navigateur et on ré-essaie chaque chunk jusqu'à JSON.

Variables d'environnement :
  B2S_SERVICE_TOKEN   (requis)
  B2S_ACCESS_TOKEN    (optionnel mais nécessaire pour publier : c'est lui qui porte les
                       réseaux connectés. Sans lui, un token est minté → 0 réseau connecté.)

Usage :
  B2S_SERVICE_TOKEN=... B2S_ACCESS_TOKEN=... python publish.py accounts
  B2S_SERVICE_TOKEN=...                      python publish.py connect --network-id 36
  B2S_SERVICE_TOKEN=... B2S_ACCESS_TOKEN=... python publish.py post \
        --account 2179158 --video https://exemple.com/clip.mp4 --caption "Texte #tags"
"""
from __future__ import annotations
import argparse
import json
import math
import os
import sys
import time
import uuid
import urllib.request
import urllib.error
import urllib.parse

API = os.environ.get("B2S_API", "https://api.blog2social.com/rest/v1.0")
UPLOAD_API = os.environ.get("B2S_UPLOAD_API", "https://api-upload.blog2social.com/api/rest/v1.0")
UA = ("Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 "
      "(KHTML, like Gecko) Chrome/124.0 Safari/537.36")

# Mode debug : imprime chaque réponse brute (create / upload / check). Activable via --debug ou B2S_DEBUG=1.
DEBUG = os.environ.get("B2S_DEBUG") == "1"

# Champ b2s_error_code → interprétation (c'est le seul signal d'erreur exposé par l'API).
ERROR_CODES = {
    "TOKEN": "jeton invalide/expiré — ré-authentifier.",
    "LOGIN": "authentification refusée.",
    "RIGHT": "droits insuffisants sur le réseau (permission / publication non autorisée).",
    "CONTENT": "contenu refusé par le réseau (texte ou média non conforme).",
    "LIMIT": "limite de publications atteinte.",
    "RATE_LIMIT": "trop de requêtes (rate limit).",
    "VIDEO_NETWORK_FORMAT": "format vidéo refusé par le réseau.",
    "NO_DATA": "données manquantes dans la requête.",
    "DEFAULT": "erreur générique côté réseau, sans détail exposé par l'API "
               "(souvent : publication directe non autorisée).",
}


def explain(code: str | None) -> str:
    return ERROR_CODES.get(code or "", "code inconnu")


def log(*a):
    print(*a, file=sys.stderr, flush=True)


class B2SError(RuntimeError):
    pass


class Blog2Social:
    def __init__(self, service_token: str, access_token: str | None = None):
        if not service_token:
            raise B2SError("B2S_SERVICE_TOKEN manquant.")
        self.service_token = service_token
        self.access_token = access_token

    # ---- HTTP helpers ---------------------------------------------------
    def _post_json(self, url: str, payload: dict, retries: int = 4) -> object:
        body = json.dumps(payload).encode()
        for attempt in range(retries):
            req = urllib.request.Request(
                url, data=body,
                headers={"Content-Type": "application/json", "User-Agent": UA, "Accept": "application/json"},
            )
            try:
                with urllib.request.urlopen(req, timeout=120) as r:
                    text = r.read().decode()
            except urllib.error.HTTPError as e:
                text = e.read().decode()
            except urllib.error.URLError as e:
                if attempt < retries - 1:
                    time.sleep(2 * (attempt + 1)); continue
                raise B2SError(f"réseau: {e}")
            if DEBUG:
                log(f"<< JSON {url}\n   {text[:1200]}")
            s = text.strip()
            if s.startswith(("{", "[")):  # vraie réponse JSON (sinon page de challenge HTML)
                return json.loads(s)
            if attempt < retries - 1:
                time.sleep(2 * (attempt + 1))
        raise B2SError(f"réponse non-JSON après {retries} essais ({url})")

    def _post_multipart(self, url: str, fields: dict, file_bytes: bytes, retries: int = 10) -> object:
        boundary = "----b2s" + uuid.uuid4().hex
        bb = boundary.encode()
        for attempt in range(retries):
            body = b""
            for k, v in fields.items():
                body += b"--" + bb + b"\r\n"
                body += f'Content-Disposition: form-data; name="{k}"\r\n\r\n{v}\r\n'.encode()
            body += b"--" + bb + b"\r\n"
            body += b'Content-Disposition: form-data; name="chunk"; filename="chunk.bin"\r\n'
            body += b"Content-Type: application/octet-stream\r\n\r\n" + file_bytes + b"\r\n"
            body += b"--" + bb + b"--\r\n"
            req = urllib.request.Request(
                url, data=body,
                headers={"Content-Type": "multipart/form-data; boundary=" + boundary,
                         "User-Agent": UA, "Accept": "application/json"},
            )
            try:
                with urllib.request.urlopen(req, timeout=180) as r:
                    text = r.read().decode()
            except urllib.error.HTTPError as e:
                text = e.read().decode()
            if DEBUG:
                log(f"<< MULTIPART {url}\n   {text[:1200]}")
            s = text.strip()
            if s.startswith(("{", "[")) and '"error":1' not in s:
                return json.loads(s)
            time.sleep(2)
        raise B2SError("upload de chunk: échec après plusieurs essais (challenge anti-bot ?)")

    # ---- API ------------------------------------------------------------
    def ensure_access_token(self) -> str:
        if self.access_token:
            return self.access_token
        log("Aucun B2S_ACCESS_TOKEN — mint d'un token (⚠️ utilisateur sans réseau connecté).")
        d = self._post_json(f"{API}/user/auth", {"service_token": self.service_token})
        self.access_token = d["access_token"]
        return self.access_token

    def _base(self) -> dict:
        return {"service_token": self.service_token, "access_token": self.ensure_access_token()}

    def accounts(self) -> list:
        d = self._post_json(f"{API}/user/auth/list", self._base())
        return d if isinstance(d, list) else []

    def connect_link(self, network_id: int, network_type_id: int = 0) -> dict:
        return self._post_json(f"{API}/network/add",
                               {**self._base(), "network_id": network_id, "network_type_id": network_type_id})

    def create_post(self, account_id: int, title: str, message: str, video_url: str,
                    share_settings: dict | None = None) -> dict:
        entry = {
            "client_user_network_id": account_id,  # requis DANS l'entrée (sinon 500)
            "title": title,
            "message": message,
            "postFormat": 2,  # vidéo
            "mediaObjects": [{"type": "VIDEO", "url": video_url}],
        }
        if share_settings:
            # TikTok : pilote direct/brouillon + confidentialité. Absent → comportement réseau par défaut.
            entry["share_settings"] = share_settings
        payload = {
            **self._base(),
            "client_user_network_id": account_id,
            "b2s_posts": [entry],
        }
        d = self._post_json(f"{API}/network/post/create", payload)
        item = d[0] if isinstance(d, list) and d else d
        if isinstance(item, dict) and item.get("error") == 1:
            raise B2SError(f"create_post: {item}")
        return item

    def upload_video(self, video_token: str, path: str, chunk_size: int) -> None:
        assert chunk_size % 8 == 0, "la taille de chunk doit être divisible par 8"
        size = os.path.getsize(path)
        n = math.ceil(size / chunk_size)
        log(f"Upload vidéo : {size} octets en {n} chunk(s) de {chunk_size}")
        with open(path, "rb") as f:
            for i in range(n):
                data = f.read(chunk_size)
                self._post_multipart(f"{UPLOAD_API}/video/upload", {
                    **self._base(), "video_token": video_token,
                    "max_count_chunks": n, "current_chunk": i + 1,
                }, data)
                log(f"  chunk {i + 1}/{n} OK")

    def check(self, video_token: str, attempts: int = 40, delay: int = 6) -> tuple[int, str | None]:
        """Poll /video/check. Retourne (état, b2s_error_code). état: 0=fini, 1=échec, 2=timeout encore en cours.
        Le traitement TikTok peut rester en `state:2` plusieurs minutes avant de basculer."""
        last_code = None
        for _ in range(attempts):
            d = self._post_json(f"{UPLOAD_API}/video/check?video_token={video_token}",
                                {"video_token": video_token})
            item = d[0] if isinstance(d, list) and d else {}
            state = item.get("state", 2)
            last_code = item.get("b2s_error_code", last_code)
            if DEBUG:
                log("   check:", json.dumps(item))
            if state in (0, 1):
                return state, item.get("b2s_error_code")
            time.sleep(delay)
        return 2, last_code


# ---- util : construire share_settings depuis les flags CLI ----------------
def build_share_settings(args) -> dict | None:
    """Assemble un share_settings TikTok COMPLET. TikTok valide l'objet entier :
    un objet partiel est rejeté (`b2s_error_code: NETWORK_36_SHARE_SETTINGS_MISMATCH`).
    On émet donc toujours les 5 champs dès qu'un flag share est fourni.

    Recette validée en test réel (publication OK) : mode=1 (brouillon), status_privacy=SELF_ONLY.
    mode=0 (publication directe) est refusé par TikTok pour une app non auditée (→ DEFAULT).

    Renvoie None si aucun flag fourni (→ pas de clé, comportement réseau par défaut).
    `--share-settings` (JSON brut) court-circuite et écrase tout le reste."""
    if getattr(args, "share_settings", None):
        return json.loads(args.share_settings)
    triggered = (getattr(args, "draft", False) or getattr(args, "mode", None) is not None
                 or getattr(args, "privacy", None) or getattr(args, "allow_comment", False))
    if not triggered:
        return None
    mode = args.mode if getattr(args, "mode", None) is not None else (1 if args.draft else 0)
    return {
        "mode": mode,
        "status_privacy": getattr(args, "privacy", None) or "SELF_ONLY",
        "allow_comment": 1 if getattr(args, "allow_comment", False) else 0,
        "promotion_option_organic": 0,
        "promotion_option_branded": 0,
    }


# ---- util : récupérer la vidéo en local pour l'upload chunké --------------
def fetch_to_temp(url: str) -> str:
    dest = os.path.join("/tmp", "b2s_" + uuid.uuid4().hex + ".mp4")
    req = urllib.request.Request(url, headers={"User-Agent": UA})
    with urllib.request.urlopen(req, timeout=300) as r, open(dest, "wb") as f:
        while True:
            buf = r.read(1 << 20)
            if not buf:
                break
            f.write(buf)
    return dest


def main() -> int:
    p = argparse.ArgumentParser(description="Publisher Blog2Social (vidéo).")
    sub = p.add_subparsers(dest="cmd", required=True)
    sub.add_parser("accounts", help="lister les comptes connectés")
    c = sub.add_parser("connect", help="générer un lien d'autorisation réseau")
    c.add_argument("--network-id", type=int, required=True)
    c.add_argument("--network-type-id", type=int, default=0)
    po = sub.add_parser("post", help="publier une vidéo")
    po.add_argument("--account", type=int, required=True, help="client_user_network_id cible")
    po.add_argument("--video", required=True, help="URL publique du MP4")
    po.add_argument("--caption", required=True)
    po.add_argument("--title", default="")
    po.add_argument("--chunk-mib", type=int, default=4)
    po.add_argument("--debug", action="store_true",
                    help="imprime chaque réponse brute de l'API (create / upload / check)")
    # --- share_settings TikTok (direct vs brouillon + confidentialité) -------
    po.add_argument("--draft", action="store_true",
                    help="TikTok : envoyer en BROUILLON (boîte de réception) au lieu de publier "
                         "directement → met share_settings.mode=1")
    po.add_argument("--mode", type=int,
                    help="share_settings.mode brut (0=publication directe, 1=brouillon). "
                         "Override de --draft.")
    po.add_argument("--privacy",
                    help="share_settings.status_privacy (ex. SELF_ONLY, PUBLIC_TO_EVERYONE, "
                         "MUTUAL_FOLLOW_FRIENDS, FOLLOWER_OF_CREATOR). Souvent requis pour un post direct.")
    po.add_argument("--allow-comment", action="store_true",
                    help="share_settings.allow_comment=1")
    po.add_argument("--share-settings",
                    help="JSON brut pour share_settings (échappatoire — écrase tous les flags ci-dessus).")

    args = p.parse_args()
    global DEBUG
    DEBUG = DEBUG or getattr(args, "debug", False)
    try:
        b2s = Blog2Social(os.environ.get("B2S_SERVICE_TOKEN", ""), os.environ.get("B2S_ACCESS_TOKEN"))

        if args.cmd == "accounts":
            for a in b2s.accounts():
                print(f"{a.get('client_user_network_id')}  {a.get('name')}  «{a.get('display_name')}»  "
                      f"instant_sharing={a.get('instant_sharing')}")
            return 0

        if args.cmd == "connect":
            d = b2s.connect_link(args.network_id, args.network_type_id)
            link = urllib.parse.unquote(d.get("auth_link", "")) if "auth_link" in d else d
            print(link)
            return 0

        if args.cmd == "post":
            share = build_share_settings(args)
            if share:
                log("share_settings:", json.dumps(share))
            created = b2s.create_post(args.account, args.title or args.caption[:40],
                                      args.caption, args.video, share)
            vt = created["video_token"]
            vtype = created.get("video_upload_type")
            log(f"Post créé (video_upload_type={vtype}).")
            if vtype == 1:  # TikTok & co : upload physique requis
                local = fetch_to_temp(args.video)
                try:
                    b2s.upload_video(vt, local, args.chunk_mib * 1024 * 1024)
                finally:
                    try: os.remove(local)
                    except OSError: pass
            state, code = b2s.check(vt)
            if state == 0:
                print("OK ✅ vidéo publiée"); return 0
            if state == 1:
                log(f"ÉCHEC ❌ — le réseau a refusé la publication. "
                    f"b2s_error_code={code!r} → {explain(code)}")
                log("(astuce : relance avec --debug pour voir chaque réponse brute de l'API.) "
                    "video_token:", vt)
                return 2
            log(f"TIMEOUT — encore en traitement (state=2, dernier b2s_error_code={code!r}). "
                f"video_token:", vt)
            return 3
    except B2SError as e:
        log("Erreur:", e)
        return 1
    return 0


if __name__ == "__main__":
    sys.exit(main())
