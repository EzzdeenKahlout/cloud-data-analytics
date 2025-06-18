<?php

namespace CloudAnalytics;

use Smalot\PdfParser\Parser as PdfParser;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Settings;

class DocumentProcessor
{
    private $pdfParser;
    private $supportedTypes = [
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/msword'
    ];

    public function __construct()
    {
        $this->pdfParser = new PdfParser();
        Settings::setOutputEscapingEnabled(true);
    }

    public function extractTextFromFile($filePath)
    {
        $mimeType = mime_content_type($filePath);
        
        switch ($mimeType) {
            case 'application/pdf':
                return $this->extractTextFromPdf($filePath);
            case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
            case 'application/msword':
                return $this->extractTextFromWord($filePath);
            default:
                throw new \Exception("Unsupported file type: " . $mimeType);
        }
    }

    private function extractTextFromPdf($filePath)
    {
        try {
            $pdf = $this->pdfParser->parseFile($filePath);
            $text = $pdf->getText();
            
            // Extract title from PDF metadata or first line
            $details = $pdf->getDetails();
            $title = isset($details['Title']) ? $details['Title'] : $this->extractTitleFromText($text);
            
            return [
                'title' => $title,
                'content' => $text,
                'metadata' => $details
            ];
        } catch (\Exception $e) {
            throw new \Exception("Error parsing PDF: " . $e->getMessage());
        }
    }

    private function extractTextFromWord($filePath)
    {
        try {
            $phpWord = IOFactory::load($filePath);
            $text = '';
            $title = '';
            
            foreach ($phpWord->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    if (method_exists($element, 'getText')) {
                        $elementText = $element->getText();
                        if (empty($title) && !empty(trim($elementText))) {
                            $title = trim($elementText);
                        }
                        $text .= $elementText . "\n";
                    }
                }
            }
            
            // If no title found, extract from first line
            if (empty($title)) {
                $title = $this->extractTitleFromText($text);
            }
            
            return [
                'title' => $title,
                'content' => $text,
                'metadata' => [
                    'creator' => $phpWord->getDocInfo()->getCreator(),
                    'created' => $phpWord->getDocInfo()->getCreated(),
                    'modified' => $phpWord->getDocInfo()->getModified()
                ]
            ];
        } catch (\Exception $e) {
            throw new \Exception("Error parsing Word document: " . $e->getMessage());
        }
    }

    private function extractTitleFromText($text)
    {
        $lines = explode("\n", trim($text));
        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line) && strlen($line) > 3 && strlen($line) < 200) {
                return $line;
            }
        }
        return 'Untitled Document';
    }

    public function searchInText($text, $keywords)
    {
        $keywords = is_array($keywords) ? $keywords : [$keywords];
        $matches = [];
        $text = strtolower($text);
        
        foreach ($keywords as $keyword) {
            $keyword = strtolower(trim($keyword));
            if (empty($keyword)) continue;
            
            $positions = [];
            $offset = 0;
            
            while (($pos = strpos($text, $keyword, $offset)) !== false) {
                $positions[] = $pos;
                $offset = $pos + 1;
            }
            
            if (!empty($positions)) {
                $matches[$keyword] = $positions;
            }
        }
        
        return $matches;
    }

    public function highlightText($text, $keywords)
    {
        $keywords = is_array($keywords) ? $keywords : [$keywords];
        
        foreach ($keywords as $keyword) {
            $keyword = trim($keyword);
            if (empty($keyword)) continue;
            
            $text = preg_replace(
                '/(' . preg_quote($keyword, '/') . ')/i',
                '<mark class="highlight">$1</mark>',
                $text
            );
        }
        
        return $text;
    }

    public function classifyDocument($text, $classificationTree)
    {
        // Simple keyword-based classification
        $text = strtolower($text);
        $scores = [];
        
        foreach ($classificationTree as $category => $keywords) {
            $score = 0;
            foreach ($keywords as $keyword) {
                $keyword = strtolower($keyword);
                $count = substr_count($text, $keyword);
                $score += $count;
            }
            $scores[$category] = $score;
        }
        
        // Return the category with highest score
        arsort($scores);
        $topCategory = array_key_first($scores);
        
        return [
            'category' => $topCategory,
            'confidence' => $scores[$topCategory],
            'all_scores' => $scores
        ];
    }

    public function getSupportedTypes()
    {
        return $this->supportedTypes;
    }

    public function isSupported($mimeType)
    {
        return in_array($mimeType, $this->supportedTypes);
    }
}

