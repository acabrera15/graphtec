<?php
class RestApiResponse {

    // public constants
    public const RESPONSE_CODE_ACCEPTED = 202;
    public const RESPONSE_CODE_BAD_REQUEST = 400;
    public const RESPONSE_CODE_CONFLICT = 409;
    public const RESPONSE_ENTITY_TOO_LARGE = 413;
    public const RESPONSE_CODE_MULTI_STATUS = 207;
    public const RESPONSE_CODE_NO_CONTENT = 204;
    public const RESPONSE_CODE_NOT_FOUND = 404;
    public const RESPONSE_CODE_OK = 200;
    public const RESPONSE_CODE_RESOURCE_CREATED = 201;
    public const RESPONSE_CODE_UNPROCESSABLE_ENTITY = 422;
    public const SUCCESSFUL_RESPONSE_CODES = [
        self::RESPONSE_CODE_ACCEPTED,
        self::RESPONSE_CODE_NO_CONTENT,
        self::RESPONSE_CODE_OK,
        self::RESPONSE_CODE_RESOURCE_CREATED
    ];
    // end public constants


    public ?string $body = null;
    public ?string $raw_response = null;
    public ?int $status_code = null;
}