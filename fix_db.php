<?php

// Create missing tables directly via SQLite so artisan can boot
$db = new PDO('sqlite:storage/app/database.sqlite');

$tables = [
    "CREATE TABLE IF NOT EXISTS recurring_invoices (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        company_id INTEGER UNSIGNED NOT NULL,
        currency_id INTEGER UNSIGNED DEFAULT 1,
        customer_id INTEGER UNSIGNED DEFAULT NULL,
        invoice_date DATETIME NOT NULL,
        due_date DATE DEFAULT NULL,
        invoice_number VARCHAR(255) NOT NULL,
        reference VARCHAR(255) DEFAULT NULL,
        status VARCHAR(100) NOT NULL DEFAULT 'DRAFT',
        paid_status VARCHAR(100) NOT NULL DEFAULT 'UNPAID',
        sub_total DOUBLE NOT NULL DEFAULT 0,
        discount DOUBLE NOT NULL DEFAULT 0,
        discount_type VARCHAR(100) NOT NULL DEFAULT 'fixed',
        discount_val DOUBLE NOT NULL DEFAULT 0,
        total DOUBLE NOT NULL DEFAULT 0,
        due_amount DOUBLE NOT NULL DEFAULT 0,
        tax_per_item TINYINT(1) NOT NULL DEFAULT 0,
        discount_per_item TINYINT(1) NOT NULL DEFAULT 0,
        notes TEXT DEFAULT NULL,
        private_notes TEXT DEFAULT NULL,
        base_sub_total DOUBLE DEFAULT 0,
        base_discount_val DOUBLE DEFAULT 0,
        base_total DOUBLE DEFAULT 0,
        base_due_amount DOUBLE DEFAULT 0,
        exchange_rate DOUBLE DEFAULT 1,
        created_at TIMESTAMP NULL DEFAULT NULL,
        updated_at TIMESTAMP NULL DEFAULT NULL,
        deleted_at TIMESTAMP NULL DEFAULT NULL
    )",
    "CREATE TABLE IF NOT EXISTS recurring_invoice_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        recurring_invoice_id INTEGER UNSIGNED NOT NULL,
        company_id INTEGER UNSIGNED NOT NULL,
        item_id INTEGER UNSIGNED DEFAULT NULL,
        name VARCHAR(255) DEFAULT NULL,
        description TEXT DEFAULT NULL,
        quantity DOUBLE DEFAULT 0,
        price DOUBLE DEFAULT 0,
        total DOUBLE DEFAULT 0,
        discount DOUBLE DEFAULT 0,
        discount_type VARCHAR(100) DEFAULT 'fixed',
        discount_val DOUBLE DEFAULT 0,
        tax_per_item TINYINT(1) DEFAULT 0,
        base_quantity DOUBLE DEFAULT 0,
        base_price DOUBLE DEFAULT 0,
        base_total DOUBLE DEFAULT 0,
        base_discount_val DOUBLE DEFAULT 0,
        created_at TIMESTAMP NULL DEFAULT NULL,
        updated_at TIMESTAMP NULL DEFAULT NULL
    )",
    'CREATE TABLE IF NOT EXISTS customers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        company_id INTEGER UNSIGNED NOT NULL,
        currency_id INTEGER UNSIGNED DEFAULT NULL,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) DEFAULT NULL,
        phone VARCHAR(255) DEFAULT NULL,
        website VARCHAR(255) DEFAULT NULL,
        contact_name VARCHAR(255) DEFAULT NULL,
        company_name VARCHAR(255) DEFAULT NULL,
        display_name VARCHAR(255) DEFAULT NULL,
        enable_portal TINYINT(1) DEFAULT 1,
        billing_address_id INTEGER UNSIGNED DEFAULT NULL,
        shipping_address_id INTEGER UNSIGNED DEFAULT NULL,
        created_at TIMESTAMP NULL DEFAULT NULL,
        updated_at TIMESTAMP NULL DEFAULT NULL,
        deleted_at TIMESTAMP NULL DEFAULT NULL
    )',
    'CREATE TABLE IF NOT EXISTS user_company (
        user_id INTEGER UNSIGNED NOT NULL,
        company_id INTEGER UNSIGNED NOT NULL,
        is_owner TINYINT(1) DEFAULT 0,
        PRIMARY KEY (user_id, company_id)
    )',
    'CREATE TABLE IF NOT EXISTS company_invitations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        company_id INTEGER UNSIGNED NOT NULL,
        user_id INTEGER UNSIGNED DEFAULT NULL,
        role_id INTEGER UNSIGNED DEFAULT NULL,
        token VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        created_at TIMESTAMP NULL DEFAULT NULL,
        updated_at TIMESTAMP NULL DEFAULT NULL
    )',
    'CREATE TABLE IF NOT EXISTS impersonation_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        impersonator_id INTEGER UNSIGNED NOT NULL,
        impersonated_id INTEGER UNSIGNED NOT NULL,
        created_at TIMESTAMP NULL DEFAULT NULL,
        updated_at TIMESTAMP NULL DEFAULT NULL
    )',
    'CREATE TABLE IF NOT EXISTS ai_conversations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        company_id INTEGER UNSIGNED NOT NULL,
        user_id INTEGER UNSIGNED NOT NULL,
        title VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP NULL DEFAULT NULL,
        updated_at TIMESTAMP NULL DEFAULT NULL
    )',
    'CREATE TABLE IF NOT EXISTS ai_messages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        conversation_id BIGINT UNSIGNED NOT NULL,
        role VARCHAR(50) NOT NULL,
        content TEXT NOT NULL,
        created_at TIMESTAMP NULL DEFAULT NULL,
        updated_at TIMESTAMP NULL DEFAULT NULL,
        FOREIGN KEY (conversation_id) REFERENCES ai_conversations(id) ON DELETE CASCADE
    )',
    "CREATE TABLE IF NOT EXISTS lorry_receipts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        company_id INTEGER UNSIGNED NOT NULL,
        challan_no VARCHAR(100) DEFAULT NULL,
        received_no_bilties TEXT DEFAULT NULL,
        consignor_name VARCHAR(255) DEFAULT NULL,
        consignee_name VARCHAR(255) DEFAULT NULL,
        billing_party VARCHAR(255) DEFAULT NULL,
        from_location VARCHAR(255) DEFAULT NULL,
        to_location VARCHAR(255) DEFAULT NULL,
        truck_no VARCHAR(100) DEFAULT NULL,
        advance_amount VARCHAR(100) DEFAULT NULL,
        advance_paid_date VARCHAR(50) DEFAULT NULL,
        net_amount_payable VARCHAR(100) DEFAULT NULL,
        balance_payment_date VARCHAR(50) DEFAULT NULL,
        bank_name VARCHAR(255) DEFAULT NULL,
        cheque_no VARCHAR(100) DEFAULT NULL,
        cash_cheque VARCHAR(50) DEFAULT 'Cash',
        remarks TEXT DEFAULT NULL,
        customer_id INTEGER UNSIGNED DEFAULT NULL,
        created_at TIMESTAMP NULL DEFAULT NULL,
        updated_at TIMESTAMP NULL DEFAULT NULL
    )",
    'CREATE TABLE IF NOT EXISTS sessions (
        id VARCHAR(255) NOT NULL PRIMARY KEY,
        user_id INTEGER UNSIGNED DEFAULT NULL,
        ip_address VARCHAR(45) DEFAULT NULL,
        user_agent TEXT DEFAULT NULL,
        payload LONGTEXT NOT NULL,
        last_activity INTEGER NOT NULL
    )',
];

foreach ($tables as $sql) {
    try {
        $db->exec($sql);
        echo 'OK: '.substr($sql, 0, 50)."...\n";
    } catch (Exception $e) {
        echo 'ERROR: '.$e->getMessage()."\n";
    }
}

// Also add missing columns to invoices
$alterations = [
    'ALTER TABLE invoices ADD COLUMN customer_id INTEGER UNSIGNED DEFAULT NULL',
    "ALTER TABLE invoices ADD COLUMN template_name VARCHAR(255) DEFAULT 'office_invoice'",
    'ALTER TABLE invoices ADD COLUMN consignee_customer_id INTEGER UNSIGNED DEFAULT NULL',
];

foreach ($alterations as $sql) {
    try {
        $db->exec($sql);
        echo 'OK: '.$sql."\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'duplicate column') !== false) {
            echo 'SKIP (exists): '.$sql."\n";
        } else {
            echo 'ERROR: '.$e->getMessage()."\n";
        }
    }
}

echo "\nDone! Now run php artisan migrate --force\n";
