<?php
class BigCommerceRestApiClient {

    use Logger;

    // private constants
    private const CURL_TIMEOUT = 30;
    private const VERBOSE_LOGGING = true;
    private const VERBOSE_STATUS_CODE_RESPONSES = [
        400,
        422,
        500
    ];
    // end private constants

    // private members
    private ApiCredentialsConfig    $config;
    private ?CurlHandle             $curl_handle = null;
    private RestApiResponse         $response;
    private string                  $resource_name;
    // end private members
    
    // public functions
    public function set_config(ApiCredentialsConfig $config): void {
        $this->config = $config;
        $this->set_up_curl_resource();
    }
    public function set_resource_name(string $resource_name): void {
        $this->resource_name = $resource_name;
        $this->set_up_curl_resource();
    }
    public function __construct(ApiCredentialsConfig $config, string $resource_name){
        $this->response = new RestApiResponse();
        $this->config = $config;
        $this->resource_name = $resource_name;
        $this->set_up_curl_resource();
    }

    public function __destruct() {
        curl_close($this->curl_handle);
    }

    public function delete(array $data): RestApiResponse {
        curl_setopt($this->curl_handle, CURLOPT_CUSTOMREQUEST, 'DELETE');
        return $this->execute($data);
    }

    public function get(array $data): RestApiResponse {
        $additional_data = !empty($data) ? '?' . http_build_query($data) : '';

        curl_setopt($this->curl_handle, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($this->curl_handle, CURLOPT_URL, $this->config->endpoint . $this->resource_name . $additional_data);

        $this->write_to_log($this->standard_log_file_name(), "GET $this->config->endpoint . $this->resource_name . $additional_data");

        return $this->execute($data);
    }

    public function post(array $data): RestApiResponse {
        curl_setopt($this->curl_handle, CURLOPT_POSTFIELDS, gzencode(json_encode($data)));
        curl_setopt($this->curl_handle, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($this->curl_handle, CURLOPT_HTTPHEADER, [
            'Content-Encoding: gzip',
            'Content-Type: application/json',
            'User-Agent: Silhouette (www.silhouetteamerica.com)',
            'X-Auth-Token: ' . $this->config->access_token
        ]);
        curl_setopt($this->curl_handle, CURLOPT_ENCODING, 'gzip');
        return $this->execute($data);
    }

    public function put(array $data): RestApiResponse {
        curl_setopt($this->curl_handle, CURLOPT_POSTFIELDS, gzencode(json_encode($data)));
        curl_setopt($this->curl_handle, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($this->curl_handle, CURLOPT_ENCODING, 'gzip');
        curl_setopt($this->curl_handle, CURLOPT_HTTPHEADER, [
            'Content-Encoding: gzip',
            'Content-Type: application/json',
            'User-Agent: Silhouette (www.silhouetteamerica.com)',
            'X-Auth-Token: ' . $this->config->access_token
        ]);
        return $this->execute($data);
    }
    // end public functions
    
    // private functions
    private function execute(array $data): RestApiResponse {
        $this->response->raw_response =  curl_exec($this->curl_handle);
        $this->response->status_code = curl_getinfo($this->curl_handle, CURLINFO_HTTP_CODE);
        $header_size = curl_getinfo($this->curl_handle, CURLINFO_HEADER_SIZE);
        $this->response->body = substr($this->response->raw_response, $header_size);


        if (
            self::VERBOSE_LOGGING
            || !in_array(
                $this->response->status_code,
                array_merge(RESTAPIResponse::SUCCESSFUL_RESPONSE_CODES, [RESTAPIResponse::RESPONSE_CODE_NOT_FOUND])
            )
        ):
            $this->write_to_log('class.' . get_class($this) . '.log', $this->response->status_code . " received\n\tBody: "
                . $this->response->body . "\n\tRequest Header: " . curl_getinfo($this->curl_handle, CURLINFO_HEADER_OUT));
            if (in_array($this->response->status_code, self::VERBOSE_STATUS_CODE_RESPONSES)):
                $this->write_to_log('class.' . get_class($this) . '.log', "Post Fields: "
                    . substr(json_encode($data), 0, 32768));
            endif;
        endif;

        return $this->response;
    }

    private function set_up_curl_resource(): void {
        $this->curl_handle = curl_init($this->config->endpoint . $this->resource_name);
        curl_setopt($this->curl_handle, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Content-Type: application/json',
            'User-Agent: Silhouette (www.silhouetteamerica.com)',
            'X-Auth-Token: ' . $this->config->access_token
        ]);
        curl_setopt($this->curl_handle, CURLOPT_HEADER, true);
        curl_setopt($this->curl_handle, CURLINFO_HEADER_OUT, true);
        curl_setopt($this->curl_handle, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($this->curl_handle, CURLOPT_TIMEOUT, self::CURL_TIMEOUT);
    }
    // end private functions
}