<?php

namespace Database\Migrations;

use Core\Migration;

class CreateKnowledgeBaseTables extends Migration
{
    public function up()
    {
        $sql = "CREATE TABLE IF NOT EXISTS kb_documents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            doc_id CHAR(36) NOT NULL UNIQUE,
            document_key VARCHAR(255) NOT NULL,
            title VARCHAR(255) NOT NULL,
            original_filename VARCHAR(255) NOT NULL,
            file_ext VARCHAR(20) NOT NULL,
            mime_type VARCHAR(120) NOT NULL,
            format ENUM('pdf', 'html', 'md', 'txt', 'docx', 'other') NOT NULL DEFAULT 'other',
            storage_key VARCHAR(500) NOT NULL,
            file_size BIGINT NOT NULL DEFAULT 0,
            content_hash CHAR(64) NOT NULL,
            version INT NOT NULL DEFAULT 1,
            language ENUM('zh', 'en', 'mixed', 'other') NOT NULL DEFAULT 'other',
            source_org VARCHAR(120) NOT NULL DEFAULT 'Unknown',
            source_org_confidence DECIMAL(5,4) NOT NULL DEFAULT 0.0000,
            library ENUM('rules', 'parent', 'age_content', 'mixed') NOT NULL DEFAULT 'mixed',
            library_confidence DECIMAL(5,4) NOT NULL DEFAULT 0.0000,
            audience ENUM('system', 'parent', 'child', 'teacher', 'mixed') NOT NULL DEFAULT 'mixed',
            audience_confidence DECIMAL(5,4) NOT NULL DEFAULT 0.0000,
            age_bands JSON NOT NULL,
            age_bands_confidence DECIMAL(5,4) NOT NULL DEFAULT 0.0000,
            safety_visibility ENUM('system_only', 'parent_only', 'retrieval_visible', 'mixed') NOT NULL DEFAULT 'mixed',
            topics JSON NOT NULL,
            tags JSON NOT NULL,
            summary TEXT NULL,
            error_message TEXT NULL,
            risk_level ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'low',
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            review_status ENUM('auto_accepted', 'needs_review', 'blocked') NOT NULL DEFAULT 'needs_review',
            parser_status ENUM('pending', 'completed', 'failed') NOT NULL DEFAULT 'pending',
            chunk_status ENUM('pending', 'completed', 'failed') NOT NULL DEFAULT 'pending',
            embedding_status ENUM('pending', 'completed', 'failed') NOT NULL DEFAULT 'pending',
            indexing_status ENUM('pending', 'completed', 'failed') NOT NULL DEFAULT 'pending',
            created_at VARCHAR(40) NOT NULL,
            updated_at VARCHAR(40) NOT NULL,
            last_indexed_at VARCHAR(40) NULL,
            INDEX idx_doc_id (doc_id),
            INDEX idx_document_key (document_key),
            INDEX idx_content_hash (content_hash),
            INDEX idx_library (library),
            INDEX idx_audience (audience),
            INDEX idx_enabled (enabled),
            INDEX idx_review_status (review_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $this->pdo->exec($sql);
        echo " Knowledge base documents table created successfully.\n";

        $sql = "CREATE TABLE IF NOT EXISTS kb_chunks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            chunk_id VARCHAR(80) NOT NULL UNIQUE,
            doc_id CHAR(36) NOT NULL,
            chunk_index INT NOT NULL,
            heading_path JSON NULL,
            content MEDIUMTEXT NOT NULL,
            char_count INT NOT NULL DEFAULT 0,
            token_count INT NOT NULL DEFAULT 0,
            age_bands JSON NOT NULL,
            audience ENUM('system', 'parent', 'child', 'teacher') NOT NULL DEFAULT 'child',
            visibility ENUM('system_only', 'parent_only', 'retrieval_visible', 'blocked') NOT NULL DEFAULT 'blocked',
            topics JSON NOT NULL,
            risk_level ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'low',
            retrieval_enabled TINYINT(1) NOT NULL DEFAULT 1,
            confidence DECIMAL(5,4) NOT NULL DEFAULT 0.0000,
            chunk_summary TEXT NULL,
            embedding_id VARCHAR(80) NULL,
            vector_id VARCHAR(80) NULL,
            created_at VARCHAR(40) NOT NULL,
            updated_at VARCHAR(40) NOT NULL,
            INDEX idx_chunk_id (chunk_id),
            INDEX idx_doc_id (doc_id),
            INDEX idx_visibility (visibility),
            INDEX idx_retrieval_enabled (retrieval_enabled),
            CONSTRAINT fk_kb_chunks_doc_id FOREIGN KEY (doc_id) REFERENCES kb_documents(doc_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $this->pdo->exec($sql);
        echo " Knowledge base chunks table created successfully.\n";
    }

    public function down()
    {
        $this->pdo->exec("DROP TABLE IF EXISTS kb_chunks");
        echo " Knowledge base chunks table dropped.\n";

        $this->pdo->exec("DROP TABLE IF EXISTS kb_documents");
        echo " Knowledge base documents table dropped.\n";
    }
}
