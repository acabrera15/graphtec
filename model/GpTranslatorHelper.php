<?php
const INSTRUMENTS_PREFIX = 'BC2-';
const MAIN_PREFIX = 'BC-';
trait GpTranslatorHelper {

    public function gp_order_number_to_order_id(string $gp_order_number, string $store_id): string {
        return str_replace($this->store_id_to_prefix($store_id), '', $gp_order_number);
    }

    public function order_id_to_gp_order_number(string $order_id, string $store_id): string {
        return $this->store_id_to_prefix($store_id) . $order_id;
    }

    public function store_id_to_prefix(string $store_id): string {
        return ($store_id === BIGCOMMERCE_STORE_ID_INSTRUMENTS ? INSTRUMENTS_PREFIX : MAIN_PREFIX);
    }
}