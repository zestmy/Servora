# Servora — Project Memory

## Repository
- **GitHub**: https://github.com/zestmy/Servora
- **Branch**: `main` (always push here after any commit)

## Deployment
- **Production server IP**: 206.189.155.9
- **SSH user**: root
- **SSH key**: converted OpenSSH key saved at `.ssh_key` in project root (C:\WebDev\servora\.ssh_key)
- On every session start, copy `.ssh_key` to `~/.ssh/affandy_vps` and set chmod 600 before connecting
- **Deploy command**: `ssh -i ~/.ssh/affandy_vps -o StrictHostKeyChecking=no root@206.189.155.9 "cd /var/www/servora && bash deploy/update.sh"`
- **Deploy script**: `bash deploy/update.sh` (run on production server as root)
- After every commit, push to `origin main` then deploy to production automatically.

## Stack
- Laravel 12 + Livewire 3 + Tailwind CSS
- Multi-tenant F&B operations management (ingredients, recipes, purchasing, sales, inventory, cost reporting)
- Outlets scoped per user session

## Git Identity
- Name: `ZEST Loyalty`
- Email: `162604845+zestmy@users.noreply.github.com`

## Push Credentials
- Auth method: HTTPS with GitHub PAT in ~/.netrc
- Token: stored securely (do not commit to repo)
- On every session start, write token to ~/.netrc before pushing

## Conventions
- Commit messages: imperative, concise, describe the "what" in title + "why" in body
- Always co-author commits with: `Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>`
- Always push to `origin main` after committing — don't leave commits unpushed
- Run `php artisan migrate --force` note in commit body when migrations are included

## Task Tracking
- Tasks file: `TASKS.md` in project root
- Update Done list after every completed task (most recent first)
