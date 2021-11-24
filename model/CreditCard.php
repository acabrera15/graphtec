<?php
class CreditCard {

    // public constants
    public const TYPE_AMEX = 'AMEX';
    public const TYPE_DISCOVER = 'DISCOVER';
    public const TYPE_MASTERCARD = 'MASTER';
    public const TYPE_VISA = 'VISA';
    // end public constants

    //private constants
    private const DEFAULT_CARD_TYPE = self::TYPE_VISA;
    // end private constants

    public ?string  $cvv = null;
    public ?string  $exp_month = null;
    public ?string  $exp_year = null;
    public ?string  $id = null;
    public bool     $is_default = true;
    public ?string  $name_on_card = null;
    public ?string  $number = null;
    public ?string  $type = self::DEFAULT_CARD_TYPE;
}