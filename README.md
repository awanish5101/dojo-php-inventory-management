# Enterprise Asset & Inventory Management System

[![CI Pipeline](https://github.com/awanish5101/dojo-php-inventory-management/actions/workflows/ci.yml/badge.svg)](https://github.com/awanish5101/dojo-php-inventory-management/actions)
![PHP](https://img.shields.io/badge/PHP-8.2%20%7C%208.5-blue?logo=php)
![Dojo Toolkit](https://img.shields.io/badge/Frontend-Dojo%20Toolkit%201.17+-orange)
![MySQL](https://img.shields.io/badge/Database-MySQL%208.0-00758F?logo=mysql)
![Docker](https://img.shields.io/badge/Container-Docker%20Compose-2496ED?logo=docker)

A production-grade, full-stack enterprise inventory and resource tracking portal. Built with **PHP 8** on the backend using Object-Oriented architecture and PDO prepared statements, backed by a normalized **MySQL** relational database with transactional audit logging, and driven by an asynchronous **Dojo Toolkit (AMD)** frontend for dynamic single-page interactions.

---

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────┐
│                      Client Frontend                        │
│   Dojo Toolkit 1.17+ (AMD Loader, Dijit UI, dojo/request)   │
│   HTML5 / Responsive CSS3 Grid / AJAX State Synchronization │
└──────────────────────────────┬──────────────────────────────┘
                               │ Asynchronous JSON HTTP/REST
                               ▼
┌─────────────────────────────────────────────────────────────┐
│                     PHP 8 Backend Engine                    │
│   public/api/assets.php ───► AssetService (Business Logic)  │
│   public/api/stats.php  ───► Validator (Sanitization/XSS)   │
│   public/api/categories.php ─► Database (PDO Wrapper)       │
└──────────────────────────────┬──────────────────────────────┘
                               │ Prepared Statements / ACID Tx
                               ▼
┌─────────────────────────────────────────────────────────────┐
│                  Relational Storage Layer                   │
│   MySQL 8.0 / InnoDB (Foreign Keys, Indexes, Audit Tables)  │
│   - categories (1:N) assets (1:N) inventory_transactions    │
└─────────────────────────────────────────────────────────────┘
```

---

## Key Features

1. **Dojo Toolkit AMD Frontend:**
   * Utilizes Dojo's Asynchronous Module Definition (`require(["dojo/dom", "dojo/on", "dojo/request", ...])`) for modular UI component loading.
   * Real-time search by asset tag, model description, or warehouse location with debounce handling.
   * Category and inventory status filtering (`In Stock`, `Low Stock`, `Out of Stock`).
   * Dynamic modal dialogs for asset registration, stock adjustment, and historical audit trail inspection.

2. **Robust PHP Backend & RESTful APIs:**
   * Full CRUD support across structured REST endpoints (`GET`, `POST`, `PUT`, `PATCH`, `DELETE`).
   * Strict input validation preventing SQL injection and Cross-Site Scripting (XSS).
   * Transactional stock adjustments (`CHECK_IN`, `CHECK_OUT`) with boundary validation preventing negative stock overdraw.

3. **Relational MySQL Database Design:**
   * Normalized schema across `categories`, `assets`, and `inventory_transactions`.
   * Indexed queries for high-speed search across thousands of hardware assets.
   * Foreign-key cascade rules and atomic transactions (`beginTransaction`, `commit`, `rollBack`).

4. **Containerization & CI/CD:**
   * Multi-container setup with `docker-compose.yml` for Apache PHP 8.2 and MySQL 8.0.
   * Automated GitHub Actions CI workflow executing unit and API integration suites on every commit.

---

## Directory Layout

```
.
├── .github/
│   └── workflows/
│       └── ci.yml               # GitHub Actions CI workflow
├── database/
│   └── schema.sql               # MySQL relational schema & seed data
├── public/
│   ├── api/
│   │   ├── assets.php           # REST API router for assets
│   │   ├── categories.php       # Categories endpoint
│   │   └── stats.php            # Dashboard analytics endpoint
│   ├── css/
│   │   └── style.css            # Responsive layout & status styling
│   ├── js/
│   │   └── app.js               # Dojo AMD client logic & event bus
│   └── index.html               # Main single-page application entry point
├── src/
│   ├── AssetService.php         # Core business logic & database queries
│   ├── Database.php             # PDO database manager (MySQL + SQLite)
│   └── Validator.php            # Input sanitization and boundary validator
├── tests/
│   ├── AssetServiceTest.php     # PHP unit tests
│   └── test_api.py              # End-to-end REST API integration tests
├── Dockerfile                   # Apache PHP 8.2 image definition
├── docker-compose.yml           # Multi-service stack (Web + MySQL)
└── README.md
```

---

## REST API Specification

| Endpoint | Method | Description |
| :--- | :--- | :--- |
| `/api/categories.php` | `GET` | Retrieve list of all equipment categories |
| `/api/stats.php` | `GET` | Aggregated metrics (count, valuation, low stock alerts) |
| `/api/assets.php` | `GET` | List assets (supports `?category=`, `?status=`, `?search=`) |
| `/api/assets.php?id={id}` | `GET` | Retrieve single asset profile with transaction audit log |
| `/api/assets.php` | `POST` | Register a new asset (validates tag uniqueness & ranges) |
| `/api/assets.php?id={id}&action=adjust` | `PATCH` | Adjust stock quantity (`+` or `-`) with audit reason |
| `/api/assets.php?id={id}` | `DELETE` | Remove asset and associated historical logs |

---

## Getting Started

### Option 1: Quickstart with Local PHP Server

1. **Clone the repository:**
   ```bash
   git clone https://github.com/awanish5101/dojo-php-inventory-management.git
   cd dojo-php-inventory-management
   ```

2. **Launch the built-in development server:**
   ```bash
   php -S 127.0.0.1:8080 -t public
   ```

3. **Access the application:**
   Open your browser and navigate to `http://127.0.0.1:8080`.

---

### Option 2: Run with Docker Compose (PHP + MySQL 8.0)

1. **Start the containers:**
   ```bash
   docker-compose up -d --build
   ```

2. **Check container status:**
   ```bash
   docker-compose ps
   ```

3. **Open the application:**
   Navigate to `http://localhost:8080` in your web browser.

---

## Running the Automated Test Suite

### 1. PHP Unit Tests
Runs isolated business logic and boundary tests:
```bash
php tests/AssetServiceTest.php
```

### 2. End-to-End REST API Integration Tests
Spins up a lightweight server and validates all HTTP routes:
```bash
python3 tests/test_api.py
```

---

## Author & Contact

**Awanish Chaurasiya**  
*Email:* [avanish84ya@gmail.com](mailto:avanish84ya@gmail.com)  
*LinkedIn:* [linkedin.com/in/awanish-chaurasiya](https://linkedin.com/in/awanish-chaurasiya)  
*GitHub:* [github.com/awanish5101](https://github.com/awanish5101)
