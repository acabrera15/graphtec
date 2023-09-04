<?php
class BigCommerceApiCredentialsConfig {
    public ?string $access_token;
    public ?string $client_id;
    public ?string $client_secret;
    public ?string $store_id;

    public function v2_endpoint_root(): string {
        return 'https://api.bigcommerce.com/stores/' . $this->store_id . '/v2/';
    }

    public function v3_endpoint_root(): string {
        return 'https://api.bigcommerce.com/stores/' . $this->store_id . '/v3/';
    }
}