-- =====================================================================
-- XIT HRM - database schema
--
-- Import into an EXISTING, SELECTED database (phpMyAdmin: click the
-- database first, then Import). No CREATE DATABASE / USE on purpose:
-- the database name differs per server (e.g. if0_12345678_xit_hrm).
--
-- Rules:
--  - InnoDB, utf8mb4, utf8mb4_unicode_ci on every table.
--  - No DEFAULT CURRENT_TIMESTAMP: the app writes all timestamps from PHP's Clock.
--  - Foreign keys use RESTRICT: history is never deleted by cascade.
--  - Plain CREATE TABLE (no DROP): importing twice fails instead of wiping data.
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- admins: standalone, inserted manually (no registration feature)
-- ---------------------------------------------------------------------
CREATE TABLE admins (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    name        VARCHAR(100)    NOT NULL,
    email       VARCHAR(255)    NOT NULL,
    password    VARCHAR(255)    NOT NULL,
    phone       VARCHAR(20)     NULL,
    created_at  DATETIME        NOT NULL,
    updated_at  DATETIME        NOT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_admins_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- employees
--   Category A (admin):    employee_id, name, designation, xit_email,
--                          joining_date, status, shift_duration_minutes
--   Category B (employee): phone, second_phone, personal_email, nid,
--                          photo_path, address, birth_date  (NULL until first login)
-- ---------------------------------------------------------------------
CREATE TABLE employees (
    id                      INT UNSIGNED                NOT NULL AUTO_INCREMENT,
    employee_id             VARCHAR(20)                 NOT NULL,
    name                    VARCHAR(100)                NOT NULL,
    designation             VARCHAR(100)                NOT NULL,
    xit_email               VARCHAR(255)                NOT NULL,
    joining_date            DATE                        NOT NULL,
    status                  ENUM('active', 'inactive')  NOT NULL,
    shift_duration_minutes  SMALLINT UNSIGNED           NOT NULL,
    password                VARCHAR(255)                NOT NULL,

    phone                   VARCHAR(20)                 NULL,
    second_phone            VARCHAR(20)                 NULL,
    personal_email          VARCHAR(255)                NULL,
    nid                     VARCHAR(20)                 NULL,
    photo_path              VARCHAR(255)                NULL,
    address                 VARCHAR(500)                NULL,
    birth_date              DATE                        NULL,

    profile_completed_at    DATETIME                    NULL,
    deleted_at              DATETIME                    NULL,
    created_at              DATETIME                    NOT NULL,
    updated_at              DATETIME                    NOT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_employees_employee_id (employee_id),
    UNIQUE KEY uq_employees_xit_email (xit_email),
    KEY idx_employees_status (status),
    KEY idx_employees_deleted_at (deleted_at),
    KEY idx_employees_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- departments: fixed list, filled by seeds/departments.sql
-- ---------------------------------------------------------------------
CREATE TABLE departments (
    id    INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    name  VARCHAR(50)   NOT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_departments_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- employee_departments: many-to-many link (one row = one membership)
-- ---------------------------------------------------------------------
CREATE TABLE employee_departments (
    employee_id    INT UNSIGNED  NOT NULL,
    department_id  INT UNSIGNED  NOT NULL,

    PRIMARY KEY (employee_id, department_id),
    KEY idx_employee_departments_department_id (department_id),

    CONSTRAINT fk_employee_departments_employee
        FOREIGN KEY (employee_id) REFERENCES employees (id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_employee_departments_department
        FOREIGN KEY (department_id) REFERENCES departments (id)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- attendance_records: one row per employee per day
--   left_at NULL = work not ended (incomplete if the day is over)
-- ---------------------------------------------------------------------
CREATE TABLE attendance_records (
    id               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    employee_id      INT UNSIGNED  NOT NULL,
    attendance_date  DATE          NOT NULL,
    joined_at        DATETIME      NOT NULL,
    left_at          DATETIME      NULL,
    created_at       DATETIME      NOT NULL,
    updated_at       DATETIME      NOT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_attendance_employee_date (employee_id, attendance_date),
    KEY idx_attendance_date (attendance_date),

    CONSTRAINT fk_attendance_records_employee
        FOREIGN KEY (employee_id) REFERENCES employees (id)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- break_sessions: many breaks per attendance day
--   ended_at NULL = break still open
-- ---------------------------------------------------------------------
CREATE TABLE break_sessions (
    id             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    attendance_id  INT UNSIGNED  NOT NULL,
    started_at     DATETIME      NOT NULL,
    ended_at       DATETIME      NULL,

    PRIMARY KEY (id),
    KEY idx_break_sessions_attendance_id (attendance_id),

    CONSTRAINT fk_break_sessions_attendance
        FOREIGN KEY (attendance_id) REFERENCES attendance_records (id)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;