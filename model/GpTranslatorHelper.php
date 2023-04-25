<?php
trait GpTranslatorHelper {

    public function gp_order_number_to_order_id(string $gp_order_number): string {
        return str_replace('BC-', '', $gp_order_number);
    }

    public function order_id_to_gp_order_number(string $order_id): string {
        return 'BC-' . $order_id;
    }
}