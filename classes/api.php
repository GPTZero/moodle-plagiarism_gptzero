<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Functions to communicate with GPTZero endpoints
 *
 * @package    plagiarism_gptzero
 * @copyright  2024 Tyler Vu <tyler@gptzero.me>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace plagiarism_gptzero;

class api {
    
    private $api_key;
    private $api_url = 'https://9cb3-2605-bc0-1208-24-24bc-cc80-d767-4192.ngrok-free.app';
    
    public function __construct() {
        $this->api_key = get_config('plagiarism_gptzero', 'gptzero_apikey');
    }

    public function submit_file($file, $params) {
        $file_content = $file->get_content();
        $file_name = $file->get_filename();
        $file_type = $file->get_mimetype();
    
        $boundary = "----CustomBoundary123456789";
        $payload = "--" . $boundary . "\r\n";
        $payload .= "Content-Disposition: form-data; name=\"file\"; filename=\"" . basename($file_name) . "\"\r\n";
        $payload .= "Content-Type: " . $file_type . "\r\n\r\n";
        $payload .= $file_content . "\r\n";

        foreach ($params as $key => $value) {
            $payload .= "--" . $boundary . "\r\n";
            $payload .= "Content-Disposition: form-data; name=\"" . $key . "\"\r\n\r\n";
            $payload .= $value . "\r\n";
        }
        $payload .= "--" . $boundary . "--\r\n";
    
        $curl = curl_init();
    
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->api_url . '/v3/moodle/submit',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                "Accept: application/json",
                "Content-Type: multipart/form-data; boundary=" . $boundary,
                "x-api-key: {$this->api_key}"
            ],
        ]);
    
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
    
        if ($err) {
            debugging("cURL Error #:" . $err, DEBUG_DEVELOPER);
        } else {
            debugging("Response received: " . $response, DEBUG_DEVELOPER);
        }
    
        return $response;
    }

    public function submit_text($text, $params) {
        $boundary = "----CustomBoundary123456789";
        $payload = "--" . $boundary . "\r\n";
        $payload .= "Content-Disposition: form-data; name=\"text\"\r\n\r\n";
        $payload .= $text . "\r\n";
    
        foreach ($params as $key => $value) {
            $payload .= "--" . $boundary . "\r\n";
            $payload .= "Content-Disposition: form-data; name=\"" . $key . "\"\r\n\r\n";
            $payload .= $value . "\r\n";
        }
        $payload .= "--" . $boundary . "--\r\n";
    
        $curl = curl_init();
    
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->api_url . '/v3/moodle/submit',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                "Accept: application/json",
                "Content-Type: multipart/form-data; boundary=" . $boundary,
                "x-api-key: {$this->api_key}"
            ],
        ]);
    
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
    
        if ($err) {
            debugging("cURL Error #:" . $err, DEBUG_DEVELOPER);
        } else {
            debugging("Response received: " . $response, DEBUG_DEVELOPER);
        }
    
        return $response;
    }    

    public function create_assignment($userName, $userEmail, $userId) {
        // Prepare the data for the POST request
        $data = json_encode([
            'userName' => $userName,
            'userEmail' => $userEmail,
            'userId' => $userId,
            'api' => $this->api_key
        ]);
    
        // Initialize cURL session
        $curl = curl_init();
    
        // Set cURL options
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->api_url . "/v3/moodle/deep-linking",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => [
                "Accept: application/json",
                "Content-Type: application/json",
                "x-api-key: {$this->api_key}"
            ],
        ]);

        // Execute cURL request
        $response = curl_exec($curl);
        $err = curl_error($curl);
    
        // Close cURL session
        curl_close($curl);
        
        // Handle response and errors
        if ($err) {
            debugging("cURL Error #:" . $err, DEBUG_DEVELOPER);
        } else {
            debugging("Assignment creation response: " . $response, DEBUG_DEVELOPER);
        }

        return $response;
    }
    
    public function has_gptzero_account($userEmail) {
        // Prepare the data for the POST request
        $data = json_encode([
            'userEmail' => $userEmail,
        ]);
    
        // Initialize cURL session
        $curl = curl_init();
    
        // Set cURL options
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->api_url . "/v3/moodle/launch",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => [
                "Accept: application/json",
                "Content-Type: application/json",
                "x-api-key: {$this->api_key}"
            ],
        ]);

        // Execute cURL request
        $response = curl_exec($curl);
        $err = curl_error($curl);
    
        // Close cURL session
        curl_close($curl);
        
        // Handle response and errors
        if ($err) {
            debugging("cURL Error #:" . $err, DEBUG_DEVELOPER);
        } else {
            debugging("GPTZero Account API Response: " . $response, DEBUG_DEVELOPER);
        }

        return $response;
    }
}
?>
