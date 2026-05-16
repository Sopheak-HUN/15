# 🛡️ Enterprise ERP (Multi-Tenant)

![ERP Dashboard Hero](./design/assets/erp-hero.png)

[![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)](./package.json)
[![Stack](https://img.shields.io/badge/stack-Laravel_11_%7C_Nuxt_3-orange.svg)](#tech-stack)
[![Architecture](https://img.shields.io/badge/architecture-Multi--Tenant-green.svg)](#core-architecture)

A high-performance, modular, and premium Enterprise Resource Planning (ERP) system designed for modern business operations. Built with a focus on strict data isolation, atomic business logic, and a state-of-the-art user experience.

---

## ✨ Core Features

- **🔐 Multi-Database Isolation**: Each tenant has its own physical database for maximum security and performance.
- **🏗️ Modular Architecture**: 12+ enterprise-ready modules (IAM, FMS, HRM, etc.) that can be scaled independently.
- **💎 Premium UI/UX**: Built with Nuxt 3 and PrimeVue, featuring dark mode, glassmorphism, and responsive layouts.
- **🚀 Agent-Ready**: Standardized skills and rules integrated for AI-assisted development and automation.

---

## 🛠️ Tech Stack

### Backend
- **Core**: Laravel 11+ (PHP 8.2+)
- **Database**: PostgreSQL (via `stancl/tenancy`)
- **Auth**: Laravel Passport (OAuth2 & OIDC)
- **Testing**: Pest PHP (Security & Tenancy focus)

### Frontend
- **Core**: Nuxt 3+ (Vue 3, TypeScript)
- **Styling**: Tailwind CSS 4+
- **UI Components**: PrimeVue (Premium presets)
- **State Management**: Pinia

---

## 📂 Project Structure

```bash
├── 📁 backend        # Laravel Multi-tenant RESTful API
├── 📁 frontend       # NuxtJS 3 Client application
├── 📁 skills         # Standardized Agent Skills (Business Logic)
├── 📁 rules          # Global Development Rules & Standards
├── 📁 tools          # Internal CLI Tools (e.g., skills-cli)
└── 📄 AGENTS.md      # Primary Agent Context
└── 📄 CONTEXT.md     # Project Single Source of Truth
```

---

## 🧭 Navigation & Standards

To maintain the integrity of this enterprise system, all contributors (human and AI) must adhere to the following:

- **[Project Context](./PROJECT_CONTEXT.md)**: The definitive guide to the system architecture.
- **[Agent Rules](./AGENTS.md)**: Behavioral guidelines and specialized skills.
- **[Modular Features](./skills/features.md)**: Detailed breakdown of the 12 core ERP modules.

---

## 🛠️ Quick Start

### 1. Prerequisites
- Docker & Docker Compose
- PHP 8.2+ / Node 20+
- Composer & NPM

### 2. Installation
```bash
# Clone the repository
git clone https://github.com/pphatdev/erp-prompt.git

# Initialize Backend
cd backend && composer install && php artisan migrate

# Initialize Frontend
cd ../frontend && npm install

# Bootstrap Skills
npx skills add pphatdev/erp-prompt
```

### 3. Running Locally
```bash
# Start Backend
php artisan serve

# Start Frontend
npm run dev
```

---

## 📊 ERP Modules

| Identity & Access | Finance & HR | Supply Chain | Specialized |
| :--- | :--- | :--- | :--- |
| [IAM](./skills/iam) | [FMS](./skills/fms) | [Inventory](./skills/inventory) | [eApprovals](./skills/eapprovals) |
| [Sales](./skills/sales) | [HRM](./skills/hrm) | [Fleet](./skills/fleet) | [eDocuments](./skills/edocuments) |
| [Reporting](./skills/reporting) | [Projects](./skills/projects) | [Assets](./skills/assets) | [Documents](./skills/documents) |

---

## 📜 License
Internal Enterprise License. All rights reserved.

---
*Built with ⚠️ by [PPhat](https://github.com/pphatdev).* 
