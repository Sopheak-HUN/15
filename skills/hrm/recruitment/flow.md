# **Recruitment Module: Applicant Flow Context**

*Human Resource Management (HRM) System*

---

## **1. Overview**

This document outlines the **end-to-end flow for applicants** in the **Recruitment Module** of the HRM system. It covers the journey from **sourcing to onboarding**, including key stages, stakeholders, automation opportunities, and metrics for success.

**Purpose**:

- Standardize the recruitment process.
- Improve efficiency and candidate experience.
- Provide a reference for HR, hiring managers, and developers.

---

## **2. Applicant Flow Stages**

### **Stage 1: Sourcing**

**Objective**: Attract qualified candidates for open positions.

#### **Sub-Processes**

1. **Job Requisition Creation**
  - **Action**: Hiring manager submits a request via HRM system.
  - **Details**:
    - Job title, description, requirements.
    - Department, reporting line, salary range.
    - Urgency (e.g., "Fill within 30 days").
  - **Approval**: HR reviews and approves the requisition.
2. **Job Posting**
  - **Action**: HR posts the job to:
    - Company careers page (integrated with ATS).
    - External platforms (LinkedIn, Indeed, Glassdoor).
    - Internal referrals (employee referral program).
  - **Automation**: ATS syncs postings across platforms.
3. **Candidate Sourcing**
  - **Active Sourcing**: Recruiters search for passive candidates (e.g., LinkedIn Recruiter).
  - **Passive Sourcing**: Candidates apply directly via job postings.

#### **Output**

- Pool of applicants in the ATS.

---

### **Stage 2: Application**

**Objective**: Collect and organize candidate information.

#### **Sub-Processes**

1. **Application Submission**
  - Candidates apply via:
    - **Careers Portal**: Online form with resume/CV upload.
    - **Email**: Resumes sent to `careers@company.com`.
    - **Walk-ins**: For local hires (e.g., Cambodia-based roles).
  - **Data Captured**:
    - Personal details (name, contact info).
    - Resume/CV, cover letter.
    - Portfolio/links (for creative/technical roles).
2. **Automated Acknowledgment**
  - System sends a **confirmation email** to the applicant:
    - "Thank you for applying. We’ll review your application and contact you if there’s a match."
3. **Data Entry into ATS**
  - If applied via email/walk-in, HR manually uploads the resume to the ATS.
  - **Automation**: ATS parses resumes to extract key fields (skills, experience, education).

#### **Output**

- Candidate profile created in the ATS with a unique **Applicant ID**.

---

### **Stage 3: Screening**

**Objective**: Shortlist candidates for interviews.

#### **Sub-Processes**

1. **Resume Screening**
  - **Action**: HR/Recruiter reviews applications based on:
    - Job requirements (skills, experience, education).
    - Keywords (e.g., "Python," "Project Management").
  - **Automation**:
    - ATS ranks candidates using **keyword matching** or **AI scoring**.
    - Flags mismatches (e.g., missing required skills).
2. **Initial Phone/Video Screen**
  - Recruiter conducts a **15-30 minute call** to:
    - Verify qualifications.
    - Assess communication skills and cultural fit.
    - Explain the role and company.
  - **Outcome**:
    - **Advance to Interview**: Schedule next round.
    - **Reject**: Send a polite rejection email (template from ATS).
3. **Shortlisting**
  - Recruiter creates a **shortlist** of candidates for the hiring manager.
  - **Collaboration**: Hiring manager reviews and approves the shortlist.

#### **Output**

- Shortlisted candidates move to the **Interview Stage**.

---

### **Stage 4: Interview Process**

**Objective**: Evaluate candidates for fit and competence.

#### **Sub-Processes**

1. **Interview Scheduling**
  - Recruiter coordinates with:
    - Hiring manager.
    - Interview panel (e.g., team leads, peers).
    - Candidate (via email/calendar invite).
  - **Automation**:
    - ATS integrates with **Google Calendar/Outlook** to send invites.
    - Candidates receive **reminders** (24 hours before).
2. **Interview Types**
  - **Technical Round**: For role-specific skills (e.g., coding test for developers).
  - **Behavioral Round**: Soft skills, cultural fit (e.g., STAR method questions).
  - **Panel Interview**: Multiple interviewers (e.g., HR, manager, team member).
  - **Assignment/Case Study**: For roles requiring practical skills (e.g., marketing plan, coding challenge).
3. **Feedback Collection**
  - Interviewers submit feedback via **structured forms** in the ATS:
    - Rating (1-5 scale).
    - Strengths/weaknesses.
    - Recommendation (Hire/Reject/Hold).
  - **Automation**:
    - ATS aggregates feedback and calculates an **average score**.
    - Flags conflicts (e.g., one interviewer says "Hire," another says "Reject").
