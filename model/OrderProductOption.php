<?php
class OrderProductOption {

    public int      $id;
    public int      $option_id;
    public string   $value;

    public function __construct(int $id, int $option_id, string $value){
        $this->id = $id;
        $this->option_id = $option_id;
        $this->value = $value;
    }
}