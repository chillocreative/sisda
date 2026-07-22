# Sticky Filters Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remember each user's dropdown and date-range selections for as long as they stay logged in, so navigating away and back restores both the controls and the filtered results.

**Architecture:** A `RememberFilters` middleware stores whitelisted filter params in the Laravel session and, on a request that carries none, merges the remembered ones back into `$request`. Controllers then build their query and echo their filters from that one merged source, so the control and the result cannot disagree. Logout already invalidates the session, so the reset requirement needs no code.

**Tech Stack:** Laravel 12, Inertia, React 18, PHPUnit.

## Global Constraints

- **All user-facing text is Bahasa Melayu.** No i18n layer; strings are hardcoded inline.
- **Unknown is not zero.** Never coerce an absent filter to `0`. An unset filter is `''` or `null`.
- **Run the suite with `php -d memory_limit=2G vendor/bin/phpunit`.** `php artisan test` currently OOMs inside Collision's syntax highlighter.
- **Baseline is 20 pre-existing failures** (`UserFactory` does not set the NOT NULL `telephone` column). Only worry if that count grows.
- **Never commit `public/build/`.** It is tracked, so `npm run build` always dirties it; revert with `git checkout -- public/build/ && git clean -fd public/build/` before committing.
- Branch: `feature/sticky-filters`. Do not push to `main` — it auto-deploys to production.
- Spec: `docs/superpowers/specs/2026-07-22-sticky-filters-design.md`

## File Structure

| File | Responsibility |
|---|---|
| `config/sticky_filters.php` (create) | The whitelist. Scope → route patterns + permitted keys. The only place scopes are defined. |
| `app/Support/FilterScopes.php` (create) | Pure resolver: route name → scope + keys. No framework state, unit-testable. |
| `app/Http/Middleware/RememberFilters.php` (create) | Save-or-merge. The only writer of the session key. |
| `app/Http/Middleware/HandleInertiaRequests.php` (modify) | Share `rememberedFilters` for the current scope. |
| `app/Http/Controllers/DashboardController.php` (modify) | Fix the echo bug — return `'filters'`. |
| `resources/js/Pages/Dashboard/Index.jsx` (modify) | Seed state from `filters` prop (camelCase mapping). |
| `resources/js/Pages/Pilihanraya/filters.js` (modify) | Add `initialFilters()` so the axios cluster seeds in one line each. |

---

### Task 1: Scope registry

**Files:**
- Create: `config/sticky_filters.php`
- Create: `app/Support/FilterScopes.php`
- Test: `tests/Unit/FilterScopesTest.php`

**Interfaces:**
- Produces: `FilterScopes::forRoute(?string $routeName): ?array` returning `['scope' => string, 'keys' => array<string>]` or `null`.

- [ ] **Step 1: Write the failing test**

```php
<?php
namespace Tests\Unit;

use App\Support\FilterScopes;
use Tests\TestCase;

class FilterScopesTest extends TestCase
{
    public function test_resolves_a_named_route_to_its_scope_and_keys(): void
    {
        $out = FilterScopes::forRoute('dashboard');

        $this->assertSame('dashboard', $out['scope']);
        $this->assertContains('negeri_id', $out['keys']);
        $this->assertContains('tarikh_hingga', $out['keys']);
    }

    public function test_unknown_route_has_no_scope(): void
    {
        $this->assertNull(FilterScopes::forRoute('profile.edit'));
        $this->assertNull(FilterScopes::forRoute(null));
    }

    public function test_wildcard_routes_share_one_scope(): void
    {
        // Tab XHR endpoints MESTI memetakan ke skop yang sama seperti halaman
        // induknya — itulah yang menjadikan pengambilan data halaman merangkap
        // penyimpanan.
        $a = FilterScopes::forRoute('pilihanraya.war-room');
        $b = FilterScopes::forRoute('pilihanraya.war-room.battlefield');

        $this->assertSame($a['scope'], $b['scope']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php -d memory_limit=1G vendor/bin/phpunit --filter=FilterScopesTest`
Expected: FAIL — `Class "App\Support\FilterScopes" not found`.

- [ ] **Step 3: Write the config**

Create `config/sticky_filters.php`:

