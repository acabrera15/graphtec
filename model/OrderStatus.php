<?php
class OrderStatus {
    public string $order_id;
    public string $status;
    /**
     * @var OrderShipTracking[]
     */
    public array $tracking;
}