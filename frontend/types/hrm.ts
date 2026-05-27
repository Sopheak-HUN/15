import type { Paginated } from '~/types/iam'

export type EmploymentType = 'full_time' | 'part_time' | 'contract' | 'intern'

export interface Department {
  id: string
  name: string
  code: string
  parent_id?: string | null
  parent?: Pick<Department, 'id' | 'name'> | null
  manager_id?: string | null
  manager?: { id: string; first_name: string; last_name: string } | null
  description?: string | null
  is_active: boolean
  created_at: string
  updated_at: string
}

export interface Position {
  id: string
  title: string
  code: string
  department_id?: string | null
  department?: Pick<Department, 'id' | 'name'> | null
  min_salary?: string | number | null
  max_salary?: string | number | null
  is_active: boolean
  created_at: string
  updated_at: string
}

export interface EmployeeAddress {
  id: string
  employee_id: string
  type: 'current' | 'permanent' | 'emergency'
  home_number?: string | null
  street?: string | null
  province_code?: string | null
  district_code?: string | null
  commune_code?: string | null
  village_code?: string | null
  group?: string | null
  lat?: string | number | null
  lng?: string | number | null
}

export interface EmployeeSpouse {
  employee_id: string
  name?: string | null
  date_of_birth?: string | null
  education?: string | null
  occupation?: string | null
}

export interface EmployeeEmergencyContact {
  employee_id: string
  father_name?: string | null
  father_occupation?: string | null
  mother_name?: string | null
  mother_occupation?: string | null
  phone_number?: string | null
  home_phone?: string | null
}

export interface EmployeeEducationRow {
  id: string
  employee_id: string
  level?: string | null
  major_subject?: string | null
  status?: string | null
  university_school?: string | null
}

export interface EmployeeContract {
  id: string
  employee_id: string
  type: string
  start_date: string
  end_date?: string | null
  comment?: string | null
  status: 'active' | 'expired' | 'terminated'
}

export interface Employee {
  id: string
  employee_id: string
  first_name: string
  last_name: string
  first_name_kh?: string | null
  last_name_kh?: string | null
  email: string
  phone?: string | null
  office_phone?: string | null
  contact_phone?: string | null
  date_of_birth?: string | null
  gender?: string | null
  nationality?: string | null
  nssf_id?: string | null
  role_name?: string | null
  identification_type?: string | null
  id_card_number?: string | null
  id_issued_date?: string | null
  id_issued_by?: string | null
  id_issued_place?: string | null
  religion?: string | null
  marital_status?: string | null
  blood_group?: string | null
  children_count?: number | null
  address?: string | null
  city?: string | null
  country?: string | null
  photo_path?: string | null
  photo_url?: string | null            // 5-min presigned GET, set by the model accessor
  department_id?: string | null
  position_id?: string | null
  manager_id?: string | null
  user_id?: string | null
  hire_date?: string | null
  termination_date?: string | null
  employment_type: EmploymentType
  status: string
  currency: string
  pay_frequency: string
  base_salary?: string | number | null
  national_id?: string | null
  bank_account?: string | null
  tax_id?: string | null
  department?: Pick<Department, 'id' | 'name'> | null
  position?: Pick<Position, 'id' | 'title'> | null
  manager?: { id: string; first_name: string; last_name: string } | null
  // Tenant user account linked via employees.user_id — populated on the
  // detail endpoint (employee.user:id,email,handle). null when the
  // employee has no login yet.
  user?: { id: string; email: string; handle: string | null } | null
  // Relations loaded by GET /api/hrm/employees/{id}
  current_address?: EmployeeAddress | null
  permanent_address?: EmployeeAddress | null
  emergency_address?: EmployeeAddress | null
  spouse?: EmployeeSpouse | null
  emergency_contact?: EmployeeEmergencyContact | null
  educations?: EmployeeEducationRow[] | null
  active_contract?: EmployeeContract | null
  contracts?: EmployeeContract[] | null
  created_at: string
  updated_at: string
  deleted_at?: string | null
}

export interface LeaveType {
  id: string
  name: string
  code: string
  default_balance: string | number
  is_paid: boolean
  accrues: boolean
  requires_approval: boolean
  color?: string | null
}

export interface LeaveBalance {
  id: string
  employee_id: string
  leave_type_id: string
  year: number
  balance: string | number
  used: string | number
  pending: string | number
  leave_type?: LeaveType
}

export type LeaveDurationType = 'full_day' | 'half_day'

export interface LeaveRequest {
  id: string
  employee_id: string
  leave_type_id: string
  duration_type: LeaveDurationType
  start_date: string
  end_date: string
  days: string | number
  reason?: string | null
  assign_to?: string | null
  reference_path?: string | null
  reference_url?: string | null  // 5-min presigned GET from the model accessor
  status: string
  approved_by?: string | null
  approved_at?: string | null
  rejection_reason?: string | null
  // Laravel serializes relations as snake_case of the method name, so
  // `leaveType()` → `leave_type`, `assignedTo()` → `assigned_to`, etc.
  employee?: Pick<Employee, 'id' | 'first_name' | 'last_name' | 'employee_id'>
  leave_type?: Pick<LeaveType, 'id' | 'name' | 'code' | 'color'>
  approver?: Pick<Employee, 'id' | 'first_name' | 'last_name'>
  assigned_to?: Pick<Employee, 'id' | 'first_name' | 'last_name' | 'employee_id'>
}

