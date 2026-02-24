<?php
// google_drive_upload.php
// Uploads an image to Google Drive and returns a direct link
// Requirements: composer require google/apiclient
// Place your OAuth2 credentials as credentials.json in the same directory

require_once __DIR__ . '/vendor/autoload.php';

function uploadToGoogleDrive($filePath, $fileName = null) {
    $client = new Google_Client();
    $client->setAuthConfig(__DIR__ . '/credentials.json');
    $client->addScope(Google_Service_Drive::DRIVE);
    $client->setAccessType('offline');

    $service = new Google_Service_Drive($client);

    $fileMetadata = new Google_Service_Drive_DriveFile([
        'name' => $fileName ?: basename($filePath),
        'parents' => ["root"]
    ]);

    $content = file_get_contents($filePath);
    $file = $service->files->create($fileMetadata, [
        'data' => $content,
        'mimeType' => mime_content_type($filePath),
        'uploadType' => 'multipart',
        'fields' => 'id'
    ]);

    // Make file public
    $permission = new Google_Service_Drive_Permission([
        'type' => 'anyone',
        'role' => 'reader',
    ]);
    $service->permissions->create($file->id, $permission);

    // Get direct link
    $directLink = "https://drive.google.com/uc?export=view&id=" . $file->id;
    return $directLink;
}

// Example usage:
// $link = uploadToGoogleDrive('uploads/example.jpg');
// echo $link;

?>
