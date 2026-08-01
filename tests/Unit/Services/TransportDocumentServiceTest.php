<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\Document\TransportDocumentService;
use PHPUnit\Framework\TestCase;

class TransportDocumentServiceTest extends TestCase
{
private TransportDocumentService $service;

protected function setUp(): void
{
  parent::setUp();
  $this->service = new TransportDocumentService;
}

public function test_detects_transport_template(): void
{
  $this->assertTrue($this->service->isTransportTemplate('lorry_receipt'));
  $this->assertTrue($this->service->isTransportTemplate('lr_receipt'));
  $this->assertTrue($this->service->isTransportTemplate('office_invoice'));
  $this->assertFalse($this->service->isTransportTemplate('invoice'));
  $this->assertFalse($this->service->isTransportTemplate('estimate'));
}

public function test_adds_document_prefixes(): void
{
  $data = ['docket_no' => 'DOC-001'];
  $result = $this->service->addDocumentPrefixes($data, 'lorry_receipt');

  $this->assertArrayHasKey('docket_no', $result);
  $this->assertEquals('DOC-001', $result['docket_no']);
}

public function test_creates_transport_items(): void
{
  $items = [
      ['name' => 'Item 1', 'quantity' => 10, 'price' => 100],
      ['name' => 'Item 2', 'quantity' => 5, 'price' => 200],
  ];

  $result = $this->service->createItems($items);

  $this->assertCount(2, $result);
  $this->assertEquals(1000, $result[0]['amount']);
  $this->assertEquals(1000, $result[1]['amount']);
}

public function test_updates_transport_items(): void
{
  $existing = [
      ['id' => 1, 'name' => 'Item 1', 'quantity' => 10, 'price' => 100],
  ];

  $updates = [
      ['id' => 1, 'name' => 'Item 1 Updated', 'quantity' => 15, 'price' => 150],
  ];

  $result = $this->service->updateItems($existing, $updates);

  $this->assertEquals('Item 1 Updated', $result[0]['name']);
  $this->assertEquals(15, $result[0]['quantity']);
}

public function test_creates_transport_taxes(): void
{
  $data = [
      'basic_freight' => 1000,
      'hamali' => 100,
  ];

  $result = $this->service->createTaxes($data);

  $this->assertIsArray($result);
}

public function test_recalculates_profit_loss(): void
{
  $data = [
      'basic_freight' => 1000,
      'hamali' => 100,
      'other_charge' => 50,
      'net_amount' => 900,
  ];

  $result = $this->service->recalculateProfitLoss($data);

  $this->assertArrayHasKey('profit', $result);
  $this->assertArrayHasKey('loss', $result);
}
}