```php
<?php

// Senarai putih penapis yang diingat sepanjang sesi log masuk.
//
// SEMPADAN KESELAMATAN: hanya kunci yang disenaraikan di sini akan disimpan
// atau digabungkan ke dalam permintaan. Middleware TIDAK BOLEH menyuntik
// parameter yang pengawal tidak jangka.
//
// `routes` menerima corak Str::is, jadi beberapa laluan boleh berkongsi satu
// skop. Endpoint XHR yang dipanggil sesuatu halaman MESTI berkongsi skop
// halaman itu — dengan itu pengambilan data biasa halaman merangkap
// penyimpanan, tanpa panggilan "simpan" berasingan.
return [
    'dashboard' => [
        'routes' => ['dashboard'],
        'keys' => ['negeri_id', 'bandar_id', 'kadun_id', 'mpkk_id', 'tarikh_dari', 'tarikh_hingga'],
    ],

    'war_room' => [
        'routes' => ['pilihanraya.war-room', 'pilihanraya.war-room.*'],
        'keys' => [
            'negeri_id', 'parlimen_id', 'kadun_id',
            'tarikh_dari', 'tarikh_hingga',
            'umur_dari', 'umur_hingga', 'status_pengundi',
        ],
    ],
];
```

- [ ] **Step 4: Write the resolver**

Create `app/Support/FilterScopes.php`:

```php
<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Penyelesai skop penapis: nama laluan -> skop + kunci yang dibenarkan.
 *
 * Tulen: tiada sesi, tiada permintaan, tiada pangkalan data. Keputusan
 * "kunci mana yang sah untuk skrin ini" boleh diuji secara langsung.
 */
class FilterScopes
{
    /** @return array{scope:string,keys:array<int,string>}|null */
    public static function forRoute(?string $routeName): ?array
    {
        if ($routeName === null || $routeName === '') {
            return null;
        }

        foreach (config('sticky_filters', []) as $scope => $def) {
            foreach ($def['routes'] ?? [] as $pattern) {
                if (Str::is($pattern, $routeName)) {
                    return ['scope' => $scope, 'keys' => $def['keys'] ?? []];
                }
            }
        }

        return null;
    }

    public static function sessionKey(string $scope): string
    {
        return "sticky_filters.{$scope}";
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php -d memory_limit=1G vendor/bin/phpunit --filter=FilterScopesTest`
Expected: PASS, 3 tests.

- [ ] **Step 6: Commit**

```bash
git add config/sticky_filters.php app/Support/FilterScopes.php tests/Unit/FilterScopesTest.php
git commit -m "Sticky filters: daftar skop dan senarai putih kunci penapis"
```

---

### Task 2: The RememberFilters middleware

**Files:**
- Create: `app/Http/Middleware/RememberFilters.php`
- Modify: `bootstrap/app.php:14-19` (append to the `web` group)
- Test: `tests/Feature/StickyFiltersTest.php`

