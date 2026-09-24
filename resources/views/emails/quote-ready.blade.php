<x-mail::message>
# Quote from {{ $quote->shop->name }}

Hi {{ $quote->customer->name }},

Please find your quote attached as a PDF. The total comes to **${{ number_format($quote->total, 2) }}**.

Let us know if you have any questions.

Thanks,<br>
{{ $quote->shop->name }}
</x-mail::message>
