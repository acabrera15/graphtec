<?php
class InventoryUpdater {

    use Logger;

    // private constants
    private const BC_INVENTORY_TRACKING_VARIANT = 'variant';
    private const LOG = 'class.InventoryUpdater.log';
    private const NUM_PER_BATCH = 10;
    // end private constants

    // private members
    private BigCommerceRestApiClient    $bc_client;
    private GpInterfaceClient           $gp_client;
    private array                       $product_sku_map = [];
    private array                       $product_variant_sku_map = [];
    // end private members

    // public functions
    public function __construct(BigCommerceRestApiClient $bc_client, GpInterfaceClient $gp_client){
        $this->bc_client = $bc_client;
        $this->gp_client = $gp_client;
    }

    /**
     * @return void
     * @throws Exception
     */
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
                        if (empty($item['inventory_tracking']) || $item['inventory_tracking'] !== self::BC_INVENTORY_TRACKING_VARIANT){
                            $this->product_sku_map[$item['sku']] = $item['id'];
                        } else {

                            // we need to look up the variants
                            $this->product_variant_sku_map[$item['sku']] = [
                                'id' => $item['id'],
                                'variants' => []
                            ];

                        }
                    }
                }
            } else {
                $has_more_products = false;
            }
        }

        echo "Mapped " . count($this->product_sku_map) . " SKUs to BigCommerce IDs.\n";

        $this->build_product_variant_sku_map();
    }

    private function build_product_variant_sku_map(): void {
        echo "Mapping product variants for inventory management...\n";
        foreach ($this->product_variant_sku_map as $sku => $variant_data){
            $this->bc_client->set_resource_name('catalog/products/' . $variant_data['id'] . '/variants');
            $response = $this->bc_client->get(['page' => 1]);
            if ($response->status_code === 200){
                $response_arr = json_decode($response->body, true);
                if (empty($response_arr['data'])){
                    echo "ERROR: No product variant data\n";
                    continue;
                }
                foreach ($response_arr['data'] as $variant){
                    $this->product_variant_sku_map[$sku]['variants'][] = [
                        'id' => $variant['id'],
                        'sku' => $variant['sku']
                    ];
                    echo "\tAdded variant SKU {$variant['sku']} with ID {$variant['id']} to the variants map\n";
                }
            } else {
                echo "ERROR: Received response code {$response->status_code} when querying variants for SKU {$sku}\n";
            }
        }
    }

    private function process_batch(array &$batch): void {
        $response = $this->bc_client->put($batch);
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