**Interfaces:**
- Consumes: `FilterScopes::forRoute()`, `FilterScopes::sessionKey()` from Task 1.
- Produces: session entries under `sticky_filters.<scope>`; `$request` carrying merged filter values for downstream controllers.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/StickyFiltersTest.php`:

```php
<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class StickyFiltersTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        // UserFactory tidak menetapkan lajur NOT NULL `telephone` (pepijat sedia ada).
        return User::factory()->create([
            'role' => 'super_admin',
            'telephone' => '01277'.random_int(10000, 99999),
        ]);
    }

    /** Laluan ujian yang memantulkan apa yang pengawal SEBENARNYA nampak. */
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('sticky_filters.ujian', [
            'routes' => ['ujian.penapis'],
            'keys' => ['negeri_id', 'bandar_id'],
        ]);

        Route::middleware(['web', 'auth'])->get('/ujian-penapis', function () {
            return response()->json([
                'negeri_id' => request()->input('negeri_id'),
                'bandar_id' => request()->input('bandar_id'),
                'penceroboh' => request()->input('penceroboh'),
            ]);
        })->name('ujian.penapis');
    }

    public function test_filters_are_remembered_and_merged_into_a_bare_request(): void
    {
        $this->actingAs($this->user())
            ->getJson('/ujian-penapis?negeri_id=5&bandar_id=40')
            ->assertJson(['negeri_id' => '5', 'bandar_id' => '40']);

        // Navigasi biasa: TIADA parameter langsung -> pulihkan.
        $this->actingAs($this->user())
            ->getJson('/ujian-penapis')
            ->assertJson(['negeri_id' => '5', 'bandar_id' => '40']);
    }

    public function test_clearing_a_filter_is_remembered_as_cleared(): void
    {
        $this->actingAs($this->user())->getJson('/ujian-penapis?negeri_id=5&bandar_id=40');

        // "Set Semula" menghantar kunci HADIR-TETAPI-KOSONG.
        $this->actingAs($this->user())
            ->getJson('/ujian-penapis?negeri_id=&bandar_id=')
            ->assertJson(['negeri_id' => '', 'bandar_id' => '']);

        // Lawatan seterusnya mesti memulihkan TIADA APA-APA, bukan nilai lama.
        $this->actingAs($this->user())
            ->getJson('/ujian-penapis')
            ->assertJson(['negeri_id' => '', 'bandar_id' => '']);
    }

    public function test_a_key_outside_the_whitelist_is_never_merged(): void
    {
        $this->actingAs($this->user())
            ->getJson('/ujian-penapis?negeri_id=5&penceroboh=jahat');

        $this->actingAs($this->user())
            ->getJson('/ujian-penapis')
            ->assertJson(['negeri_id' => '5', 'penceroboh' => null]);
    }

    public function test_two_users_do_not_share_remembered_filters(): void
    {
        $this->actingAs($this->user())->getJson('/ujian-penapis?negeri_id=5&bandar_id=40');

        $this->actingAs($this->user())
            ->getJson('/ujian-penapis')
            ->assertJson(['negeri_id' => null, 'bandar_id' => null]);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php -d memory_limit=1G vendor/bin/phpunit --filter=StickyFiltersTest`
Expected: FAIL — the second request in the first test returns `negeri_id: null` because nothing remembers yet.

- [ ] **Step 3: Write the middleware**

Create `app/Http/Middleware/RememberFilters.php`:

```php
<?php

namespace App\Http\Middleware;

use App\Support\FilterScopes;
use Closure;
use Illuminate\Http\Request;

/**
 * Mengingat penapis skrin sepanjang sesi log masuk.
 *
 * INVARIAN: dropdown yang dipulihkan tetapi keputusan yang tidak ditapis
 * ialah UI yang MENIPU. Sebab itu nilai yang diingat digabungkan ke dalam
 * $request itu sendiri — pengawal membina pertanyaan DAN memulangkan penapis
 * ke dropdown daripada sumber yang SAMA, jadi keduanya tidak boleh hanyut.
 *
 * Disimpan dalam sesi Laravel: AuthenticatedSessionController memanggil
 * session()->invalidate() semasa log keluar, jadi "reset selepas log keluar"
 * ialah sifat tempat simpanan, bukan kod yang boleh terlupa dijalankan.
 */
class RememberFilters
{
    public function handle(Request $request, Closure $next)
    {
        // GET sahaja: menulis semula badan POST/PUT/DELETE akan menukar apa
        // yang pengguna hantar, bukan sekadar apa yang dia lihat.
        if (! $request->isMethod('GET') || ! $request->user()) {
            return $next($request);
        }

        $scope = FilterScopes::forRoute($request->route()?->getName());
        if (! $scope) {
            return $next($request);
        }

        $kunci = $scope['keys'];
        $sessionKey = FilterScopes::sessionKey($scope['scope']);

        // MANA-MANA kunci hadir = pengguna bertindak dengan sengaja. Kunci
        // hadir-tetapi-kosong bermakna "dibersihkan", dan itu MESTI diingat
        // sebagai kosong — jika tidak, Set Semula akan berpatah balik ke
        // pilihan lama pada lawatan berikutnya.
        $adaKunci = collect($kunci)->contains(fn ($k) => $request->has($k));

        if ($adaKunci) {
            $request->session()->put($sessionKey, collect($kunci)
                ->mapWithKeys(fn ($k) => [$k => $request->input($k)])
                ->all());

            return $next($request);
        }

        $diingat = $request->session()->get($sessionKey, []);
        if ($diingat) {
            // array_intersect_key melindungi daripada entri sesi lama yang
            // membawa kunci yang sejak itu dibuang daripada senarai putih.
            $request->merge(array_intersect_key($diingat, array_flip($kunci)));
        }

        return $next($request);
    }
}
```

- [ ] **Step 4: Register the middleware**

In `bootstrap/app.php`, append to the `web` group so it runs after session start:

```php
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            \App\Http\Middleware\LogUserPageView::class,
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\RememberFilters::class,
        ]);
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php -d memory_limit=1G vendor/bin/phpunit --filter=StickyFiltersTest`
Expected: PASS, 4 tests.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Middleware/RememberFilters.php bootstrap/app.php tests/Feature/StickyFiltersTest.php
git commit -m "Sticky filters: middleware simpan-atau-gabung penapis ke dalam permintaan"
```

