<?php
// Loaded only by the isolated integration server, with native curl functions disabled.
// No fixture can fall through to a real network request.
foreach (["CURLOPT_URL", "CURLOPT_RETURNTRANSFER", "CURLOPT_CONNECTTIMEOUT", "CURLOPT_TIMEOUT", "CURLOPT_HTTPHEADER", "CURLINFO_HTTP_CODE"] as $index => $name) {
    if (!defined($name)) define($name, 1000 + $index);
}

class SkillProofMockCurl {
    public array $options = [];
    public array $response = [];
}

function curl_init($url = null) {
    $handle = new SkillProofMockCurl();
    if ($url !== null) $handle->options[CURLOPT_URL] = $url;
    return $handle;
}

function curl_setopt_array($handle, $options) {
    $handle->options = $options + $handle->options;
    return true;
}

function curl_exec($handle) {
    $url = $handle->options[CURLOPT_URL] ?? "";
    file_put_contents(getenv("SKILLPROOF_TEST_API_LOG"), $url . "\n", FILE_APPEND | LOCK_EX);
    $fixtures = json_decode(file_get_contents(getenv("SKILLPROOF_TEST_API_FIXTURES")), true, 512, JSON_THROW_ON_ERROR);
    if (!array_key_exists($url, $fixtures)) {
        throw new RuntimeException("Unexpected mocked API request: " . $url);
    }
    $handle->response = $fixtures[$url];
    if (!empty($handle->response["error"])) return false;
    return $handle->response["raw"] ?? json_encode($handle->response["data"] ?? null, JSON_THROW_ON_ERROR);
}

function curl_getinfo($handle, $option = null) {
    if ($option !== CURLINFO_HTTP_CODE) throw new RuntimeException("Unsupported mock curl_getinfo option");
    return $handle->response["status"] ?? 200;
}

function curl_error($handle) {
    return $handle->response["error"] ?? "";
}

function curl_close($handle) {}

// Disable PHP's alternate HTTP transport as an additional guard against live API calls.
stream_wrapper_unregister("https");
stream_wrapper_unregister("http");
