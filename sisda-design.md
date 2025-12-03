# SISDA Design Guidelines

## 1. Clean and Minimalist Design
- **Philosophy**: Focus on content and functionality with ample whitespace.
- **Typography**: Use clean sans-serif fonts (e.g., Inter, Roboto).
- **Layout**: Grid-based layouts with consistent padding and margins.
- **Visual Hierarchy**: Use font weights and sizes to distinguish between headers, subheaders, and body text.

## 2. Shadcn UI Style with Custom Colors
- **Base Style**: Follow the Shadcn UI aesthetic—neutral grays, crisp borders, and subtle shadows.
- **Color Palette**:
    - **Primary**: Neutral dark/light (Slate/Zinc) for structural elements.
    - **Accent**: A primary brand color for actions (e.g., a deep blue or emerald green).
    - **Graphs & Indicators**:
        - **Success**: Soft Green (e.g., Emerald-500)
        - **Warning**: Warm Amber (e.g., Amber-500)
        - **Danger/Error**: Soft Red (e.g., Rose-500)
        - **Info**: Calm Blue (e.g., Sky-500)
        - **Neutral/Inactive**: Slate-400
- **Components**:
    - Cards with subtle borders and slight rounded corners (rounded-lg).
    - Buttons with clear states (hover, active, disabled).
    - Inputs with focus rings matching the accent color.

## 3. Mobile Responsiveness
- **Grid System**: Use a responsive grid that adapts from 1 column on mobile to multiple columns on desktop.
- **Navigation**:
    - Desktop: Sidebar or top navigation bar.
    - Mobile: Hamburger menu or bottom navigation bar.
- **Touch Targets**: Ensure buttons and interactive elements are large enough for touch (min 44px).
- **Tables**: Use horizontal scrolling or card views for data tables on smaller screens.
- **Testing**: Verify layouts on standard breakpoints (sm, md, lg, xl).

## 4. User Roles & Permissions

### Role-Based Access Control
- **Super Admin**: Full access to all modules and all data across all territories
- **Admin**: Full CRUD access within their assigned territory (Negeri/Bandar/KADUN)
- **User**: Can create new records, edit their own submissions, view-only for others within their territory

### Reports Module Access Control

#### Hasil Culaan & Data Pengundi
**Super Admin**:
- Full CRUD access to all records
- Can view, create, edit, and delete any record
- No territory restrictions

**Admin**:
- Full CRUD access within their Bandar (Parliament constituency)
- Can view all records in their Bandar
- Can create new records for their Bandar
- Can edit any record in their Bandar
- Can delete any record in their Bandar
- Cannot modify records outside their Bandar

**Regular User**:
- Can view all records within their KADUN (State Assembly)
- Can create new records for their KADUN
- Can edit ONLY their own submissions within their KADUN
- Can delete ONLY their own submissions within their KADUN
- Cannot modify other users' records
- View-only access for records created by others

### Territory-Based Filtering
- **Super Admin**: Sees all data (no filtering)
- **Admin**: Automatically filtered to their Bandar
- **User**: Automatically filtered to their KADUN

### UI Element Visibility
- **Create Button**: Visible to all authenticated users
- **Edit Button**: Visible only for records the user can modify
- **Delete Button**: Visible only for records the user can modify
- **Bulk Select Checkbox**: Visible only for records the user can modify
- **View Button**: Shown for records user cannot modify (Eye icon)

### Frontend Permission Logic (`canModifyRecord`)
- **Super Admin**: Returns `true` for all records.
- **Admin**: Returns `true` if record's `bandar` matches user's `bandar`.
- **User**: Returns `true` if record's `submitted_by` matches user's `id` AND record's `kadun` matches user's `kadun`.

## 5. Navigation Structure
### Super Admin Sidebar
- **Dashboard**: Overview of key metrics.
- **Users**: User management (CRUD).
- **Master Data**: Management of core system data.
- **Report**: Analytics and reporting tools.
- **Profile**: User account settings.
- **Settings**: System-wide configurations.
- **Logout**: Secure session termination.

### Mobile Navigation
- **Design**: Modern, bottom-tab bar or floating action button (FAB) style for primary actions.
- **Menu**:
    - Use high-quality, modern icons (e.g., Lucide React or Heroicons).
    - Smooth transitions for opening/closing the menu (slide-over or drawer).
    - Active state highlighting with accent colors.
    - "More" menu for secondary items if space is limited.

## 6. Language
- **Primary Language**: Bahasa Melayu (Malaysian Language).
- All UI text, labels, messages, and notifications must be in Bahasa Melayu.

## 7. Authentication & Authorization
- **Authentication Method**: Telephone number-based authentication.
- **User Roles**:
    - `super_admin`: Full system access, can approve users from any territory.
    - `admin`: Management access, restricted to assigned territory (Negeri/Bandar/KADUN).
    - `user`: Standard access, restricted to assigned territory.
- **Login Credentials**: Users log in with telephone number and password.
- **Registration**: 
    - Users register with Name, Telephone, Email (optional), Password.
    - **Territory Selection**: Users must select Negeri, Bandar, and KADUN during registration.
    - **Default Role**: All new registrations default to `user`.
    - **Approval Workflow**: New accounts are `pending` and must be approved by an Admin or Super Admin before logging in.

## 8. Database Schema Updates
- **Users Table**:
    - `id`: Primary key
    - `name`: User's full name
    - `telephone`: Unique telephone number (used for login)
    - `email`: Email address (nullable)
    - `role`: User role (default: 'user')
    - `status`: Account status (`pending`, `approved`, `rejected`) - **[NEW]**
    - `negeri_id`: Foreign key to `negeri` table - **[NEW]**
    - `bandar_id`: Foreign key to `bandar` table - **[NEW]**
    - `kadun_id`: Foreign key to `kadun` table - **[NEW]**
    - `approved_by`: Foreign key to `users` table (approver) - **[NEW]**
    - `approved_at`: Timestamp of approval - **[NEW]**
    - `password`: Hashed password
    - `remember_token`: Session token
    - `created_at`, `updated_at`: Timestamps

## 9. Seeded Accounts
- **Super Admin**:
    - Telephone: `0123456789`
    - Password: `password`
    - Role: `super_admin`
    - Status: `approved`

## 10. Dashboard Design Reference
- **Style**: Modern, clean dashboard with card-based layout
- **Components**:
    - Sidebar navigation with icons
    - Top metric cards for key statistics
    - Data visualization (bar charts, progress bars)
    - Data tables with filtering/sorting
    - Soft color palette with subtle shadows
- **Reference**: Dashboard mockup saved for implementation
- **Layout**: Responsive grid system adapting to screen sizes

---

## 11. Laporan (Reports) Module - Complete Implementation

### 11.1 Module Overview
- **Purpose**: Comprehensive data collection and reporting system
- **Primary Feature**: Hasil Culaan (Data Collection Results)
- **Secondary Feature**: Data Pengundi (Voter Data) - Planned

### 11.2 Hasil Culaan Features

#### Database Schema
**Table**: `hasil_culaan` (22 data fields + metadata)

**Personal Information**:
- `nama`: Full name (required)
- `no_ic`: IC number - digits only, 12 characters max (required)
- `umur`: Age - auto-calculated from IC (required, read-only)
- `no_tel`: Telephone number (required)
- `bangsa`: Ethnicity (required, dropdown)

**Address Information**:
- `alamat`: Full address (required, textarea)
- `poskod`: Postal code (required)
- `negeri`: State (required)
- `bandar`: City (required)
- `kadun`: KADUN (required)

**Household Information**:
- `bil_isi_rumah`: Number of household members (required, integer)
- `pendapatan_isi_rumah`: Household income in RM (required, decimal)
- `pekerjaan`: Occupation (required)
- `pemilik_rumah`: Home ownership status (required, dropdown)

**Assistance & Political Information**:
- `jenis_sumbangan`: Type of contribution (optional)
- `tujuan_sumbangan`: Purpose of contribution (optional)
- `bantuan_lain`: Other assistance (optional)
- `keahlian_parti`: Party membership (optional)
- `kecenderungan_politik`: Political inclination (optional)

**Documents & Notes**:
- `kad_pengenalan`: IC card image path (optional, file upload)
- `nota`: Additional notes (optional, textarea)

**Metadata**:
- `submitted_by`: Foreign key to users table (auto-filled)
- `created_at`, `updated_at`: Timestamps

#### Smart IC Features
**IC Number Validation**:
- Only digits allowed (no symbols)
- Maximum 12 characters
- Real-time validation
- Example format: `900101145678`
- Helper text: "Hanya angka sahaja (contoh: 900101145678)"

**Auto-Age Calculation**:
- Automatically calculates age from IC number
- Uses Malaysian IC format (YYMMDD...)
- Smart century detection:
  - `00-25` = born in 2000s
  - `26-99` = born in 1900s
- Age field is read-only with gray background
- Age clears automatically when IC is empty or has < 6 digits
- Helper text: "Dikira automatik dari No. IC"
- Example: IC `900101145678` → Age `35` (born 01 Jan 1990)

