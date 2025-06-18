<?php

namespace CloudAnalytics;

use PDO;
use PDOException;

class DatabaseManager
{
    private $pdo;
    private $dbPath;

    public function __construct($dbPath = 'data/documents.db')
    {
        $this->dbPath = $dbPath;
        $this->initializeDatabase();
    }

    private function initializeDatabase()
    {
        try {
            // Create directory if it doesn't exist
            $dir = dirname($this->dbPath);
            if (!file_exists($dir)) {
                mkdir($dir, 0755, true);
            }

            $this->pdo = new PDO('sqlite:' . $this->dbPath);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $this->createTables();
        } catch (PDOException $e) {
            throw new \Exception("Database connection failed: " . $e->getMessage());
        }
    }

    private function createTables()
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS documents (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                drive_file_id TEXT UNIQUE NOT NULL,
                filename TEXT NOT NULL,
                title TEXT,
                content TEXT,
                mime_type TEXT,
                file_size INTEGER,
                category TEXT,
                classification_confidence REAL,
                upload_date DATETIME DEFAULT CURRENT_TIMESTAMP,
                last_modified DATETIME DEFAULT CURRENT_TIMESTAMP,
                metadata TEXT
            );

            CREATE TABLE IF NOT EXISTS search_history (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                query TEXT NOT NULL,
                results_count INTEGER,
                search_time REAL,
                search_date DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS classification_tree (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                category TEXT NOT NULL,
                keywords TEXT NOT NULL,
                created_date DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE INDEX IF NOT EXISTS idx_documents_title ON documents(title);
            CREATE INDEX IF NOT EXISTS idx_documents_category ON documents(category);
            CREATE INDEX IF NOT EXISTS idx_documents_drive_file_id ON documents(drive_file_id);
        ";

        $this->pdo->exec($sql);
        
        // Insert default classification tree if empty
        $this->insertDefaultClassificationTree();
    }

    private function insertDefaultClassificationTree()
    {
        $count = $this->pdo->query("SELECT COUNT(*) FROM classification_tree")->fetchColumn();
        
        if ($count == 0) {
            $defaultCategories = [
                'Academic' => ['research', 'study', 'university', 'academic', 'thesis', 'paper', 'journal'],
                'Business' => ['business', 'company', 'corporate', 'finance', 'marketing', 'sales', 'profit'],
                'Technical' => ['technical', 'programming', 'software', 'development', 'code', 'algorithm', 'system'],
                'Legal' => ['legal', 'law', 'contract', 'agreement', 'court', 'justice', 'regulation'],
                'Medical' => ['medical', 'health', 'doctor', 'patient', 'treatment', 'diagnosis', 'medicine'],
                'General' => ['general', 'information', 'document', 'text', 'content']
            ];

            $stmt = $this->pdo->prepare("INSERT INTO classification_tree (category, keywords) VALUES (?, ?)");
            
            foreach ($defaultCategories as $category => $keywords) {
                $stmt->execute([$category, json_encode($keywords)]);
            }
        }
    }

    public function insertDocument($data)
    {
        $sql = "INSERT OR REPLACE INTO documents 
                (drive_file_id, filename, title, content, mime_type, file_size, category, classification_confidence, metadata) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $data['drive_file_id'],
            $data['filename'],
            $data['title'],
            $data['content'],
            $data['mime_type'],
            $data['file_size'],
            $data['category'] ?? null,
            $data['classification_confidence'] ?? null,
            json_encode($data['metadata'] ?? [])
        ]);
    }

    public function getDocuments($orderBy = 'title', $order = 'ASC', $limit = null, $offset = 0)
    {
        $allowedOrderBy = ['title', 'filename', 'upload_date', 'category', 'file_size'];
        $orderBy = in_array($orderBy, $allowedOrderBy) ? $orderBy : 'title';
        $order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';
        
        $sql = "SELECT * FROM documents ORDER BY {$orderBy} {$order}";
        
        if ($limit) {
            $sql .= " LIMIT {$limit} OFFSET {$offset}";
        }
        
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function searchDocuments($keywords, $searchInContent = true)
    {
        $keywords = is_array($keywords) ? $keywords : [$keywords];
        $conditions = [];
        $params = [];
        
        foreach ($keywords as $keyword) {
            $keyword = trim($keyword);
            if (empty($keyword)) continue;
            
            if ($searchInContent) {
                $conditions[] = "(title LIKE ? OR content LIKE ?)";
                $params[] = "%{$keyword}%";
                $params[] = "%{$keyword}%";
            } else {
                $conditions[] = "title LIKE ?";
                $params[] = "%{$keyword}%";
            }
        }
        
        if (empty($conditions)) {
            return [];
        }
        
        $sql = "SELECT * FROM documents WHERE " . implode(' OR ', $conditions) . " ORDER BY title";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDocumentsByCategory($category)
    {
        $sql = "SELECT * FROM documents WHERE category = ? ORDER BY title";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$category]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDocument($id)
    {
        $sql = "SELECT * FROM documents WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getDocumentByDriveId($driveFileId)
    {
        $sql = "SELECT * FROM documents WHERE drive_file_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$driveFileId]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function deleteDocument($id)
    {
        $sql = "DELETE FROM documents WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$id]);
    }

    public function getStatistics()
    {
        $stats = [];
        
        // Total documents
        $stats['total_documents'] = $this->pdo->query("SELECT COUNT(*) FROM documents")->fetchColumn();
        
        // Total size
        $stats['total_size'] = $this->pdo->query("SELECT SUM(file_size) FROM documents")->fetchColumn() ?: 0;
        
        // Documents by category
        $stmt = $this->pdo->query("SELECT category, COUNT(*) as count FROM documents GROUP BY category");
        $stats['by_category'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // Documents by type
        $stmt = $this->pdo->query("SELECT mime_type, COUNT(*) as count FROM documents GROUP BY mime_type");
        $stats['by_type'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        return $stats;
    }

    public function logSearch($query, $resultsCount, $searchTime)
    {
        $sql = "INSERT INTO search_history (query, results_count, search_time) VALUES (?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$query, $resultsCount, $searchTime]);
    }

    public function getSearchHistory($limit = 50)
    {
        $sql = "SELECT * FROM search_history ORDER BY search_date DESC LIMIT ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$limit]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getClassificationTree()
    {
        $stmt = $this->pdo->query("SELECT category, keywords FROM classification_tree");
        $tree = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $tree[$row['category']] = json_decode($row['keywords'], true);
        }
        
        return $tree;
    }

    public function updateClassificationTree($tree)
    {
        // Clear existing tree
        $this->pdo->exec("DELETE FROM classification_tree");
        
        // Insert new tree
        $stmt = $this->pdo->prepare("INSERT INTO classification_tree (category, keywords) VALUES (?, ?)");
        
        foreach ($tree as $category => $keywords) {
            $stmt->execute([$category, json_encode($keywords)]);
        }
        
        return true;
    }
}