---

### Task 3: Share remembered filters to the frontend, and prove logout clears them

**Files:**
- Modify: `app/Http/Middleware/HandleInertiaRequests.php:47-56`
- Test: `tests/Feature/StickyFiltersTest.php` (append)

**Interfaces:**
- Consumes: `FilterScopes::forRoute()`, `FilterScopes::sessionKey()`.
- Produces: shared Inertia prop `rememberedFilters` — an object of `key => value` for the current scope, `{}` when there is no scope. Consumed by the axios-driven pages in Task 5.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/StickyFiltersTest.php`:

```php
    public function test_logging_out_forgets_the_filters(): void
    {
        $user = $this->user();

        $this->actingAs($user)->getJson('/ujian-penapis?negeri_id=5&bandar_id=40');
        $this->post(route('logout'));

        // Log masuk semula pada sesi baharu -> lalai, seperti diminta.
        $this->actingAs($user)
            ->getJson('/ujian-penapis')
            ->assertJson(['negeri_id' => null, 'bandar_id' => null]);
    }

    public function test_remembered_filters_are_shared_to_inertia(): void
    {
        $user = $this->user();

        $this->actingAs($user)->get(route('dashboard', ['negeri_id' => 5]));

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('rememberedFilters.negeri_id', '5'));
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php -d memory_limit=1G vendor/bin/phpunit --filter=StickyFiltersTest`
Expected: the share test FAILS — `rememberedFilters` is not a prop. The logout test may already pass (the session is invalidated); if it passes, keep it as a regression guard.

- [ ] **Step 3: Share the prop**

In `app/Http/Middleware/HandleInertiaRequests.php`, add to the array returned by `share()`, after `'app_url'`:

```php
            // Penapis yang diingat bagi skop skrin semasa. Halaman yang
            // dipacu axios (War Room, Analisa, Borang 14, Scoreboard) menyemai
            // useState awalnya daripada sini; halaman yang dipacu URL tidak
            // memerlukannya kerana middleware sudah menggabungkan nilai itu
            // ke dalam permintaan sebelum pengawal berjalan.
            'rememberedFilters' => function () use ($request) {
                $scope = \App\Support\FilterScopes::forRoute($request->route()?->getName());

                return $scope
                    ? (object) $request->session()->get(\App\Support\FilterScopes::sessionKey($scope['scope']), [])
                    : (object) [];
            },
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php -d memory_limit=1G vendor/bin/phpunit --filter=StickyFiltersTest`
Expected: PASS, 6 tests.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Middleware/HandleInertiaRequests.php tests/Feature/StickyFiltersTest.php
git commit -m "Sticky filters: kongsi rememberedFilters ke Inertia; sahkan log keluar memadamnya"
```

---

### Task 4: Dashboard — fix the echo bug, seed the controls, prove authorization is not widened

**Files:**
- Modify: `app/Http/Controllers/DashboardController.php:524` (the `Inertia::render` array)
- Modify: `resources/js/Pages/Dashboard/Index.jsx:69-76`
- Test: `tests/Feature/StickyFiltersTest.php` (append)