4. **Decision**
  - Hiring manager reviews feedback and makes a decision:
    - **Advance to Next Round**: Repeat interview process.
    - **Offer**: Move to Stage 5.
    - **Reject**: Send rejection email.

#### **Output**

- Final candidate(s) selected for an offer.

---

### **Stage 5: Offer & Onboarding**

**Objective**: Secure the candidate and integrate them into the company.

#### **Sub-Processes**

1. **Offer Preparation**
  - HR drafts an **offer letter** including:
    - Position, salary, benefits.
    - Start date, probation period.
    - Contingencies (e.g., background check, reference checks).
  - **Approval**: Hiring manager and finance (for budget) approve the offer.
2. **Offer Communication**
  - HR sends the offer via:
    - **Email**: PDF attachment or eSignature link (e.g., DocuSign).
    - **Portal**: Candidate logs in to accept/reject.
  - **Automation**:
    - ATS tracks offer status (Pending/Accepted/Rejected).
    - Sends reminders if no response within **3-5 days**.
3. **Offer Negotiation (if applicable)**
  - Candidate may negotiate salary, benefits, or start date.
  - HR and hiring manager discuss and update the offer.
4. **Acceptance & Pre-Onboarding**
  - Candidate accepts the offer (e.g., signs digitally).
  - **Automation**:
    - ATS triggers **pre-onboarding tasks**:
      - Background check initiation.
      - Document collection (ID, certificates, bank details).
      - IT setup (laptop, email, system access).
  - **Welcome Email**: Sent with:
    - Onboarding schedule.
    - First-day instructions (e.g., dress code, reporting time).
    - Links to company policies/handbook.
5. **Onboarding**
  - **Day 1**:
    - HR introduces the employee to the team.
    - IT provides access to systems/tools.
    - Manager conducts a **role orientation**.
  - **First Week**:
    - Training sessions (company culture, tools, processes).
    - Meetings with key stakeholders.
  - **30/60/90-Day Check-ins**: Manager reviews progress and addresses concerns.

#### **Output**

- Employee is fully integrated into the workforce.

---

---

## **3. Flowchart**

### **Visual Lifecycle Diagram**

```mermaid
flowchart TD
    %% Sourcing
    Start([Start]) --> Requisition[Job Requisition Creation]
    Requisition --> Posting[Job Posting: Careers Page, External Platforms]
    Posting --> Sourcing[Candidate Sourcing: Active & Passive]
    
    %% Application
    Sourcing --> Submit[Application Submission: CV/Resume Upload]
    Submit --> Ack[Automated Email Acknowledgment]
    Ack --> ATSEntry[Data Entry & AI Resume Parsing into ATS]
    
    %% Screening
    ATSEntry --> Screening[Resume Screening & Keyword Matching]
    Screening --> PhoneScreen[Initial Phone/Video Screen]
    PhoneScreen --> Shortlist[Hiring Manager Shortlist Approval]
    
    %% Interview
    Shortlist --> Schedule[Interview Scheduling & Google/Outlook Invite]
    Schedule --> Conduct{Conduct Interview Rounds}
    Conduct -->|Technical| TechRound[Technical Coding / Assignment Round]
    Conduct -->|Behavioral| BehRound[Behavioral Fit Round]
    Conduct -->|Panel| PanelRound[Panel Review]
    Conduct -->|Assignment| AssignRound[Case Study / Task Submission]
    
    TechRound & BehRound & PanelRound & AssignRound --> Feedback[Submit Feedback Forms & ATS Scoring]
    Feedback --> Decision{Hiring Decision}
    Decision -->|Reject| RejectMail[Send Polite Rejection Email]
    Decision -->|Next Round| Schedule
    Decision -->|Select Candidate| OfferPrep[Offer Preparation: Salary & Start Date]
    
    %% Offer & Onboarding
    OfferPrep --> OfferSend[Send Offer: eSignature Link / DocuSign]
    OfferSend --> Negotiate{Negotiate?}
    Negotiate -->|Yes| OfferPrep
    Negotiate -->|No / Accepted| Accept[Digital Offer Acceptance]
    
    Accept --> PreOnboarding[Pre-Onboarding: Background Checks & IT Setup]
    PreOnboarding --> Welcome[Send Welcome Email & Instructions]
    Welcome --> Day1[Day 1: Team Intro, IT Access & Role Orientation]
    Day1 --> Week1[Week 1: Training & Stakeholder Alignment]
    Week1 --> Checkins[30/60/90-Day Performance Check-ins]
    Checkins --> End([Employee Fully Integrated])

    %% Styling Theme
    classDef stage fill:#fdfdfd,stroke:#555,stroke-width:1px,stroke-dasharray: 2 2;
    classDef process fill:#f4f7fb,stroke:#3b82f6,stroke-width:1.5px,color:#1e3a8a;
    classDef decision fill:#fef3c7,stroke:#d97706,stroke-width:1.5px,color:#78350f;
    classDef endPoint fill:#ecfdf5,stroke:#10b981,stroke-width:2px,color:#065f46;

    class Start,End endPoint;
    class Conduct,Decision,Negotiate decision;
    class Requisition,Posting,Sourcing,Submit,Ack,ATSEntry,Screening,PhoneScreen,Shortlist,Schedule,TechRound,BehRound,PanelRound,AssignRound,Feedback,RejectMail,OfferPrep,OfferSend,Accept,PreOnboarding,Welcome,Day1,Week1,Checkins process;
```

