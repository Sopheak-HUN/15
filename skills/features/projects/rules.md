# Project Management Workflow Rules

## 1. Permissions (IAM Integration)
Permissions follow the standard `module.feature.action` pattern defined in [iam.md](../iam.md).

### Permission Keys:
- **Module**: `projects`
- **Actions**: `read`, `write`, `delete`, `export`

### Feature Matrix:
| Feature | Read | Write | Delete | Export |
|---------|------|-------|--------|--------|
| `planning` | `projects.planning.read` | `projects.planning.write` | `projects.planning.delete` | `projects.planning.export` |
| `tasks` | `projects.tasks.read` | `projects.tasks.write` | `projects.tasks.delete` | `projects.tasks.export` |
| `resources` | `projects.resources.read`| `projects.resources.write`| - | `projects.resources.export`|
| `budget` | `projects.budget.read` | `projects.budget.write` | - | `projects.budget.export` |

## 2. Implementation Standards

### Project Lifecycle Flow
1. **Initiation**: Create project and define stakeholders.
2. **Planning**: Build WBS and assign task dependencies.
3. **Resource**: Allocate staff and set budgets.
4. **Execution**: Log hours and update task progress.
5. **Monitoring**: Real-time Gantt and budget vs actual tracking.
6. **Closure**: Archive project and generate final report.

### Backend (Laravel)
- **Namespace**: `App\Tenants\Modules\Projects`
- **Service Layer**: `Services/ProjectService.php`, `Services/TaskService.php`.
- **Collaboration**: Implement real-time task updates via WebSockets.
- **Time Tracking**: Link tasks to `hrm` timesheets for payroll if required.

### Frontend (Nuxt/PrimeVue)
- **Path**: `src/modules/projects/`
- **Views**: Gantt charts and Kanban boards (integrated with PrimeVue).
- **Communication**: Inline commenting system on tasks.