#### Image Upload Feature
**Kad Pengenalan Upload**:
- Click-to-upload interface with dashed border
- File type validation (images only)
- File size validation (max 5MB)
- Image preview after selection
- Animated progress indicator (0-100%)
- Loading spinner with percentage display
- Remove button to clear uploaded image
- Displays filename after upload
- Storage location: `storage/app/public/kad-pengenalan/`
- Public access via symbolic link

**Backend Handling**:
- Validation rule: `nullable|image|max:5120`
- Automatic file storage using Laravel Storage
- File path saved to database
- Old image deletion on update (if new image uploaded)

#### CRUD Operations
**List Page** (`Reports/HasilCulaan/Index.jsx`):
- Data table with 11 key columns
- Checkbox selection (individual & select all)
- Bulk delete functionality
- Individual edit and delete actions
- Pagination with page numbers
- "Menunjukkan X hingga Y daripada Z rekod"

**Filters**:
- Search field (Nama, No. IC, No. Tel)
- Date range filter (Tarikh Dari, Tarikh Hingga)
- Collapsible filter panel with "Tunjukkan/Sembunyikan" toggle
- ChevronDown icon with rotation animation
- Reset button to clear all filters
- Filter button to apply filters

**Create Form** (`Reports/HasilCulaan/Create.jsx`):
- 22 fields organized in 5 sections:
  1. Maklumat Peribadi (Personal Information)
  2. Maklumat Alamat (Address Information)
  3. Maklumat Isi Rumah (Household Information)
  4. Maklumat Bantuan & Politik (Assistance & Political)
  5. Dokumen & Nota (Documents & Notes)
- Form validation with error display
- Required field indicators (*)
- Helper text for special fields
- Cancel and Save buttons
- Auto-fill submitted_by on save

**Edit Form** (`Reports/HasilCulaan/Edit.jsx`):
- Identical structure to Create form
- Pre-populated with existing data
- Update button instead of Save
- Preserves existing image if not replaced

**Delete**:
- Individual delete with confirmation
- Bulk delete for selected items
- Confirmation dialog: "Adakah anda pasti mahu memadam rekod ini?"
- Success message after deletion

**Excel Export**:
- Export button with Download icon
- Exports filtered data to .xlsx format
- Uses `maatwebsite/excel` package
- Includes all 22 fields plus metadata
- Custom headings in Bahasa Melayu
- Filename format: `hasil-culaan-{date}.xlsx`

#### UI/UX Enhancements
**Filter Toggle**:
- Button text: "Tunjukkan" (show) / "Sembunyikan" (hide)
- ChevronDown icon next to text
- Icon rotates 180° when filters expanded
- Smooth transition animation
- Flexbox layout with proper spacing

**Form Sections**:
- White cards with rounded corners
- Section headers with semibold text
- Responsive grid layout (1 column mobile, 2 columns desktop)
- Consistent padding and spacing
- Gray background for read-only fields

**Data Table**:
- Hover effect on rows
- Alternating row colors (subtle)
- Action buttons with icon-only design
- Edit button: Sky blue hover
- Delete button: Rose red hover
- Responsive horizontal scroll on mobile

**Pagination**:
- Active page highlighted (dark background)
- Disabled state for unavailable pages
- Previous/Next buttons with labels
- Page numbers in between
- Hover effects on clickable pages

### 11.3 Routes
```php
// Reports Dashboard
GET /reports

// Hasil Culaan
GET /reports/hasil-culaan
GET /reports/hasil-culaan/create
POST /reports/hasil-culaan
GET /reports/hasil-culaan/{id}/edit
PUT /reports/hasil-culaan/{id}
DELETE /reports/hasil-culaan/{id}
POST /reports/hasil-culaan/bulk-delete
GET /reports/hasil-culaan/export
```

### 11.4 Controllers
**ReportsController** (`app/Http/Controllers/ReportsController.php`):
- `index()`: Reports dashboard
- `hasilCulaanIndex()`: List with filters and pagination
- `hasilCulaanCreate()`: Show create form
- `hasilCulaanStore()`: Save new record with file upload
- `hasilCulaanEdit()`: Show edit form
- `hasilCulaanUpdate()`: Update record with file upload
- `hasilCulaanDestroy()`: Delete single record
- `hasilCulaanBulkDelete()`: Delete multiple records
- `exportHasilCulaan()`: Export to Excel

### 11.5 Models
**HasilCulaan** (`app/Models/HasilCulaan.php`):
- Fillable: All 22 fields + submitted_by
- Relationship: `belongsTo(User::class, 'submitted_by')`
- Casts: Numeric fields to appropriate types

### 11.6 Dependencies
- `maatwebsite/excel`: Excel export functionality
- `lucide-react`: Modern icon library
- Laravel Storage: File upload handling
- Inertia.js: SPA-like experience

---

## 12. Data Induk (Master Data) Module - Complete Implementation

### 12.1 Module Overview
- **Purpose**: Central management of core system data
- **Access**: Super Admin (Full), Admin (Restricted to Parlimen)
- **Features**: Full CRUD operations with inline editing
- **UI Pattern**: Consistent across all modules

### 12.2 Implemented Master Data Categories

#### 1. Negeri (States)
- **Table**: `negeri`
- **Fields**: `id`, `nama`, `timestamps`
- **Data**: All 16 Malaysian states and federal territories
- **Features**: Inline editing, search, pagination
- **Route**: `/master-data/negeri`

#### 2. Parlimen (Parliament)
- **Table**: `bandar`
- **Fields**: `id`, `nama`, `kod_parlimen`, `negeri_id`, `timestamps`
- **Relationships**: `belongsTo(Negeri)`
- **Features**: 
  - Table list view with Bil, Negeri, Parlimen columns
  - Filter by Negeri (State)
  - Search by Name or Parliament Code
  - Inline editing (Negeri dropdown, Kod + Nama inputs)
  - Edit and Delete icons
  - Add new parlimen via modal
  - Display format: "Kod - Nama" (e.g., "P41 - Kepala Batas")
- **Routes**: 
  - `GET /master-data/parlimen` - Index
  - `POST /master-data/parlimen` - Store
  - `PUT /master-data/parlimen/{parlimen}` - Update
  - `DELETE /master-data/parlimen/{parlimen}` - Destroy
- **Controller**: `MasterDataController@parlimenIndex`, `parlimenStore`, `parlimenUpdate`, `parlimenDestroy`
- **Data**: Pulau Pinang parliaments seeded (P41-P53, 13 constituencies)
- **Access**: Super Admin only

#### 3. Bandar (Cities/Parliament)
- **Table**: `bandar`
- **Fields**: `id`, `nama`, `kod_parlimen`, `negeri_id`, `timestamps`
- **Relationships**: `belongsTo(Negeri)`
- **Features**: 
  - List view with pagination
  - Filter by Negeri (State)
  - Search by Name or Parliament Code
  - Inline editing
  - Display associated State
- **Routes**: 
  - List view: `/master-data/bandar`
- **Data**: Pulau Pinang parliaments seeded
- **Note**: Shares same table as Parlimen but different UI/presentation

#### 4. KADUN (State Assembly/DUN)
- **Table**: `kadun`
- **Fields**: `id`, `nama`, `kod_dun`, `bandar_id`, `timestamps`
- **Relationships**: `belongsTo(Bandar)`
- **Features**: Filter by Parliament, inline editing
- **Route**: `/master-data/kadun`
- **Data**: Pulau Pinang DUNs seeded (40 constituencies)

#### 5. MPKK (Community Management Council)
- **Table**: `mpkk`
- **Fields**: `id`, `nama`, `kadun_id`, `timestamps`
- **Relationships**: `belongsTo(Kadun)`
- **Features**: 
  - Display Parlimen, KADUN, and MPKK name
  - Filter by KADUN
  - Enhanced search (MPKK name, KADUN name, Parlimen name)
  - Inline editing
- **Route**: `/master-data/mpkk`
- **Data**: MPKKs for Pinang Tunggal, Bertam, and Penaga seeded

#### 6. Tujuan Sumbangan (Donation Purpose)
- **Table**: `tujuan_sumbangan`
- **Fields**: `id`, `nama`, `timestamps`
- **Features**: Inline editing, search, pagination
- **Route**: `/master-data/tujuan-sumbangan`
- **Data Seeded**:
  - Asnaf / Keluarga Miskin
  - Kematian
  - Kelahiran Bayi
  - Rumah Terbakar
  - Kemalangan Jalan Raya
  - Ribut
  - Banjir

#### 7. Jenis Sumbangan (Donation Type)
- **Table**: `jenis_sumbangan`
- **Fields**: `id`, `nama`, `timestamps`
- **Features**: Inline editing, search, pagination
- **Route**: `/master-data/jenis-sumbangan`
- **Data Seeded**:
  - Hamper Barangan Keperluan Dapur
  - Wang Tunai
  - Hamper Perayaan
  - Tiada
  - Lain-lain

