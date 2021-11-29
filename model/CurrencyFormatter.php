<?php
trait CurrencyFormatter {

    public function format_currency_value(float $amount, string $currency_code): float {
        $decimals = 2;
        $countries_arr = array(
            'JPY' => 0
        );
        if (isset($countries_arr[$currency_code])):
            $decimals = $countries_arr[$currency_code];
        endif;


        $amount *= pow(100, $decimals);
        $amount = ceil($amount);
        $amount /= pow(100, $decimals);

        return round($amount, $decimals);
    }

}