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

export interface Employee {
  id: string
  employee_id: string
  first_name: string
  last_name: string
  email: string
  phone?: string | null
  date_of_birth?: string | null
  gender?: string | null
  address?: string | null
  city?: string | null
  country?: string | null
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
  department?: Pick<Department, 'id' | 'name'> | null
  position?: Pick<Position, 'id' | 'title'> | null
  manager?: { id: string; first_name: string; last_name: string } | null
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

export interface LeaveRequest {
  id: string
  employee_id: string
  leave_type_id: string
  start_date: string
  end_date: string
  days: string | number
  reason?: string | null
  status: string
  approved_by?: string | null
  approved_at?: string | null
  rejection_reason?: string | null
  employee?: Pick<Employee, 'id' | 'first_name' | 'last_name' | 'employee_id'>
  leaveType?: Pick<LeaveType, 'id' | 'name' | 'code'>
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
