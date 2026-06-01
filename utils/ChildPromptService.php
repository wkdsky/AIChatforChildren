<?php

namespace Utils;

use Core\Database;
use DateTimeImmutable;
use PDO;

class ChildPromptService
{
    private PDO $pdo;
    private PromptTemplateService $promptTemplateService;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getInstance();
        $this->promptTemplateService = new PromptTemplateService($this->pdo);
    }

    public function ensureSchema(): void
    {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS child_prompt_profiles (
                id INT AUTO_INCREMENT PRIMARY KEY,
                child_id INT NOT NULL,
                parent_id INT NULL,
                source_age_band VARCHAR(16) NOT NULL DEFAULT '6_12',
                source_template_key VARCHAR(100) NOT NULL DEFAULT 'child_chat_age_6_12',
                default_prompt_content MEDIUMTEXT NULL,
                prompt_content MEDIUMTEXT NOT NULL,
                is_customized TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_child_prompt_child (child_id),
                INDEX idx_child_prompt_parent (parent_id),
                INDEX idx_child_prompt_age_band (source_age_band),
                CONSTRAINT fk_child_prompt_profiles_child FOREIGN KEY (child_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_child_prompt_profiles_parent FOREIGN KEY (parent_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        if (!$this->columnExists('child_prompt_profiles', 'parent_id')) {
            $this->pdo->exec("ALTER TABLE child_prompt_profiles ADD COLUMN parent_id INT NULL AFTER child_id");
        }

        if (!$this->columnExists('child_prompt_profiles', 'source_age_band')) {
            $this->pdo->exec("ALTER TABLE child_prompt_profiles ADD COLUMN source_age_band VARCHAR(16) NOT NULL DEFAULT '6_12' AFTER parent_id");
        }

        if (!$this->columnExists('child_prompt_profiles', 'source_template_key')) {
            $this->pdo->exec("ALTER TABLE child_prompt_profiles ADD COLUMN source_template_key VARCHAR(100) NOT NULL DEFAULT 'child_chat_age_6_12' AFTER source_age_band");
        }

        if (!$this->columnExists('child_prompt_profiles', 'default_prompt_content')) {
            $this->pdo->exec("ALTER TABLE child_prompt_profiles ADD COLUMN default_prompt_content MEDIUMTEXT NULL AFTER source_template_key");
            $this->pdo->exec("UPDATE child_prompt_profiles SET default_prompt_content = prompt_content WHERE default_prompt_content = '' OR default_prompt_content IS NULL");
        }

        if (!$this->columnExists('child_prompt_profiles', 'is_customized')) {
            $this->pdo->exec("ALTER TABLE child_prompt_profiles ADD COLUMN is_customized TINYINT(1) NOT NULL DEFAULT 0 AFTER prompt_content");
        }

        if (!$this->indexExists('child_prompt_profiles', 'uniq_child_prompt_child')) {
            $this->pdo->exec("ALTER TABLE child_prompt_profiles ADD UNIQUE KEY uniq_child_prompt_child (child_id)");
        }

        if (!$this->indexExists('child_prompt_profiles', 'idx_child_prompt_parent')) {
            $this->pdo->exec("ALTER TABLE child_prompt_profiles ADD INDEX idx_child_prompt_parent (parent_id)");
        }

        if (!$this->indexExists('child_prompt_profiles', 'idx_child_prompt_age_band')) {
            $this->pdo->exec("ALTER TABLE child_prompt_profiles ADD INDEX idx_child_prompt_age_band (source_age_band)");
        }
    }

    public function getPromptProfileByChildId(int $childId): ?array
    {
        $this->ensureSchema();

        $stmt = $this->pdo->prepare(
            "SELECT *
            FROM child_prompt_profiles
            WHERE child_id = :child_id
            LIMIT 1"
        );
        $stmt->execute(['child_id' => $childId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function getPromptContentForChild(array $childProfile, string $fallback = ''): string
    {
        if (!$this->isChildProfile($childProfile)) {
            return $fallback;
        }

        $profile = $this->getPromptProfileByChildId((int) $childProfile['id']);
        if (!$profile) {
            $profile = $this->initializePromptProfileForChild($childProfile);
        }

        $content = trim((string) ($profile['prompt_content'] ?? ''));
        if ($content !== '') {
            return $content;
        }

        return $fallback !== '' ? $fallback : $this->buildDefaultPromptContent($this->resolveAgeBand($childProfile));
    }

    public function initializePromptProfileForChild(array $childProfile): ?array
    {
        if (!$this->isChildProfile($childProfile)) {
            return null;
        }

        $existing = $this->getPromptProfileByChildId((int) $childProfile['id']);
        if ($existing) {
            return $existing;
        }

        $ageBand = $this->resolveAgeBand($childProfile);
        $templateKey = $this->ageTemplateKeyForBand($ageBand);
        $promptContent = $this->buildDefaultPromptContent($ageBand);

        $saved = $this->upsertPromptProfile(
            (int) $childProfile['id'],
            isset($childProfile['parent_id']) ? (int) $childProfile['parent_id'] : null,
            $promptContent,
            $promptContent,
            $ageBand,
            $templateKey,
            false
        );

        return $saved ? $this->getPromptProfileByChildId((int) $childProfile['id']) : null;
    }

    public function upsertPromptProfile(
        int $childId,
        ?int $parentId,
        string $defaultPromptContent,
        string $promptContent,
        string $sourceAgeBand,
        string $sourceTemplateKey,
        bool $isCustomized
    ): bool {
        $this->ensureSchema();

        $content = trim($promptContent);
        if ($childId <= 0 || $content === '') {
            return false;
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO child_prompt_profiles (
                child_id,
                parent_id,
                source_age_band,
                source_template_key,
                default_prompt_content,
                prompt_content,
                is_customized
            ) VALUES (
                :child_id,
                :parent_id,
                :source_age_band,
                :source_template_key,
                :default_prompt_content,
                :prompt_content,
                :is_customized
            )
            ON DUPLICATE KEY UPDATE
                parent_id = VALUES(parent_id),
                source_age_band = VALUES(source_age_band),
                source_template_key = VALUES(source_template_key),
                default_prompt_content = VALUES(default_prompt_content),
                prompt_content = VALUES(prompt_content),
                is_customized = VALUES(is_customized),
                updated_at = CURRENT_TIMESTAMP"
        );

        return $stmt->execute([
            'child_id' => $childId,
            'parent_id' => $parentId,
            'source_age_band' => $sourceAgeBand,
            'source_template_key' => $sourceTemplateKey,
            'default_prompt_content' => trim($defaultPromptContent),
            'prompt_content' => $content,
            'is_customized' => $isCustomized ? 1 : 0,
        ]);
    }

    public function getPromptEditorData(array $childProfile): ?array
    {
        if (!$this->isChildProfile($childProfile)) {
            return null;
        }

        $profile = $this->getPromptProfileByChildId((int) $childProfile['id']);
        if (!$profile) {
            $profile = $this->initializePromptProfileForChild($childProfile);
        }

        if (!$profile) {
            return null;
        }

        $sourceAgeBand = trim((string) ($profile['source_age_band'] ?? ''));
        if ($sourceAgeBand === '') {
            $sourceAgeBand = $this->resolveAgeBand($childProfile);
        }

        $defaultPromptContent = trim((string) ($profile['default_prompt_content'] ?? ''));
        if ($defaultPromptContent === '') {
            $defaultPromptContent = $this->buildDefaultPromptContent($sourceAgeBand);
        }

        $promptContent = trim((string) ($profile['prompt_content'] ?? ''));
        if ($promptContent === '') {
            $promptContent = $defaultPromptContent;
        }

        return [
            'child_id' => (int) ($profile['child_id'] ?? $childProfile['id'] ?? 0),
            'source_age_band' => $sourceAgeBand,
            'source_template_key' => (string) ($profile['source_template_key'] ?? $this->ageTemplateKeyForBand($sourceAgeBand)),
            'default_prompt_content' => $defaultPromptContent,
            'prompt_content' => $promptContent,
            'is_customized' => !empty($profile['is_customized']),
            'updated_at' => $profile['updated_at'] ?? null,
        ];
    }

    public function savePromptContentForChild(array $childProfile, string $promptContent): bool
    {
        if (!$this->isChildProfile($childProfile)) {
            return false;
        }

        $editorData = $this->getPromptEditorData($childProfile);
        if ($editorData === null) {
            return false;
        }

        $normalizedPromptContent = trim($promptContent);
        if ($normalizedPromptContent === '') {
            return false;
        }

        $defaultPromptContent = trim((string) ($editorData['default_prompt_content'] ?? ''));
        $isCustomized = $normalizedPromptContent !== $defaultPromptContent;

        return $this->upsertPromptProfile(
            (int) ($childProfile['id'] ?? 0),
            isset($childProfile['parent_id']) ? (int) $childProfile['parent_id'] : null,
            $defaultPromptContent,
            $normalizedPromptContent,
            (string) ($editorData['source_age_band'] ?? $this->resolveAgeBand($childProfile)),
            (string) ($editorData['source_template_key'] ?? $this->ageTemplateKeyForBand($this->resolveAgeBand($childProfile))),
            $isCustomized
        );
    }

    public function backfillMissingChildProfiles(): int
    {
        $this->ensureSchema();

        $stmt = $this->pdo->query(
            "SELECT
                u.id,
                u.parent_id,
                u.name,
                u.birth_date,
                u.role
            FROM users u
            LEFT JOIN child_prompt_profiles cpp
                ON cpp.child_id = u.id
            WHERE u.role = 'child'
                AND cpp.child_id IS NULL
            ORDER BY u.id ASC"
        );

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $created = 0;

        foreach ($rows as $row) {
            $profile = [
                'id' => (int) ($row['id'] ?? 0),
                'parent_id' => isset($row['parent_id']) ? (int) $row['parent_id'] : null,
                'name' => trim((string) ($row['name'] ?? '')),
                'birth_date' => $row['birth_date'] ?? null,
                'role' => 'child',
            ];

            if ($this->initializePromptProfileForChild($profile)) {
                $created++;
            }
        }

        return $created;
    }

    private function buildDefaultPromptContent(string $ageBand): string
    {
        $templateKey = $this->ageTemplateKeyForBand($ageBand);
        $agePrompt = trim($this->promptTemplateService->getTemplateContent(
            $templateKey,
            PromptTemplateService::getDefaultContent($templateKey)
        ));

        return trim(implode("\n", array_filter([
            'Private child-specific prompt for this account:',
            '- This prompt belongs only to {child_name} and is the private personalization layer for this child account.',
            '- Keep answers aligned with the authoritative child profile: {child_profile}.',
            '- Tailor tone, pacing, vocabulary, examples, and support level to this child\'s current developmental stage.',
            '- Keep following the global safety and account-identity prompts. This private prompt adds child-specific response style guidance and can be refined over time.',
            $agePrompt,
        ])));
    }

    private function resolveAgeBand(array $childProfile): string
    {
        $ageBand = trim((string) ($childProfile['age_band'] ?? ''));
        if (in_array($ageBand, ['0_3', '3_6', '6_12', '12_18'], true)) {
            return $ageBand;
        }

        $birthDate = $childProfile['birth_date'] ?? null;
        $ageYears = isset($childProfile['age_years']) && $childProfile['age_years'] !== null
            ? (int) $childProfile['age_years']
            : $this->calculateAgeYears(is_string($birthDate) ? $birthDate : null);

        return $this->mapAgeYearsToBand($ageYears);
    }

    private function ageTemplateKeyForBand(string $ageBand): string
    {
        return match ($ageBand) {
            '0_3' => 'child_chat_age_0_3',
            '3_6' => 'child_chat_age_3_6',
            '12_18' => 'child_chat_age_12_18',
            default => 'child_chat_age_6_12',
        };
    }

    private function mapAgeYearsToBand(?int $ageYears): string
    {
        if ($ageYears === null) {
            return '6_12';
        }

        if ($ageYears < 3) {
            return '0_3';
        }

        if ($ageYears < 6) {
            return '3_6';
        }

        if ($ageYears < 12) {
            return '6_12';
        }

        return '12_18';
    }

    private function calculateAgeYears(?string $birthDate): ?int
    {
        if ($birthDate === null || trim($birthDate) === '') {
            return null;
        }

        try {
            $birth = new DateTimeImmutable($birthDate);
            $today = new DateTimeImmutable('today');
        } catch (\Throwable $e) {
            return null;
        }

        return max(0, $birth->diff($today)->y);
    }

    private function isChildProfile(array $childProfile): bool
    {
        return (int) ($childProfile['id'] ?? 0) > 0
            && trim((string) ($childProfile['role'] ?? 'child')) === 'child';
    }

    private function columnExists(string $tableName, string $columnName): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = :table_name
                AND COLUMN_NAME = :column_name"
        );
        $stmt->execute([
            'table_name' => $tableName,
            'column_name' => $columnName,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = :table_name
                AND INDEX_NAME = :index_name"
        );
        $stmt->execute([
            'table_name' => $tableName,
            'index_name' => $indexName,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }
}
