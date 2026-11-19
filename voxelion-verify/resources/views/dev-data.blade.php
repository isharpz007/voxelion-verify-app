<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Voxelion — Dev Data Browser</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: ui-monospace, 'Cascadia Mono', Menlo, monospace; background: #0f172a; color: #e2e8f0; margin: 0; padding: 24px; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        .sub { color: #64748b; font-size: 13px; margin-bottom: 24px; }
        .nav { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 28px; }
        .nav a { color: #93c5fd; text-decoration: none; background: #1e293b; padding: 4px 10px; border-radius: 6px; font-size: 12px; }
        .nav a:hover { background: #334155; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 36px; font-size: 12px; }
        caption { text-align: left; font-size: 14px; font-weight: 700; color: #f8fafc; padding: 8px 0; }
        caption .count { color: #4ade80; font-weight: 400; }
        th, td { border: 1px solid #334155; padding: 6px 10px; text-align: left; vertical-align: top; max-width: 320px; overflow-wrap: anywhere; }
        th { background: #1e293b; color: #93c5fd; position: sticky; top: 0; }
        tr:nth-child(even) td { background: #16202f; }
        .empty { color: #475569; font-style: italic; padding: 6px 0; }
        .badge { display: inline-block; background: #7f1d1d; color: #fecaca; font-size: 11px; padding: 2px 8px; border-radius: 999px; margin-left: 8px; }
    </style>
</head>
<body>
    <h1>Dev Data Browser <span class="badge">local only</span></h1>
    <p class="sub">Latest {{ $limit }} rows per table · {{ now()->toDateTimeString() }} · <a href="/dev/data" style="color:#93c5fd">refresh</a></p>

    <nav class="nav">
        @foreach ($tables as $name => $table)
            <a href="#{{ $name }}">{{ $name }} ({{ $table['count'] }})</a>
        @endforeach
    </nav>

    @foreach ($tables as $name => $table)
        <table id="{{ $name }}">
            <caption>{{ $name }} <span class="count">— {{ $table['count'] }} row(s)</span></caption>
            @if (count($table['rows']) === 0)
                <tr><td class="empty">empty</td></tr>
            @else
                <thead>
                    <tr>
                        @foreach (array_keys($table['rows'][0]) as $col)
                            <th>{{ $col }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($table['rows'] as $row)
                        <tr>
                            @foreach ($row as $value)
                                <td>{{ is_scalar($value) || $value === null ? (string) $value : json_encode($value) }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            @endif
        </table>
    @endforeach
</body>
</html>
