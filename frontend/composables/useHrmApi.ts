import type {
  Application, Appraisal, AppraisalCycle, Department, Employee, EmployeeDocument,
  EmployeeNote, Interview, InterviewFeedback, LeaveBalance, LeaveRequest, LeaveType,
  ListResp, PaginatedResp, PayComponent, Payslip, PayrollPeriod, Position, Suggestion,
  Vacancy,
} from '~/types/hrm'

/**
 * Typed wrappers for the HRM endpoints exposed under /api/hrm/*.
 * Routes match backend/routes/tenant.php (HRM block).
 */
export function useHrmApi() {
  const api = useApi()
  const qs = (params: Record<string, unknown>) => {
    const entries = Object.entries(params).filter(([, v]) => v !== undefined && v !== null && v !== '')
    return entries.length ? '?' + entries.map(([k, v]) => `${k}=${encodeURIComponent(String(v))}`).join('&') : ''
  }

  return {
    // ----- Departments -----
    listDepartments: (params: { q?: string; include_inactive?: boolean; per_page?: number; page?: number } = {}) =>
      api.get<PaginatedResp<Department>>('/api/hrm/departments' + qs(params)),
    showDepartment:   (id: string) => api.get<{ data: Department }>(`/api/hrm/departments/${id}`),
    createDepartment: (body: Partial<Department>) => api.post<{ success: boolean; data: Department }>('/api/hrm/departments', body),
    updateDepartment: (id: string, body: Partial<Department>) =>
      api.put<{ success: boolean; data: Department }>(`/api/hrm/departments/${id}`, body),
    deleteDepartment: (id: string) => api.del<{ success: boolean }>(`/api/hrm/departments/${id}`),

    // ----- Positions -----
    listPositions: (params: { department_id?: string; include_inactive?: boolean; per_page?: number; page?: number } = {}) =>
      api.get<PaginatedResp<Position>>('/api/hrm/positions' + qs(params)),
    createPosition: (body: Partial<Position>) => api.post<{ success: boolean; data: Position }>('/api/hrm/positions', body),
    updatePosition: (id: string, body: Partial<Position>) =>
      api.put<{ success: boolean; data: Position }>(`/api/hrm/positions/${id}`, body),
    deletePosition: (id: string) => api.del<{ success: boolean }>(`/api/hrm/positions/${id}`),

    // ----- Employees -----
    listEmployees: (params: { q?: string; department_id?: string; status?: string; per_page?: number; page?: number } = {}) =>
      api.get<PaginatedResp<Employee>>('/api/hrm/employees' + qs(params)),
    showEmployee:    (id: string) => api.get<{ data: Employee }>(`/api/hrm/employees/${id}`),
    createEmployee:  (body: Partial<Employee> & Record<string, unknown>) =>
      api.post<{ success: boolean; data: Employee }>('/api/hrm/employees', body),
    updateEmployee:  (id: string, body: Partial<Employee> & Record<string, unknown>) =>
      api.put<{ success: boolean; data: Employee }>(`/api/hrm/employees/${id}`, body),
    terminateEmployee: (id: string, body: { reason?: string; effective_at?: string } = {}) =>
      api.del<{ success: boolean }>(`/api/hrm/employees/${id}`, { body } as never),
    restoreEmployee: (id: string) => api.post<{ success: boolean; data: Employee }>(`/api/hrm/employees/${id}/restore`),

    // ----- Leave -----
    listLeaveTypes:   () => api.get<ListResp<LeaveType>>('/api/hrm/leave-types'),
    createLeaveType:  (body: Partial<LeaveType>) => api.post<{ success: boolean; data: LeaveType }>('/api/hrm/leave-types', body),
    updateLeaveType:  (id: string, body: Partial<LeaveType>) =>
      api.put<{ success: boolean; data: LeaveType }>(`/api/hrm/leave-types/${id}`, body),
    deleteLeaveType:  (id: string) => api.del<{ success: boolean }>(`/api/hrm/leave-types/${id}`),

    listLeaveRequests: (params: { status?: string; employee_id?: string; from?: string; to?: string; per_page?: number; page?: number } = {}) =>
      api.get<PaginatedResp<LeaveRequest>>('/api/hrm/leave-requests' + qs(params)),
    submitLeaveRequest: (body: { employee_id: string; leave_type_id: string; start_date: string; end_date: string; days?: number; reason?: string }) =>
      api.post<{ success: boolean; data: LeaveRequest }>('/api/hrm/leave-requests', body),
    approveLeaveRequest: (id: string) =>
      api.post<{ success: boolean; data: LeaveRequest }>(`/api/hrm/leave-requests/${id}/approve`),
    rejectLeaveRequest: (id: string, reason?: string) =>
      api.post<{ success: boolean; data: LeaveRequest }>(`/api/hrm/leave-requests/${id}/reject`, { reason }),
    employeeLeaveBalances: (employeeId: string) =>
      api.get<ListResp<LeaveBalance>>(`/api/hrm/employees/${employeeId}/leave-balances`),

    // ----- Payroll -----
    listPayComponents:  () => api.get<ListResp<PayComponent>>('/api/hrm/pay-components'),
    createPayComponent: (body: Partial<PayComponent>) =>
      api.post<{ success: boolean; data: PayComponent }>('/api/hrm/pay-components', body),
    updatePayComponent: (id: string, body: Partial<PayComponent>) =>
      api.put<{ success: boolean; data: PayComponent }>(`/api/hrm/pay-components/${id}`, body),
    deletePayComponent: (id: string) => api.del<{ success: boolean }>(`/api/hrm/pay-components/${id}`),

    listPayrollPeriods: (params: { status?: string; per_page?: number; page?: number } = {}) =>
      api.get<PaginatedResp<PayrollPeriod>>('/api/hrm/payroll-periods' + qs(params)),
    showPayrollPeriod:  (id: string) => api.get<{ data: PayrollPeriod }>(`/api/hrm/payroll-periods/${id}`),
    createPayrollPeriod: (body: { start_date: string; end_date: string; label?: string }) =>
      api.post<{ success: boolean; data: PayrollPeriod }>('/api/hrm/payroll-periods', body),
    processPayrollPeriod: (id: string) =>
      api.post<{ success: boolean; data: PayrollPeriod }>(`/api/hrm/payroll-periods/${id}/process`),

    listPayslips: (params: { employee_id?: string; period_id?: string; per_page?: number; page?: number } = {}) =>
      api.get<PaginatedResp<Payslip>>('/api/hrm/payslips' + qs(params)),
    showPayslip:  (id: string) => api.get<{ data: Payslip }>(`/api/hrm/payslips/${id}`),

    // ----- Recruitment -----
    listVacancies: (params: { q?: string; status?: string; per_page?: number; page?: number } = {}) =>
      api.get<PaginatedResp<Vacancy>>('/api/hrm/vacancies' + qs(params)),
    showVacancy:    (id: string) => api.get<{ data: Vacancy }>(`/api/hrm/vacancies/${id}`),
    createVacancy:  (body: Partial<Vacancy> & Record<string, unknown>) =>
      api.post<{ success: boolean; data: Vacancy }>('/api/hrm/vacancies', body),
    updateVacancy:  (id: string, body: Partial<Vacancy> & Record<string, unknown>) =>
      api.put<{ success: boolean; data: Vacancy }>(`/api/hrm/vacancies/${id}`, body),
    deleteVacancy:  (id: string) => api.del<{ success: boolean }>(`/api/hrm/vacancies/${id}`),

    listApplications: (params: { q?: string; status?: string; vacancy_id?: string; per_page?: number; page?: number } = {}) =>
      api.get<PaginatedResp<Application>>('/api/hrm/applications' + qs(params)),
    showApplication: (id: string) => api.get<{ data: Application }>(`/api/hrm/applications/${id}`),
    submitApplication: (body: Partial<Application> & Record<string, unknown>) =>
      api.post<{ success: boolean; data: Application }>('/api/hrm/applications', body),
    transitionApplication: (id: string, status: string) =>
      api.post<{ success: boolean; data: Application }>(`/api/hrm/applications/${id}/transition`, { status }),
    convertApplication: (id: string) =>
      api.post<{ success: boolean; data: Employee; linkedExisting: boolean; fresh: boolean }>(`/api/hrm/applications/${id}/convert-to-employee`),
    bulkConvertApplications: (ids: string[]) =>
      api.post<{ success: boolean; converted: number; alreadyLinked: string[]; ineligible: string[]; missing: string[]; errors: Array<{ id: string; message: string }> }>('/api/hrm/applications/bulk-convert-to-employee', { ids }),
    revertApplicationConversion: (id: string) =>
      api.post<{ success: boolean; data: Application }>(`/api/hrm/applications/${id}/revert-employee-conversion`),
    deleteApplication: (id: string) => api.del<{ success: boolean }>(`/api/hrm/applications/${id}`),

    listInterviews: (params: { application_id?: string; status?: string; per_page?: number; page?: number } = {}) =>
      api.get<PaginatedResp<Interview>>('/api/hrm/interviews' + qs(params)),
    createInterview: (body: Partial<Interview> & Record<string, unknown>) =>
      api.post<{ success: boolean; data: Interview }>('/api/hrm/interviews', body),
    updateInterview: (id: string, body: Partial<Interview> & Record<string, unknown>) =>
      api.put<{ success: boolean; data: Interview }>(`/api/hrm/interviews/${id}`, body),
    deleteInterview: (id: string) => api.del<{ success: boolean }>(`/api/hrm/interviews/${id}`),
    storeInterviewFeedback: (interviewId: string, body: Partial<InterviewFeedback>) =>
      api.post<{ success: boolean; data: InterviewFeedback }>(`/api/hrm/interviews/${interviewId}/feedbacks`, body),

    // ----- Performance -----
    listAppraisalCycles:  () => api.get<ListResp<AppraisalCycle>>('/api/hrm/appraisal-cycles'),
    createAppraisalCycle: (body: Partial<AppraisalCycle>) =>
      api.post<{ success: boolean; data: AppraisalCycle }>('/api/hrm/appraisal-cycles', body),
    updateAppraisalCycle: (id: string, body: Partial<AppraisalCycle>) =>
      api.put<{ success: boolean; data: AppraisalCycle }>(`/api/hrm/appraisal-cycles/${id}`, body),
    deleteAppraisalCycle: (id: string) => api.del<{ success: boolean }>(`/api/hrm/appraisal-cycles/${id}`),

    listAppraisals: (params: { cycle_id?: string; employee_id?: string; status?: string; per_page?: number; page?: number } = {}) =>
      api.get<PaginatedResp<Appraisal>>('/api/hrm/appraisals' + qs(params)),
    createAppraisal: (body: Partial<Appraisal> & Record<string, unknown>) =>
      api.post<{ success: boolean; data: Appraisal }>('/api/hrm/appraisals', body),
    submitAppraisal: (id: string, responses?: Record<string, unknown>) =>
      api.post<{ success: boolean; data: Appraisal }>(`/api/hrm/appraisals/${id}/submit`, { responses }),
    reviewAppraisal: (id: string, body: { manager_comments?: string; overall_score?: number }) =>
      api.post<{ success: boolean; data: Appraisal }>(`/api/hrm/appraisals/${id}/review`, body),
    closeAppraisal: (id: string) =>
      api.post<{ success: boolean; data: Appraisal }>(`/api/hrm/appraisals/${id}/close`),

    // ----- Suggestions -----
    listSuggestions: (params: { status?: string; category?: string; per_page?: number; page?: number } = {}) =>
      api.get<PaginatedResp<Suggestion>>('/api/hrm/suggestions' + qs(params)),
    submitSuggestion: (body: { title: string; body: string; category?: string; is_anonymous?: boolean }) =>
      api.post<{ success: boolean; data: Suggestion }>('/api/hrm/suggestions', body),
    transitionSuggestion: (id: string, body: { action: 'acknowledge' | 'action' | 'dismiss'; response?: string }) =>
      api.post<{ success: boolean; data: Suggestion }>(`/api/hrm/suggestions/${id}/transition`, body),
    deleteSuggestion: (id: string) => api.del<{ success: boolean }>(`/api/hrm/suggestions/${id}`),

    // ----- Notes & Documents -----
    listEmployeeNotes: (params: { employee_id?: string; category?: string; per_page?: number; page?: number } = {}) =>
      api.get<PaginatedResp<EmployeeNote>>('/api/hrm/employee-notes' + qs(params)),
    createEmployeeNote: (body: Partial<EmployeeNote> & Record<string, unknown>) =>
      api.post<{ success: boolean; data: EmployeeNote }>('/api/hrm/employee-notes', body),
    updateEmployeeNote: (id: string, body: Partial<EmployeeNote>) =>
      api.put<{ success: boolean; data: EmployeeNote }>(`/api/hrm/employee-notes/${id}`, body),
    deleteEmployeeNote: (id: string) => api.del<{ success: boolean }>(`/api/hrm/employee-notes/${id}`),

    listEmployeeDocuments: (params: { employee_id?: string; category?: string; expiring_soon?: boolean; per_page?: number; page?: number } = {}) =>
      api.get<PaginatedResp<EmployeeDocument>>('/api/hrm/employee-documents' + qs(params)),
    createEmployeeDocument: (body: Partial<EmployeeDocument> & Record<string, unknown>) =>
      api.post<{ success: boolean; data: EmployeeDocument }>('/api/hrm/employee-documents', body),
    deleteEmployeeDocument: (id: string) => api.del<{ success: boolean }>(`/api/hrm/employee-documents/${id}`),
  }
}