### **Text-Based Flowchart**

```
Start
  │
  ▼
[Sourcing] → Job Requisition → Job Posting → Candidate Sourcing
  │
  ▼
[Application] → Submit Resume → Acknowledgment Email → ATS Entry
  │
  ▼
[Screening] → Resume Review → Phone Screen → Shortlist
  │
  ▼
[Interview] → Schedule Interviews → Conduct Interviews → Collect Feedback → Decision
  │
  ▼
[Offer & Onboarding] → Prepare Offer → Send Offer → Negotiate (if needed) → Acceptance → Pre-Onboarding → Onboarding
  │
  ▼
End (Employee Integrated)
```

---

---

## **4. Key Metrics to Track**


| **Metric**                   | **Purpose**                                               | **Target**                            |
| ---------------------------- | --------------------------------------------------------- | ------------------------------------- |
| Time-to-Fill                 | Average days to fill a position.                          | < 30 days (varies by role)            |
| Cost-per-Hire                | Total recruitment cost (ads, agency fees, etc.) per hire. | Industry benchmark (e.g., $1,000)     |
| Applicant-to-Interview Ratio | % of applicants who reach the interview stage.            | 10-20% (higher for high-volume roles) |
| Offer Acceptance Rate        | % of offers accepted by candidates.                       | > 80%                                 |
| Quality of Hire              | Performance rating of new hires after 6/12 months.        | > 4/5 average                         |
| Drop-off Rate                | % of candidates who drop out during the process.          | < 10%                                 |


---

---

## **5. Pain Points & Solutions**


| **Pain Point**                          | **Solution**                                                                |
| --------------------------------------- | --------------------------------------------------------------------------- |
| High volume of unqualified applications | Use **AI resume screening** to filter candidates before manual review.      |
| Slow feedback from interviewers         | Set **deadlines** (e.g., 24 hours to submit feedback) and send reminders.   |
| Scheduling conflicts                    | Integrate ATS with **calendar tools** (e.g., Calendly) for self-scheduling. |
| Poor candidate experience               | Send **personalized updates** at each stage.                                |
| Manual data entry                       | **Automate** resume parsing and ATS updates.                                |


---

---

## **6. Tools & Technologies**


| **Category**             | **Tools**                                              |
| ------------------------ | ------------------------------------------------------ |
| **ATS**                  | Greenhouse, Lever, BambooHR, Workday Recruiting        |
| **Job Posting**          | LinkedIn Jobs, Indeed, Glassdoor, Company Careers Page |
| **Interview Scheduling** | Calendly, Google Calendar, Outlook                     |
| **Feedback Collection**  | Typeform, Google Forms, Built-in ATS forms             |
| **eSignature**           | DocuSign, HelloSign, Adobe Sign                        |
| **Onboarding**           | BambooHR, Gusto, Sapling                               |
| **AI Screening**         | HireVue, Pymetrics, Ideal                              |


---

---

## **7. Next Steps**

1. **Map Current vs. Ideal Flow**:
  - Document your **existing recruitment process** and identify gaps.
  - Compare with this flow to prioritize improvements.
2. **Select an ATS**:
  - Evaluate tools based on **budget, scalability, and integration** needs.
3. **Automate Repetitive Tasks**:
  - Start with **email templates**, **resume parsing**, and **calendar integrations**.
4. **Pilot the Flow**:
  - Test the new process with **1-2 job openings** and gather feedback.
5. **Train Stakeholders**:
  - Ensure **HR, hiring managers, and interviewers** are familiar with the ATS and workflows.


---

---

## **8. Glossary**


| **Term**        | **Definition**                                                              |
| --------------- | --------------------------------------------------------------------------- |
| **ATS**         | Applicant Tracking System: Software to manage recruitment workflows.        |
| **STAR Method** | Situation-Task-Action-Result: Framework for behavioral interview questions. |
| **NSSF**        | National Social Security Fund: Statutory deductions for payroll compliance. |
| **OKRs**        | Objectives and Key Results: Goal-setting framework for performance.         |
| **eApprovals**  | Electronic approval workflows (e.g., for leave requests or offers).         |


---

---

## **9. References**

- [SHRM Recruitment Best Practices](https://www.shrm.org)
- [Greenhouse ATS Documentation](https://www.greenhouse.io)
- [LinkedIn Recruiter Guide](https://business.linkedin.com/talent-solutions/recruiter)