export type PayComponentKind = 'earning' | 'deduction'
export type PayComponentCalculation = 'fixed' | 'percentage_of_base'

export interface PayComponent {
  id: string
  name: string
  code: string
  kind: PayComponentKind
  calculation: PayComponentCalculation
  amount: string | number
  is_taxable: boolean
  is_active: boolean
}

export interface PayrollPeriod {
  id: string
  start_date: string
  end_date: string
  label: string
  status: string
  processed_at?: string | null
  processed_by?: string | null
  payslips?: Payslip[]
}

export interface Payslip {
  id: string
  payroll_period_id: string
  employee_id: string
  gross_earnings: string | number
  total_deductions: string | number
  net_pay: string | number
  currency: string
  line_items?: Array<{ code: string; name: string; kind: string; amount: number }> | null
  issued_at?: string | null
  employee?: Pick<Employee, 'id' | 'first_name' | 'last_name' | 'employee_id'>
  period?: Pick<PayrollPeriod, 'id' | 'label' | 'start_date' | 'end_date'>
}

export interface Vacancy {
  id: string
  title: string
  reference: string
  department_id?: string | null
  position_id?: string | null
  description?: string | null
  requirements?: string | null
  location?: string | null
  salary_min?: string | number | null
  salary_max?: string | number | null
  employment_type: EmploymentType
  status: string
  opens_at?: string | null
  closes_at?: string | null
  hiring_manager_id?: string | null
  department?: Pick<Department, 'id' | 'name'> | null
  position?: Pick<Position, 'id' | 'title'> | null
}

export interface Application {
  id: string
  vacancy_id: string
  first_name: string
  last_name: string
  email: string
  phone?: string | null
  resume_path?: string | null
  cover_letter_path?: string | null
  expected_salary?: string | number | null
  status: string
  rating?: number | null
  employee_id?: string | null
  converted_at?: string | null
  vacancy?: Pick<Vacancy, 'id' | 'title' | 'reference'>
  employee?: Pick<Employee, 'id' | 'first_name' | 'last_name' | 'employee_id'>
}

export interface Interview {
  id: string
  application_id: string
  scheduled_at: string
  duration_minutes: number
  mode: 'virtual' | 'onsite' | 'phone'
  location?: string | null
  round_label?: string | null
  status: 'scheduled' | 'completed' | 'cancelled'
  application?: Pick<Application, 'id' | 'first_name' | 'last_name' | 'vacancy_id'>
}

export interface InterviewFeedback {
  id: string
  interview_id: string
  reviewer_id?: string | null
  rating?: number | null
  recommendation?: 'hire' | 'reject' | 'hold' | null
  strengths?: string | null
  weaknesses?: string | null
  notes?: string | null
}

export interface AppraisalCycle {
  id: string
  name: string
  start_date: string
  end_date: string
  rating_scale?: Array<{ value: number; label: string }> | null
  is_active: boolean
}

export interface Appraisal {
  id: string
  cycle_id: string
  employee_id: string
  reviewer_id?: string | null
  status: string
  overall_score?: string | number | null
  manager_comments?: string | null
  employee_comments?: string | null
  responses?: Record<string, unknown> | null
  submitted_at?: string | null
  closed_at?: string | null
  cycle?: Pick<AppraisalCycle, 'id' | 'name'>
  employee?: Pick<Employee, 'id' | 'first_name' | 'last_name'>
  reviewer?: { id: string; first_name: string; last_name: string } | null
}

export interface Suggestion {
  id: string
  employee_id?: string | null
  category: string
  title: string
  body: string
  is_anonymous: boolean
  status: string
  reviewed_by?: string | null
  reviewed_at?: string | null
  response?: string | null
  created_at: string
}

export interface EmployeeNote {
  id: string
  employee_id: string
  author_id?: string | null
  category: 'general' | 'performance' | 'disciplinary' | 'praise'
  title?: string | null
  body: string
  is_private: boolean
  is_disciplinary: boolean
  incident_date?: string | null
  created_at: string
  author?: { id: string; first_name: string; last_name: string } | null
}

export interface EmployeeDocument {
  id: string
  employee_id: string
  title: string
  category: 'contract' | 'id' | 'certificate' | 'other'
  file_path: string
  mime_type?: string | null
  size_bytes?: number | null
  issued_at?: string | null
  expires_at?: string | null
  created_at: string
}

export type PaginatedResp<T> = { data: Paginated<T> }
export type ListResp<T> = { data: T[] }

export interface Attendance {
  id: string
  employee_id: string
  date: string
  check_in: string | null
  check_out: string | null
  status: 'present' | 'late' | 'absent' | 'half_day' | 'on_leave'
  notes: string | null
  created_at: string
  updated_at: string
  employee?: {
    id: string
    first_name: string
    last_name: string
    employee_id: string
  }
}

export interface AttendanceStats {
  present: number
  late: number
  absent: number
  half_day: number
  on_leave: number
}
