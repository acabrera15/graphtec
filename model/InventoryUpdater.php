<?php
class InventoryUpdater {

    use Logger;

    // private constants
    private const LOG = 'class.InventoryUpdater.log';
    private const NUM_PER_BATCH = 10;
    // end private constants

    // private members
    private BigCommerceRestApiClient    $bc_client;
    private GpInterfaceClient           $gp_client;
    private array                       $product_sku_map = [];
    // end private members

    // public functions
    public function __construct(BigCommerceRestApiClient $bc_client, GpInterfaceClient $gp_client){
        $this->bc_client = $bc_client;
        $this->gp_client = $gp_client;
    }

    public function update_inventory(): void {
        $this->build_product_sku_map();

        $inventory = $this->gp_client->query_inventory(GP_SITE_ID, array_keys($this->product_sku_map));
        while (!empty($inventory)){
            $batch = [];
            foreach ($inventory as $key => $item){
                if (!empty($this->product_sku_map[$item->sku])){
                    $batch[] = [
                        'id' => $this->product_sku_map[$item->sku],
                        'inventory_level' => $item->quantity,
                        'inventory_tracking' => 'product'
                    ];
                } else {
                    echo "No product ID found for SKU {$item->sku}\n";
                }
                unset($inventory[$key]);

                if (count($batch) >= self::NUM_PER_BATCH){
                    $this->process_batch($batch);
                }
            }
        }

        if (!empty($batch)){
            $this->process_batch($batch);
        }
    }
    // end public functions

    // private functions
    private function build_product_sku_map(): void {
        echo "Querying BigCommerce products to map GP SKUs to Big Commerce IDs...\n";
        $has_more_products = true;
        $page = 1;
        while ($has_more_products){
            $response = $this->bc_client->get(['page' => $page]);
            $response_arr = json_decode($response->body, true);
            if (!empty($response_arr['meta'])){
                if ($response_arr['meta']['pagination']['total_pages'] > $page){
                    $page++;
                } else {
                    $has_more_products = false;
                }

                foreach ($response_arr['data'] as $item){
                    if (!empty($item['sku'])){
                        $this->product_sku_map[$item['sku']] = $item['id'];
                    }
                }
            } else {
                $has_more_products = false;
            }
        }

        echo "Mapped " . count($this->product_sku_map) . " SKUs to BigCommerce IDs.\n";
    }

    private function process_batch(array &$batch): void {
        $response = $this->bc_client->put($batch);
        echo "{$response->status_code} - ";
        echo match ($response->status_code) {
            $response::RESPONSE_CODE_CONFLICT => "ERROR: Product was in conflict with another\n",
            $response::RESPONSE_ENTITY_TOO_LARGE => "ERROR: Request entity too large\n",
            $response::RESPONSE_CODE_MULTI_STATUS => "PARTIAL-SUCCESS: Some items updated, while others failed. \n\nFull response: {$response->body}\n",
            $response::RESPONSE_CODE_NOT_FOUND => "ERROR: Resource not found\n",
            $response::RESPONSE_CODE_OK => "SUCCESS: Updated inventory on " . count($batch) . " items\n",
            $response::RESPONSE_CODE_UNPROCESSABLE_ENTITY => "ERROR: Unprocessable entity\n",
            default => "ERROR: Unknown status returned\n",
        };
        $batch = [];

        // log anything that wasn't successful
        if ($response->status_code !== $response::RESPONSE_CODE_OK){
            $this->write_to_log(self::LOG, "Error updating inventory ({$response->status_code}): {$response->body}\n");
        }
    }

    // end private functions
}