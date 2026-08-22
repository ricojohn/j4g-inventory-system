<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111827; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .meta { color: #6b7280; margin-bottom: 16px; }
        .answer { white-space: pre-wrap; line-height: 1.5; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #d1d5db; padding: 6px 8px; text-align: left; vertical-align: top; }
        th { background: #f3f4f6; font-size: 11px; text-transform: uppercase; letter-spacing: 0.02em; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <p class="meta">Generated {{ $generatedAt }}</p>

    <div class="answer">{!! $answerHtml !!}</div>

    @if (! empty($headers) && ! empty($rows))
        <table>
            <thead>
                <tr>
                    @foreach ($headers as $header)
                        <th>{{ $header }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    @if (is_array($row))
                        <tr>
                            @foreach ($headers as $header)
                                <td>
                                    @php
                                        $value = $row[$header] ?? '';
                                        if (is_bool($value)) {
                                            $value = $value ? 'true' : 'false';
                                        } elseif (is_array($value)) {
                                            $value = json_encode($value);
                                        }
                                    @endphp
                                    {{ $value }}
                                </td>
                            @endforeach
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