#### 8. Bantuan Lain (Other Assistance)
- **Table**: `bantuan_lain`
- **Fields**: `id`, `nama`, `timestamps`
- **Features**: Inline editing, search, pagination
- **Route**: `/master-data/bantuan-lain`
- **Data Seeded**:
  - ZAKAT PULAU PINANG (ZPP)
  - JABATAN KEBAJIKAN MASYARAKAT (JKM)
  - WARGA EMAS (i-Sejahtera)
  - SURI EMAS (i-Sejahtera)
  - TIADA
  - Ibu Tunggal (i-Sejahtera)
  - Lain-lain

#### 9. Keahlian Parti (Party Membership)
- **Table**: `keahlian_parti`
- **Fields**: `id`, `nama`, `timestamps`
- **Features**: Inline editing, search, pagination
- **Route**: `/master-data/keahlian-parti`
- **Data Seeded**:
  - KEADILAN, PPBM, UMNO, DAP, MIC, MCA
  - GERAKAN, PUTRA, PBM, MUDA, PEJUANG
  - TIDAK PASTI, TIDAK BERPARTI

#### 10. Kecenderungan Politik (Political Tendency)
- **Table**: `kecenderungan_politik`
- **Fields**: `id`, `nama`, `timestamps`
- **Features**: Inline editing, search, pagination
- **Route**: `/master-data/kecenderungan-politik`
- **Data Seeded**:
  - PAKATAN HARAPAN (PH/BN)
  - BARISAN NASIONAL (BN/PN)
  - TIDAK PASTI

#### 11. Hubungan (Relationship)
- **Table**: `hubungan`
- **Fields**: `id`, `nama`, `timestamps`
- **Features**: Inline editing, search, pagination
- **Route**: `/master-data/hubungan`
- **Data Seeded**:
  - Suami, Isteri, Anak
  - Menantu, Adik, Abang

### 12.3 Common Features Across All Modules

#### UI/UX Pattern
- **List View**: Clean table with alternating row hover
- **Inline Editing**: Click edit icon → input field appears
- **Action Icons**:
  - Amber Edit button (pencil icon)
  - Red Delete button (trash icon)
  - Green Save button (check icon) when editing
  - Gray Cancel button (X icon) when editing
- **Add Modal**: Popup form for creating new entries
- **Search**: Real-time search functionality
- **Pagination**: Standard Laravel pagination with page numbers

#### Technical Implementation
- **Controller**: `MasterDataController.php`
- **Authorization**: Super Admin (Global access), Admin (Restricted to `bandar_id`)
- **Validation**: Unique name validation for all entries
- **Success Messages**: Bahasa Melayu feedback messages
- **Icons**: Lucide React icons throughout
- **Styling**: Consistent Tailwind CSS classes

### 12.4 Navigation Structure
- **Sidebar Menu**: "Data Induk" with dropdown
- **Submenu Items**:
  1. Negeri
  2. Parlimen
  3. Bandar (links to map view)
  4. KADUN
  5. MPKK
  6. Tujuan Sumbangan
  7. Jenis Sumbangan
  8. Bantuan Lain
  9. Keahlian Parti
  10. Kecenderungan Politik
  11. Hubungan

---

## 13. Data Pengundi (Voter Data) Module - Complete Implementation

### 13.1 Module Overview
- **Purpose**: Comprehensive voter data management
- **Access**: All authenticated users
- **Features**: Full CRUD operations with advanced filtering

### 13.2 Database Schema
**Table**: `data_pengundi` (27 fields)

**Personal Information**:
- `nama`: Full name (required)
- `no_ic`: IC number (required, unique)
- `umur`: Age - auto-calculated from IC (required, read-only)
- `jantina`: Gender (required, dropdown)
- `no_tel`: Telephone number (required)
- `bangsa`: Ethnicity (required)
- `agama`: Religion (required)

**Address Information**:
- `alamat`: Full address (required, textarea)
- `poskod`: Postal code (required)
- `negeri_id`: State (required, foreign key)
- `bandar_id`: City/Parliament (required, foreign key)
- `kadun_id`: KADUN (required, foreign key)
- `mpkk_id`: MPKK (required, foreign key)

**Household Information**:
- `bil_isi_rumah`: Number of household members (required)
- `pendapatan_isi_rumah`: Household income (required)
- `pekerjaan`: Occupation (required)
- `pemilik_rumah`: Home ownership (required)

**Assistance Information**:
- `jenis_sumbangan_id`: Donation type (optional, foreign key)
- `tujuan_sumbangan_id`: Donation purpose (optional, foreign key)
- `bantuan_lain_id`: Other assistance (optional, foreign key)

**Political Information**:
- `keahlian_parti_id`: Party membership (optional, foreign key)
- `kecenderungan_politik_id`: Political tendency (optional, foreign key)

**Family Information**:
- `nama_waris`: Heir name (optional)
- `hubungan_id`: Relationship (optional, foreign key)
- `no_tel_waris`: Heir telephone (optional)

**Documents & Notes**:
- `kad_pengenalan`: IC image path (optional)
- `nota`: Notes (optional, textarea)

**Metadata**:
- `submitted_by`: User who created record (auto-filled)
- `created_at`, `updated_at`: Timestamps

### 13.3 Features

#### Smart Cascading Dropdowns
- **Negeri → Bandar**: Cities filtered by selected state
- **Bandar → KADUN**: DUNs filtered by selected parliament
- **KADUN → MPKK**: MPKKs filtered by selected KADUN
- Real-time updates when parent selection changes
- Auto-clear child selections when parent changes

#### Advanced Filtering
- **Search**: Name, IC, Telephone
- **Date Range**: From/To date filters
- **Location Filters**:
  - Filter by Negeri (State)
  - Filter by Bandar (Parliament)
  - Filter by KADUN
  - Filter by MPKK
- **Collapsible Filter Panel**: Show/Hide toggle
- **Reset Filters**: Clear all filters button

#### CRUD Operations
- **Create**: Multi-section form with validation
- **Read**: Paginated list with 11 key columns
- **Update**: Edit form with pre-populated data
- **Update**: Edit form with pre-populated data
- **Delete**: Individual and bulk delete with confirmation
- **Role-Based UI**:
  - Edit/Delete buttons only visible for authorized records
  - View-only mode for unauthorized records
  - Bulk selection restricted to authorized records

#### Excel Export
- Export filtered data to .xlsx
- All 27 fields included
- Bahasa Melayu column headers
- Filename: `data-pengundi-{date}.xlsx`

### 13.4 UI Sections
**Create/Edit Form Sections**:
1. **Maklumat Peribadi** (Personal Information)
2. **Maklumat Alamat** (Address Information)
3. **Maklumat Isi Rumah** (Household Information)
4. **Maklumat Bantuan** (Assistance Information)
5. **Maklumat Politik** (Political Information)
6. **Maklumat Waris** (Heir Information)
7. **Dokumen & Nota** (Documents & Notes)

### 13.5 Routes
```php
GET /reports/data-pengundi
GET /reports/data-pengundi/create
POST /reports/data-pengundi
GET /reports/data-pengundi/{id}/edit
PUT /reports/data-pengundi/{id}
DELETE /reports/data-pengundi/{id}
POST /reports/data-pengundi/bulk-delete
GET /reports/data-pengundi/export
```

### 13.6 Relationships
**DataPengundi Model** (`app/Models/DataPengundi.php`):
- `belongsTo(User::class, 'submitted_by')`
- `belongsTo(Negeri::class)`
- `belongsTo(Bandar::class)`
- `belongsTo(Kadun::class)`
- `belongsTo(Mpkk::class)`
- `belongsTo(JenisSumbangan::class)`
- `belongsTo(TujuanSumbangan::class)`
- `belongsTo(BantuanLain::class)`
- `belongsTo(KeahlianParti::class)`
- `belongsTo(KecenderunganPolitik::class)`
- `belongsTo(Hubungan::class)`

---

## 14. File Storage Configuration

### 14.1 Storage Setup
- **Command**: `php artisan storage:link`
- **Purpose**: Create symbolic link from `public/storage` to `storage/app/public`
- **Result**: Uploaded files accessible via public URL

### 14.2 Directory Structure
```
storage/
  app/
    public/
      kad-pengenalan/     # IC card images
```

### 14.3 Public Access
- Files stored in `storage/app/public/kad-pengenalan/`
- Accessible via `/storage/kad-pengenalan/{filename}`
- Automatic path generation by Laravel Storage

---

## 15. Design Patterns & Best Practices

### 15.1 Form Design
- Grouped fields in logical sections
- Required field indicators (*)
- Inline validation errors
- Helper text for complex fields
- Read-only fields with gray background
- Consistent button placement (Cancel left, Submit right)

### 15.2 Data Tables
- Checkbox selection column
- Action column on the right
- Responsive design with horizontal scroll
- Hover effects for better UX
- Empty state messaging
- Pagination controls

### 15.3 File Uploads
- Visual upload area with dashed border
- Drag-and-drop support (via click)
- File type and size validation
- Preview before upload
- Progress indicator
- Remove/replace functionality

