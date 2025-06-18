<?php

namespace CloudAnalytics;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class CloudAnalyticsService
{
    private $driveManager;
    private $documentProcessor;
    private $databaseManager;
    private $logger;

    public function __construct()
    {
        $this->driveManager = new GoogleDriveManager();
        $this->documentProcessor = new DocumentProcessor();
        $this->databaseManager = new DatabaseManager();
        
        // Setup logger
        $this->logger = new Logger('CloudAnalytics');
        $this->logger->pushHandler(new StreamHandler('logs/app.log', Logger::INFO));
    }

    public function isAuthenticated()
    {
        return $this->driveManager->isAuthenticated();
    }

    public function getAuthUrl()
    {
        return $this->driveManager->getAuthUrl();
    }

    public function handleAuthCode($authCode)
    {
        return $this->driveManager->handleAuthCode($authCode);
    }

    public function uploadDocument($filePath, $fileName = null)
    {
        try {
            $startTime = microtime(true);
            
            // Upload to Google Drive
            $driveFile = $this->driveManager->uploadFile($filePath, $fileName);
            
            // Process document
            $documentData = $this->documentProcessor->extractTextFromFile($filePath);
            
            // Classify document
            $classificationTree = $this->databaseManager->getClassificationTree();
            $classification = $this->documentProcessor->classifyDocument(
                $documentData['content'], 
                $classificationTree
            );
            
            // Store in database
            $dbData = [
                'drive_file_id' => $driveFile->getId(),
                'filename' => $driveFile->getName(),
                'title' => $documentData['title'],
                'content' => $documentData['content'],
                'mime_type' => $driveFile->getMimeType(),
                'file_size' => $driveFile->getSize(),
                'category' => $classification['category'],
                'classification_confidence' => $classification['confidence'],
                'metadata' => array_merge($documentData['metadata'], [
                    'drive_created_time' => $driveFile->getCreatedTime()
                ])
            ];
            
            $this->databaseManager->insertDocument($dbData);
            
            $processingTime = microtime(true) - $startTime;
            $this->logger->info("Document uploaded and processed", [
                'file_id' => $driveFile->getId(),
                'filename' => $driveFile->getName(),
                'processing_time' => $processingTime
            ]);
            
            return [
                'success' => true,
                'file_id' => $driveFile->getId(),
                'filename' => $driveFile->getName(),
                'title' => $documentData['title'],
                'category' => $classification['category'],
                'processing_time' => $processingTime
            ];
            
        } catch (\Exception $e) {
            $this->logger->error("Error uploading document", [
                'error' => $e->getMessage(),
                'file_path' => $filePath
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function syncFromDrive($query = null)
    {
        try {
            $startTime = microtime(true);
            $syncedCount = 0;
            
            // Get files from Drive
            $files = $this->driveManager->listFiles($query);
            
            foreach ($files as $file) {
                // Check if file is already in database
                $existingDoc = $this->databaseManager->getDocumentByDriveId($file->getId());
                
                if (!$existingDoc && $this->documentProcessor->isSupported($file->getMimeType())) {
                    // Download and process file
                    $tempPath = 'uploads/temp_' . $file->getId() . '_' . $file->getName();
                    $this->driveManager->downloadFile($file->getId(), $tempPath);
                    
                    // Process document
                    $documentData = $this->documentProcessor->extractTextFromFile($tempPath);
                    
                    // Classify document
                    $classificationTree = $this->databaseManager->getClassificationTree();
                    $classification = $this->documentProcessor->classifyDocument(
                        $documentData['content'], 
                        $classificationTree
                    );
                    
                    // Store in database
                    $dbData = [
                        'drive_file_id' => $file->getId(),
                        'filename' => $file->getName(),
                        'title' => $documentData['title'],
                        'content' => $documentData['content'],
                        'mime_type' => $file->getMimeType(),
                        'file_size' => $file->getSize(),
                        'category' => $classification['category'],
                        'classification_confidence' => $classification['confidence'],
                        'metadata' => array_merge($documentData['metadata'], [
                            'drive_created_time' => $file->getCreatedTime(),
                            'drive_modified_time' => $file->getModifiedTime()
                        ])
                    ];
                    
                    $this->databaseManager->insertDocument($dbData);
                    
                    // Clean up temp file
                    unlink($tempPath);
                    $syncedCount++;
                }
            }
            
            $syncTime = microtime(true) - $startTime;
            $this->logger->info("Drive sync completed", [
                'synced_count' => $syncedCount,
                'sync_time' => $syncTime
            ]);
            
            return [
                'success' => true,
                'synced_count' => $syncedCount,
                'sync_time' => $syncTime
            ];
            
        } catch (\Exception $e) {
            $this->logger->error("Error syncing from Drive", [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function searchDocuments($keywords, $searchInContent = true)
    {
        try {
            $startTime = microtime(true);
            
            $results = $this->databaseManager->searchDocuments($keywords, $searchInContent);
            
            // Highlight search terms in results
            foreach ($results as &$result) {
                $result['highlighted_content'] = $this->documentProcessor->highlightText(
                    substr($result['content'], 0, 500) . '...', 
                    $keywords
                );
                $result['highlighted_title'] = $this->documentProcessor->highlightText(
                    $result['title'], 
                    $keywords
                );
            }
            
            $searchTime = microtime(true) - $startTime;
            
            // Log search
            $this->databaseManager->logSearch(
                is_array($keywords) ? implode(', ', $keywords) : $keywords,
                count($results),
                $searchTime
            );
            
            $this->logger->info("Search completed", [
                'keywords' => $keywords,
                'results_count' => count($results),
                'search_time' => $searchTime
            ]);
            
            return [
                'success' => true,
                'results' => $results,
                'count' => count($results),
                'search_time' => $searchTime,
                'keywords' => $keywords
            ];
            
        } catch (\Exception $e) {
            $this->logger->error("Error searching documents", [
                'error' => $e->getMessage(),
                'keywords' => $keywords
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function sortDocuments($sortBy = 'title', $order = 'ASC', $limit = null, $offset = 0)
    {
        try {
            $startTime = microtime(true);
            
            $documents = $this->databaseManager->getDocuments($sortBy, $order, $limit, $offset);
            
            $sortTime = microtime(true) - $startTime;
            
            $this->logger->info("Documents sorted", [
                'sort_by' => $sortBy,
                'order' => $order,
                'count' => count($documents),
                'sort_time' => $sortTime
            ]);
            
            return [
                'success' => true,
                'documents' => $documents,
                'count' => count($documents),
                'sort_time' => $sortTime,
                'sort_by' => $sortBy,
                'order' => $order
            ];
            
        } catch (\Exception $e) {
            $this->logger->error("Error sorting documents", [
                'error' => $e->getMessage(),
                'sort_by' => $sortBy
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function classifyDocuments($forceReclassify = false)
    {
        try {
            $startTime = microtime(true);
            $classifiedCount = 0;
            
            $classificationTree = $this->databaseManager->getClassificationTree();
            
            // Get documents that need classification
            $condition = $forceReclassify ? "" : "WHERE category IS NULL OR category = ''";
            $documents = $this->databaseManager->getDocuments();
            
            foreach ($documents as $doc) {
                if (!$forceReclassify && !empty($doc['category'])) {
                    continue;
                }
                
                $classification = $this->documentProcessor->classifyDocument(
                    $doc['content'], 
                    $classificationTree
                );
                
                // Update document with classification
                $updateData = [
                    'drive_file_id' => $doc['drive_file_id'],
                    'filename' => $doc['filename'],
                    'title' => $doc['title'],
                    'content' => $doc['content'],
                    'mime_type' => $doc['mime_type'],
                    'file_size' => $doc['file_size'],
                    'category' => $classification['category'],
                    'classification_confidence' => $classification['confidence'],
                    'metadata' => json_decode($doc['metadata'], true)
                ];
                
                $this->databaseManager->insertDocument($updateData);
                $classifiedCount++;
            }
            
            $classificationTime = microtime(true) - $startTime;
            
            $this->logger->info("Documents classified", [
                'classified_count' => $classifiedCount,
                'classification_time' => $classificationTime
            ]);
            
            return [
                'success' => true,
                'classified_count' => $classifiedCount,
                'classification_time' => $classificationTime
            ];
            
        } catch (\Exception $e) {
            $this->logger->error("Error classifying documents", [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function getStatistics()
    {
        try {
            $stats = $this->databaseManager->getStatistics();
            $searchHistory = $this->databaseManager->getSearchHistory(10);
            
            return [
                'success' => true,
                'statistics' => $stats,
                'recent_searches' => $searchHistory
            ];
            
        } catch (\Exception $e) {
            $this->logger->error("Error getting statistics", [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function getDocument($id)
    {
        try {
            $document = $this->databaseManager->getDocument($id);
            
            if (!$document) {
                return [
                    'success' => false,
                    'error' => 'Document not found'
                ];
            }
            
            return [
                'success' => true,
                'document' => $document
            ];
            
        } catch (\Exception $e) {
            $this->logger->error("Error getting document", [
                'error' => $e->getMessage(),
                'document_id' => $id
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function getClassificationTree()
    {
        try {
            $tree = $this->databaseManager->getClassificationTree();
            
            return [
                'success' => true,
                'classification_tree' => $tree
            ];
            
        } catch (\Exception $e) {
            $this->logger->error("Error getting classification tree", [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function updateClassificationTree($tree)
    {
        try {
            $this->databaseManager->updateClassificationTree($tree);
            
            $this->logger->info("Classification tree updated", [
                'categories' => array_keys($tree)
            ]);
            
            return [
                'success' => true,
                'message' => 'Classification tree updated successfully'
            ];
            
        } catch (\Exception $e) {
            $this->logger->error("Error updating classification tree", [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

