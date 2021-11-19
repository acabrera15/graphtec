<?php
trait Logger {
    protected function write_to_log(string $log_file_name, string $log_data): false|int {
        return file_put_contents(
            dirname(__FILE__) . '/../logs/' . $log_file_name,
            date('Y-m-d H:i:s') . " - {$log_data}\n",
            FILE_APPEND
        );
    }
}