### 15.4 Icons & Visual Feedback
- Lucide React icons throughout
- Consistent icon sizing (h-4 w-4 for small, h-5 w-5 for medium)
- Hover states with color changes
- Loading states with spinners
- Success/error messages
- Animated transitions

### 15.5 Color Scheme
- **Primary**: Slate-900 (dark) for main actions
- **Success**: Emerald-500/600 for positive actions
- **Danger**: Rose-500/600 for destructive actions
- **Warning**: Amber-400/500 for edit actions
- **Info**: Sky-600 for informational elements
- **Neutral**: Slate-600 for secondary text
- **Borders**: Slate-200/300 for subtle separation

---

## 16. Session Summary (2025-11-24)

### 16.1 Completed Master Data Modules
✅ **Negeri** - All 16 Malaysian states
✅ **Bandar** - List view with state filtering (Map removed)
✅ **KADUN** - 40 Pulau Pinang constituencies
✅ **MPKK** - Community councils with enhanced search
✅ **Tujuan Sumbangan** - 7 donation purposes
✅ **Jenis Sumbangan** - 5 donation types
✅ **Bantuan Lain** - 7 assistance types
✅ **Keahlian Parti** - 13 political parties
✅ **Kecenderungan Politik** - 3 political tendencies
✅ **Hubungan** - 6 relationship types

### 16.2 Completed Data Pengundi Module
✅ Database schema with 27 fields
✅ Smart cascading dropdowns (Negeri → Bandar → KADUN → MPKK)
✅ Advanced filtering system
✅ Full CRUD operations
✅ Excel export functionality
✅ Bulk delete with selection
✅ Image upload for IC cards
✅ Auto-age calculation from IC

### 16.3 UI/UX Enhancements
✅ Consistent inline editing across all modules
✅ Icon-based action buttons (Edit, Delete, Save, Cancel)
✅ Collapsible filter panels
✅ Responsive design for mobile
✅ Bahasa Melayu throughout
✅ Success/error feedback messages
✅ Loading states and animations

### 16.4 Navigation Updates
✅ Complete Master Data submenu with 11 items
✅ Laporan submenu with 2 items (Hasil Culaan, Data Pengundi)
✅ Removed "Tetapan" menu (no longer needed)
✅ Dropdown menu with single-open behavior

### 16.5 Data Seeding
✅ All Master Data tables seeded with initial data
✅ Pulau Pinang specific data (Parliaments, DUNs, MPKKs)
✅ Political parties and tendencies
✅ Donation types and purposes
✅ Relationships and assistance types

### 16.6 Technical Achievements
- **Total Tables Created**: 13 (10 Master Data + 2 Reports + 1 User)
- **Total Routes**: 50+ CRUD routes
- **Total Pages**: 25+ React components
- **Total Seeders**: 10+ database seeders
- **Authorization**: Super Admin restrictions on all Master Data
- **Validation**: Comprehensive form validation throughout
- **Relationships**: Complex foreign key relationships implemented


---

## 17. Dashboard Module - Complete Implementation

### 17.1 Module Overview
- **Purpose**: Comprehensive analytics and data visualization dashboard
- **Access**: All authenticated users
- **Technology**: React.js with Recharts library
- **Design**: Shadcn UI style with responsive layout

### 17.2 Dashboard Features

#### Metric Cards (5 Cards)
1. **Jumlah Pengundi** (Total Voters)
   - Real-time count from `data_pengundi` table
   - Green emerald icon (Users)
   - Displays total voter records

2. **Jumlah Hasil Culaan** (Data Collection Results)
   - Real-time count from `hasil_culaan` table
   - Sky blue icon (ClipboardList)
   - Displays total collection records

3. **Sokongan PH/BN** (PH/BN Support %)
   - Calculated from `kecenderungan_politik` field
   - Green emerald icon (TrendingUp)
   - Percentage of PH/BN supporters

4. **Sokongan BN/PN** (BN/PN Support %)
   - Calculated from `kecenderungan_politik` field
   - Amber icon (TrendingDown)
   - Percentage of BN/PN supporters

5. **Pengundi Mengikut KADUN** (Voters by KADUN)
   - Count of top 5 active KADUNs
   - Purple icon (MapPin)
   - Shows number of active constituencies

**Card Design:**
- Soft shadows with hover effects
- Rounded corners (rounded-xl)
- Large bold numbers (text-3xl)
- Colored icon backgrounds
- Responsive grid layout (1 column mobile → 4 columns desktop)

#### Interactive Charts (5 Charts)

1. **Kecenderungan Politik** (Political Tendency) - Donut Chart
   - Shows distribution of political support
   - Colors: Emerald (PH/BN), Amber (BN/PN), Slate (Tidak Pasti)
   - Interactive tooltips on hover
   - Legend display

2. **Taburan Bangsa** (Ethnicity Distribution) - Bar Chart
   - Displays voter distribution by ethnicity
   - Categories: Melayu, Cina, India, Lain-lain
   - Blue bars with rounded tops
   - Grid lines for readability

3. **Taburan Umur** (Age Distribution) - Bar Chart
   - Age groups: 18-25, 26-35, 36-45, 46-55, 56-65, 65+
   - Emerald green bars
   - Shows voter demographics

4. **Trend Pengumpulan Data Bulanan** (Monthly Data Collection Trend) - Line Chart
   - Last 6 months of data collection
   - Purple line with dot markers
   - Smooth monotone curve
   - Shows data collection momentum

5. **Sokongan Politik Mengikut KADUN** (Political Support by KADUN) - Stacked Bar Chart
   - Top 5 KADUNs by voter count
   - Stacked segments: PH/BN (Emerald), BN/PN (Amber), Tidak Pasti (Slate)
   - Shows political distribution per constituency

**Chart Design:**
- Clean, minimalist style
- Soft color palette
- Responsive containers
- Tooltips on hover
- No strong borders
- Grid lines with subtle colors

#### Data Tables (2 Tables)

1. **Kawasan Paling Aktif** (Most Active Areas)
   - Columns:
     - KADUN name
     - Jumlah Pengundi (Total Voters)
     - Jumlah Culaan (Collection Results)
     - Sokongan PH/BN (%)
     - Sokongan BN/PN (%)
     - Tidak Pasti (%)
   - Shows top 5 KADUNs by activity
   - Color-coded percentages

2. **Petugas Teraktif** (Most Active Officers)
   - Columns:
     - Nama Petugas (Officer Name)
     - Jumlah Rekod (Total Records)
     - Kawasan (Area/KADUN)
     - Tarikh Terakhir (Last Date)
   - Shows top 5 officers by record count
   - Combines data_pengundi and hasil_culaan counts

**Table Design:**
- Responsive horizontal scroll
- Hover row highlighting
- Soft alternating row colors
- Clean typography
- Bordered cards with headers

#### Advanced Filters

**Collapsible Filter Panel:**
- Toggle button with ChevronDown icon
- Animated expand/collapse
- "Tunjukkan/Sembunyikan Penapis" text

**Filter Fields:**
- Negeri (State) - Dropdown
- Bandar/Parlimen (City/Parliament) - Dropdown
- KADUN - Dropdown
- MPKK - Dropdown (UI only, not functional due to schema)
- Tarikh Dari (Date From) - Date picker
- Tarikh Hingga (Date To) - Date picker

**Filter Actions:**
- "Tapis" (Filter) button - Apply filters
- "Set Semula" (Reset) button - Clear all filters

### 17.3 Backend Implementation

**DashboardController** (`app/Http/Controllers/DashboardController.php`):
- `index()` method handles all dashboard logic
- Real-time data aggregation
- Filter support for Negeri, Bandar, KADUN, Date Range
- Political tendency calculations using string matching
- Ethnicity distribution grouping
- Age group calculations
- Monthly trend analysis (last 6 months)
- KADUN statistics with support percentages
- Top officers ranking with combined counts

**Data Sources:**
- `data_pengundi` table - Primary voter data
- `hasil_culaan` table - Collection results
- String-based queries (no foreign key relationships)
- Uses `LIKE` for political tendency matching

**Query Optimizations:**
- Grouped queries for statistics
- Selective data retrieval
- Efficient counting methods
- Raw SQL for complex aggregations

### 17.4 Routes

```php
GET /dashboard - Main dashboard view
```

### 17.5 Dependencies

- **Recharts**: Chart library for React
  - `npm install recharts`
  - Components: PieChart, BarChart, LineChart
  - Features: Tooltips, Legends, Responsive containers

- **Lucide React**: Icon library
  - Icons used: Users, ClipboardList, TrendingUp, TrendingDown, MapPin, ChevronDown, Filter

### 17.6 Design Principles

- **Shadcn UI Style**: Clean, minimalist, professional
- **Responsive Design**: Mobile-first approach
- **Color Palette**:
  - Primary: Slate-900 for text
  - Success: Emerald-500/600
  - Warning: Amber-400/500
  - Info: Sky-600
  - Neutral: Slate-400/600
  - Borders: Slate-200/300
