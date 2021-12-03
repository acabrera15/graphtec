<?php
trait GpTranslatorHelper {
    public function order_id_to_gp_order_number(string $order_id): string {
        return 'BC-' . $order_id;
    }
}