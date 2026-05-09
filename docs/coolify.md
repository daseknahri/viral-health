# Coolify Deployment Checklist

1. Connect the GitHub repository to Coolify as a Docker Compose application.
2. Set the branch to `main`.
3. Use the root `docker-compose.yml` only.
4. Let Coolify build the repo image `dr-purg-jr-wordpress`.
5. Assign the domain `https://health.ibnbatoutaweb.com` to service `wordpress`, internal port `80`.
   - The compose file also defines `SERVICE_FQDN_WORDPRESS_80=https://health.ibnbatoutaweb.com` and `PORT=80` for Coolify's proxy routing.
   - Do not publish host port `80` with a `ports:` mapping in production; Coolify's proxy should route to the container's internal port `80`.
   - It is normal for multiple Coolify apps to use internal port `80`; they do not collide because the proxy routes by domain.
6. Add persistent volumes created by Compose:
   - `dr_purg_jr_db`
   - `dr_purg_jr_wordpress`
   - `dr_purg_jr_uploads`
7. Add all required variables from `.env.example`.
8. Enable GitHub auto-deploy.
9. Leave the `seed` profile disabled for normal deploys. The `wordpress` container self-seeds only on a fresh install, before real site content exists.
10. Use the container health status only as a basic uptime signal. The compose healthcheck now probes `/robots.txt`, which is a lighter public endpoint than `/wp-login.php` and still confirms WordPress is serving requests.

After launch, do not run the seed for normal redeploys. Seed content is only for first launch. Manual posts, manual featured images, dates, and live content should be managed in WordPress.

If a manual reseed is truly needed later, set `KEPOLI_FORCE_RESEED=1` temporarily, redeploy or run:

```sh
docker compose --profile seed run --rm wp-init
```

Then set `KEPOLI_FORCE_RESEED=0` immediately after the repair. `wp-init` is intentionally one-shot and is hidden behind the `seed` Compose profile so Coolify does not treat its clean exit as a failed deployment. The public service to monitor is `wordpress`.

Do not use `docker-compose.local.yml` in Coolify. That override publishes host port `8082` for local development and can fail on shared servers when the port is already allocated. Production should use domain routing to the `wordpress` service on container port `80`.

If Coolify skips or stops the one-shot service during the first launch, the `wordpress` image already contains `seed` and `content`; the `kepoli-autoseed` MU plugin runs the seed once on the next request and activates the Dr Purg Jr. theme. Once `kepoli_seed_version` exists or real content exists, auto-seed stops and future deploys do not touch posts again.

For a temporary deploy check, set `KEPOLI_DEPLOY_FINGERPRINT=1`, redeploy, then verify the public site is actually on the current repo build:

```sh
node scripts/check-live-deploy.mjs https://health.ibnbatoutaweb.com
```

What the result means:

- `Live target` mismatch: Coolify is still serving an older image or did not redeploy the latest commit.
- `Live current` mismatch: the new code reached production, but the database seed version is older. After launch this can be normal because auto-seed is intentionally first-run only.
- Missing `kepoli-seed-*` meta tags: the fingerprint flag is disabled, or the public site is still on a build older than the deploy fingerprint update. The meta tag prefix is an internal implementation name and does not affect the public brand.

Turn `KEPOLI_DEPLOY_FINGERPRINT` back off after the check so normal production pages do not expose internal deployment details.

For a broader release pass before redeploying or applying to a monetization platform, run:

```sh
node scripts/preflight-launch.mjs --live https://health.ibnbatoutaweb.com
```