- **Typography**: Clean sans-serif with proper hierarchy
- **Spacing**: Consistent padding and margins
- **Animations**: Smooth transitions and hover effects

### 17.7 Language

- **All UI elements in Bahasa Melayu**
- Examples:
  - "Jumlah Pengundi" (Total Voters)
  - "Kecenderungan Politik" (Political Tendency)
  - "Taburan Bangsa" (Ethnicity Distribution)
  - "Kawasan Paling Aktif" (Most Active Areas)
  - "Tapis" (Filter)
  - "Set Semula" (Reset)

---

## 18. Territory-Based Registration & Authorization - Complete Implementation

### 18.1 Overview
- **Purpose**: Restrict access and data visibility based on user's assigned territory.
- **Scope**: Registration, Authentication, Dashboard, and User Management.
- **Key Feature**: Approval workflow for new registrations.

### 18.2 Registration System
- **Route**: `/register`
- **Form Fields**:
  - Name, Telephone, Email, Password
  - **Territory Selection**: Negeri → Bandar → KADUN (Cascading Dropdowns)
  - **Role Selection**: Removed (Defaults to `user`)
- **Workflow**:
  1. User registers with territory details.
  2. Account created with `status: pending` and `role: user`.
  3. User redirected to "Pending Approval" page.
  4. User cannot login until approved by Admin/Super Admin.

### 18.3 Approval Workflow
- **Pending Approval Page**:
  - Route: `/pending-approval`
  - Displays status message and contact info.
  - Accessible to unapproved authenticated users.
- **User Approval Interface**:
  - Route: `/user-approval`
  - Accessible to: `super_admin` and `admin`.
  - **Super Admin**: Sees ALL pending users from ALL territories.
  - **Admin**: Sees ONLY pending users from THEIR assigned territory.
  - Actions: Approve (`status: approved`) or Reject (`status: rejected`).

### 18.4 Authorization & Data Access
- **Super Admin**:
  - Full access to all data and features.
  - Can approve/reject users from any territory.
  - Dashboard shows data from ALL territories.
- **Admin**:
  - Restricted to assigned territory (Negeri/Bandar/KADUN).
  - **User Approval**: Can only approve/reject users within their Parlimen (Bandar).
  - **User Management**: Can only view/edit/delete users within their Parlimen. Cannot see Super Admins.
  - **Master Data**: Can only view/manage data linked to their Parlimen.
  - **Reports**: Can only view/export reports for their Parlimen.
  - Dashboard shows data ONLY from their territory.
- **User**:
  - Restricted to assigned territory.
  - Cannot approve/reject users.
  - Dashboard shows data ONLY from their territory.

### 18.5 Dashboard Filtering
- **Controller**: `DashboardController`
- **Logic**:
  - Checks user role.
  - If `super_admin`: No territory filters applied (unless selected in filter panel).
  - If `admin` or `user`: Automatically applies `where` clauses for `negeri_id`, `bandar_id`, and `kadun_id`.
- **Impact**:
  - Metric cards, charts, and tables only show relevant local data.

---

## 19. Future Enhancements (Planned)

### 19.1 Additional Features
- Advanced analytics and reporting
- Batch import functionality (CSV/Excel)
- PDF export for reports
- Audit trail for data changes
- Advanced search with multiple criteria
- Data backup and restore

### 19.2 Performance Optimization
- Database indexing for faster queries
- Caching for frequently accessed data
- Lazy loading for large datasets
- Query optimization

### 19.3 Security Enhancements
- Two-factor authentication
- Activity logging
- IP whitelisting for admin access
- Enhanced password policies

---

## 20. Parlimen-Level Restrictions (Implemented 2025-11-24)

### 20.1 Overview
- **Goal**: Ensure strict data isolation for Admin users based on their assigned Parlimen (Bandar).
- **Scope**: User Approval, User Management, Master Data, and Reports.

### 20.2 Specific Rules
1.  **User Approval**:
    - Admins can only view and approve users registered under their `bandar_id`.
    - Super Admins see all pending users.

2.  **User Management**:
    - Admins can only view/edit/delete users within their `bandar_id`.
    - Admins CANNOT view, edit, or delete Super Admin accounts.
    - Admins CANNOT change a user's territory to outside their own.

3.  **Master Data**:
    - **Schema Change**: Added `bandar_id` to `tujuan_sumbangan`, `jenis_sumbangan`, `bantuan_lain`, `keahlian_parti`, `kecenderungan_politik`, `hubungan`.
    - **Logic**:
        - Super Admin: Sees all data.
        - Admin: Sees only data with their `bandar_id`.
        - Admin Create: `bandar_id` automatically set to Admin's `bandar_id`.

4.  **Reports (Hasil Culaan & Data Pengundi)**:
    - **Logic**:
        - Super Admin: Sees all records.
        - Admin: Sees only records where `bandar` matches their assigned Parlimen.
    - **Export**: Excel exports are filtered by the same logic.

---

## 21. User Role Customization & Access Control (Implemented 2025-11-29)

### 21.1 Sidebar Navigation Customization
- **User Role (`user`)**:
  - **Dashboard**: Standard overview.
  - **Mula Culaan**: Direct link to Create form (`/reports/hasil-culaan/create`).
  - **Data Pengundi**: Direct link to Create form (`/reports/data-pengundi/create`).
  - **Laporan**: Submenu with List views (Hasil Culaan, Data Pengundi).
  - **Profil**: User settings.
  - *Hidden*: Pengguna, Data Induk, Kelulusan Pengguna.

- **Admin/Super Admin**:
  - Full access to all menus including Pengguna, Data Induk, and standard Laporan menu.

### 21.2 Data Access Control (KADUN Level)
- **User Role**:
  - **View Scope**: Restricted to records within their assigned **KADUN** only.
  - **Edit/Delete Scope**: Restricted to **own submissions** only.
  - **View Others**: Can view records from other users in the same KADUN (read-only).
- **Admin Role**:
  - **View Scope**: Restricted to records within their assigned **Bandar/Parlimen**.
  - **Edit/Delete Scope**: Can manage all records within their Bandar.

### 21.3 Form Enhancements
- **Auto-Population**:
  - **Poskod Selection**: Automatically fills **Negeri** and **Bandar** fields.
  - **Read-Only**: Auto-filled fields are locked to prevent errors.
- **Conditional Logic**:
  - **KADUN Dropdown**: Only appears/enabled after Bandar is determined.
  - **Dynamic Options**: KADUN options filtered based on the selected Bandar.
- **Input Formatting**:
  - **Nama/Alamat**: Auto-converts to **UPPERCASE**.
  - **No. Telefon**: Restricts input to **Digits Only**.

### 21.4 Technical Implementation
- **Middleware/Controller Logic**:
  - `ReportsController`: Applies `where('kadun', $user->kadun->nama)` for Users.
  - `AuthenticatedLayout`: Conditional rendering of sidebar items based on `user.role`.
  - `Index.jsx`: Conditional rendering of Edit/Delete buttons based on `submitted_by_id`.
- **Route Highlighting**:
  - Fixed logic to prevent parent menu highlighting when on Create pages.

---

## 22. Daerah Mengundi & Call Center Modules (Implemented 2025-11-29)

### 22.1 Daerah Mengundi Module
- **Purpose**: Manage polling districts (Daerah Mengundi) within Parlimen constituencies.
- **Access**: Restricted to **Super Admin** and **Admin**.
- **Data Structure**:
  - `kod_dm`: Code (e.g., 041/01/02)
  - `nama`: Name (e.g., PULAU MERTAJAM)
  - `bandar_id`: Linked to Parlimen (Bandar)
- **Features**:
  - Full CRUD (Create, Read, Update, Delete)
  - Search functionality
  - **Admin Restriction**: Admins can only manage Daerah Mengundi within their assigned Parlimen.
  - **Data Source**: Populated with official SPR data for P041 Kepala Batas (29 districts).

### 22.2 Call Center Module
- **Purpose**: Future module for managing voter communications.
- **Access**: Strictly restricted to **Super Admin** only.
- **Current State**:
  - "Coming Soon" placeholder page.
  - Features modern UI with animated icons and feature preview.
- **Navigation**:
  - Placed between "Laporan" and "Profil" in the sidebar.

### 22.3 Navigation Updates
- **Menu Order**:
  1. Dashboard
  2. Kelulusan Pengguna (Super Admin/Admin)
  3. Pengguna (Super Admin/Admin)
  - "Kecenderungan Politik" (Political Tendency)
  - "Taburan Bangsa" (Ethnicity Distribution)
  - "Kawasan Paling Aktif" (Most Active Areas)
  - "Tapis" (Filter)
  - "Set Semula" (Reset)

---

## 18. Territory-Based Registration & Authorization - Complete Implementation

### 18.1 Overview
- **Purpose**: Restrict access and data visibility based on user's assigned territory.
- **Scope**: Registration, Authentication, Dashboard, and User Management.
- **Key Feature**: Approval workflow for new registrations.

