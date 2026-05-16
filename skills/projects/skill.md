# Project Management

Use this skill when developing project planning, task tracking, and resource allocation features.

## Guidelines

### 1. Project Planning
- **WBS**: Support Work Breakdown Structure with nested tasks and milestones.
- **Gantt Charts**: Provide visual scheduling with dependency tracking.

### 2. Task Management
- **Kanban/Lists**: Implement flexible views for task tracking.
- **Time Tracking**: Allow employees to log hours against specific tasks.

### 3. Resource Allocation
- **Capacity Planning**: Visualize team workload to prevent over-allocation.
- **Budgeting**: Track project costs (Labor + Expenses) against the initial budget.

## Best Practices
- **Collaboration**: Implement real-time comments and file attachments on tasks.
- **Notifications**: Alert team members of upcoming deadlines or priority changes.
- **FMS Integration**: Automatically bill clients based on billable hours logged in projects.

## Troubleshooting
- **Timeline Slippage**: Check if dependencies are correctly linked in the `ProjectService`.
- **Budget Overrun**: Audit the `ProjectCostJob` for accurate labor cost calculations.
- **Performance**: Optimize the Project Dashboard for projects with thousands of tasks using pagination and lazy loading.
