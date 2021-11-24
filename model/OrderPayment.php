<?php
class OrderPayment {
    public float        $amount = 0.00;
    public CreditCard   $card;
    public ?string      $transaction_id = null;
}