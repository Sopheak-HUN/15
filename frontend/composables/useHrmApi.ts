import type {
  Application, Attendance, AttendanceStats, Department, Employee, EmployeeDocument,
  EmployeeNote, EmployeePromotion, Interview, InterviewFeedback, LeaveBalance, LeaveRequest, LeaveType,
  ListResp, PaginatedResp, PayComponent, Payslip, PayrollPeriod, Position, PromotionType,
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
    // Returns the employee row linked to the current user, or null when
    // the user has no linked profile (e.g. fresh admin account).
    me: () => api.get<{ data: Employee | null }>('/api/hrm/me'),
    // The wizard's create/update payload nests sub-blocks (current_address,
    // spouse, emergency_contact, contract, etc.) whose shape is the
    // *inner* fields only — not the full relation rows that come back on
    // read. Typing this as Partial<Employee> would force the caller to
    // invent fake `id`/`employee_id` on each sub-block, so we keep the
    // write surface loose.
    createEmployee:  (body: Record<string, unknown>) =>
      api.post<{ success: boolean; data: Employee }>('/api/hrm/employees', body),
    updateEmployee:  (id: string, body: Record<string, unknown>) =>
      api.put<{ success: boolean; data: Employee }>(`/api/hrm/employees/${id}`, body),
    terminateEmployee: (id: string, body: { reason?: string; effective_at?: string } = {}) =>
      api.del<{ success: boolean }>(`/api/hrm/employees/${id}`, { body } as never),
    restoreEmployee: (id: string) => api.post<{ success: boolean; data: Employee }>(`/api/hrm/employees/${id}/restore`),
    createUserForEmployee: (id: string, body: { email: string; password: string; role_id: string; handle?: string }) =>
      api.post<{
        success: boolean
        data: {
          user: { id: string; name: string; email: string; handle: string | null; role_id: string; is_active: boolean }
          employee: Employee
        }
      }>(`/api/hrm/employees/${id}/user`, body),

    // ----- Career history (promotions / transfers / salary adjustments) -----
    listEmployeePromotions: (employeeId: string) =>
      api.get<ListResp<EmployeePromotion>>(`/api/hrm/employees/${employeeId}/promotions`),
    createEmployeePromotion: (employeeId: string, body: {
      effective_date: string
      type: PromotionType
      new_position_id?: string | null
      new_department_id?: string | null
      new_role_name?: string | null
      new_salary?: number | null
      currency?: string | null
      reason?: string | null
      approved_by?: string | null
      apply_now?: boolean
    }) =>
      api.post<{ success: boolean; data: EmployeePromotion }>(`/api/hrm/employees/${employeeId}/promotions`, body),
    deleteEmployeePromotion: (employeeId: string, promotionId: string) =>
      api.del<{ success: boolean }>(`/api/hrm/employees/${employeeId}/promotions/${promotionId}`),

    // ----- Leave -----
    listLeaveTypes:   () => api.get<ListResp<LeaveType>>('/api/hrm/leave-types'),
    createLeaveType:  (body: Partial<LeaveType>) => api.post<{ success: boolean; data: LeaveType }>('/api/hrm/leave-types', body),
    updateLeaveType:  (id: string, body: Partial<LeaveType>) =>
      api.put<{ success: boolean; data: LeaveType }>(`/api/hrm/leave-types/${id}`, body),
    deleteLeaveType:  (id: string) => api.del<{ success: boolean }>(`/api/hrm/leave-types/${id}`),

    listLeaveRequests: (params: { status?: string; employee_id?: string; from?: string; to?: string; per_page?: number; page?: number } = {}) =>
      api.get<PaginatedResp<LeaveRequest>>('/api/hrm/leave-requests' + qs(params)),
    showLeaveRequest: (id: string) =>
      api.get<{ data: LeaveRequest }>(`/api/hrm/leave-requests/${id}`),
    submitLeaveRequest: (body: {
      employee_id: string
      leave_type_id: string
      duration_type?: 'full_day' | 'half_day'
      start_date: string
      end_date: string
      days?: number
      reason?: string
      assign_to?: string | null
      reference_path?: string | null
    }) =>
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

    // ----- Attendance -----
    listAttendances: (params: { employee_id?: string; start_date?: string; end_date?: string; date?: string; status?: string; per_page?: number; page?: number } = {}) =>
      api.get<PaginatedResp<Attendance>>('/api/hrm/attendances' + qs(params)),
    showAttendance: (id: string) => api.get<{ data: Attendance }>(`/api/hrm/attendances/${id}`),
    createAttendance: (body: Partial<Attendance>) => api.post<{ success: boolean; data: Attendance }>('/api/hrm/attendances', body),
    updateAttendance: (id: string, body: Partial<Attendance>) =>
      api.put<{ success: boolean; data: Attendance }>(`/api/hrm/attendances/${id}`, body),
    deleteAttendance: (id: string) => api.del<{ success: boolean }>(`/api/hrm/attendances/${id}`),
    checkIn: (body?: { notes?: string }) => api.post<{ success: boolean; data: Attendance }>('/api/hrm/attendance/check-in', body),
    breakOut: (body?: { notes?: string }) => api.post<{ success: boolean; data: Attendance }>('/api/hrm/attendance/break-out', body),
    breakIn: (body?: { notes?: string }) => api.post<{ success: boolean; data: Attendance }>('/api/hrm/attendance/break-in', body),
    checkOut: (body?: { notes?: string }) => api.post<{ success: boolean; data: Attendance }>('/api/hrm/attendance/check-out', body),
    getAttendanceStats: (params: { employee_id: string; start_date: string; end_date: string }) =>
      api.get<{ data: AttendanceStats }>('/api/hrm/attendance/stats' + qs(params)),
  }
}
