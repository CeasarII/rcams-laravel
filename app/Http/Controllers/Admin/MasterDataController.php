<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Shared CRUD behaviour for the Admin "Master Data" maintenance screens
 * (Company, Department, Vessel Type, Vessel).
 *
 * Each concrete controller only declares its model, its rules, and the
 * screen settings (columns, form fields). There is no hard delete: these
 * rows are referenced by users, vessels and fund requests (all FKs are
 * restrictOnDelete), so a record is retired by unticking "Active".
 */
abstract class MasterDataController extends Controller
{
    /** @return class-string<Model> */
    abstract protected function modelClass(): string;

    /**
     * Keys: singular, plural, subtitle, route (e.g. 'admin.companies'),
     * display (attribute used in flash messages), columns, fields,
     * search (columns for the search box), orderBy, with, withCount.
     */
    abstract protected function settings(): array;

    /** Validation rules for the entity's own fields ("is_active" is added here). */
    abstract protected function rules(Request $request, ?Model $model): array;

    public function index(Request $request)
    {
        $class = $this->modelClass();
        $this->authorize('viewAny', $class);

        $s = $this->settings();
        $q = trim((string) $request->query('q', ''));

        $query = $class::query()
            ->with($s['with'] ?? [])
            ->withCount($s['withCount'] ?? []);

        if ($q !== '') {
            $query->where(function ($where) use ($s, $q) {
                foreach ($s['search'] ?? [] as $column) {
                    $where->orWhere($column, 'like', "%{$q}%");
                }
            });
        }

        foreach ((array) ($s['orderBy'] ?? ['id']) as $column) {
            $query->orderBy($column);
        }

        return view('admin.master.index', [
            's' => $s,
            'rows' => $query->paginate(20)->withQueryString(),
            'q' => $q,
        ]);
    }

    public function create()
    {
        $this->authorize('create', $this->modelClass());

        return view('admin.master.form', ['s' => $this->settings(), 'model' => null]);
    }

    public function store(Request $request)
    {
        $class = $this->modelClass();
        $this->authorize('create', $class);

        $data = $this->validated($request, null);
        $model = $class::create($data);

        $s = $this->settings();

        return redirect()
            ->route($s['route'].'.index')
            ->with('status', "{$s['singular']} \"{$model->{$s['display']}}\" was created.");
    }

    public function edit(string $id)
    {
        $model = $this->modelClass()::findOrFail($id);
        $this->authorize('update', $model);

        return view('admin.master.form', ['s' => $this->settings(), 'model' => $model]);
    }

    public function update(Request $request, string $id)
    {
        $model = $this->modelClass()::findOrFail($id);
        $this->authorize('update', $model);

        $model->update($this->validated($request, $model));

        $s = $this->settings();

        return redirect()
            ->route($s['route'].'.index')
            ->with('status', "{$s['singular']} \"{$model->{$s['display']}}\" was updated.");
    }

    private function validated(Request $request, ?Model $model): array
    {
        // Codes are stored upper-case (same convention as roles/statuses).
        if ($request->filled('code')) {
            $request->merge(['code' => strtoupper(trim($request->input('code')))]);
        }

        $data = $request->validate($this->rules($request, $model) + [
            'is_active' => ['required', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
