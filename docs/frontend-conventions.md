# Frontend Conventions

Cross-cutting standards every Nuxt page / component should follow.
Things that aren't enforced by the type-checker but matter for
consistency, accessibility, and localisation.

---

## 1. Dates & times

**Always** render API datetimes through the shared formatter at
[`composables/useDateFormatter.ts`](../frontend/composables/useDateFormatter.ts).
Never paste raw ISO strings into a template, never call
`new Date(...).toLocaleString()` inline — those vary by browser locale
and produce inconsistent UX between users.

Both helpers are **auto-imported by Nuxt** (no `import` statement
needed in `<script setup>` or templates).

```ts
formatDate('2026-05-26')                       // → '26-05-2026'           (default DD-MM-yyyy)
formatDate('2026-05-26T10:15:00Z')             // → '26-05-2026'
formatDate(new Date())                         // → '26-05-2026'
formatDate('2026-05-26', 'yyyy/MM/DD')         // → '2026/05/26'
formatDate(null)                               // → '—'

formatDateTime('2026-05-26T10:15:30Z')         // → '26-05-2026 10:15'     (default DD-MM-yyyy HH:mm)
formatDateTime(v, 'DD-MM-yyyy HH:mm:ss')       // → '26-05-2026 10:15:30'  (with seconds)
```

### Token vocabulary

| Token | Meaning | Example |
|---|---|---|
| `yyyy` | 4-digit year | `2026` |
| `yy` | 2-digit year | `26` |
| `MM` | zero-padded month (01-12) | `05` |
| `DD` (or `dd`) | zero-padded day-of-month | `26` |
| `HH` | zero-padded hour (00-23) | `10` |
| `mm` | zero-padded minute | `15` |
| `ss` | zero-padded second | `30` |

Any other character in the format string is passed through literally —
useful for separators (`-`, `/`, `:`, ` `, etc.).

### When to pick which helper

- **date-only display** (DOB, hire_date, expires_at, end_date, …) — `formatDate(value)`.
- **timestamp display** (created_at, processed_at, scheduled_at, audit logs, …) — `formatDateTime(value)`. Add seconds (`HH:mm:ss`) only for forensic contexts like audit logs.
- **input bindings** — DatePickers / form fields use ISO `yyyy-MM-dd` so they're shippable to the backend without re-parsing. Convert once via `formatDate(value, 'yyyy-MM-dd')` if you really need to format an input value as a string (rare; usually let `vee-validate` keep the Date object until submit).

### Anti-patterns we don't want to see

```ts
// ❌ raw ISO leaks into the UI
<span>{{ employee.hire_date }}</span>

// ❌ locale-dependent output, varies by browser
<span>{{ new Date(value).toLocaleString() }}</span>

// ❌ shadowing the global helper with a one-off implementation
const formatDate = (iso: string) => new Date(iso).toLocaleString('en-US', {...})

// ✅ use the project helper
<span>{{ formatDate(employee.hire_date) }}</span>
```

The only intentional exception today is
[`pages/hrm/attendance/index.vue`](../frontend/pages/hrm/attendance/index.vue)'s
live-clock card which shows `Monday, January 26, 2026` — that's a
deliberate UX choice (weekday label for the clock widget), not a
backend value.

---

## 2. Fonts

Configured in [`assets/css/main.css`](../frontend/assets/css/main.css).

| Language | Font | When applied |
|---|---|---|
| Latin (EN, default) | **Inter** (400/500/600/700) | Default `body` and `--p-font-family` |
| Khmer (KH) | **Kantumruy Pro** (400/500/600/700) | `<html lang="km">` triggers `:lang(km)` and swaps the stack |

The locale switch is driven by [`app.vue`](../frontend/app.vue):

```ts
const { locale } = useI18n()
useHead({ htmlAttrs: { lang: locale } })   // reactive — toggles <html lang> on locale change
```

When the user flips to Khmer, `<html lang="km">` triggers the CSS rule
that swaps `font-family` site-wide, including PrimeVue components
(via `--p-font-family`) and form controls (via the `:where(input,
textarea, button, select)` rule that forces inheritance from body).

### Mixed-content behaviour

- **EN page with an inline Khmer word**: Inter renders Latin chars; the browser falls back to Kantumruy Pro (next in the stack) for the Khmer glyphs. Both look correct.
- **KH page with an inline Latin word**: Kantumruy Pro carries a full Latin face, so it renders both. Type styles stay consistent.

### Forcing one font regardless of locale

Use the Tailwind utility:

- `class="font-sans"` — Inter-first stack
- `class="font-khmer"` — Kantumruy Pro-first stack

Useful for bilingual chips, the language switcher itself, etc.

---

## 3. Component naming & folder layout

Nuxt 3 auto-imports everything under `~/components/` with the **folder
name as a prefix**. A component at `components/hrm/EmployeeWizardForm.vue`
is registered as `<HrmEmployeeWizardForm>`, not `<EmployeeWizardForm>`.

```vue
<!-- ❌ doesn't resolve — renders nothing (silent in production) -->
<EmployeeWizardForm />

<!-- ✅ folder-prefixed name -->
<HrmEmployeeWizardForm />
```

The current config relies on this prefixing — don't drop the
`{ pathPrefix: false }` setting into `nuxt.config.ts` without
auditing existing usages first.

---

## 4. API conventions on the frontend

- **Tenant header**: handled centrally by [`composables/useApi.ts`](../frontend/composables/useApi.ts). Never paste a tenant header into a one-off `$fetch` call.
- **Photo uploads**: never upload bytes through Laravel. Use [`composables/useUpload.ts`](../frontend/composables/useUpload.ts) which issues a presigned PUT and returns a temp key the create/update endpoint commits. See [`docs/object-storage.md`](./object-storage.md).
- **422 validation errors**: server returns `{ message, errors: { field: [msg, ...] } }`. UIs should map server field names back to client form keys (see `SERVER_FIELD_TO_WIZARD` in the employee wizard for the pattern with dotted-path keys like `current_address.village_code`).

---

## 5. Cross-references

- [docs/api-authentication.md](./api-authentication.md) — tenant + auth headers
- [docs/object-storage.md](./object-storage.md) — MinIO/S3 presigned-upload flow
- [docs/hrm-employee-creation.md](./hrm-employee-creation.md) — the canonical end-to-end consumer of every convention above
- [.task/hrm/task.md](../.task/hrm/task.md) — session log with rationale for individual fixes
