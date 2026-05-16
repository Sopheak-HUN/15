# eApprovals Workflow Rules

## 1. Permissions (IAM Integration)
Permissions follow the standard `module.feature.action` pattern defined in [iam.md](../iam.md).

### Permission Keys:
- **Module**: `approvals`
- **Actions**: `read`, `write`, `delete`, `execute` (Approve/Reject)

### Feature Matrix:
| Feature | Read | Write | Execute | Export |
|---------|------|-------|---------|--------|
| `requests` | `approvals.requests.read` | `approvals.requests.write` | - | `approvals.requests.export` |
| `actions` | `approvals.actions.read` | - | `approvals.actions.execute` | - |
| `workflows` | `approvals.workflows.read` | `approvals.workflows.write` | `approvals.workflows.delete` | - |

## 2. Implementation Standards

### Approval Engine Flow
1. **Submission**: Requester submits module-specific data.
2. **Policy Match**: Identify approvers based on tenant rules.
3. **Notification**: Send real-time alerts to current level approvers.
4. **Decision**: Process Approve/Reject with comments.
5. **Progression**: Advance to next level or finalize.
6. **Side-Effect**: Notify origin module and requester of final status.

### Backend (Laravel)
- **Namespace**: `App\Tenants\Modules\Approvals`
- **State Machine**: Use a state machine pattern for approval flows (Pending -> Approved/Rejected).
- **Notifications**: Trigger WebSocket and Email notifications on status change.
- **Audit**: Every approval step must be logged with timestamp and user handle.

### Frontend (Nuxt/PrimeVue)
- **Path**: `src/modules/approvals/`
- **Components**: Timeline component for visualizing approval history.
- **UI**: Badge indicators for pending approvals count.
