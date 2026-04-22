<?php

namespace App\Http\Controllers;

use App\Models\NotificationTemplate;
use App\Services\WhatsappService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class NotificationTemplateController extends Controller
{
    public function index(Request $request)
    {
        $category = $request->query('category');

        $templates = NotificationTemplate::query()
            ->when($category && array_key_exists($category, NotificationTemplate::CATEGORIES), fn ($q) => $q->where('category', $category))
            ->orderBy('category')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return Inertia::render('Settings/Notifications', [
            'templates' => $templates,
            'categories' => NotificationTemplate::CATEGORIES,
            'activeCategory' => $category,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateTemplate($request);
        $validated['code'] = $this->uniqueCode($validated['code'] ?? $validated['name']);
        $validated['variables'] = $this->deriveVariables($validated['body'], $validated['variables'] ?? null);

        if (!empty($validated['is_default'])) {
            NotificationTemplate::where('category', $validated['category'])
                ->update(['is_default' => false]);
        }

        NotificationTemplate::create($validated);

        return redirect()->route('settings.notifications.index', ['category' => $validated['category']])
            ->with('success', 'Templat berjaya dicipta.');
    }

    public function update(Request $request, NotificationTemplate $template)
    {
        $validated = $this->validateTemplate($request, $template->id);
        $validated['code'] = $validated['code'] ?? $template->code;
        if ($validated['code'] !== $template->code) {
            $validated['code'] = $this->uniqueCode($validated['code'], $template->id);
        }
        $validated['variables'] = $this->deriveVariables($validated['body'], $validated['variables'] ?? null);

        if (!empty($validated['is_default'])) {
            NotificationTemplate::where('category', $validated['category'])
                ->where('id', '!=', $template->id)
                ->update(['is_default' => false]);
        }

        $template->update($validated);

        return redirect()->route('settings.notifications.index', ['category' => $template->category])
            ->with('success', 'Templat berjaya dikemaskini.');
    }

    public function destroy(NotificationTemplate $template)
    {
        $category = $template->category;
        $template->delete();

        return redirect()->route('settings.notifications.index', ['category' => $category])
            ->with('success', 'Templat telah dihapuskan.');
    }

    public function duplicate(NotificationTemplate $template)
    {
        $copy = $template->replicate();
        $copy->name = $template->name . ' (Salinan)';
        $copy->code = $this->uniqueCode($template->code . '_copy');
        $copy->is_default = false;
        $copy->sort_order = ((int) $template->sort_order) + 1;
        $copy->save();

        return redirect()->route('settings.notifications.index', ['category' => $template->category])
            ->with('success', 'Templat berjaya disalin.');
    }

    public function toggle(NotificationTemplate $template)
    {
        $template->update(['is_active' => !$template->is_active]);

        return back()->with('success', $template->is_active ? 'Templat diaktifkan.' : 'Templat dinyahaktifkan.');
    }

    public function makeDefault(NotificationTemplate $template)
    {
        NotificationTemplate::where('category', $template->category)
            ->update(['is_default' => false]);
        $template->update(['is_default' => true, 'is_active' => true]);

        return back()->with('success', 'Templat ditetapkan sebagai lalai.');
    }

    public function testSend(Request $request, NotificationTemplate $template)
    {
        $validated = $request->validate([
            'phone' => 'required|string|max:32',
            'variables' => 'nullable|array',
        ]);

        $vars = $this->fillSampleVars($template, $validated['variables'] ?? []);
        $rendered = $template->render($vars);

        $sent = WhatsappService::send($validated['phone'], $rendered, 'template_test:' . $template->code);

        return back()->with(
            $sent ? 'success' : 'error',
            $sent ? 'Mesej ujian berjaya dihantar.' : 'Gagal menghantar. Semak tetapan Sendora.'
        );
    }

    private function validateTemplate(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'category' => ['required', Rule::in(array_keys(NotificationTemplate::CATEGORIES))],
            'code' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9_\-]+$/i'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:4000'],
            'variables' => ['nullable', 'array'],
            'variables.*' => ['string', 'max:64'],
            'is_active' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    private function uniqueCode(string $base, ?int $ignoreId = null): string
    {
        $code = Str::slug($base, '_');
        if ($code === '') {
            $code = 'tpl_' . Str::random(6);
        }
        $code = substr($code, 0, 90);

        $candidate = $code;
        $n = 1;
        while (NotificationTemplate::where('code', $candidate)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()
        ) {
            $candidate = $code . '_' . (++$n);
        }

        return $candidate;
    }

    private function deriveVariables(string $body, $provided): array
    {
        preg_match_all('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', $body, $m);
        $fromBody = array_values(array_unique($m[1] ?? []));

        if (is_array($provided) && !empty($provided)) {
            return array_values(array_unique(array_merge($fromBody, array_map('strval', $provided))));
        }

        return $fromBody;
    }

    private function fillSampleVars(NotificationTemplate $template, array $provided): array
    {
        $vars = [];
        foreach (($template->variables ?? []) as $v) {
            $vars[$v] = $provided[$v] ?? '{' . $v . '}';
        }
        foreach ($provided as $k => $value) {
            if ($value !== null && $value !== '') {
                $vars[$k] = $value;
            }
        }
        return $vars;
    }
}