### 18.2 Registration System
- **Route**: `/register`
- **Form Fields**:
  - Name, Telephone, Email, Password
  - **Territory Selection**: Negeri → Bandar → KADUN (Cascading Dropdowns)
  - **Role Selection**: Removed (Defaults to `user`)
- **Workflow**:
  1. User registers with territory details.
  2. Account created with `status: pending` and `role: user`.
  3. User redirected to "Pending Approval" page.
  4. User cannot login until approved by Admin/Super Admin.

### 18.3 Approval Workflow
- **Pending Approval Page**:
  - Route: `/pending-approval`
  - Displays status message and contact info.
  - Accessible to unapproved authenticated users.
- **User Approval Interface**:
  - Route: `/user-approval`
  - Accessible to: `super_admin` and `admin`.
  - **Super Admin**: Sees ALL pending users from ALL territories.
  - **Admin**: Sees ONLY pending users from THEIR assigned territory.
  - Actions: Approve (`status: approved`) or Reject (`status: rejected`).

### 18.4 Authorization & Data Access
- **Super Admin**:
  - Full access to all data and features.
  - Can approve/reject users from any territory.
  - Dashboard shows data from ALL territories.
- **Admin**:
  - Restricted to assigned territory (Negeri/Bandar/KADUN).
  - **User Approval**: Can only approve/reject users within their Parlimen (Bandar).
  - **User Management**: Can only view/edit/delete users within their Parlimen. Cannot see Super Admins.
  - **Master Data**: Can only view/manage data linked to their Parlimen.
  - **Reports**: Can only view/export reports for their Parlimen.
  - Dashboard shows data ONLY from their territory.
- **User**:
  - Restricted to assigned territory.
  - Cannot approve/reject users.
  - Dashboard shows data ONLY from their territory.

### 18.5 Dashboard Filtering
- **Controller**: `DashboardController`
- **Logic**:
  - Checks user role.
  - If `super_admin`: No territory filters applied (unless selected in filter panel).
  - If `admin` or `user`: Automatically applies `where` clauses for `negeri_id`, `bandar_id`, and `kadun_id`.
- **Impact**:
  - Metric cards, charts, and tables only show relevant local data.

---

## 19. Future Enhancements (Planned)

### 19.1 Additional Features
- Advanced analytics and reporting
- Batch import functionality (CSV/Excel)
- PDF export for reports
- Audit trail for data changes
- Advanced search with multiple criteria
- Data backup and restore

### 19.2 Performance Optimization
- Database indexing for faster queries
- Caching for frequently accessed data
- Lazy loading for large datasets
- Query optimization

### 19.3 Security Enhancements
- Two-factor authentication
- Activity logging
- IP whitelisting for admin access
- Enhanced password policies

---

## 20. Parlimen-Level Restrictions (Implemented 2025-11-24)

### 20.1 Overview
- **Goal**: Ensure strict data isolation for Admin users based on their assigned Parlimen (Bandar).
- **Scope**: User Approval, User Management, Master Data, and Reports.

### 20.2 Specific Rules
1.  **User Approval**:
    - Admins can only view and approve users registered under their `bandar_id`.
    - Super Admins see all pending users.

2.  **User Management**:
    - Admins can only view/edit/delete users within their `bandar_id`.
    - Admins CANNOT view, edit, or delete Super Admin accounts.
    - Admins CANNOT change a user's territory to outside their own.

3.  **Master Data**:
    - **Schema Change**: Added `bandar_id` to `tujuan_sumbangan`, `jenis_sumbangan`, `bantuan_lain`, `keahlian_parti`, `kecenderungan_politik`, `hubungan`.
    - **Logic**:
        - Super Admin: Sees all data.
        - Admin: Sees only data with their `bandar_id`.
        - Admin Create: `bandar_id` automatically set to Admin's `bandar_id`.

4.  **Reports (Hasil Culaan & Data Pengundi)**:
    - **Logic**:
        - Super Admin: Sees all records.
        - Admin: Sees only records where `bandar` matches their assigned Parlimen.
    - **Export**: Excel exports are filtered by the same logic.

---

## 21. User Role Customization & Access Control (Implemented 2025-11-29)

### 21.1 Sidebar Navigation Customization
- **User Role (`user`)**:
  - **Dashboard**: Standard overview.
  - **Mula Culaan**: Direct link to Create form (`/reports/hasil-culaan/create`).
  - **Data Pengundi**: Direct link to Create form (`/reports/data-pengundi/create`).
  - **Laporan**: Submenu with List views (Hasil Culaan, Data Pengundi).
  - **Profil**: User settings.
  - *Hidden*: Pengguna, Data Induk, Kelulusan Pengguna.

- **Admin/Super Admin**:
  - Full access to all menus including Pengguna, Data Induk, and standard Laporan menu.

### 21.2 Data Access Control (KADUN Level)
- **User Role**:
  - **View Scope**: Restricted to records within their assigned **KADUN** only.
  - **Edit/Delete Scope**: Restricted to **own submissions** only.
  - **View Others**: Can view records from other users in the same KADUN (read-only).
- **Admin Role**:
  - **View Scope**: Restricted to records within their assigned **Bandar/Parlimen**.
  - **Edit/Delete Scope**: Can manage all records within their Bandar.

### 21.3 Form Enhancements
- **Auto-Population**:
  - **Poskod Selection**: Automatically fills **Negeri** and **Bandar** fields.
  - **Read-Only**: Auto-filled fields are locked to prevent errors.
- **Conditional Logic**:
  - **KADUN Dropdown**: Only appears/enabled after Bandar is determined.
  - **Dynamic Options**: KADUN options filtered based on the selected Bandar.
- **Input Formatting**:
  - **Nama/Alamat**: Auto-converts to **UPPERCASE**.
  - **No. Telefon**: Restricts input to **Digits Only**.

### 21.4 Technical Implementation
- **Middleware/Controller Logic**:
  - `ReportsController`: Applies `where('kadun', $user->kadun->nama)` for Users.
  - `AuthenticatedLayout`: Conditional rendering of sidebar items based on `user.role`.
  - `Index.jsx`: Conditional rendering of Edit/Delete buttons based on `submitted_by_id`.
- **Route Highlighting**:
  - Fixed logic to prevent parent menu highlighting when on Create pages.

---

## 22. Daerah Mengundi & Call Center Modules (Implemented 2025-11-29)

### 22.1 Daerah Mengundi Module
- **Purpose**: Manage polling districts (Daerah Mengundi) within Parlimen constituencies.
- **Access**: Restricted to **Super Admin** and **Admin**.
- **Data Structure**:
  - `kod_dm`: Code (e.g., 041/01/02)
  - `nama`: Name (e.g., PULAU MERTAJAM)
  - `bandar_id`: Linked to Parlimen (Bandar)
- **Features**:
  - Full CRUD (Create, Read, Update, Delete)
  - Search functionality
  - **Admin Restriction**: Admins can only manage Daerah Mengundi within their assigned Parlimen.
  - **Data Source**: Populated with official SPR data for P041 Kepala Batas (29 districts).

### 22.2 Call Center Module
- **Purpose**: Future module for managing voter communications.
- **Access**: Strictly restricted to **Super Admin** only.
- **Current State**:
  - "Coming Soon" placeholder page.
  - Features modern UI with animated icons and feature preview.
- **Navigation**:
  - Placed between "Laporan" and "Profil" in the sidebar.

### 22.3 Navigation Updates
- **Menu Order**:
  1. Dashboard
  2. Kelulusan Pengguna (Super Admin/Admin)
  3. Pengguna (Super Admin/Admin)
  4. Data Induk (Super Admin/Admin)
     - *New Submenu*: **Daerah Mengundi** (between MPKK and Tujuan Sumbangan)
  5. Mula Culaan / Data Pengundi (User)
  6. Laporan (All Roles)
  7. **Call Center** (Super Admin only)
  8. Profil

---

## 23. Edit Form Data Connection Enhancement (Implemented 2025-11-30)

### 23.1 Overview
- **Purpose**: Standardize Edit forms to use dynamic dropdowns connected to Master Data tables
- **Scope**: Hasil Culaan and Data Pengundi Edit forms
- **Impact**: Improved data consistency and user experience

### 23.2 Backend Changes

#### Controller Updates
**File**: `app/Http/Controllers/ReportsController.php`

**hasilCulaanEdit Method**:
- Now passes all required Master Data lists to the Edit view:
  - `bangsaList` - Ethnicity options
  - `negeriList` - State options
  - `bandarList` - Parliament/City options
  - `kadunList` - KADUN options
  - `daerahMengundiList` - Polling district options
  - `jenisSumbanganList` - Donation type options
  - `tujuanSumbanganList` - Donation purpose options
  - `bantuanLainList` - Other assistance options
  - `keahlianPartiList` - Party membership options
  - `kecenderunganPolitikList` - Political tendency options

