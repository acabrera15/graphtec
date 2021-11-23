<?php
class CreditCard {

    //private constants
    private const DEFAULT_GP_CARD_TYPE = 'VISA';
    // end private constants

    public ?string  $cvv = null;
    public ?string  $exp_month = null;
    public ?string  $exp_year = null;
    public ?string  $id = null;
    public bool     $is_default = true;
    public ?string  $name_on_card = null;
    public ?string  $number = null;
    public ?string  $type = self::DEFAULT_GP_CARD_TYPE;
}