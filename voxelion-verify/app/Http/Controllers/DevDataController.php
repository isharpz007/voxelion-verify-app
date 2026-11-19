<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Local-development data browser: dumps every table's rows so the backend
 * state can be inspected in the browser. 404s outside APP_ENV=local —
 * it exposes personal data and must never ship to production.
 */
class DevDataController extends Controller
{
    private const ROW_LIMIT = 50;

    private const SKIP_TABLES = ['migrations', 'sqlite_sequence'];

    public function __invoke()
    {
        abort_unless(app()->environment('local'), 404);

        $tables = [];
        foreach (Schema::getTableListing() as $table) {
            $name = str_replace('main.', '', $table);
            if (in_array($name, self::SKIP_TABLES, true)) {
                continue;
            }

            $tables[$name] = [
                'count' => DB::table($name)->count(),
                'rows' => DB::table($name)->limit(self::ROW_LIMIT)->get()
                    ->map(fn ($row) => (array) $row)->all(),
            ];
        }

        return view('dev-data', ['tables' => $tables, 'limit' => self::ROW_LIMIT]);
    }
}