**Interfaces:**
- Consumes: the merged `$request` from Task 2.
- Produces: Inertia prop `filters` — `['negeri_id','bandar_id','kadun_id','mpkk_id','tarikh_dari','tarikh_hingga']`, all string-or-null.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/StickyFiltersTest.php`:

```php
    public function test_dashboard_echoes_its_filters_back_to_the_page(): void
    {
        // Pepijat sedia ada: pengawal MEMBACA keenam-enam parameter tetapi
        // tidak pernah memulangkannya, jadi setiap dropdown bermula kosong
        // walaupun URL membawanya. Refresh biasa pun kehilangannya.
        $this->actingAs($this->user())
            ->get(route('dashboard', ['negeri_id' => 5, 'bandar_id' => 40]))
            ->assertInertia(fn ($page) => $page
                ->where('filters.negeri_id', '5')
                ->where('filters.bandar_id', '40'));
    }

    public function test_remembered_filters_cannot_widen_a_scoped_admins_access(): void
    {
        // Penapis yang diingat MENYEMPITKAN di dalam sempadan kebenaran
        // pengguna; ia tidak boleh MELUASKANNYA. Sempadan itu dikenakan di
        // DashboardController.php:55-63 tanpa mengira parameter penapis.
        $negeri = \App\Models\Negeri::create(['nama' => 'Negeri Sembilan']);
        $kita = \App\Models\Bandar::create(['nama' => 'Kuala Pilah', 'negeri_id' => $negeri->id]);
        $orangLain = \App\Models\Bandar::create(['nama' => 'Seremban', 'negeri_id' => $negeri->id]);

        $admin = User::factory()->create([
            'role' => 'admin',
            'telephone' => '01277'.random_int(10000, 99999),
            'negeri_id' => $negeri->id,
            'bandar_id' => $kita->id,
        ]);

        // Cuba mencapai Bandar orang lain melalui penapis, kemudian melalui
        // penapis yang DIINGAT pada lawatan kosong berikutnya.
        $this->actingAs($admin)->get(route('dashboard', ['bandar_id' => $orangLain->id]));

        $res = $this->actingAs($admin)->get(route('dashboard'));

        $res->assertOk();
        // Pengesahan sebenar: pertanyaan masih diskop kepada bandar admin itu.
        // Jumlah pengundi mesti 0 kerana tiada data untuk Seremban DAN
        // penskopan masih mengenakan Kuala Pilah.
        $res->assertInertia(fn ($page) => $page->where('totalPengundi', 0));
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php -d memory_limit=1G vendor/bin/phpunit --filter=StickyFiltersTest`
Expected: the echo test FAILS — prop `filters` does not exist.

- [ ] **Step 3: Fix the echo bug**

In `app/Http/Controllers/DashboardController.php`, add to the `Inertia::render('Dashboard/Index', [...])` array (around line 524):

```php
            // Pulangkan penapis supaya dropdown boleh dipulihkan. Nilai ini
            // ialah nilai yang SAMA yang membina pertanyaan di atas — satu
            // sumber, jadi kawalan dan keputusan tidak boleh bercanggah.
            'filters' => [
                'negeri_id' => $negeriId,
                'bandar_id' => $bandarId,
                'kadun_id' => $kadunId,
                'mpkk_id' => $mpkkId,
                'tarikh_dari' => $tarikhDari,
                'tarikh_hingga' => $tarikhHingga,
            ],
```

- [ ] **Step 4: Seed the page state**

In `resources/js/Pages/Dashboard/Index.jsx`, accept the prop and seed from it. The page holds camelCase names but sends snake_case params, so map explicitly:

```jsx
    // Disemai daripada pelayan supaya pilihan bertahan merentasi navigasi.
    // Nama tempatan camelCase; parameter yang dihantar snake_case — petakan
    // secara jelas, jangan andaikan ia sama.
    const [filters, setFilters] = useState({
        negeri: filtersProp?.negeri_id ?? '',
        bandar: filtersProp?.bandar_id ?? '',
        kadun: filtersProp?.kadun_id ?? '',
        mpkk: filtersProp?.mpkk_id ?? '',
        tarikhDari: filtersProp?.tarikh_dari ?? '',
        tarikhHingga: filtersProp?.tarikh_hingga ?? ''
    });
```

Add `filters: filtersProp` to the component's destructured props (rename to avoid colliding with the local `filters` state).

- [ ] **Step 5: Run tests and build**

Run: `php -d memory_limit=1G vendor/bin/phpunit --filter=StickyFiltersTest`
Expected: PASS, 8 tests.

Run: `npm run build`
Expected: build succeeds.

- [ ] **Step 6: Commit (source only)**

```bash
git checkout -- public/build/ && git clean -fdq public/build/
git add app/Http/Controllers/DashboardController.php resources/js/Pages/Dashboard/Index.jsx tests/Feature/StickyFiltersTest.php
git commit -m "Sticky filters: Dashboard pulangkan penapis dan semai dropdown"
```

---

### Task 5: Seed the axios-driven Pilihanraya pages

**Files:**
- Modify: `resources/js/Pages/Pilihanraya/filters.js`
- Modify: `resources/js/Pages/Pilihanraya/WarRoom.jsx:454`
- Modify: `resources/js/Pages/Pilihanraya/Simulasi.jsx:10`
- Test: `tests/Unit/FilterScopesTest.php` (append — scope coverage for the routes involved)

**Interfaces:**
- Consumes: Inertia prop `rememberedFilters` from Task 3.
- Produces: `initialFilters(remembered, defaults)` exported from `filters.js`.

These pages fetch via axios in a `useEffect` keyed on the filter state, so seeding the state is enough — the results follow with no extra work.

- [ ] **Step 1: Add the seeding helper**

In `resources/js/Pages/Pilihanraya/filters.js`, append:

```js
/**
 * Semai keadaan penapis awal daripada nilai yang diingat pelayan.
 *
 * Hanya kunci yang WUJUD dalam bentuk lalai diterima, jadi entri sesi lama
 * tidak boleh menyuntik medan yang halaman ini tidak faham. Nilai kosong
 * dikekalkan sebagai kosong — "dibersihkan" ialah pilihan yang sah, bukan
 * ketiadaan pilihan.
 */
export function initialFilters(remembered, defaults = EMPTY_FILTERS) {
    if (!remembered) return { ...defaults };

    return Object.fromEntries(
        Object.entries(defaults).map(([k, v]) => [k, remembered[k] ?? v]),
    );
}
```

- [ ] **Step 2: Seed WarRoom**

In `resources/js/Pages/Pilihanraya/WarRoom.jsx`, import the helper and replace the state initialiser at line 454:

```jsx
import { EMPTY_FILTERS, cleanParams, initialFilters } from './filters';
// ...
const { rememberedFilters } = usePage().props;
const [filters, setFilters] = useState(() => initialFilters(rememberedFilters));
```

- [ ] **Step 3: Seed Simulasi**

In `resources/js/Pages/Pilihanraya/Simulasi.jsx`, apply the identical change at line 10:

```jsx
import { EMPTY_FILTERS, initialFilters } from './filters';
// ...
const { rememberedFilters } = usePage().props;
const [filters, setFilters] = useState(() => initialFilters(rememberedFilters));
```

- [ ] **Step 4: Verify the routes are in the whitelist**

Run: `php artisan route:list --name=war-room`
Confirm every route name printed is matched by the `war_room` patterns in `config/sticky_filters.php`. Add any missing pattern — an XHR route outside the scope means the page's fetch never saves.

- [ ] **Step 5: Build and run the suite**

Run: `npm run build`
Expected: build succeeds.

Run: `php -d memory_limit=2G vendor/bin/phpunit`
Expected: 20 failed (the pre-existing baseline), everything else passing.

- [ ] **Step 6: Commit (source only)**

```bash
git checkout -- public/build/ && git clean -fdq public/build/
git add resources/js/Pages/Pilihanraya/filters.js resources/js/Pages/Pilihanraya/WarRoom.jsx resources/js/Pages/Pilihanraya/Simulasi.jsx config/sticky_filters.php
git commit -m "Sticky filters: semai War Room dan Simulasi daripada penapis yang diingat"
```

---

### Task 6: Extend the whitelist to the remaining screens, then verify

**Files:**
- Modify: `config/sticky_filters.php`
- Test: `tests/Feature/StickyFiltersTest.php` (append)

The URL-driven pages need **no JavaScript change** — they already seed from a `filters` prop and the middleware now merges the remembered values before their controller runs. This task is whitelist entries plus proof.

Screens to add, with their filter keys (text-search keys are deliberately excluded per the spec):

| Scope | Route pattern | Keys |
|---|---|---|
| `users` | `users.index` | `role`, `status`, `negeri_id`, `bandar_id`, `kadun_id` |
| `user_log` | `user-log.index` | `tab`, `user_id`, `event`, `date_from`, `date_to` |
| `keanggotaan_senarai` | `keanggotaan.senarai` | `status_kawasan`, `parlimen`, `dun`, `daerah_mengundi`, `lokaliti`, `bangsa`, `jantina`, `status_anggota`, `sentimen`, `sayap` |
| `keanggotaan_analisa` | `keanggotaan.analisa` | same list as `keanggotaan_senarai` |
| `jawatankuasa` | `pilihanraya.jawatankuasa` | `jenis`, `parlimen`, `dun` |
| `hasil_culaan` | `laporan.hasil-culaan.index` | `umur`, `bangsa`, `negeri`, `bandar`, `lokaliti` |
| `data_pengundi` | `laporan.data-pengundi.index` | `date_from`, `date_to` |
| `masterdata_bandar` | `master-data.bandar.index` | `negeri_id` |
| `masterdata_parlimen` | `master-data.parlimen.index` | `negeri_id` |
| `kaum_dm` | `pilihanraya.kaum-dm` | `kawasan` |
| `minima` | `pilihanraya.minima` | `kawasan` |

- [ ] **Step 1: Confirm the real route names**

Run: `php artisan route:list --columns=name,uri | grep -Ei "users|user-log|keanggotaan|jawatankuasa|culaan|pengundi|bandar|parlimen"`
Use the exact names printed. A wrong name silently means "no scope" and the screen simply won't remember — a silent no-op, so verify rather than assume.

- [ ] **Step 2: Write the failing test**

Append to `tests/Feature/StickyFiltersTest.php`:

```php
    public function test_every_configured_scope_resolves_and_has_keys(): void
    {
        // Nama laluan yang salah eja gagal SENYAP — skrin itu sekadar tidak
        // mengingat apa-apa. Kunci setiap corak kepada laluan sebenar.
        $daftar = \Illuminate\Support\Facades\Route::getRoutes();

        foreach (config('sticky_filters') as $scope => $def) {
            $this->assertNotEmpty($def['keys'], "Skop {$scope} tiada kunci.");

            foreach ($def['routes'] as $pattern) {
                if (str_contains($pattern, '*')) {
                    continue; // corak wildcard disemak melalui laluan induknya
                }
                $this->assertNotNull(
                    collect($daftar)->first(fn ($r) => $r->getName() === $pattern),
                    "Laluan '{$pattern}' bagi skop '{$scope}' tidak wujud.",
                );
            }
        }
    }
```

- [ ] **Step 3: Run test to verify it fails**

Run: `php -d memory_limit=1G vendor/bin/phpunit --filter=test_every_configured_scope_resolves`
Expected: FAIL for any route name that does not exist. Correct the names in the config until it passes — this is the point of the test.

- [ ] **Step 4: Add the scopes**

Add each row from the table above to `config/sticky_filters.php`, using the exact route names confirmed in Step 1.

- [ ] **Step 5: Run test to verify it passes**

Run: `php -d memory_limit=1G vendor/bin/phpunit --filter=StickyFiltersTest`
Expected: PASS, 9 tests.

- [ ] **Step 6: Run the full suite**

Run: `php -d memory_limit=2G vendor/bin/phpunit`
Expected: 20 failed (pre-existing `UserFactory` telephone), no growth. If the count grew, a merged filter reached a controller that did not expect it — check the whitelist before anything else.

- [ ] **Step 7: Commit**

```bash
git add config/sticky_filters.php tests/Feature/StickyFiltersTest.php
git commit -m "Sticky filters: lanjutkan skop ke baki skrin dan kunci nama laluan dengan ujian"
```

---

### Task 7: Seed the remaining axios-driven screens

**Files:**
- Modify: `resources/js/Pages/Pilihanraya/analisa/KawasanPicker.jsx:25-27`
- Modify: `resources/js/Pages/Pilihanraya/Scoreboard.jsx:209-211`
- Modify: `resources/js/Pages/Pilihanraya/borang14/KeyinTab.jsx:19`
- Modify: `resources/js/Pages/Pilihanraya/borang14/PaparTab.jsx:34-36`
- Modify: `config/sticky_filters.php`

**Interfaces:**
- Consumes: `rememberedFilters` (Task 3), `initialFilters()` (Task 5).

These four own their filter state in `useState` and fetch by axios, so seeding the state is
sufficient — each already has a `useEffect` keyed on those values.

**Read this before touching Borang 14.** `KeyinTab` carries a documented data-corruption
hazard around seat switching: `picker` updates synchronously while `struktur` only updates
when the fetch resolves, and the panel is force-remounted by a geography-keyed React `key`
at `KeyinTab.jsx:369`. Seeding the picker's **initial** value is safe because it happens
before first render. Do **not** move, delay, or reintroduce a second write to `picker` —
see the Task 7 notes in `.superpowers/sdd/progress.md` for the full trace.

- [ ] **Step 1: Add the scopes**

Add to `config/sticky_filters.php`, using route names confirmed with
`php artisan route:list --columns=name,uri | grep -Ei "analisa|scoreboard|borang-14"`:

```php
    'analisa' => [
        'routes' => ['pilihanraya.analisa', 'pilihanraya.analisa.*'],
        'keys' => ['negeri_id', 'bandar_id', 'kadun_id'],
    ],
    'scoreboard' => [
        'routes' => ['pilihanraya.scoreboard', 'pilihanraya.scoreboard.*'],
        'keys' => ['negeri_id', 'parlimen_id', 'kadun_id'],
    ],
    'borang14' => [
        'routes' => ['pilihanraya.borang-14', 'pilihanraya.borang-14.*'],
        // Tab Keyin dan Papar berkongsi satu skop dengan sengaja.
        'keys' => ['negeri_id', 'parlimen_id', 'kadun_id', 'kawasan_type', 'jenis_pr', 'tahun'],
    ],
```

**Do not add** `pilihanraya.borang-14.struktur` or `.struktur.kesan` — those are POST routes
and the middleware ignores non-GET, but leaving them out of the patterns keeps the intent
obvious to the next reader.

- [ ] **Step 2: Seed the analisa KawasanPicker**

`resources/js/Pages/Pilihanraya/analisa/KawasanPicker.jsx` owns its state internally. Seed it:

```jsx
    const { rememberedFilters } = usePage().props;
    const [negeriId, setNegeriId] = useState(rememberedFilters?.negeri_id ?? '');
    const [bandarId, setBandarId] = useState(rememberedFilters?.bandar_id ?? '');
    const [kadunId, setKadunId] = useState(rememberedFilters?.kadun_id ?? '');
```

Add `import { usePage } from '@inertiajs/react';` if absent.

- [ ] **Step 3: Seed Scoreboard (internal only)**

In `resources/js/Pages/Pilihanraya/Scoreboard.jsx`:

```jsx
    const { rememberedFilters } = usePage().props;
    const [negeriId, setNegeriId] = useState(rememberedFilters?.negeri_id ?? '');
    const [parlimenId, setParlimenId] = useState(rememberedFilters?.parlimen_id ?? '');
    const [kadunId, setKadunId] = useState(rememberedFilters?.kadun_id ?? '');
```

Leave `resources/js/Pages/Public/Scoreboard.jsx` untouched — it is public, has no session.

- [ ] **Step 4: Seed the Borang 14 tabs**

In `resources/js/Pages/Pilihanraya/borang14/KeyinTab.jsx`, seed the picker's initial value only:

```jsx
    const [picker, setPicker] = useState(() => ({
        negeriId: rememberedFilters?.negeri_id ?? '',
        kawasanType: rememberedFilters?.kawasan_type ?? '',
        parlimenId: rememberedFilters?.parlimen_id ?? '',
        kadunId: rememberedFilters?.kadun_id ?? '',
        jenisPr: rememberedFilters?.jenis_pr ?? '',
        tahun: rememberedFilters?.tahun ?? '',
    }));
```

Apply the same shape to `PaparTab.jsx:34-36` for its three ids.

- [ ] **Step 5: Build and run the full suite**

Run: `npm run build`
Expected: build succeeds.

Run: `php -d memory_limit=2G vendor/bin/phpunit`
Expected: 20 failed (pre-existing baseline), no growth.

- [ ] **Step 6: Regression-check Borang 14 specifically**

Run: `php -d memory_limit=1G vendor/bin/phpunit --filter='Borang14'`
Expected: all pass. Borang 14 holds live production vote data; a change to its picker must
not disturb the structure-editor guards.

- [ ] **Step 7: Commit (source only)**

```bash
git checkout -- public/build/ && git clean -fdq public/build/
git add config/sticky_filters.php resources/js/Pages/Pilihanraya/analisa/KawasanPicker.jsx resources/js/Pages/Pilihanraya/Scoreboard.jsx resources/js/Pages/Pilihanraya/borang14/KeyinTab.jsx resources/js/Pages/Pilihanraya/borang14/PaparTab.jsx
git commit -m "Sticky filters: semai Analisa, Scoreboard dan tab Borang 14"
```

---

## Manual verification before merge

Local, logged in as a super admin:

1. Dashboard → pick Negeri + Bandar. Go to Keanggotaan. Return to Dashboard. **Both dropdowns still set, and the KPI cards show that seat's numbers, not the national ones.**
2. Press **Set Semula**. Navigate away and back. **Filters stay cleared** — they must not spring back.
3. Open the Dashboard in a second browser tab. **Same selection appears.**
4. War Room → set filters → go to Analisa → return. **Filters restored and the tables reloaded for them.**
5. Log out, log back in. **Every screen is back to default.**
6. Log in as an admin scoped to one Bandar. Confirm you cannot see another Bandar's figures, whether by URL or by a remembered filter.
