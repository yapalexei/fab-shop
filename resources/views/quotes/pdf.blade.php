<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #111; }
        h1 { font-size: 18px; margin-bottom: 0; }
        .muted { color: #666; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { text-align: left; padding: 6px 8px; border-bottom: 1px solid #ddd; }
        th { background: #f5f5f5; }
        .text-right { text-align: right; }
        .totals td { border-bottom: none; }
        .totals .label { font-weight: bold; }
    </style>
</head>
<body>
    <h1>{{ $quote->shop->name }}</h1>
    <p class="muted">Quote #{{ $quote->id }} &middot; {{ $quote->created_at->copy()->setTimezone($quote->shop->localTimezone())->format('M j, Y') }}</p>

    <p>
        <strong>Customer:</strong> {{ $quote->customer->name }}<br>
        @if($quote->customer->email)
            {{ $quote->customer->email }}<br>
        @endif
        @if($quote->customer->address)
            {{ $quote->customer->address }}
        @endif
    </p>

    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th class="text-right">Qty</th>
                <th class="text-right">Unit cost</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($quote->lineItems as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td class="text-right">{{ rtrim(rtrim(number_format($item->quantity, 2), '0'), '.') }}</td>
                    <td class="text-right">${{ number_format($item->unit_cost, 2) }}</td>
                    <td class="text-right">${{ number_format($item->total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="totals">
                <td colspan="3" class="label text-right">Total</td>
                <td class="text-right">${{ number_format($quote->total, 2) }}</td>
            </tr>
        </tfoot>
    </table>

    @if($quote->notes)
        <p><strong>Notes:</strong><br>{{ $quote->notes }}</p>
    @endif
</body>
</html>
