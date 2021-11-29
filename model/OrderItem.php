<?php
class OrderItem {
    public int      $backorder_quantity = 0;
    public ?string  $sku = null;
    public int      $quantity = 1;
    public float    $unit_price = 0.00;

    public function extended_price(): float { return $this->unit_price * $this->quantity; }
}