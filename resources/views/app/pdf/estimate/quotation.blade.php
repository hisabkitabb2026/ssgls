






@include("app.pdf.partials.fonts") +


 + +

  <table width="100%">
      <tr>
          <td class="text-center">
              @if ($logo)
                  <img class="header-logo" style="height:50px" src="{{ \App\Support\Pdf\ImageUtils::toBase64Src($logo) }}" alt="Company Logo">
              @else
                  @if ($estimate->customer->company)
                      <h2 class="header-logo"> {{ $estimate->customer->company->name }} </h2>
                  @endif
              @endif
          </td>
      </tr>
  </table>
  <hr class="header-bottom-divider" />



  <div class="company-details-container">
      <div class="company-address-container company-address">
          {!! $company_address !!}
      </div>

      <div class="estimate-details-container">
          <table class="estimate-details-table">
              <tr>
                  <td class="attribute-label">Quotation #</td>
                  <td class="attribute-value"> &nbsp;{{ $estimate->estimate_number }}</td>
              </tr>
              <tr>
                  <td class="attribute-label">Date</td>
                  <td class="attribute-value"> &nbsp;{{ $estimate->formattedEstimateDate }}</td>
              </tr>
              <tr>
                  <td class="attribute-label">Valid Until</td>
                  <td class="attribute-value"> &nbsp;{{ $estimate->formattedExpiryDate }}</td>
              </tr>
          </table>
      </div>
      <div style="clear: both;"></div>
  </div>

  <div class="customer-address-container">
      @if ($billing_address !== '<br />')
          <div class="billing-address-container billing-address">
              @if ($billing_address)
                  <b>@lang('pdf_bill_to')</b> <br>
                  {!! $billing_address !!}
              @endif
          </div>
      @endif


      <div @if ($billing_address !== '<br />') class="shipping-address-container shipping-address" @else class="shipping-address-container--left shipping-address" style="padding-left:30px;" @endif>

          @if ($shipping_address)
              <b>@lang('pdf_ship_to') </b> <br>
              {!! $shipping_address !!}
          @endif
      </div>

      <div style="clear: both;"></div>
  </div>

  <!-- Quotation Rate Table -->
  <div style="position:relative">
      <table width="100%" class="quotation-table" cellspacing="0" border="0">
          <tr class="quotation-table-heading-row">
              <th width="15%" class="quotation-table-heading quotation-cell-station">Station</th>
              <th width="12%" class="quotation-table-heading">9 MT</th>
              <th width="12%" class="quotation-table-heading">10 MT</th>
              <th width="12%" class="quotation-table-heading">12 MT</th>
              <th width="12%" class="quotation-table-heading">15 MT</th>
              <th width="12%" class="quotation-table-heading">18 MT</th>
              <th width="12%" class="quotation-table-heading">24 MT</th>
              <th width="13%" class="quotation-table-heading">30 MT</th>
          </tr>
          @if(!empty($quotation_rates) && is_array($quotation_rates))
              @foreach ($quotation_rates as $row)
                  <tr class="quotation-row">
                      <td class="quotation-cell quotation-cell-station">{{ $row['station'] ?? '' }}</td>
                      <td class="quotation-cell">{{ $row['9mt'] ?? '' }}</td>
                      <td class="quotation-cell">{{ $row['10mt'] ?? '' }}</td>
                      <td class="quotation-cell">{{ $row['12mt'] ?? '' }}</td>
                      <td class="quotation-cell">{{ $row['15mt'] ?? '' }}</td>
                      <td class="quotation-cell">{{ $row['18mt'] ?? '' }}</td>
                      <td class="quotation-cell">{{ $row['24mt'] ?? '' }}</td>
                      <td class="quotation-cell">{{ $row['30mt'] ?? '' }}</td>
                  </tr>
              @endforeach
          @else
              <tr class="quotation-row">
                  <td colspan="8" class="quotation-cell" style="text-align: center; color: #999;">
                      No quotation rates provided
                  </td>
              </tr>
          @endif
      </table>
  </div>

  <!-- Notes Section -->
  <div class="notes">
      @if ($notes)
          <div class="notes-label">
              @lang('pdf_notes')
          </div>

          {!! $notes !!}
      @endif
  </div>

 + +