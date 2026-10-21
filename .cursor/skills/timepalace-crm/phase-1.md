# Phase 1 implementation notes

Source of truth for scope and acceptance: `development-plan.md` section 4.

## Sync

1. Save departments by WeCom department id and parent id. Skip the department named `其他（待设置部门）`; do not store it.
2. Classify by depth, not by brand name: the root is the company, its children are stores, their children are counters. Staff belong to a counter.
3. Upsert members by `userid`. Counter is the staff member's department. Store is that counter's parent. If WeCom lists several departments, use the main department; if it sends none, use the first and note it on the sync log. Do not require a mobile number.
4. Map WeCom status `1/2/4/5` to active, disabled, inactive, left.
5. Status `2` and `5` cannot authenticate. Do not delete the user row.
6. Absence from a payload is not a deletion. Only an explicit disabled or left status changes login ability.
7. Full pull plus compare is the sync. Do not invent a cursor. Do not add contact callbacks in this phase.

## Login

Synced, active staff entering from WeCom are logged in immediately. No login page, password, code, or activation step. The session user is the row with that `userid`.

- Missing user: refuse and tell an admin to sync first. Do not create a user.
- Password login is limited to admin users. Staff have no password.
- Login does not require a mobile number or a store. A synced, active user still gets a session.

## Stop and record

After a real WeCom run, record:

- Which sensitive fields actually arrived (mobile, email, avatar may be empty on apps created after 2025-06-20).
- A member under `麦凯乐 → 欧米茄` has store 麦凯乐 and counter 欧米茄. `其他（待设置部门）` is absent. Do not attach customers to stores in this phase.
