# SISDA - Sistem Data Pengundi

## Project Overview
SISDA is a comprehensive voter data management system built with Laravel 11 and React (Inertia.js). It is designed to manage voter information, track data collection (Culaan), and handle user approvals with role-based access control.

## Latest Development Updates (December 4, 2025)

### 1. User Approval Module Enhancements
- **Custom Modal Integration**: Replaced native browser `confirm()` dialogs with a custom, styled `Modal` component for approving and rejecting users.
- **UI/UX Improvements**:
  - Added `PrimaryButton` (Green) for approval and `DangerButton` (Red) for rejection.
  - Dynamic confirmation messages displaying the user's name.
  - Loading states during processing.
- **Notification System**:
  - Implemented a **Bell Icon** in the sidebar navigation next to "Kelulusan Pengguna".
  - The icon is **red and filled** when there are pending approvals.
  - **Size Optimization**: Adjusted icon size to `h-3 w-3` (half size) for better visual balance.
  - **Visibility**: Restricted to Super Admin and Admin roles.

### 2. User Management (`/users`)
- **Delete Functionality Upgrade**:
  - Implemented a custom **Delete Confirmation Modal**.
  - **Rejected User Handling**: Added specific logic to allow deletion of users with "Ditolak" status, displaying a warning message: *"Pengguna ini mempunyai status 'Ditolak'"*.
  - Fixed issue where native confirm dialogs were not providing a good user experience.
- **Timestamp Formatting**:
  - Updated the "Last Login" column to show a detailed, readable format: `DD MMM YYYY, HH:MM AM/PM` (e.g., *04 Dec 2025, 02:05 PM*).

### 3. Deployment Readiness
- **cPanel Configuration**:
  - Created `.cpanel.yml` for automated deployment via cPanel Git Version Control.
  - Configured to handle Composer dependencies, migrations, and asset building.
- **Production Build**:
  - Successfully ran `npm run build` to compile and optimize assets for production.
  - Verified that all icons (including the new bell icon) render correctly in the production build.
- **Documentation**:
  - Created `DEPLOYMENT_CHECKLIST.txt` and `DEPLOYMENT_READY.txt` to guide the deployment process.
  - Updated `.gitignore` to exclude markdown documentation files (except `sisda.md`) from the repository to keep the production environment clean.

## Technical Stack
- **Backend**: Laravel 11 (PHP 8.2+)
- **Frontend**: React.js 18, Inertia.js
- **Styling**: Tailwind CSS, Lucide React (Icons)
- **Database**: MySQL
- **Authentication**: Laravel Breeze

## Key Features
- **Role-Based Access Control (RBAC)**:
  - **Super Admin**: Full access to all modules and data.
  - **Admin**: Access limited to their specific territory (Parlimen/Bandar).
  - **User**: Restricted access for data entry and viewing specific reports.
- **Master Data Management**: Comprehensive management of Negeri, Parlimen, Bandar, KADUN, and other reference data.
- **Reporting**: "Hasil Culaan" and "Data Pengundi" modules for detailed data analysis and export.

## Next Steps
- **Post-Deployment Verification**:
  - Verify bell icon visibility on the live server.
  - Test the full user registration and approval flow in the production environment.
- **Future Enhancements**:
  - Advanced filtering for reports.
  - Bulk import functionality for voter data.
