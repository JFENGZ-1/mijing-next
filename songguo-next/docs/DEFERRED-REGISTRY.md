# DEFERRED / REJECT Registry

**Updated:** 2026-07-13 overnight-batch truth audit  
**Rule:** REJECT capabilities never ship; DEFERRED items need product sign-off or production credentials before PASS.

---

## Traceability `planned:*` remainder (truth audit 2026-07-13)

| Metric | Count | Notes |
|---|---:|---|
| API `AcceptanceCase=planned:*` | **4** | **All REJECT** — API-149 (`findMyOrder` jscode), API-157 (`clearUserPoint`), API-160 (`delPointLog`), API-294 (`myOrderList_notoken`) |
| Page `AcceptanceCase=planned:*` | **0** | Overnight batch mapped all remaining pages |
| **Non-REJECT actionable remainder** | **0** | Traceability closure for shippable scope |

---

## WeChat Pay — member pending-order checkout UI wiring (NEW)

**Status:** DEFERRED — backend scaffold complete; member UI not wired.

**Backend (L2 done):**

- `MemberCardWechatPaymentTest` — `PAYMENT_DRIVER=wechat` creates `pending_payment` order with `data.payment` payload (no card until webhook)
- Webhook scaffold fulfills order + issues card
- Demo path (`PAYMENT_DRIVER=demo`) auto-pays at catalog submit

**UI gap:**

- `pages/orders/index` + `result` list/detail only — **no pay button** for `pending_payment`
- `pages/cards/catalog` uses demo auto-paid path; does not surface `wx.requestPayment` when driver=wechat

**Minimal wiring plan (next implementer):**

1. **`src/api/member.ts`** — add `repayMemberCardOrder(tenantId, orderId, commandKey)` → `POST /member/orders/{id}/pay` (or reuse purchase endpoint if unified)
2. **`pages/orders/result.vue`** — when `order.status === 'pending_payment'`, show `u-button type="primary"`「去支付」→ call API → `uni.requestPayment` with returned `timeStamp/nonceStr/package/signType/paySign`
3. **`pages/orders/index.vue`** — status chip + inline CTA for pending rows (stop propagation on button tap)
4. **Error path** — `u-toast` on pay cancel/fail; poll order detail after success
5. **L2** — extend `MemberCardWechatPaymentTest` or add `MemberOrderRepayTest` for repay endpoint
6. **L5** — capture `member-orders-pending-pay.png` with `PAYMENT_DRIVER=wechat` + mock gateway

**Unblock for production PASS:** platform cert, webhook URL, `PAYMENT_DRIVER=wechat` in prod env.

---

## REJECT (10 API + 3 page — archaeology locked)

| ID | Legacy | Rationale |
|---|---|---|
| API-029 | `getAllCardForHasAgreement` | Vendor-only export; no call site |
| API-149 | `findMyOrder` (jscode) | One-time jscode is not an order auth credential |
| API-157 | `clearUserPoint` | Destructive zeroing; use reversing ledger entries |
| API-160 | `delPointLog` | Physical delete of points history forbidden |
| API-031–033 | Member QR pages | REJECT per archaeology; not in new product scope |
| … | See `docs/traceability-apis.csv` `Disposition=REJECT` | Full list in CSV |

---

## DEFERRED — product / UI (blocks L4–L6 PASS)

| Capability | Legacy pages | New status | Owner / unblock |
|---|---|---|---|
| **Member order list** | `pages/myOrder/index` | **Shipped** `pages/orders/index` + `MemberOrderTest` | WeChat Pay pending-order checkout UI still DEFERRED |
| **Legal document viewer** | `pages/webView`, `memberAgreement` | **Shipped** `pages/legal/index` | — |
| **Platform SaaS orders** | `shopOrder`, `pageServer/order` | **Shipped** `pages/settings/platform/subscription-orders/index` | L5 PNG pending |
| **Card transfer claim** | `receiveCard/*` (3) | **Shipped** `pages/cards/transfer` + `MemberCardTransferTest` | Phone-gate sub-page merged/deferred |
| **Schedule timetable editor** | `pagesCourse/index/*` (7) | **Shipped** `pages/course/timetable/*` + display-config | Export-image API placeholder only |
| **Chain drill-down polish** | `pageChain/*` | Report hub shipped | UX polish only — matrix PARTIAL |
| **Drag-sort card catalog** | legacy card UI | Up/down sort via API | Cosmetic — matrix PARTIAL |
| **Course group scopes** | legacy card groups | Single-course scope shipped | Group matrix deferred |
| **Official-account follow** | member home CTA | Backend stub | WeChat OA integration |
| **WeChat card-pack** | Stage 07 step 9 | — | `member-card.wechat-pack.*` |
| **Schedule batch unsuspend / change-course** | `management-schedule.js` | `planned:schedule.batch.unsuspend`, `planned:schedule.batch.change-course` | Backend not shipped; per-course batch page unmapped |

---

## DEFERRED — production gates (blocks revenue / ops PASS)

| Gate | Current | Unblock |
|---|---|---|
| **WeChat Pay V3 checkout** | Demo auto-paid + driver stub; `MemberCardWechatPaymentTest` covers scaffold | Platform cert, webhook URL, `PAYMENT_DRIVER=wechat` prod |
| **SMS provider** | `SMS_DRIVER=log` | Production template IDs + credentials |
| **Queue worker (export)** | `ProcessExportJob` + `AsyncQueueDispatchTest` PASS; sync queue in dev | CI/worker runbook for D-L export polling |
| **Token revocation on staff departure** | PARTIAL matrix row | Global logout + audit |
| **Device / refresh sessions** | DEFERRED matrix row | Rotation, device list design |
| **Restricted MySQL test account** | `songguo_next_test` only | CI hardening |

---

## Traceability historical note

2026-07-11 EOD reported 55 API + 27 page `planned:*`. **2026-07-13 grep confirms 4 REJECT API + 0 page.** Overnight batch closed non-REJECT traceability backlog.
