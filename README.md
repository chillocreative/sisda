# SISDA - Sistem Data Pengundi

A comprehensive voter data management system built with Laravel 12 and React.

## Features

- 🔐 **User Management** - Role-based access control (Super Admin, Admin, Regular User)
- 📊 **Dashboard** - Real-time statistics and data visualization
- 📝 **Data Collection** - Hasil Culaan and Data Pengundi modules
- 🗺️ **Territory Management** - Negeri, Bandar, Parlimen, KADUN, MPKK
- 📈 **Reports & Export** - Excel export functionality
- 🔍 **Search** - IC number search across databases
- 👥 **User Approval** - Admin approval workflow for new users

## Tech Stack

- **Backend:** Laravel 12, PHP 8.2+
- **Frontend:** React 18, Inertia.js, Tailwind CSS
- **Database:** MySQL
- **Build Tool:** Vite 7

## Quick Start

### Local Development

1. Clone the repository:
```bash
git clone https://github.com/chillocreative/sisda.git
cd sisda
```

2. Install dependencies:
```bash
composer install
npm install
```

3. Set up environment:
```bash
cp .env.example .env
php artisan key:generate
```

4. Configure database in `.env`:
```env
DB_CONNECTION=mysql
DB_DATABASE=sisda
DB_USERNAME=root
DB_PASSWORD=
```

5. Run migrations:
```bash
php artisan migrate
```

6. Start development servers:
```bash
npm run dev
php artisan serve
```

Visit: http://localhost:8000

## Deployment

See [DEPLOYMENT.md](DEPLOYMENT.md) for detailed cPanel deployment instructions.

## Project Structure

```
├── app/
│   ├── Http/Controllers/     # Application controllers
│   ├── Models/               # Eloquent models
│   └── Exports/              # Excel export classes
├── database/
│   ├── migrations/           # Database migrations
│   └── seeders/              # Database seeders
├── resources/
│   ├── js/
│   │   ├── Components/       # Reusable React components
│   │   ├── Layouts/          # Page layouts
│   │   └── Pages/            # Inertia pages
│   └── views/                # Blade templates
├── routes/
│   ├── web.php               # Web routes
│   └── auth.php              # Authentication routes
└── public/                   # Public assets
```

## Key Modules

### Master Data
- Negeri (States)
- Bandar (Districts)
- Parlimen (Parliament)
- KADUN (State Assembly)
- MPKK (Community Management Council)
- Daerah Mengundi (Voting Districts)
- Tujuan Sumbangan (Donation Purposes)
- Jenis Sumbangan (Donation Types)
- Bantuan Lain (Other Assistance)
- Keahlian Parti (Party Membership)
- Kecenderungan Politik (Political Inclination)
- Hubungan (Relationships)
- Bangsa (Ethnicity)

### Reports
- **Hasil Culaan** - Data collection results with IC upload
- **Data Pengundi** - Voter data management

### User Roles
- **Super Admin** - Full system access
- **Admin** - Territory-based management
- **Regular User** - Data entry and viewing within assigned territory

## License

Proprietary - All rights reserved

## Support

For support, contact the development team.

---
Developed with ❤️ for efficient voter data management
