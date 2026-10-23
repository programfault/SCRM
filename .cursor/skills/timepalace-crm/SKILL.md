---
name: timepalace-crm
description: >-
  Implements and changes TimePalace CRM, a Laravel 13 and Filament 5 app for a
  multi-store watch retailer. Use when adding features, fixing bugs, writing
  Filament resources, or touching 企业微信, WeCom, sync, customers, orders,
  visibility, or permissions. Follow the current development phase and keep
  business rules in services so later stages do not break earlier ones.
---

# TimePalace CRM

Read `business-requirements.md` for business rules and `development-plan.md` for scope and acceptance. If they disagree, the requirements doc wins for behavior and the plan wins for what may be built now.

## Current phase

Phase 1 remains the baseline: WeCom department and member sync, store mapping, workbench login, admin password login, sync log.

The user explicitly opened a migration slice on top of that: old customers with name, phone, and owner; CSV import; assignment by name plus department; a reconcile that marks 已加入 only when the WeCom remark mobile and the owner's `wecom_userid` both match; admin progress; and the employee's own list on `/me`. Do not add opportunities, follow-ups, join requests, visibility rules, or orders onto this slice.

## Where code goes

- WeCom HTTP and sync decisions live in a sync service, not in a Filament page.
- The page calls the service. The queue job calls the same service.
- Tests replace the WeCom client. No test may call WeCom or the network.
- Secrets stay in `.env`. Do not commit corp id, secret, or tokens.
- Real WeCom login is verified on the 宝塔 HTTPS test site in `deployment.md`, not on localhost or a tunnel.

## Invariants

- One Laravel app. WeCom and the desktop browser use the same URL.
- Match employees by `wecom_userid`. A second sync must not duplicate users or stores.
- A failed sync must not mark existing active users as left.
- Local admin accounts with no `wecom_userid` survive sync.
- A synced, active employee who opens the app from WeCom is logged in with no login screen, password, or activation step. Identity is `wecom_userid`.
- Disabled and left users cannot log in.
- Unknown `wecom_userid` on login does not create a user.
- Org depth is company, then store, then counter. Staff sit on the counter; their store is the counter's parent. Skip the department named `其他（待设置部门）`. Do not classify counters by matching brand names.
- Later phases, when started: phone is the only customer identity; no product master or SKU import; consumption changes only when an order becomes completed, once; detail visibility is enforced in queries, not only by hiding fields.

## Filament

Use Filament 5 as documented by the framework. Do not copy framework tutorials into the repo.

- Resources and pages stay thin.
- Authorization uses policies, including actions that mutate data.
- A new visible behavior needs a Pest feature test in the same change.

## Phase 1 detail

Sync flow, status mapping, and the two business checks (sensitive fields, department tree) are in [phase-1.md](phase-1.md).
