# Skill: eApprovals Implementation

## Context
Use this skill when developing approval workflows for any ERP module (Leave, Expense, Procurement). It provides a centralized engine for authorization.

## Guidelines

### 1. Workflow Definition
- **Dynamic Steps**: Support multi-stage approval paths (e.g., Manager -> HR -> Finance).
- **Conditionals**: Implement logic to skip or add steps based on request value (e.g., Expenses > $1000 require CFO approval).

### 2. Action Handling
- **Atomic State**: Transitions (Pending -> Approved) must be atomic and trigger relevant side-effects (e.g., updating leave balance).
- **Comments**: Require comments for Rejections to provide feedback to the requester.

### 3. Notifications
- **Real-time**: Send push notifications or WebSockets to approvers immediately.
- **Escalation**: Implement reminders for pending approvals that exceed a time limit.

## Best Practices
- **Timeline UI**: Use a vertical timeline component to show the history of approvals.
- **Audit Trail**: Log every action, including the approver's handle and timestamp.
- **Delegation**: Allow users to delegate their approval authority during leave.

## Troubleshooting
- **Stuck Approval**: If a request doesn't move to the next stage, check the `WorkflowEngine` for broken logic in the `next_approver` calculation.
- **Missing Notification**: Verify the `NotificationServiceProvider` is correctly configured for the tenant.
- **Concurrency**: Use database locking (`lockForUpdate`) when processing approvals to prevent double-actioning.
