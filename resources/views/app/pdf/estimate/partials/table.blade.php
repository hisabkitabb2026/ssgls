{{-- Rate Card Matrix Table for Estimate PDF
     Stations as rows, weight types (units) as columns, rates from rate_card JSON.
     Each estimate item has a rate_card = { "unitId": rateInCents }.
     $units is passed from EstimateService::getPdfData(). --}}
<table width="100%" class="items-table" cellspacing="0" border="0">
    <tr class="item-table-heading-row">
        <th width="2%" class="pr-20 text-right item-table-heading">#</th>
        <th width="25%" class="pl-0 text-left item-table-heading">Station</th>
        @foreach($customFields as $field)
            <th class="text-right item-table-heading">{{ $field->label }}</th>
        @endforeach
        @foreach($units as $unit)
            <th class="pr-20 text-right item-table-heading">{{ $unit->name }}</th>
        @endforeach
    </tr>
    @php
        $index = 1
    @endphp
    @foreach ($estimate->items as $item)
        <tr class="item-row">
            <td
                class="pr-20 text-right item-cell"
                style="vertical-align: top;"
            >
                {{$index}}
            </td>
            <td
                class="pl-0 text-left item-cell"
            >
                <span>{{ $item->name }}</span><br>
                <span
                    class="item-description"
                >
                    {!! nl2br(htmlspecialchars($item->description)) !!}
                </span>
            </td>
            @foreach($customFields as $field)
                <td class="text-right item-cell" style="vertical-align: top;">
                    {{ $item->getCustomFieldValueBySlug($field->slug) }}
                </td>
            @endforeach
            @foreach($units as $unit)
                <td
                    class="pr-20 text-right item-cell"
                    style="vertical-align: top;"
                >
                    @php
                        $rateCard = $item->rate_card ?? [];
                        $rateCents = $rateCard[$unit->id] ?? null;
                    @endphp
                    @if ($rateCents !== null)
                        {!! format_money_pdf($rateCents, $estimate->customer->currency) !!}
                    @else
                        —
                    @endif
                </td>
            @endforeach
        </tr>
        @php
            $index += 1
        @endphp
    @endforeach
</table>