**dataPengundiEdit Method**:
- Now passes all required Master Data lists to the Edit view:
  - `bangsaList` - Ethnicity options
  - `hubunganList` - Relationship options
  - `negeriList` - State options
  - `bandarList` - Parliament/City options
  - `kadunList` - KADUN options
  - `daerahMengundiList` - Polling district options
  - `keahlianPartiList` - Party membership options
  - `kecenderunganPolitikList` - Political tendency options

### 23.3 Frontend Changes

#### Hasil Culaan Edit Form
**File**: `resources/js/Pages/Reports/HasilCulaan/Edit.jsx`

**Updated Props**:
```jsx
export default function Edit({
    hasilCulaan,
    bangsaList,
    negeriList,
    bandarList,
    kadunList,
    daerahMengundiList,
    jenisSumbanganList,
    tujuanSumbanganList,
    bantuanLainList,
    keahlianPartiList,
    kecenderunganPolitikList
})
```

**Converted Fields** (from text input to dropdown):
1. **Bangsa** (Ethnicity)
   - Now uses `bangsaList` from database
   - Dynamic options instead of hardcoded values
   
2. **Jenis Sumbangan** (Donation Type)
   - Connected to `jenis_sumbangan` table
   - Dropdown with database-driven options
   
3. **Tujuan Sumbangan** (Donation Purpose)
   - Connected to `tujuan_sumbangan` table
   - Dropdown with database-driven options
   
4. **Bantuan Lain** (Other Assistance)
   - Connected to `bantuan_lain` table
   - Dropdown with database-driven options
   
5. **Keahlian Parti** (Party Membership)
   - Connected to `keahlian_parti` table
   - Dropdown with database-driven options
   
6. **Kecenderungan Politik** (Political Tendency)
   - Connected to `kecenderungan_politik` table
   - Dropdown with database-driven options

#### Data Pengundi Edit Form
**File**: `resources/js/Pages/Reports/DataPengundi/Edit.jsx`

**Updated Props**:
```jsx
export default function Edit({
    dataPengundi,
    bangsaList,
    hubunganList,
    negeriList,
    bandarList,
    kadunList,
    daerahMengundiList,
    keahlianPartiList,
    kecenderunganPolitikList
})
```

**Converted Fields** (from text input to dropdown):
1. **Bangsa** (Ethnicity)
   - Now uses `bangsaList` from database
   - Dynamic options instead of hardcoded values
   
2. **Hubungan** (Relationship)
   - Connected to `hubungan` table
   - Dropdown with database-driven options
   
3. **Keahlian Parti** (Party Membership)
   - Connected to `keahlian_parti` table
   - Dropdown with database-driven options
   
4. **Kecenderungan Politik** (Political Tendency)
   - Connected to `kecenderungan_politik` table
   - Dropdown with database-driven options

### 23.4 Benefits

#### Data Consistency
- All dropdowns now use centralized Master Data
- Changes to Master Data automatically reflect in Edit forms
- No more hardcoded values that can become outdated

#### User Experience
- Consistent dropdown behavior across Create and Edit forms
- Easier data entry with standardized options
- Reduced data entry errors

#### Maintainability
- Single source of truth for dropdown options
- Easier to add/modify options through Master Data module
- Reduced code duplication

### 23.5 Technical Implementation

**Dropdown Pattern**:
```jsx
<select
    value={data.field_name}
    onChange={(e) => setData('field_name', e.target.value)}
    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
>
    <option value="">Pilih {Field Name}</option>
    {fieldList.map((item) => (
        <option key={item.id} value={item.nama}>
            {item.nama}
        </option>
    ))}
</select>
```

**Data Flow**:
1. Controller fetches Master Data lists from database
2. Lists passed to Inertia view as props
3. React component receives lists via props
4. Dropdowns populated dynamically from lists
5. Selected values saved to respective tables

### 23.6 Consistency Achievements

✅ **Edit Forms Match Create Forms**:
- Same dropdown fields
- Same data sources
- Same validation rules
- Same user experience

✅ **All Master Data Connected**:
- Bangsa (Ethnicity)
- Hubungan (Relationship)
- Jenis Sumbangan (Donation Type)
- Tujuan Sumbangan (Donation Purpose)
- Bantuan Lain (Other Assistance)
- Keahlian Parti (Party Membership)
- Kecenderungan Politik (Political Tendency)

✅ **Dynamic Address Fields**:
- Poskod (Postcode) - Searchable dropdown
- Negeri (State) - Auto-filled
- Bandar (Parliament) - Auto-filled
- KADUN - Dynamic dropdown based on Bandar
- Daerah Mengundi - Dynamic dropdown based on Bandar

---

## 24. Auto-Copy Data from Hasil Culaan to Data Pengundi (Implemented 2025-11-30)

### 24.1 Overview
- **Purpose**: Automatically populate Data Pengundi records from Hasil Culaan submissions
- **Trigger**: When user saves a new Hasil Culaan record
- **Benefit**: Reduces duplicate data entry and ensures consistency

### 24.2 Implementation Details

#### Automatic Data Transfer
When a user submits a Hasil Culaan form, the system automatically:
1. Checks if a Data Pengundi record with the same IC number already exists
2. If NOT exists, creates a new Data Pengundi record with matching fields
3. If exists, skips creation to avoid duplicates

#### Matching Fields Copied
**Personal Information (Maklumat Peribadi)**:
- `nama` - Full name
- `no_ic` - IC number (used as unique identifier)
- `umur` - Age
- `no_tel` - Telephone number
- `bangsa` - Ethnicity

**Address Information (Maklumat Alamat)**:
- `alamat` - Full address
- `poskod` - Postal code
- `negeri` - State
- `bandar` - Parliament/City
- `parlimen` - Parliament (copied from bandar)
- `kadun` - KADUN
- `daerah_mengundi` - Polling district

**Political Information**:
- `keahlian_parti` - Party membership
- `kecenderungan_politik` - Political tendency

**System Fields**:
- `submitted_by` - User who created the record

#### Fields NOT Copied
The following Data Pengundi fields are left empty for manual update:
- `hubungan` - Relationship (family relationship context)
- All other Data Pengundi-specific fields not present in Hasil Culaan

### 24.3 Business Logic

**Duplicate Prevention**:
- Uses `no_ic` (IC number) as unique identifier
- Prevents creating duplicate Data Pengundi records
- If IC already exists in Data Pengundi, no new record is created

