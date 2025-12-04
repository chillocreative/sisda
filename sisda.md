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
  - Implemented a **Red Badge with Count** in the sidebar navigation next to "Kelulusan Pengguna".
  - Displays the exact number of pending approvals (e.g., "1").
  - **Visibility**: Restricted to Super Admin and Admin roles.
  - **Backend**: Uses `HandleInertiaRequests` middleware to share `pendingApprovalsCount` with all pages.

### 2. User Management (`/users`)
- **Delete Functionality Upgrade**:
  - Implemented a custom **Delete Confirmation Modal**.
  - **Rejected User Handling**: Added specific logic to allow deletion of users with "Ditolak" status, displaying a warning message: *"Pengguna ini mempunyai status 'Ditolak'"*.
  - Fixed issue where native confirm dialogs were not providing a good user experience.
- **Last Login Tracking**:
  - Implemented backend logic to update `last_login` timestamp on successful authentication.
  - Updated `User` model to include `last_login` in fillable and casts.
  - **Timestamp Formatting**: Updated the column to show a detailed, readable format: `DD MMM YYYY, HH:MM AM/PM` (e.g., *04 Dec 2025, 02:52 PM*).
  - Changed locale to `en-US` to ensure AM/PM format instead of PG (Malay format).

### 3. Production Image Handling
- **Issue Identified**: Uploaded images (Kad Pengenalan) were showing 404 errors in production.
- **Root Cause**: 
  - User-uploaded files in `storage/app/public` are not tracked by Git.
  - Missing or broken symbolic link between `public/storage` and `storage/app/public`.
- **Solutions Provided**:
  - Created `TROUBLESHOOTING_IMAGES.txt` with manual file upload instructions via cPanel File Manager.
  - Created `FIX_BROKEN_IMAGES.txt` with commands to repair the storage symlink.
  - Added `onError` handlers to all image preview components to show "Imej Tidak Dijumpai" placeholder when images are missing.

### 4. Deployment Configuration
- **cPanel Deployment Strategy**:
  - **Critical Discovery**: npm is NOT available on the cPanel server.
  - **Solution**: Build assets locally and commit to Git instead of building on server.
  - **Changes Made**:
    - Removed `/public/build` from `.gitignore` to allow committing built assets.
    - Removed all npm commands from `.cpanel.yml` deployment script.
    - Assets are now built locally with `npm run build` before committing.
- **Deployment Process**:
  1. Make frontend changes locally.
  2. Run `npm run build` to compile assets.
  3. Commit changes (including `public/build` folder).
  4. Push to Git.
  5. Deploy via cPanel Git Version Control.
- **Documentation**:
  - Created `CPANEL_DEPLOYMENT.txt` with detailed manual setup instructions.
  - Created `TROUBLESHOOTING_IMAGES.txt` for image-related issues.
  - Created `FIX_BROKEN_IMAGES.txt` for storage symlink repair.

### 5. Debug Tools Added
- **Debug Route**: `/debug-pending-count` - Returns JSON with user details and pending approval count for troubleshooting.
- **Console Logging**: Added version marker (`=== SISDA Layout v2.0 ===`) and detailed logging in `AuthenticatedLayout.jsx` to verify frontend version and data.

## Technical Stack
- **Backend**: Laravel 11 (PHP 8.2+)
- **Frontend**: React.js 18, Inertia.js, Vite
- **Styling**: Tailwind CSS, Lucide React (Icons)
- **Database**: MySQL
- **Authentication**: Laravel Breeze
- **Deployment**: cPanel Git Version Control

## Key Features
- **Role-Based Access Control (RBAC)**:
  - **Super Admin**: Full access to all modules and data.
  - **Admin**: Access limited to their specific territory (Parlimen/Bandar).
  - **User**: Restricted access for data entry and viewing specific reports.
- **Master Data Management**: Comprehensive management of Negeri, Parlimen, Bandar, KADUN, and other reference data.
- **Reporting**: "Hasil Culaan" and "Data Pengundi" modules for detailed data analysis and export.
- **Image Upload**: Support for Kad Pengenalan images with preview and error handling.

## Important Notes for Production
1. **Frontend Changes**: Always run `npm run build` locally before committing and deploying.
2. **Uploaded Files**: Must be manually transferred to production server (not tracked by Git).
3. **Storage Symlink**: Ensure `php artisan storage:link` is run on production server.
4. **Cache Clearing**: Deployment automatically runs `php artisan optimize:clear` to refresh caches.

## Next Steps
- **Post-Deployment Verification**:
  - Verify pending approval badge visibility on the live server.
  - Test image upload and display functionality.
  - Confirm storage symlink is working correctly.
- **Future Enhancements**:
  - Advanced filtering for reports.
  - Bulk import functionality for voter data.
  - Automated image backup/sync solution.
