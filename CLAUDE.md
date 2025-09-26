# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Laravel 8 application called "Sistem Data Pengundi" (Voter Data System) - a Malaysian voter registration and data management system. The application manages voter information and initial registration ("Mula Culaan") data with role-based access control.

## Development Commands

### Laravel Artisan Commands
- `php artisan serve` - Start development server
- `php artisan migrate` - Run database migrations
- `php artisan migrate:rollback` - Rollback migrations
- `php artisan tinker` - Laravel REPL for testing
- `php artisan key:generate` - Generate application key
- `php artisan config:cache` - Cache configuration files
- `php artisan route:list` - List all routes

### Frontend Build Commands
- `npm run dev` - Build assets for development
- `npm run production` - Build assets for production
- `npm run watch` - Watch files and rebuild on changes
- `npm run hot` - Hot module replacement for development

### Testing
- `vendor/bin/phpunit` or `php artisan test` - Run PHPUnit tests

## Core Application Architecture

### User Roles & Authentication
The system has three user roles managed through the `Otentikasi` middleware:
- **superadmin**: Full system access including user management
- **admin**: Data master management and user oversight
- **user**: Data entry for voter information and initial registration

Role-based route protection is implemented using middleware groups in `routes/web.php`:
- Lines 68-70: superadmin only routes
- Lines 72-97: admin/superadmin routes (data master management)
- Lines 99-106: user/admin routes (data entry)

### Main Data Models

#### DataPengundi (Voter Data)
- **File**: `app/Models/DataPengundi.php`
- **Purpose**: Stores voter registration information
- **Key fields**: name, no_kad (ID number), umur (age), phone, bangsa (ethnicity), hubungan (relationship), address details, political preferences
- **Features**: Draft mode support, user association

#### MulaCulaan (Initial Registration)
- **File**: `app/Models/MulaCulaan.php`
- **Purpose**: Stores initial voter outreach and registration data
- **Key fields**: personal info, address, household data, income, occupation, assistance types, political preferences, IC photo upload
- **Features**: Date/time tracking, file upload support

### Key Controllers

#### DataPengundiController
- Handles voter data CRUD operations
- Supports draft saving functionality
- Edit/update routes: `/data-pengundi/{id}`

#### MulaCulaanController
- Manages initial registration data
- Edit/update routes: `/mula-culaan/{id}`

#### ReportController
- Generates reports for both data types
- Excel export functionality using Maatwebsite/Excel
- Routes: `/report/data-pengundi` and `/report/mula-culaan`

#### GlobalController
- Provides AJAX endpoints for dynamic form data
- Location-based filtering (negeri -> bandar -> parlimen -> kadun -> mpkk)
- Routes under `/global` prefix

### Data Master Management
Administrative data is managed through resource controllers under `/data-master`:
- Negeri (States), Bandar (Towns), Parlimen (Parliament), Kadun (State Assembly), MPKK (Village Development Committee)
- Support data: TujuanSumbangan, JenisSumbangan, BantuanLain, KeahlianPartai, KecenderunganPolitik, Hubungan

### File Upload & Export Features
- IC (Identity Card) photo uploads for MulaCulaan records
- Excel export functionality for both DataPengundi and MulaCulaan using `app/Exports/` classes
- PDF export support via DomPDF (barryvdh/laravel-dompdf)

### Frontend Architecture
- Traditional Blade templating with layouts in `resources/views/layouts/`
- Bootstrap-based UI with custom CSS in `public/assets/css/styles.css`
- Laravel Mix for asset compilation
- AJAX interactions for dynamic form updates

## Database Schema
The application uses MySQL with migrations in `database/migrations/`. Key tables include:
- `users` with role relationships
- `data_pengundi` for voter data
- `mula_culaan` for initial registrations
- Master data tables (negeri, bandar, parlimen, kadun, mpkk, etc.)
- Support tables for dropdown options

## Development Notes
- Uses Laravel Sanctum for API authentication (though primarily web-based)
- Custom middleware `Otentikasi` for role-based access control
- File uploads stored in `public/ic/` directory
- Environment configuration follows Laravel standards (copy `.env.example` to `.env`)
- Uses Laravel Mix for frontend asset compilation