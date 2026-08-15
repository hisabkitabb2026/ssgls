<!DOCTYPE html>
<html>

<head>
    <title>@lang('pdf_estimate_label') - {{ $estimate->estimate_number }}</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

@include("app.pdf.partials.fonts")

    <style type="text/css">
        @page {
            margin: 10mm;
            size: 297mm 210mm;
        }

        * {
            box-sizing: border-box;
        }

        @media print {
            thead { display: table-header-group; }
            tr { page-break-inside: avoid; }
        }

        body {
            color: #111;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            margin: 0;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        td,
        th {
            border: 1px solid #666;
            padding: 4px 6px;
            vertical-align: top;
        }

        .no-border {
            border: 0 !important;
        }

        /* -- Jurisdiction -- */
        .jurisdiction {
            font-size: 11px;
            line-height: 13px;
            margin-bottom: 2px;
            text-align: right;
            text-decoration: underline;
        }

        /* -- Master shell -- */
        .estimate-shell {
            width: 100%;
        }

        .estimate-shell > .master {
            border: 2px solid #000;
        }

        .master {
            border: 0;
            table-layout: fixed;
        }

        .master > tbody > tr > td,
        .master > tbody > tr > th {
            padding: 0;
        }

        .left-zone {
            width: 60%;
        }

        .right-zone {
            width: 40%;
        }

        .right-zone table td:first-child,
        .right-zone table th:first-child {
            border-left: 0;
        }

        /* -- Brand Row -- */
        .brand-row {
            background: #f8f8f8;
            border-bottom: 2px solid #000;
            min-height: 104px;
            table-layout: fixed;
        }

        .brand-row td {
            border-left: 0;
            border-right: 0;
            border-top: 0;
            padding: 6px 8px;
            vertical-align: middle;
        }

        .logo-cell {
            text-align: center;
            width: 20%;
        }

        .company-logo {
            max-height: 100px;
            max-width: 150px;
        }

        .brand-fallback {
            color: #111;
            font-size: 34px;
            font-weight: bold;
            line-height: 34px;
            padding-top: 8px;
        }

        .brand-fallback span {
            display: block;
            font-size: 11px;
            letter-spacing: 0;
            line-height: 13px;
        }

        .company-cell {
            text-align: center;
            width: 80%;
        }

        .company-name {
            font-family: "Arial Narrow", Arial, Helvetica, sans-serif;
            font-size: 28px;
            font-weight: bold;
            line-height: 30px;
            margin-top: 1px;
        }

        .company-tagline {
            font-size: 14px;
            font-weight: bold;
            line-height: 16px;
        }

        .company-address {
            font-size: 12px;
            line-height: 15px;
            margin-top: 4px;
            text-align: center;
        }

        .company-contact {
            font-size: 12px;
            font-weight: bold;
            line-height: 15px;
            margin-top: 2px;
            text-align: center;
        }

        /* -- Party Box -- */
        .party-box {
            border-top: 0 !important;
            min-height: 130px;
            padding: 0 !important;
        }

        .party-head {
            table-layout: fixed;
        }

        .party-head td {
            border-bottom: 0;
            border-left: 0;
            border-right: 0;
            border-top: 0;
            font-size: 16px;
            min-height: 18px;
            padding: 3px 8px;
        }

        .party-address-lines {
            font-size: 14px;
            line-height: 18px;
            overflow: hidden;
            padding: 3px 8px 20px;
        }

        .party-display-name {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 2px;
        }

        .party-gstin {
            border-top: 1px solid #111;
            bottom: 0;
            font-size: 14px;
            line-height: 18px;
            overflow: hidden;
            padding: 4px 8px;
            text-overflow: clip;
            white-space: nowrap;
        }

        .party-gstin b {
            font-size: 16px;
        }

        /* -- Tax Box -- */
        .tax-box {
            min-height: 26px;
            padding: 5px 6px !important;
        }

        .tax-box div {
            font-size: 13px;
            font-weight: bold;
            line-height: 16px;
            overflow: hidden;
            overflow-wrap: anywhere;
            white-space: normal;
            word-break: break-all;
        }

        /* -- Estimate Details -- */
        .estimate-details td {
            font-size: 13px;
            min-height: 16px;
            padding: 3px 6px;
            vertical-align: middle;
        }

        .highlight-value {
            background: #f0f0f0;
            display: inline;
            font-weight: bold;
            line-height: 1.4;
            padding: 2px 8px;
        }

        /* -- Items Table (Rate Card Matrix) -- */
        .items thead tr:first-child th {
            border-top: 2px solid #000;
        }

        .items th {
            background-color: #e8e8e8;
            border: 1px solid #000;
            font-size: 12px;
            font-weight: bold;
            letter-spacing: 0.3px;
            line-height: 14px;
            padding: 5px 3px;
            text-align: center;
            text-transform: uppercase;
            vertical-align: middle;
        }

        .items {
            table-layout: fixed;
        }

        .items td {
            border: 0;
            border-bottom: 1px solid #999;
            font-size: 12px;
            padding: 4px 3px;
            text-align: center;
            vertical-align: top;
        }

        .items tbody tr.alt-row td {
            background-color: #f7f7f7;
        }

        .items tbody tr:last-child td {
            border-bottom: 1px solid #000;
        }

        /* -- Notes -- */
        .notes {
            font-size: 12px;
            color: #555;
            margin-top: 20px;
            padding: 0 8px;
            text-align: left;
            page-break-inside: avoid;
        }

        .notes-label {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 6px;
        }

        /* -- Helpers -- */
        .bold {
            font-weight: bold;
        }

        .text-left {
            text-align: left !important;
        }

        .text-right {
            text-align: right !important;
        }

        .text-center {
            text-align: center !important;
        }
    </style>
</head>

<body>
@php
    // ── Company field extraction (mirrors office_invoice.blade.php) ──
    $companyName = $estimate->company?->name ?: '';
    $companyInitials = collect(preg_split('/\s+/', trim($companyName)))
        ->filter()
        ->map(fn ($word) => mb_substr($word, 0, 1))
        ->take(2)
        ->implode('');
    $companyTagline = $estimate->company?->tagline ?: '';
    $companyTopHeading = $estimate->company?->top_heading ?: 'Subject to Vapi Jurisdiction';
    $companyGstin = $estimate->company?->gstin ?: '';
    $companyEnrollmentNo = $estimate->company?->enrollment_no ?: '';
    $companyTaxIdentityLabel = $companyEnrollmentNo ? 'Enrollment No' : 'GSTIN';
    $companyTaxIdentityValue = $companyEnrollmentNo ?: $companyGstin;
    $panNo = $estimate->company?->pan_no ?: '';

    $companyPhone = $estimate->company?->address?->phone;
    $companyEmail = $estimate->company?->address?->email
        ?: ($estimate->company?->notification_email
            ?: \App\Models\CompanySetting::getSetting('notification_email', $estimate->company_id));
    $mobile = $companyPhone ?: '';
    $email = $companyEmail ?: '';

    // Clean the company address HTML — strip duplicate name/phone/email
    $displayCompanyAddress = preg_replace('/^\s*<h[1-6][^>]*>.*?<\/h[1-6]>\s*/is', '', (string) $company_address);
    if ($companyName) {
        $cleanNamePattern = '/^\s*(?:<[^>]+>)*\s*' . preg_quote($companyName, '/') . '\s*(?:<\/[^>]+>)*\s*(?:<br\s*\/?>)?/i';
        $displayCompanyAddress = preg_replace($cleanNamePattern, '', $displayCompanyAddress);
    }
    $displayCompanyAddress = preg_replace('/(?:<br\s*\/?>|\s)*E-?mail\s*:?\s*[^<\r\n]+/i', '', $displayCompanyAddress);
    $displayCompanyAddress = preg_replace('/(?:<br\s*\/?>|\s)*Mob(?:ile)?\.?\s*:?\s*[^<\r\n]+/i', '', $displayCompanyAddress);
    if ($companyPhone) {
        $displayCompanyAddress = preg_replace('/(?:<br\s*\/?>|\s)*' . preg_quote($companyPhone, '/') . '\s*/i', '', $displayCompanyAddress);
    }
    if ($companyEmail) {
        $displayCompanyAddress = preg_replace('/(?:<br\s*\/?>|\s)*' . preg_quote($companyEmail, '/') . '\s*/i', '', $displayCompanyAddress);
    }

    // ── Party (customer) field extraction ──
    $billingAddress = $estimate->customer?->billingAddress;
    $partyDisplayName = $billingAddress?->name ?: $estimate->customer?->display_name ?: $estimate->customer?->name;
    $partyAddressLines = collect();

    if ($billingAddress) {
        $cityState = collect([$billingAddress->city, $billingAddress->state])->filter()->implode(', ');
        $cityStateZip = collect([$cityState, $billingAddress->zip])->filter()->implode(' ');
        $phone = $billingAddress->phone ?: ($estimate->customer?->phone ?? null);

        $partyAddressLines = collect([
            $billingAddress->address_street_1,
            $billingAddress->address_street_2,
            $cityStateZip,
            $billingAddress->country?->name,
            $phone ? 'Phone: ' . $phone : null,
        ])->filter()->values();
    }

    if ($partyAddressLines->isEmpty()) {
        $partyAddressHtml = preg_replace('/<br\s*\/?>/i', "\n", (string) $billing_address);
        $partyAddressHtml = preg_replace('/<\/p>\s*<p[^>]*>/i', "\n", $partyAddressHtml);
        $partyAddressHtml = preg_replace('/<\/?p[^>]*>/i', "\n", $partyAddressHtml);
        $partyAddressText = html_entity_decode(strip_tags($partyAddressHtml), ENT_QUOTES, 'UTF-8');
        $partyAddressLines = collect(preg_split('/\r\n|\r|\n/', $partyAddressText))
            ->map(fn ($line) => trim(preg_replace('/\s+/', ' ', $line)))
            ->reject(fn ($line) => $partyDisplayName && strcasecmp($line, $partyDisplayName) === 0)
            ->filter()
            ->values();

        if (! $partyDisplayName && $partyAddressLines->isNotEmpty()) {
            $partyDisplayName = $partyAddressLines->shift();
        }
    }

    $partyGstin = $estimate->customer?->tax_id ?: '';

    // ── Auto-fit font sizing ──
    $getFontForWidth = function ($value, $widthLimit, $baseSize = 11.5, $minSize = 6.5) {
        $length = strlen((string) $value);
        if ($length === 0) {
            return '';
        }
        $estimatedWidth = $length * ($baseSize * 0.55);
        if ($estimatedWidth > $widthLimit) {
            $shrunkSize = ($widthLimit / $length) / 0.55;
            return 'font-size: ' . number_format(max($minSize, min($baseSize, $shrunkSize)), 1) . 'px;';
        }
        return '';
    };

    $partyDisplayNameStyle = $getFontForWidth($partyDisplayName, 330, 11.5, 6.5);
    $partyGstinStyle = $getFontForWidth($partyGstin, 170, 16, 6.5);
    $companyNameStyle = $getFontForWidth($companyName, 430, 27, 10);
@endphp

    <div class="jurisdiction">{{ $companyTopHeading }}</div>
    <div class="estimate-shell">
        <table class="master">
            {{-- ── Brand Row: Logo + Company Name/Tagline/Address/Contact ── --}}
            <tr>
                <td colspan="2">
                    <table class="brand-row">
                        <tr>
                            <td class="logo-cell">
                                @if ($logo)
                                    <img class="company-logo" src="{{ \App\Support\Pdf\ImageUtils::toBase64Src($logo) }}" alt="Company Logo">
                                @else
                                    <div class="brand-fallback">{{ $companyInitials }}</div>
                                @endif
                            </td>
                            <td class="company-cell">
                                <div class="company-name" style="{{ $companyNameStyle }}">{{ $companyName }}</div>
                                <div class="company-tagline">{{ $companyTagline }}</div>
                                <div class="company-address">{!! $displayCompanyAddress !!}</div>
                                <div class="company-contact">
                                    @if ($mobile)Mob. {{ $mobile }}@endif
                                    @if ($email)@if ($mobile)&nbsp;|&nbsp;@endif E-mail : {{ $email }}@endif
                                    @if ($panNo)@if ($mobile || $email)&nbsp;|&nbsp;@endif PAN : {{ $panNo }}@endif
                                    @if ($companyTaxIdentityValue)@if ($mobile || $email || $panNo)&nbsp;|&nbsp;@endif {{ $companyTaxIdentityLabel }} : {{ $companyTaxIdentityValue }}@endif
                                </div>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>

            {{-- ── Two-Column: Party (left) + Estimate Details (right) ── --}}
            <tr>
                <td class="left-zone party-box">
                    <table class="party-head">
                        <tr>
                            <td><b>Party Name & Address :</b></td>
                        </tr>
                    </table>
                    <div class="party-address-lines">
                        <div class="party-display-name" style="{{ $partyDisplayNameStyle }}">{{ $partyDisplayName }}</div>
                        {!! nl2br(e($partyAddressLines->implode("\n"))) ?: "\u{00A0}" !!}
                    </div>
                    @if ($partyGstin)
                        <div class="party-gstin" style="{{ $partyGstinStyle }}"><b>GSTIN :</b> {{ $partyGstin }}</div>
                    @endif
                </td>
                <td class="right-zone">
                    <table class="estimate-details">
                        <tr>
                            <td><b>Estimate No.:</b> <span class="highlight-value">{{ $estimate->estimate_number }}</span></td>
                        </tr>
                        <tr>
                            <td><b>Date :</b> <span class="highlight-value">{{ $estimate->formattedEstimateDate }}</span></td>
                        </tr>
                        <tr>
                            <td><b>Expiry :</b> <span class="highlight-value">{{ $estimate->formattedExpiryDate }}</span></td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        {{-- ── Rate Card Matrix Table ── --}}
        <div style="position:relative; margin-top: 10px;">
            @include('app.pdf.estimate.partials.table')
        </div>

        {{-- ── Notes ── --}}
        @if ($notes)
            <div class="notes">
                <div class="notes-label">@lang('pdf_notes')</div>
                {!! $notes !!}
            </div>
        @endif
    </div>
</body>

</html>
