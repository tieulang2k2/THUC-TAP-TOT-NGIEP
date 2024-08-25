<?php
/*
Plugin Name: CF7 Google Sheets Integration
Description: Gửi thông tin từ Contact Form 7 lên Google Sheets
Version: 1.0
Author: Your Name
*/

// Autoload Composer dependencies
require_once __DIR__ . '/vendor/autoload.php';

// Hook vào action wpcf7_before_send_mail của Contact Form 7
add_action('wpcf7_before_send_mail', 'cf7_to_google_sheets');

function cf7_to_google_sheets($cf7) {
    // Lấy dữ liệu từ form submission
    $submission = WPCF7_Submission::get_instance();
    if ($submission) {
        $data = $submission->get_posted_data();

        // Đường dẫn đến tệp JSON chứa thông tin xác thực của bạn
        $client = new \Google_Client();
        $client->setAuthConfig(__DIR__ . '/credentials.json');
        $client->addScope(\Google_Service_Sheets::SPREADSHEETS);

        $service = new \Google_Service_Sheets($client);

        // ID của bảng tính Google Sheets
        $spreadsheetId = '1jSqh_1_sYuIscLo1Xlyo0CWIMgKB0zsMehUlzSqq5Lw';
        // Tên của bảng (sheet) trong Google Sheets
        $range = 'Sheet1!A2'; // Đã thay đổi để khớp với tên bảng của bạn

        // Dữ liệu cần gửi, bạn có thể thay đổi cấu trúc này dựa trên form của bạn
        $values = [
            [
                isset($data['your-name']) ? $data['your-name'] : '',
                isset($data['your-email']) ? $data['your-email'] : '',
                isset($data['your-subject']) ? $data['your-subject'] : '',
                isset($data['your-message']) ? $data['your-message'] : ''
            ]
        ];

        $body = new \Google_Service_Sheets_ValueRange([
            'values' => $values
        ]);

        $params = [
            'valueInputOption' => 'RAW'
        ];

        try {
            $result = $service->spreadsheets_values->append($spreadsheetId, $range, $body, $params);
        } catch (Exception $e) {
            error_log('Error appending data to Google Sheets: ' . $e->getMessage());
        }
    }
}
