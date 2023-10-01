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
    private bool                        $verbose_logging;
    // end private members

    // public functions
    public function __construct(BigCommerceRestApiClient $bc_client, GpInterfaceClient $gp_client, bool $verbose_logging = false){
        $this->bc_client = $bc_client;
        $this->gp_client = $gp_client;
        $this->verbose_logging = $verbose_logging;
    }

    /**
     * @return void
     * @throws Exception
     */
    public function update_inventory(): void {
        $this->build_product_sku_map();
        if ($this->verbose_logging){
            $this->write_to_log(self::LOG, "Product SKU map: \n" . print_r($this->product_sku_map, true));
        }

        // unique SKUs
        $unique_skus = array_merge(array_keys($this->product_sku_map), array_keys($this->product_variant_sku_map));
        $inventory = $this->gp_client->query_inventory(GP_SITE_ID, $unique_skus);
        if ($this->verbose_logging){
            $this->write_to_log(self::LOG, "Results from GP: \n" . print_r($inventory, true));
        }
        while (!empty($inventory)){
            $batch = [];
            foreach ($inventory as $key => $item){
                echo "\t{$item->sku}...\n";
                if (!empty($this->product_sku_map[$item->sku])){
                    $batch[] = [
                        'id' => $this->product_sku_map[$item->sku],
                        'inventory_level' => $item->quantity,
                        'inventory_tracking' => 'product'
                    ];
                } elseif (!empty($this->product_variant_sku_map[$item->sku])){
                    $this->process_variant(
                        $this->product_variant_sku_map[$item->sku]['parent_id'],
                        $this->product_variant_sku_map[$item->sku]['id'],
                        $item->quantity
                    );
                } else {
                    echo "\tNo product ID found for SKU {$item->sku}\n";
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
        $variant_ids = [];
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
                            $variant_ids[$item['sku']] = $item['id'];
                        }
                    }
                }
            } else {
                $has_more_products = false;
            }
        }

        echo "Mapped " . count($this->product_sku_map) . " SKUs to BigCommerce IDs.\n";

        $this->build_product_variant_sku_map($variant_ids);
    }

    private function build_product_variant_sku_map(array $variant_ids): void {
        echo "Mapping product variants for inventory management...\n";
        foreach ($variant_ids as $sku => $id){
            $sku = trim($sku);
            $this->bc_client->set_resource_name('catalog/products/' . $id . '/variants');
            $response = $this->bc_client->get(['page' => 1]);
            if ($response->status_code === 200){
                $response_arr = json_decode($response->body, true);
                if (empty($response_arr['data'])){
                    echo "ERROR: No product variant data\n";
                    continue;
                }
                foreach ($response_arr['data'] as $variant){
                    $variant['sku'] = trim($variant['sku']);

                    $this->product_variant_sku_map[$variant['sku']] = [
                        'id' => $variant['id'],
                        'parent_id' => $id,
                        'parent_sku' => $sku,
                        'sku' => $variant['sku']
                    ];
                    echo "\tAdded variant SKU {$variant['sku']} with ID {$variant['id']} to the variants map\n";
                }
            } else {
                echo "ERROR: Received response code {$response->status_code} when querying variants for SKU {$sku}\n";
            }
        }
    }

    private function print_status_of_tracking(RestApiResponse $response, int $num_items): void {
        echo match ($response->status_code) {
            $response::RESPONSE_CODE_CONFLICT => "ERROR: Product was in conflict with another\n",
            $response::RESPONSE_ENTITY_TOO_LARGE => "ERROR: Request entity too large\n",
            $response::RESPONSE_CODE_MULTI_STATUS => "PARTIAL-SUCCESS: Some items updated, while others failed. \n\nFull response: {$response->body}\n",
            $response::RESPONSE_CODE_NOT_FOUND => "ERROR: Resource not found\n",
            $response::RESPONSE_CODE_OK => "SUCCESS: Updated inventory on {$num_items} items\n",
            $response::RESPONSE_CODE_UNPROCESSABLE_ENTITY => "ERROR: Unprocessable entity\n",
            default => "ERROR: Unknown status returned ({$response->status_code})\n",
        };

        // log anything that wasn't successful
        if ($response->status_code !== $response::RESPONSE_CODE_OK){
            $this->write_to_log(self::LOG, "Error updating inventory ({$response->status_code}): {$response->body}\n");
        }
    }

    private function process_batch(array &$batch): void {
        $this->bc_client->set_resource_name('catalog/products');
        $response = $this->bc_client->put($batch);
        $this->print_status_of_tracking($response, count($batch));
        $batch = [];
    }

    private function process_variant(int $parent_id, int $id, int $inventory_level): void {
        $this->bc_client->set_resource_name('catalog/products/' . $parent_id . '/variants/' . $id);
        $response = $this->bc_client->put([
            'inventory_level' => $inventory_level
        ]);
        $this->print_status_of_tracking($response, 1);
    }

    // end private functions
}