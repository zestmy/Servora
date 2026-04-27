#!/usr/bin/env python3
"""
deploy/webhook-call.py — Sign and send a command to the Servora deploy webhook.

Usage:
    python3 deploy/webhook-call.py <command> [--url URL] [--secret SECRET]

Examples:
    python3 deploy/webhook-call.py migrate
    python3 deploy/webhook-call.py config:cache
    python3 deploy/webhook-call.py cache:clear

The secret and URL can also be set via environment variables:
    DEPLOY_WEBHOOK_URL    (default: https://servora.com.my/internal/deploy-hook)
    DEPLOY_WEBHOOK_SECRET
"""

import argparse
import hashlib
import hmac
import json
import os
import sys
import time
import urllib.request
import urllib.error

DEFAULT_URL = "https://servora.com.my/internal/deploy-hook"

ALLOWED_COMMANDS = [
    "migrate",
    "config:cache",
    "config:clear",
    "cache:clear",
    "queue:restart",
    "route:cache",
    "route:clear",
    "view:cache",
    "view:clear",
    "optimize",
    "optimize:clear",
]


def sign(secret: str, timestamp: str, command: str) -> str:
    payload = f"{timestamp}:{command}"
    return hmac.new(secret.encode(), payload.encode(), hashlib.sha256).hexdigest()


def call_webhook(url: str, secret: str, command: str) -> dict:
    timestamp = str(int(time.time()))
    signature = sign(secret, timestamp, command)

    body = json.dumps({"command": command}).encode()
    req = urllib.request.Request(
        url,
        data=body,
        headers={
            "Content-Type": "application/json",
            "X-Timestamp": timestamp,
            "X-Signature": signature,
        },
        method="POST",
    )

    try:
        with urllib.request.urlopen(req, timeout=30) as resp:
            return json.loads(resp.read())
    except urllib.error.HTTPError as e:
        body = e.read().decode(errors="replace")
        print(f"HTTP {e.code}: {body}", file=sys.stderr)
        sys.exit(1)
    except Exception as e:
        print(f"Error: {e}", file=sys.stderr)
        sys.exit(1)


def main():
    parser = argparse.ArgumentParser(description="Call the Servora deploy webhook.")
    parser.add_argument("command", choices=ALLOWED_COMMANDS, help="Artisan command to run")
    parser.add_argument("--url", default=os.environ.get("DEPLOY_WEBHOOK_URL", DEFAULT_URL))
    parser.add_argument("--secret", default=os.environ.get("DEPLOY_WEBHOOK_SECRET", ""))
    args = parser.parse_args()

    if not args.secret:
        print("Error: DEPLOY_WEBHOOK_SECRET not set.", file=sys.stderr)
        sys.exit(1)

    print(f"→ Calling: {args.command}")
    result = call_webhook(args.url, args.secret, args.command)
    print(f"✓ Success at {result.get('executed_at', '?')}")
    if result.get("output"):
        print(result["output"])


if __name__ == "__main__":
    main()
