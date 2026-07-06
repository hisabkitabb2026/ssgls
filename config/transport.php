<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Transport Module Configuration
    |--------------------------------------------------------------------------
    |
    | This config controls the transport/logistics features added on top of
    | InvoiceShelf. All fields are additive — setting any flag to false
    | simply hides that field from the UI without affecting the database.
    |
    | When InvoiceShelf updates, this file is untouched and all features
    | continue to work. New fields can be added here as needed.
    |
    */

    'enabled' => env('TRANSPORT_MODULE', true),

    /*
    |--------------------------------------------------------------------------
    | Invoice Fields
    |--------------------------------------------------------------------------
    |
    | Control which extra fields appear on the invoice create/edit form.
    | When true, the field is shown. When false, it's hidden.
    | The database columns always exist — this only controls UI visibility.
    |
    */

    'invoice_fields' => [
        'consignee_customer_id' => true,   // Show consignee selector
        'from_to_fields' => true,         // Show From/To custom fields
        'gst_tax_through' => true,         // Show GST Tax Through dropdown
        'amount_debit_credit' => true,     // Show debit/credit amount fields
        'lorry_documents' => true,        // Show document uploads (Aadhar, PAN, RC)
    ],

    /*
    |--------------------------------------------------------------------------
    | Company Fields
    |--------------------------------------------------------------------------
    |
    | Extra company fields shown in company settings and on PDF templates.
    |
    */

    'company_fields' => [
        'top_heading' => true,            // Company top heading text
        'billing_branch' => true,          // Branch name
        'enrollment_no' => true,          // Enrollment number
        'document_identity' => true,       // Document identity (GST/PAN)
    ],

    /*
    |--------------------------------------------------------------------------
    | Customer Fields
    |--------------------------------------------------------------------------
    |
    | Extra customer fields for transport/logistics.
    |
    */

    'customer_fields' => [
        'type' => true,                   // Customer type (consignor/consignee)
        'bank_account_no' => true,        // Bank account number
    ],

    /*
    |--------------------------------------------------------------------------
    | PDF Templates
    |--------------------------------------------------------------------------
    |
    | Register custom PDF templates. These blade files must exist in
    | resources/views/app/pdf/invoice/ directory. The template auto-
    | discovery in InvoiceShelf will pick them up automatically.
    |
    | Set to false to hide a template from the template selector.
    |
    */

    'pdf_templates' => [
        'office_invoice' => true,         // SSGLS transport bill format
        'lr_receipt' => true,              // LR Receipt
        'lorry_receipt' => true,           // Lorry Receipt
    ],

];
