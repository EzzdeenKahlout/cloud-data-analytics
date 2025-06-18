<?php

namespace CloudAnalytics;

use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;

class GoogleDriveManager
{
    private $client;
    private $service;
    private $credentialsPath;
    private $tokenPath;

    public function __construct($credentialsPath = 'config/credentials.json')
    {
        $this->credentialsPath = $credentialsPath;
        $this->tokenPath = 'config/token.json';
        $this->initializeClient();
    }

    private function initializeClient()
    {
        $this->client = new Client();
        $this->client->setApplicationName('Cloud Analytics Service');
        $this->client->setScopes([Drive::DRIVE_FILE, Drive::DRIVE_READONLY]);
        $this->client->setAuthConfig($this->credentialsPath);
        $this->client->setAccessType('offline');
        $this->client->setPrompt('select_account consent');

        // Load previously authorized token from a file, if it exists.
        if (file_exists($this->tokenPath)) {
            $accessToken = json_decode(file_get_contents($this->tokenPath), true);
            $this->client->setAccessToken($accessToken);
        }

        // If there is no previous token or it's expired.
        if ($this->client->isAccessTokenExpired()) {
            // Refresh the token if possible, else fetch a new one.
            if ($this->client->getRefreshToken()) {
                $this->client->fetchAccessTokenWithRefreshToken($this->client->getRefreshToken());
            } else {
                // Request authorization from the user.
                $authUrl = $this->client->createAuthUrl();
                throw new \Exception("Authorization required. Please visit: " . $authUrl);
            }
            // Save the token to a file.
            if (!file_exists(dirname($this->tokenPath))) {
                mkdir(dirname($this->tokenPath), 0700, true);
            }
            file_put_contents($this->tokenPath, json_encode($this->client->getAccessToken()));
        }

        $this->service = new Drive($this->client);
    }

    public function getAuthUrl()
    {
        return $this->client->createAuthUrl();
    }

    public function handleAuthCode($authCode)
    {
        $accessToken = $this->client->fetchAccessTokenWithAuthCode($authCode);
        $this->client->setAccessToken($accessToken);

        // Check if there was an error.
        if (array_key_exists('error', $accessToken)) {
            throw new \Exception(join(', ', $accessToken));
        }

        // Save the token to a file.
        if (!file_exists(dirname($this->tokenPath))) {
            mkdir(dirname($this->tokenPath), 0700, true);
        }
        file_put_contents($this->tokenPath, json_encode($this->client->getAccessToken()));

        $this->service = new Drive($this->client);
        return true;
    }

    public function uploadFile($filePath, $fileName = null, $folderId = null)
    {
        if (!file_exists($filePath)) {
            throw new \Exception("File not found: " . $filePath);
        }

        $fileName = $fileName ?: basename($filePath);
        $mimeType = mime_content_type($filePath);

        $fileMetadata = new DriveFile([
            'name' => $fileName
        ]);

        if ($folderId) {
            $fileMetadata->setParents([$folderId]);
        }

        $content = file_get_contents($filePath);
        $file = $this->service->files->create($fileMetadata, [
            'data' => $content,
            'mimeType' => $mimeType,
            'uploadType' => 'multipart',
            'fields' => 'id,name,size,mimeType,createdTime'
        ]);

        return $file;
    }

    public function listFiles($query = null, $pageSize = 100)
    {
        $optParams = [
            'pageSize' => $pageSize,
            'fields' => 'nextPageToken, files(id, name, size, mimeType, createdTime, modifiedTime)'
        ];

        if ($query) {
            $optParams['q'] = $query;
        }

        $results = $this->service->files->listFiles($optParams);
        return $results->getFiles();
    }

    public function downloadFile($fileId, $savePath)
    {
        $response = $this->service->files->get($fileId, ['alt' => 'media']);
        $content = $response->getBody()->getContents();
        
        if (!file_exists(dirname($savePath))) {
            mkdir(dirname($savePath), 0755, true);
        }
        
        file_put_contents($savePath, $content);
        return $savePath;
    }

    public function getFileMetadata($fileId)
    {
        return $this->service->files->get($fileId, [
            'fields' => 'id, name, size, mimeType, createdTime, modifiedTime, description'
        ]);
    }

    public function searchFiles($query)
    {
        // Search for PDF and Word documents
        $searchQuery = "({$query}) and (mimeType='application/pdf' or mimeType='application/vnd.openxmlformats-officedocument.wordprocessingml.document' or mimeType='application/msword')";
        
        return $this->listFiles($searchQuery);
    }

    public function createFolder($name, $parentId = null)
    {
        $fileMetadata = new DriveFile([
            'name' => $name,
            'mimeType' => 'application/vnd.google-apps.folder'
        ]);

        if ($parentId) {
            $fileMetadata->setParents([$parentId]);
        }

        $folder = $this->service->files->create($fileMetadata, [
            'fields' => 'id,name'
        ]);

        return $folder;
    }

    public function deleteFile($fileId)
    {
        return $this->service->files->delete($fileId);
    }

    public function isAuthenticated()
    {
        return !$this->client->isAccessTokenExpired();
    }
}