**User Workflow**:
1. User fills out Hasil Culaan form (Mula Culaan page)
2. User clicks Save
3. System saves Hasil Culaan record
4. System automatically creates Data Pengundi record (if IC doesn't exist)
5. User receives success message: "Rekod berjaya ditambah dan data pengundi telah dikemaskini"
6. User can later edit Data Pengundi record to fill in missing fields

### 24.4 Technical Implementation

**Controller**: `ReportsController@hasilCulaanStore`

**Code Flow**:
```php
1. Validate Hasil Culaan data
2. Create Hasil Culaan record
3. Check if DataPengundi exists with same no_ic
4. If not exists:
   - Create DataPengundi with matching fields
   - Set hubungan to null (for manual update)
5. Redirect with success message
```

**Database Query**:
```php
$existingPengundi = \App\Models\DataPengundi::where('no_ic', $validated['no_ic'])->first();
```

### 24.5 Benefits

✅ **Reduced Data Entry**:
- Users don't need to re-enter the same information twice
- Saves time and reduces errors

✅ **Data Consistency**:
- Ensures Data Pengundi has accurate information from Hasil Culaan
- Single source of truth for personal and address data

✅ **Improved Workflow**:
- Streamlined process from data collection to voter database
- Users can focus on collecting data, system handles the rest

✅ **Smart Duplicate Handling**:
- Prevents duplicate records based on IC number
- Maintains data integrity

### 24.6 User Experience

**Success Message**:
- Original: "Rekod berjaya ditambah"
- Updated: "Rekod berjaya ditambah dan data pengundi telah dikemaskini"
- Informs user that both Hasil Culaan and Data Pengundi were updated

  - Update any information as needed

---

## 25. Data Pengundi UI Standardization & RBAC (Implemented 2025-12-01)

### 25.1 Overview
- **Goal**: Align `Data Pengundi` module with the polished design and functionality of `Hasil Culaan`.
- **Focus**: UI consistency, Role-Based Access Control (RBAC), and code maintainability.

### 25.2 UI Standardization
- **Design System**: Adopted the exact Tailwind CSS classes and layout structure from `Hasil Culaan`.
- **Table Layout**:
  - Standardized column widths and styling.
  - Added "Dikemukakan" column to track record creator.
  - Consistent badge styling for status/roles.
- **Filter Panel**:
  - Implemented the collapsible "Tunjukkan/Sembunyikan" filter panel.
  - Standardized filter inputs (Search, Date Range).
- **Modals**:
  - Updated "View" modal to match the clean, grid-based layout.
  - Removed irrelevant fields (e.g., image preview) from Data Pengundi view.

### 25.3 Role-Based Access Control (RBAC) Implementation
- **Frontend Logic**: Implemented `canModifyRecord` helper function in `Index.jsx`.
- **Permission Rules**:
  1. **Super Admin**: Can edit/delete ALL records.
  2. **Admin**: Can edit/delete records ONLY within their assigned **Bandar**.
  3. **User**: Can edit/delete ONLY their **own submissions** within their assigned **KADUN**.
- **Visual Feedback**:
  - **Authorized**: Edit (Pencil) and Delete (Trash) buttons shown.
  - **Unauthorized**: Only View (Eye) button shown.
  - **Bulk Actions**: Checkboxes ONLY rendered for authorized records.

### 25.4 Technical Improvements
- **User Context**: Utilized Inertia's `usePage` hook to access authenticated user details (`role`, `bandar`, `kadun`) for client-side permission checks.
- **Code Cleanup**: Removed unused helper functions (e.g., `formatCurrency`) and state variables (`viewingImage`) that were not relevant to Data Pengundi.
- **Consistency**: Both `Hasil Culaan` and `Data Pengundi` now share the same architectural patterns and user experience.

---

## 26. Mobile Responsiveness Verification (Verified 2025-12-01)

### 26.1 Verified Pages
- **Reports/DataPengundi**:
  - `Index.jsx`: Verified table responsiveness (`overflow-x-auto`) and filter grid (`grid-cols-1 md:grid-cols-4`).
  - `Create.jsx`: Verified form layout (`grid-cols-1 md:grid-cols-2`).
  - `Edit.jsx`: Verified form layout (`grid-cols-1 md:grid-cols-2`).
- **Reports/HasilCulaan**:
  - `Index.jsx`: Verified table responsiveness and filter grid.
  - `Create.jsx`: Verified form layout.
  - `Edit.jsx`: Verified form layout.

### 26.2 Key Responsive Patterns
- **Data Tables**: Wrapped in `overflow-x-auto` container to allow horizontal scrolling on small screens.
- **Form Grids**: Use `grid-cols-1 md:grid-cols-2` to stack fields vertically on mobile and side-by-side on desktop.
- **Filter Panels**: Use `grid-cols-1 md:grid-cols-4` to stack filters on mobile.
- **Modals**: Responsive width and scrolling behavior.



---
- **Modals**: Responsive width and scrolling behavior.


---

## 27. UI Updates and Improvements (2025-12-01)

### 27.1 Master Data Dashboard
- **Database Integration**: Connected all dashboard cards to display real-time counts from the database
  - Replaced hardcoded sample data with dynamic queries using Eloquent models
  - Added route navigation to each category card for seamless access
  - Added missing categories: `Daerah Mengundi` and `Bangsa`
- **UI Cleanup**: Removed "Quick Stats" and "Info Cards" sections for a cleaner, more focused interface
- **Color Support**: Added `slate` and `blue` color classes to support all category types

### 27.2 Application Entry Point
- **Welcome Page**: Changed root URL (`/`) to redirect directly to the login page
  - Login page now serves as the main entry point for the application
  - Removed the default Laravel welcome page from the user flow

### 27.3 Data Pengundi Form Simplification
- **Field Removal**: Removed the `Hubungan` (Relationship) dropdown from Data Pengundi forms
  - Updated both Create and Edit forms (`Create.jsx` and `Edit.jsx`)
  - Removed `hubunganList` from component props and controller methods
  - Removed `hubungan` field from form data state
  - Simplified the "Maklumat Peribadi" section layout

### 27.4 Hubungan Module Removal
- **Navigation**: Removed "Hubungan" submenu item from Data Induk sidebar navigation
  - Updated `AuthenticatedLayout.jsx` to remove Hubungan from `masterDataSubmenu`
- **Dashboard**: Removed Hubungan card from Master Data dashboard page
  - Updated `MasterDataController@index` to remove Hubungan category from the categories array
- **Rationale**: Hubungan field was not being used in the Data Pengundi workflow, so the entire module was removed from the UI

### 27.5 Reports Page Improvements
- **UI Cleanup**: Removed "Maklumat Laporan" information card from Reports index page
  - Simplified the page to show only the two main report cards (Hasil Culaan and Data Pengundi)
- **Navigation**: Fixed Data Pengundi card link to properly navigate to the Data Pengundi index page
  - Changed href from `'#'` to `route('reports.data-pengundi.index')`

### 27.6 Technical Changes
- **Backend Updates**:
  - Modified `MasterDataController@index` to fetch real counts for all master data categories
  - Updated `ReportsController` to remove `hubunganList` from Data Pengundi Create and Edit methods
  - Removed Hubungan category from Master Data dashboard
- **Frontend Updates**:
  - Enhanced `MasterData/Index.jsx` with navigation functionality using Inertia router
  - Cleaned up Data Pengundi forms by removing unused Hubungan field
  - Updated `AuthenticatedLayout.jsx` to remove Hubungan from sidebar navigation
  - Simplified `Reports/Index.jsx` by removing information card and fixing navigation links
- **Routes**: Updated `web.php` to redirect root URL to login page

---

## 28. Simplified Dashboard for Admin and Regular Users (2025-12-01)

### 28.1 New User Dashboard
- **Purpose**: Created a simplified, task-focused dashboard for Admin and Regular users
- **Access**: Automatically shown to users with `admin` or `user` roles
- **Super Admin**: Continues to see the full analytics dashboard

### 28.2 IC Number Search Feature
- **Search Field**:
  - Prominent search input with placeholder "Masukkan No Kad Pengenalan"
  - Search icon with proper spacing (pl-14 for gap between icon and text)
  - Real-time search with 300ms debounce
  - Loading indicator during search
  - Minimum 3 characters required to trigger search

- **Search Functionality**:
  - Searches across both `hasil_culaan` and `data_pengundi` tables
  - Territory-based restrictions:
    - **Admin**: Can only search within their assigned Bandar
    - **Regular User**: Can only search within their assigned KADUN
  - Returns up to 5 results from each table (max 10 total)

- **Search Results Dropdown**:
  - Shows matching records with:
    - Name, IC number, phone number
    - Bandar and KADUN information
    - Badge indicating record type (Hasil Culaan or Data Pengundi)
    - Edit icon (if user can edit) or View icon (if view-only)
  - Click behavior:
    - **Can Edit**: Navigates directly to edit page
    - **View Only**: Opens modal with record details and permission notice

### 28.3 Quick Action Buttons
- **Design Philosophy**: Large, prominent, visually striking buttons for primary actions
- **Layout**: Two-column grid with equal-sized buttons

**Mula Culaan Button**:
- Emerald green gradient (from-emerald-500 via-emerald-600 to-emerald-700)
- Links to `reports.hasil-culaan.create`
- Features:
  - Large icon (h-16 w-16) with glassmorphism effect
  - 3xl heading text
  - Colored shadow on hover (shadow-emerald-500/50)
  - Scale animation on icon hover
  - "Klik untuk mula" call-to-action badge

**Data Pengundi Button**:
- Sky blue gradient (from-sky-500 via-sky-600 to-sky-700)
- Links to `reports.data-pengundi.create`
- Same visual treatment as Mula Culaan button with sky color scheme

### 28.4 Visual Design
- **Background**: Changed from `bg-slate-50` to `bg-slate-100` for better button contrast
- **Button Styling**:
  - Large padding (p-12) for prominence
  - Rounded corners (rounded-2xl)
  - Border glow with semi-transparent borders
  - Glassmorphism with backdrop blur
  - Multi-layer gradients for depth
  - Hover effects: lift (-translate-y-2), shadow (shadow-2xl), color shift
  - Smooth transitions (duration-300)

- **Search Card**:
  - White background with subtle shadow
  - Rounded corners (rounded-xl)
  - Clean border (border-slate-200)

### 28.5 Backend Implementation
**DashboardController.php**:
- Modified `index()` method to detect user role and render appropriate dashboard
- Added `searchIC()` method for IC number search API
- Added `canModifyHasilCulaan()` helper method for permission checking
- Added `canModifyDataPengundi()` helper method for permission checking
- Territory restrictions enforced in search queries

**Routes** (web.php):
- Added `/dashboard/search-ic` route for IC search API endpoint

**Files Created**:
- `resources/js/Pages/Dashboard/UserDashboard.jsx` - New simplified dashboard component

### 28.6 User Experience Features
- **Accessibility**: Large touch targets for mobile users
- **Responsiveness**: Grid adapts from 1 column (mobile) to 2 columns (desktop)
- **Feedback**: Loading states, hover effects, smooth animations
- **Permissions**: Clear visual indicators for edit vs view-only access
- **Search UX**: Click outside to close dropdown, clear results on empty query

### 28.7 Form Field Improvements
- **Daerah Mengundi Field**: Updated to match KADUN field style across all forms
  - Added required asterisk (*)
  - Made conditional (shows placeholder when Bandar not selected)
  - Added `required` attribute
  - Applied to: Data Pengundi Create/Edit, Hasil Culaan Create/Edit

- **Placeholder Text**: Changed from "Auto-filled from Poskod" to "Pilih Poskod terlebih dahulu"
  - Applied to Negeri, Bandar, and Parlimen fields
  - Provides clearer guidance to users

